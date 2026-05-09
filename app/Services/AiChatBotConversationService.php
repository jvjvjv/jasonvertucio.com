<?php

namespace App\Services;

use App\Enums\AiConversationStatus;
use App\Enums\AiInteractionStatus;
use App\Jobs\ProcessAiMemoryJob;
use App\Models\AiChatBot;
use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use App\Models\AiInteractionLog;
use App\Models\AiLlmMessage;
use App\Models\User;
use Generator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiChatBotConversationService
{
    public function __construct(
        private AiClientFactory $clientFactory,
        private AiMemoryService $memoryService,
        private ConversationUsageService $conversationUsageService,
    ) {
    }

    /**
     * Start a new generic bot conversation.
     */
    public function startConversation(AiChatBot $bot, ?User $user = null, ?string $visitorName = null, ?string $visitorEmail = null): AiConversation
    {
        $conversation = AiConversation::create([
            'user_id' => $user?->id,
            'ai_system_id' => $bot->ai_system_id,
            'ai_chat_bot_id' => $bot->id,
            'feature' => $bot->featureKey(),
            'title' => null,
            'visitor_name' => $visitorName,
            'visitor_email' => $visitorEmail,
            'status' => AiConversationStatus::Active,
            'context' => [
                'bot_slug' => $bot->slug,
                'bot_name' => $bot->name,
            ],
        ]);

        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'system',
            'content' => $this->buildSystemPrompt($bot, $visitorName, $visitorEmail),
        ]);

        return $conversation;
    }

    /**
     * Continue a bot conversation by streaming the assistant response.
     * Stores full LLM request/response data in ai_llm_messages table.
     *
     * @return Generator<int, string>
     */
    public function continueConversation(AiConversation $conversation, string $userMessage): Generator
    {
        $conversation->loadMissing(['aiSystem', 'aiChatBot', 'messages']);

        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        if (blank($conversation->title)) {
            $conversation->forceFill([
                'title' => $this->titleFromUserMessage($userMessage),
            ])->save();
        }

        $allMessages = $conversation->messages()->orderBy('created_at')->get();
        $systemPrompt = null;
        $apiMessages = [];

        foreach ($allMessages as $message) {
            if ($message->role === 'system') {
                $systemPrompt = $message->content;

                continue;
            }

            $apiMessages[] = [
                'role' => $message->role,
                'content' => $message->content,
            ];
        }

        $client = $this->clientFactory->forSystem($conversation->aiSystem);

        if ($systemPrompt !== null) {
            $client->withSystem($systemPrompt);
        }

        // Determine the turn number for this conversation
        $turnNumber = $this->getTurnNumberForConversation($conversation);

        $startTime = microtime(true);
        $fullResponse = '';
        $inputTokens = null;
        $outputTokens = null;
        $resolvedModel = $conversation->aiSystem->model;
        $maxTokens = $conversation->aiSystem->max_tokens;
        
        // Build the request payload that will be sent to LLM (before applying client overrides)
        $requestPayload = [
            'model' => $resolvedModel,
            'max_tokens' => $maxTokens,
            'messages' => $apiMessages,
        ];

        if ($systemPrompt !== null) {
            $requestPayload['system'] = $systemPrompt;
        }

        // Store the request before sending to LLM
        try {
            AiLlmMessage::create([
                'ai_conversation_id' => $conversation->id,
                'direction' => 'request',
                'turn_number' => (string) $turnNumber,
                'request_data' => $requestPayload,
                'created_at' => now(),
            ]);

            // Apply max tokens override to client before streaming
            $client->withMaxTokens($maxTokens);

            $stream = $client->stream($apiMessages);

            // Accumulate response data during streaming
            $responseEvents = [];

            foreach ($stream as $event) {
                Log::debug('Chat bot API stream event', [
                    'conversation_id' => $conversation->id,
                    'ai_chat_bot_id' => $conversation->ai_chat_bot_id,
                    'ai_system_id' => $conversation->ai_system_id,
                    'turn_number' => $turnNumber,
                    'event' => $event,
                ]);

                if (!isset($event['type'])) {
                    continue;
                }

                // Collect all response events for storage
                $responseEvents[] = $event;

                if ($event['type'] === 'content_block_delta' && isset($event['delta']['text'])) {
                    $fullResponse .= $event['delta']['text'];
                    yield 'data: ' . json_encode($event) . "\n\n";
                } elseif ($event['type'] === 'message_start' && isset($event['message']['usage'])) {
                    $inputTokens = $event['message']['usage']['input_tokens'] ?? null;
                } elseif ($event['type'] === 'message_delta' && isset($event['usage'])) {
                    $outputTokens = $event['usage']['output_tokens'] ?? null;
                } elseif ($event['type'] === 'message_stop') {
                    yield 'data: ' . json_encode($event) . "\n\n";
                }
            }

            yield "data: [DONE]\n\n";

            // Store the complete response after streaming finishes
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            
            AiLlmMessage::create([
                'ai_conversation_id' => $conversation->id,
                'direction' => 'response',
                'turn_number' => (string) $turnNumber,
                'request_data' => $requestPayload, // Store request alongside response for context
                'response_data' => [
                    'events' => $responseEvents,
                    'full_response' => $fullResponse,
                ],
                'duration_ms' => $durationMs,
                'created_at' => now(),
            ]);

            $pricingSnapshot = $this->conversationUsageService->pricingSnapshotForSystem(
                $conversation->aiSystem,
                $conversation->aiSystem->model,
            );

            if ($fullResponse !== '') {
                AiConversationMessage::create([
                    'ai_conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $fullResponse,
                    'metadata' => [
                        'input_tokens' => $inputTokens,
                        'output_tokens' => $outputTokens,
                        'model' => $conversation->aiSystem->model,
                    ],
                ]);

                ProcessAiMemoryJob::dispatch($conversation->fresh());
            }

            AiInteractionLog::create([
                'ai_system_id' => $conversation->aiSystem->id,
                'ai_conversation_id' => $conversation->id,
                'ai_chat_bot_id' => $conversation->ai_chat_bot_id,
                'user_id' => $conversation->user_id,
                'feature' => $conversation->feature,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'model' => $resolvedModel,
                'input_token_price_snapshot' => $pricingSnapshot['input_token_price_snapshot'],
                'output_token_price_snapshot' => $pricingSnapshot['output_token_price_snapshot'],
                'duration_ms' => $durationMs,
                'status' => AiInteractionStatus::Success,
            ]);

            $this->conversationUsageService->syncConversation($conversation->fresh());
        } catch (\Exception $exception) {
            // Store error information in LLM messages table
            AiLlmMessage::create([
                'ai_conversation_id' => $conversation->id,
                'direction' => 'request',
                'turn_number' => (string) $turnNumber,
                'request_data' => $requestPayload + ['error' => $exception->getMessage()],
                'created_at' => now(),
            ]);

            AiInteractionLog::create([
                'ai_system_id' => $conversation->aiSystem->id,
                'ai_conversation_id' => $conversation->id,
                'ai_chat_bot_id' => $conversation->ai_chat_bot_id,
                'user_id' => $conversation->user_id,
                'feature' => $conversation->feature,
                'model' => $resolvedModel ?? $conversation->aiSystem->model,
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'status' => AiInteractionStatus::Error,
                'error_message' => $exception->getMessage(),
            ]);

            yield 'data: ' . json_encode(['type' => 'error', 'message' => $exception->getMessage()]) . "\n\n";
        }
    }

    /**
     * Get the next turn number for a conversation.
     */
    private function getTurnNumberForConversation(AiConversation $conversation): int
    {
        $maxTurn = AiLlmMessage::query()
            ->where('ai_conversation_id', $conversation->id)
            ->max('turn_number');

        // Handle string comparison for turn numbers (e.g., "1", "2", "10")
        if ($maxTurn === null || !is_numeric($maxTurn)) {
            return 1;
        }

        return (int) $maxTurn + 1;
    }

    private function buildSystemPrompt(AiChatBot $bot, ?string $visitorName, ?string $visitorEmail): string
    {
        $replacements = [
            '{{bot_name}}' => $bot->name,
            '{{bot_slug}}' => $bot->slug,
            '{{bot_description}}' => $bot->description ?? '',
            '{{visitor_name}}' => $visitorName ?? '',
            '{{visitor_email}}' => $visitorEmail ?? '',
        ];

        $prompt = strtr($bot->prompt_template, $replacements);
        $systemPrompt = trim((string) $bot->aiSystem?->system_prompt);
        $memoryPrompt = trim($this->memoryService->getMemoriesForPrompt($bot->featureKey()));

        return collect([
            $systemPrompt !== '' ? $systemPrompt : null,
            $prompt,
            $memoryPrompt !== '' ? "## Learned Insights\n{$memoryPrompt}" : null,
        ])->filter()->implode("\n\n");
    }

    private function titleFromUserMessage(string $userMessage): string
    {
        $normalized = Str::of(strip_tags($userMessage))
            ->squish()
            ->trim();

        if ($normalized->isEmpty()) {
            return 'New chat';
        }

        return Str::limit($normalized->toString(), 80, '...');
    }
}
