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

            // Ordered sequence of interleaved text and reasoning blocks.
            // Each entry: ['type' => 'text'|'reasoning', 'content' => string]
            $blocks = [];

            $appendToBlocks = function (string $type, string $delta) use (&$blocks): void {
                if ($blocks !== [] && $blocks[count($blocks) - 1]['type'] === $type) {
                    $blocks[count($blocks) - 1]['content'] .= $delta;
                } else {
                    $blocks[] = ['type' => $type, 'content' => $delta];
                }
            };

            foreach ($stream as $event) {
                Log::debug('Chat bot API stream event', [
                    'conversation_id' => $conversation->id,
                    'ai_chat_bot_id' => $conversation->ai_chat_bot_id,
                    'ai_system_id' => $conversation->ai_system_id,
                    'turn_number' => $turnNumber,
                    'event_type' => $event['type'] ?? null,
                ]);

                if (!isset($event['type'])) {
                    continue;
                }

                // Collect all response events for storage
                $responseEvents[] = $event;

                switch ($event['type']) {
                    case 'content_block_start':
                        // New content block starting (text, thinking, tool_use, etc.)
                        yield 'data: ' . json_encode($event) . "\n\n";
                        
                        // Track if this is a thinking/reasoning or tool_use block
                        if (isset($event['block']['type'])) {
                            Log::debug('Content block started', [
                                'type' => $event['block']['type'],
                                'block_id' => $event['block']['id'] ?? null,
                            ]);
                            
                            // Track tool use blocks for function calling
                            if ($event['block']['type'] === 'tool_use') {
                                Log::debug('Detected tool_use block', [
                                    'name' => $event['block']['name'] ?? null,
                                    'id' => $event['block']['id'] ?? null,
                                ]);
                            }
                        }
                        break;
                        
                    case 'reasoning_block_delta':
                        // OpenAI-compatible reasoning models (e.g. DeepSeek R1 via LM Studio)
                        if (isset($event['delta']['reasoning'])) {
                            $appendToBlocks('reasoning', $event['delta']['reasoning']);
                        }
                        yield 'data: ' . json_encode($event) . "\n\n";
                        break;

                    case 'content_block_delta':
                        // Handle text deltas for normal response
                        if (isset($event['delta']['text'])) {
                            $appendToBlocks('text', $event['delta']['text']);
                        }

                        // Anthropic extended thinking format
                        if (isset($event['delta']['thinking']) || isset($event['delta']['signature'])) {
                            $appendToBlocks('reasoning', $event['delta']['thinking'] ?? '');
                            Log::debug('Received thinking/reasoning delta', [
                                'has_thinking' => isset($event['delta']['thinking']),
                                'has_signature' => isset($event['delta']['signature']),
                            ]);
                        }
                        
                        // Track tool_use deltas (arguments being built)
                        if (isset($event['delta']['input'])) {
                            Log::debug('Received tool_use input delta', [
                                'partial_input' => is_string($event['delta']['input']) ? substr($event['delta']['input'], 0, 100) : 'object',
                            ]);
                        }
                        
                        // Also include non-text deltas (tool_use, etc.) for complete storage
                        yield 'data: ' . json_encode($event) . "\n\n";
                        break;
                        
                    case 'content_block_stop':
                        // Content block has finished
                        if (isset($event['block']['type'])) {
                            Log::debug('Content block stopped', [
                                'type' => $event['block']['type'],
                                'id' => $event['block']['id'] ?? null,
                            ]);
                            
                            // Track when tool_use blocks complete
                            if ($event['block']['type'] === 'tool_use') {
                                Log::debug('Tool_use block completed');
                            }
                        }
                        yield 'data: ' . json_encode($event) . "\n\n";
                        break;
                        
                    case 'message_start':
                        if (isset($event['message']['usage'])) {
                            $inputTokens = $event['message']['usage']['input_tokens'] ?? null;
                        }
                        yield 'data: ' . json_encode($event) . "\n\n";
                        break;
                        
                    case 'message_delta':
                        if (isset($event['usage'])) {
                            $outputTokens = $event['usage']['output_tokens'] ?? null;
                        }
                        // Store stop_reason for debugging (e.g., "end_turn", "max_tokens", "stop_sequence")
                        yield 'data: ' . json_encode($event) . "\n\n";
                        break;
                        
                    case 'message_stop':
                        yield 'data: ' . json_encode($event) . "\n\n";
                        break;
                        
                    case 'ping':
                        // Keep-alive ping from Anthropic
                        Log::debug('Received ping event in stream');
                        break;
                        
                    default:
                        // Capture any other event types for completeness
                        yield 'data: ' . json_encode($event) . "\n\n";
                }
            }

            yield "data: [DONE]\n\n";

            // Derive flat strings from blocks for backwards-compat columns and LLM context
            $fullResponse = collect($blocks)->where('type', 'text')->pluck('content')->implode('');
            $thinkingContent = collect($blocks)->where('type', 'reasoning')->pluck('content')->implode("\n\n");

            // Store the complete response after streaming finishes
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            
            // Build comprehensive response data with usage info and stop reason if available
            $lastMessageDelta = collect($responseEvents)
                ->where('type', 'message_delta')
                ->last();

            // Extract tool_use details for structured storage
            $toolUseEvents = collect($responseEvents)
                ->filter(fn($e) => isset($e['block']['type']) && $e['block']['type'] === 'tool_use')
                ->map(function ($event) {
                    return [
                        'id' => $event['block']['id'] ?? null,
                        'name' => $event['block']['name'] ?? null,
                        'type' => 'tool_use',
                    ];
                })
                ->values()
                ->toArray();

            // Extract content block types for overview
            $contentBlockTypes = collect($responseEvents)
                ->filter(fn($e) => isset($e['block']['type']))
                ->pluck('block.type')
                ->unique()
                ->values()
                ->toArray();

            AiLlmMessage::create([
                'ai_conversation_id' => $conversation->id,
                'direction' => 'response',
                'turn_number' => (string) $turnNumber,
                'request_data' => $requestPayload, // Store request alongside response for context
                'response_data' => [
                    'events' => $responseEvents, // All SSE events captured during streaming
                    'full_response' => $fullResponse, // Reconstructed complete text response (user-facing)
                    'thinking_content' => $thinkingContent, // Reasoning/thinking content if model outputs it
                    'tool_use_events' => $toolUseEvents, // Tool/function calls made by the model
                    'content_block_types' => $contentBlockTypes, // Types of blocks returned (text, thinking, tool_use, etc.)
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'stop_reason' => $lastMessageDelta['stop_reason'] ?? null,
                    'model' => $resolvedModel,
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
                    'reasoning_content' => $thinkingContent !== '' ? $thinkingContent : null,
                    'blocks' => $blocks !== [] ? $blocks : null,
                    'metadata' => [
                        'input_tokens' => $inputTokens,
                        'output_tokens' => $outputTokens,
                        'model' => $conversation->aiSystem->model,
                    ],
                ]);

                // Dispatch memory processing with user identity for conversation-specific scoping
                ProcessAiMemoryJob::dispatch(
                    $conversation->fresh(),
                    $conversation->user_id,
                    $conversation->visitor_email
                );
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

    /**
     * Build system prompt for a chatbot.
     */
    private function buildSystemPrompt(AiChatBot $bot, ?string $visitorName = null, ?string $visitorEmail = null): string
    {
        return $this->buildSystemPromptForBot($bot, null, $visitorName, $visitorEmail);
    }

    /**
     * Build system prompt for a chatbot with optional conversation scoping.
     */
    private function buildSystemPromptForBot(AiChatBot $bot, ?AiConversation $conversation = null, ?string $visitorName = null, ?string $visitorEmail = null): string
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
        
        // For chatbot conversations, scope memories to the current user (not conversation).
        // Each individual user has their own persistent memory context across all their conversations with this chatbot.
        // Memories are identified by: user_id for logged-in users, or visitor_email for anonymous visitors.
        $memoryUserId = null;
        $memoryVisitorEmail = null;

        if ($conversation !== null) {
            $memoryUserId = $conversation->user_id;
            $memoryVisitorEmail = $conversation->visitor_email;
        } elseif (auth()->check()) {
            // If conversation not available but user is logged in, use their ID
            $memoryUserId = auth()->id();
        }

        $memoryPrompt = trim($this->memoryService->getMemoriesForPrompt(
            $bot->featureKey(),
            $memoryUserId,
            $memoryVisitorEmail
        ));

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
