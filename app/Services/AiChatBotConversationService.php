<?php

namespace App\Services;

use App\Enums\AiConversationStatus;
use App\Enums\AiInteractionStatus;
use App\Jobs\ProcessAiMemoryJob;
use App\Models\AiChatBot;
use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use App\Models\AiInteractionLog;
use App\Models\User;
use Generator;
use Illuminate\Support\Str;

class AiChatBotConversationService
{
    public function __construct(
        private AiClientFactory $clientFactory,
        private AiMemoryService $memoryService,
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

        $client->withMaxTokens($conversation->aiSystem->max_tokens);

        $startTime = microtime(true);
        $fullResponse = '';
        $inputTokens = null;
        $outputTokens = null;

        try {
            $stream = $client->stream($apiMessages);

            foreach ($stream as $event) {
                if (!isset($event['type'])) {
                    continue;
                }

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
                'model' => $conversation->aiSystem->model,
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'status' => AiInteractionStatus::Success,
            ]);
        } catch (\Exception $exception) {
            AiInteractionLog::create([
                'ai_system_id' => $conversation->aiSystem->id,
                'ai_conversation_id' => $conversation->id,
                'ai_chat_bot_id' => $conversation->ai_chat_bot_id,
                'user_id' => $conversation->user_id,
                'feature' => $conversation->feature,
                'model' => $conversation->aiSystem->model,
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'status' => AiInteractionStatus::Error,
                'error_message' => $exception->getMessage(),
            ]);

            yield 'data: ' . json_encode(['type' => 'error', 'message' => $exception->getMessage()]) . "\n\n";
        }
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
