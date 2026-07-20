<?php

namespace App\Concerns;

use Generator;
use Illuminate\Support\Facades\Log;
use Jvjvjv\CodeTalker\Contracts\AiClientContract;
use Jvjvjv\CodeTalker\Contracts\Mcp\AiToolRegistryContract;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Models\AiLlmMessage;

trait ExecutesAiTools
{
    /**
     * Run the AI streaming loop with tool execution until the model produces
     * a final text response or the iteration cap is reached.
     *
     * Yields SSE-formatted lines for streaming to the client.
     * Populates $result with the final turn's text and token counts.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array{text: string, inputTokens: int|null, outputTokens: int|null}|null  $result
     * @return Generator<int, string>
     */
    private function runToolLoop(
        AiClientContract $client,
        array $messages,
        AiToolRegistryContract $toolRegistry,
        int $maxIterations = 6,
        ?array &$result = null,
        ?AiConversation $conversation = null,
    ): Generator {
        $preambleText = '';
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $finalTextAccumulator = ''; // accumulates text across max_tokens continuation calls

        // Compute the base turn number once, before any iterations
        $baseTurnNumber = null;
        if ($conversation !== null) {
            $max = AiLlmMessage::query()
                ->where('ai_conversation_id', $conversation->id)
                ->max('turn_number');
            $baseTurnNumber = ($max === null || ! is_numeric($max)) ? 1 : (int) $max + 1;
        }

        yield ": heartbeat\n\n";

        for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
            $iterationStartTime = microtime(true);
            $iterationTurnNumber = $baseTurnNumber !== null
                ? ($iteration === 0 ? (string) $baseTurnNumber : "{$baseTurnNumber}.{$iteration}")
                : null;

            Log::info('ai-tool-loop: iteration started', [
                'conversation_id' => $conversation?->id,
                'iteration' => $iteration,
                'turn_number' => $iterationTurnNumber,
                'message_count' => count($messages),
            ]);

            $iterationRequestPayload = ['messages' => $messages];
            if ($conversation !== null) {
                $iterationRequestPayload['model'] = $conversation->aiSystem->model;
                $iterationRequestPayload['max_tokens'] = $conversation->aiSystem->max_tokens;

                AiLlmMessage::create([
                    'ai_conversation_id' => $conversation->id,
                    'direction' => 'request',
                    'turn_number' => $iterationTurnNumber,
                    'request_data' => $iterationRequestPayload,
                    'created_at' => now(),
                ]);
            }

            $apiTools = $toolRegistry->toApiTools();

            Log::info('ai-tool-loop: creating stream client', [
                'conversation_id' => $conversation?->id,
                'iteration' => $iteration,
                'tool_count' => count($apiTools),
            ]);

            $stream = $client->withTools($apiTools)->stream($messages);

            Log::info('ai-tool-loop: stream client created', [
                'conversation_id' => $conversation?->id,
                'iteration' => $iteration,
            ]);

            $fullText = '';
            $fullReasoning = '';
            $stopReason = 'end_turn';
            $inputTokens = null;
            $outputTokens = null;

            /** @var array<string, array{id: string, name: string, partialJson: string}> $pendingToolBlocks */
            $pendingToolBlocks = [];
            $currentBlockKey = null;
            $streamEventCount = 0;
            $firstStreamEventLogged = false;

            try {
                foreach ($stream as $event) {
                    $streamEventCount++;
                    $type = $event['type'] ?? '';

                    if ($type === 'heartbeat') {
                        yield ": heartbeat\n\n";

                        continue;
                    }

                    if (! $firstStreamEventLogged) {
                        $firstStreamEventLogged = true;

                        Log::info('ai-tool-loop: first stream event received', [
                            'conversation_id' => $conversation?->id,
                            'iteration' => $iteration,
                            'event_type' => $type,
                            'elapsed_ms' => (int) ((microtime(true) - $iterationStartTime) * 1000),
                        ]);
                    }

                    if ($type === 'message_start') {
                        $inputTokens = $event['message']['usage']['input_tokens'] ?? $inputTokens;

                    } elseif ($type === 'content_block_start') {
                        $block = $event['content_block'] ?? [];

                        if (($block['type'] ?? '') === 'tool_use') {
                            $id = (string) ($block['id'] ?? uniqid('tool_', true));
                            $currentBlockKey = $id;
                            $pendingToolBlocks[$id] = [
                                'id' => $id,
                                'name' => (string) ($block['name'] ?? ''),
                                'partialJson' => '',
                            ];
                        } elseif (($block['type'] ?? '') === 'thinking') {
                            yield 'data: '.json_encode($event)."\n\n";
                        }

                    } elseif ($type === 'content_block_delta') {
                        $delta = $event['delta'] ?? [];
                        $deltaType = $delta['type'] ?? '';

                        if ($deltaType === 'input_json_delta' && $currentBlockKey !== null && isset($pendingToolBlocks[$currentBlockKey])) {
                            $pendingToolBlocks[$currentBlockKey]['partialJson'] .= (string) ($delta['partial_json'] ?? '');
                        } elseif ($deltaType === 'thinking_delta' || isset($delta['reasoning'])) {
                            $fullReasoning .= (string) ($delta['thinking'] ?? $delta['reasoning'] ?? '');
                            yield 'data: '.json_encode($event)."\n\n";
                        } elseif (isset($delta['text'])) {
                            $fullText .= $delta['text'];
                            yield 'data: '.json_encode($event)."\n\n";
                        }

                    } elseif ($type === 'content_block_stop') {
                        $currentBlockKey = null;

                    } elseif ($type === 'message_delta') {
                        $outputTokens = $event['usage']['output_tokens'] ?? $outputTokens;
                        $inputTokens = $event['usage']['input_tokens'] ?? $inputTokens;
                        $reason = $event['delta']['stop_reason'] ?? null;

                        if ($reason !== null) {
                            $stopReason = (string) $reason;
                        }
                    }
                    // message_stop is deferred until we know this is the final turn
                }
            } catch (\Throwable $throwable) {
                Log::error('ai-tool-loop: stream iteration failed', [
                    'conversation_id' => $conversation?->id,
                    'iteration' => $iteration,
                    'event_count' => $streamEventCount,
                    'elapsed_ms' => (int) ((microtime(true) - $iterationStartTime) * 1000),
                    'exception' => $throwable::class,
                    'message' => $throwable->getMessage(),
                ]);

                throw $throwable;
            }

            Log::info('ai-tool-loop: stream exhausted', [
                'conversation_id' => $conversation?->id,
                'iteration' => $iteration,
                'event_count' => $streamEventCount,
                'elapsed_ms' => (int) ((microtime(true) - $iterationStartTime) * 1000),
                'stop_reason' => $stopReason,
                'full_text_length' => strlen($fullText),
                'tool_block_count' => count($pendingToolBlocks),
            ]);

            // Parse accumulated tool call JSON
            $toolCalls = [];

            foreach ($pendingToolBlocks as $block) {
                $partialJson = $block['partialJson'];
                $input = json_decode($partialJson, true);

                if ($stopReason === 'tool_use' && ! \is_array($input)) {
                    Log::warning('ai-tool-loop: tool input JSON could not be parsed', [
                        'conversation_id' => $conversation?->id,
                        'iteration' => $iteration,
                        'tool_name' => $block['name'],
                        'tool_id' => $block['id'],
                        'partial_json_length' => strlen($partialJson),
                        'json_error' => json_last_error_msg(),
                        'partial_json_preview' => mb_substr($partialJson, 0, 300),
                    ]);
                }

                $toolCalls[] = [
                    'id' => $block['id'],
                    'name' => $block['name'],
                    'input' => \is_array($input) ? $input : [],
                ];
            }

            if ($stopReason === 'tool_use') {
                Log::info('ai-tool-loop: tool-use turn captured', [
                    'conversation_id' => $conversation?->id,
                    'iteration' => $iteration,
                    'tool_count' => count($toolCalls),
                    'tool_names' => array_values(array_filter(array_map(static fn (array $tc): string => (string) ($tc['name'] ?? ''), $toolCalls))),
                    'text_length' => strlen($fullText),
                ]);
            }

            $totalInputTokens += (int) ($inputTokens ?? 0);
            $totalOutputTokens += (int) ($outputTokens ?? 0);

            if ($conversation !== null && $iterationTurnNumber !== null) {
                $contentBlocks = [];
                if ($fullReasoning !== '') {
                    $contentBlocks[] = ['type' => 'thinking', 'thinking' => $fullReasoning];
                }
                if ($fullText !== '') {
                    $contentBlocks[] = ['type' => 'text', 'text' => $fullText];
                }
                foreach ($toolCalls as $tc) {
                    $contentBlocks[] = ['type' => 'tool_use', 'id' => $tc['id'], 'name' => $tc['name'], 'input' => $tc['input']];
                }

                AiLlmMessage::create([
                    'ai_conversation_id' => $conversation->id,
                    'direction' => 'response',
                    'turn_number' => $iterationTurnNumber,
                    'request_data' => $iterationRequestPayload,
                    'response_data' => [
                        'stop_reason' => $stopReason,
                        'input_tokens' => $inputTokens,
                        'output_tokens' => $outputTokens,
                        'model' => $conversation->aiSystem->model,
                        'text_length' => strlen($fullText),
                        'reasoning_length' => strlen($fullReasoning),
                        'tool_calls' => array_map(
                            static fn (array $tc): array => ['id' => $tc['id'], 'name' => $tc['name']],
                            $toolCalls,
                        ),
                    ],
                    'raw_response' => [
                        'model' => $conversation->aiSystem->model,
                        'stop_reason' => $stopReason,
                        'usage' => ['input_tokens' => $inputTokens, 'output_tokens' => $outputTokens],
                        'content' => $contentBlocks,
                    ],
                    'duration_ms' => (int) ((microtime(true) - $iterationStartTime) * 1000),
                    'created_at' => now(),
                ]);
            }

            // No tool calls — this is the final response (or a max_tokens continuation)
            if ($stopReason !== 'tool_use' || $toolCalls === []) {
                $finalTextAccumulator .= $fullText;

                Log::info('ai-tool-loop: stop check', [
                    'conversation_id' => $conversation?->id,
                    'iteration' => $iteration,
                    'stop_reason' => $stopReason,
                    'full_text_length' => strlen($fullText),
                    'accumulator_length' => strlen($finalTextAccumulator),
                ]);

                if ($stopReason === 'max_tokens' && $finalTextAccumulator !== '') {
                    // Response was cut off at the token limit — send it back and ask the model to continue
                    $messages[] = ['role' => 'assistant', 'content' => $finalTextAccumulator];
                    $messages[] = ['role' => 'user', 'content' => 'Continue.'];

                    continue;
                }

                $combinedText = $preambleText !== ''
                    ? $preambleText.($finalTextAccumulator !== '' ? "\n\n{$finalTextAccumulator}" : '')
                    : $finalTextAccumulator;

                $result = [
                    'text' => $combinedText,
                    'inputTokens' => $totalInputTokens ?: null,
                    'outputTokens' => $totalOutputTokens ?: null,
                ];

                yield 'data: '.json_encode(['type' => 'message_stop'])."\n\n";

                return;
            }

            // Accumulate preamble text from this tool-calling iteration so it
            // is included in the final saved message even if the follow-up
            // iteration produces no additional text.
            if ($fullText !== '') {
                $preambleText .= ($preambleText !== '' ? "\n\n" : '').$fullText;
            }
            $finalTextAccumulator = ''; // reset — this iteration had tool calls, not a continuation

            // Notify the frontend that tool calls are happening so it can display a panel.
            // Include any preamble text the model wrote before calling tools.
            yield 'data: '.json_encode([
                'type' => 'tool_use_progress',
                'text' => $fullText,
                'tools' => array_column($toolCalls, 'name'),
            ])."\n\n";

            // Execute tool calls and splice results back into the message history
            $toolResults = [];

            foreach ($toolCalls as $toolCall) {
                Log::info('ai-tool-loop: dispatching tool call', [
                    'conversation_id' => $conversation?->id,
                    'tool' => $toolCall['name'],
                    'tool_id' => $toolCall['id'],
                    'input_keys' => array_keys($toolCall['input']),
                ]);

                try {
                    $toolResult = $toolRegistry->dispatch($toolCall['name'], $toolCall['input']);
                } catch (\Throwable $e) {
                    Log::warning('ai-tool-loop: tool call failed', [
                        'conversation_id' => $conversation?->id,
                        'tool' => $toolCall['name'],
                        'tool_id' => $toolCall['id'],
                        'error' => $e->getMessage(),
                    ]);
                    $toolResult = ['error' => $e->getMessage()];
                }

                Log::info('ai-tool-loop: tool call completed', [
                    'conversation_id' => $conversation?->id,
                    'tool' => $toolCall['name'],
                    'tool_id' => $toolCall['id'],
                    'result_keys' => array_keys($toolResult),
                    'requested_page_reload' => ! empty($toolResult['_page_reload']),
                ]);

                if (! empty($toolResult['_page_reload'])) {
                    yield 'data: '.json_encode(['type' => 'page_reload'])."\n\n";
                }

                $toolResults[] = [
                    'id' => $toolCall['id'],
                    'name' => $toolCall['name'],
                    'result' => array_filter(
                        $toolResult,
                        static fn (string $key): bool => $key !== '_page_reload',
                        ARRAY_FILTER_USE_KEY,
                    ),
                ];
            }

            yield ": heartbeat\n\n";

            $messages[] = $client->formatAssistantToolCallTurn($fullText, $toolCalls);

            foreach ($client->formatToolResultTurn($toolResults) as $resultMessage) {
                $messages[] = $resultMessage;
            }
        }

        // Safety cap reached
        Log::warning('ai-tool-loop: reached max iterations', [
            'conversation_id' => $conversation?->id,
            'max_iterations' => $maxIterations,
        ]);

        $result = [
            'text' => $preambleText,
            'inputTokens' => $totalInputTokens ?: null,
            'outputTokens' => $totalOutputTokens ?: null,
        ];

        yield 'data: '.json_encode(['type' => 'message_stop'])."\n\n";
    }
}
