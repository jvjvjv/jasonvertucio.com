<?php

namespace App\Services;

use App\Enums\AiConversationStatus;
use App\Models\AiConversation;
use App\Models\AiFeatureMemory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiMemoryService
{
    public function __construct(
        private AiClientFactory $clientFactory,
    ) {
    }

    /**
     * Get formatted memory text for injection into a system prompt.
     */
    public function getMemoriesForPrompt(string $feature): string
    {
        $memories = AiFeatureMemory::query()
            ->forFeature($feature)
            ->active()
            ->orderByDesc('confidence')
            ->orderByDesc('times_reinforced')
            ->get();

        if ($memories->isEmpty()) {
            return '';
        }

        $grouped = $memories->groupBy('category');
        $sections = [];

        $categoryLabels = [
            'preference' => 'User Preferences',
            'domain_knowledge' => 'Domain Knowledge',
            'system_tuning' => 'System Tuning Insights',
        ];

        foreach ($grouped as $category => $entries) {
            $label = $categoryLabels[$category] ?? ucfirst(str_replace('_', ' ', $category));
            $lines = $entries->map(fn (AiFeatureMemory $m) => "- {$m->content}")->implode("\n");
            $sections[] = "### {$label}\n{$lines}";
        }

        return implode("\n\n", $sections);
    }

    /**
     * Analyze a completed conversation and extract memory operations.
     *
     * @return array{add: array<int, mixed>, update: array<int, mixed>, remove: array<int, mixed>}
     */
    public function analyzeConversation(AiConversation $conversation): array
    {
        $conversation->loadMissing(['messages', 'aiSystem']);

        $existingMemories = AiFeatureMemory::query()
            ->forFeature($conversation->feature)
            ->active()
            ->get(['key', 'category', 'content', 'confidence'])
            ->toArray();

        $conversationMessages = $conversation->messages
            ->filter(fn ($m) => $m->role !== 'system')
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->toArray();

        $analysisPrompt = $this->buildAnalysisPrompt($existingMemories, $conversationMessages);

        $client = $this->clientFactory->forSystem($conversation->aiSystem);

        $response = $client
            ->withSystem('You are a memory management system. You analyze AI conversations and extract reusable insights. Always respond with valid JSON only, no markdown fences or extra text.')
            ->withMaxTokens(4096)
            ->withTemperature(0.2)
            ->message([
                ['role' => 'user', 'content' => $analysisPrompt],
            ]);

        return $this->parseAnalysisResponse($response);
    }

    /**
     * Apply memory operations (add/update/remove) from an analysis result.
     */
    public function applyMemoryOperations(string $feature, array $operations, int $conversationId): void
    {
        DB::transaction(function () use ($feature, $operations, $conversationId) {
            foreach ($operations['add'] ?? [] as $entry) {
                AiFeatureMemory::create([
                    'feature' => $feature,
                    'category' => $entry['category'] ?? 'general',
                    'key' => $entry['key'],
                    'content' => $entry['content'],
                    'confidence' => $entry['confidence'] ?? 50,
                    'source_conversation_id' => $conversationId,
                    'is_active' => true,
                ]);
            }

            foreach ($operations['update'] ?? [] as $entry) {
                $memory = AiFeatureMemory::query()
                    ->forFeature($feature)
                    ->where('key', $entry['key'])
                    ->active()
                    ->first();

                if ($memory === null) {
                    continue;
                }

                $updates = ['source_conversation_id' => $conversationId];

                if (isset($entry['content'])) {
                    $updates['content'] = $entry['content'];
                }

                if (isset($entry['confidence'])) {
                    $updates['confidence'] = $entry['confidence'];
                }

                if (!empty($entry['reinforced'])) {
                    $updates['times_reinforced'] = $memory->times_reinforced + 1;
                    $updates['last_reinforced_at'] = now();
                }

                $memory->update($updates);
            }

            foreach ($operations['remove'] ?? [] as $entry) {
                AiFeatureMemory::query()
                    ->forFeature($feature)
                    ->where('key', $entry['key'])
                    ->active()
                    ->update(['is_active' => false]);
            }
        });
    }

    /**
     * Orchestrate: analyze a completed conversation and apply the results.
     */
    public function processCompletedConversation(AiConversation $conversation): void
    {
        try {
            $operations = $this->analyzeConversation($conversation);
            $this->applyMemoryOperations($conversation->feature, $operations, $conversation->id);
        } catch (\Throwable $e) {
            Log::error('AI Memory processing failed', [
                'conversation_id' => $conversation->id,
                'feature' => $conversation->feature,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Rebuild all memories for a feature from scratch.
     */
    public function rebuildMemories(string $feature): void
    {
        AiFeatureMemory::query()
            ->forFeature($feature)
            ->update(['is_active' => false]);

        $conversations = AiConversation::query()
            ->where('feature', $feature)
            ->where('status', AiConversationStatus::Completed)
            ->orderBy('created_at')
            ->get();

        foreach ($conversations as $conversation) {
            $this->processCompletedConversation($conversation);
        }
    }

    /**
     * Build the analysis prompt for the AI.
     */
    private function buildAnalysisPrompt(array $existingMemories, array $conversationMessages): string
    {
        $memoriesJson = json_encode($existingMemories, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $messagesJson = json_encode($conversationMessages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
Analyze this completed AI conversation and compare against existing memory entries.

## Existing Memories
{$memoriesJson}

## Conversation Messages
{$messagesJson}

Return a JSON object with three arrays:

{
  "add": [
    {"key": "descriptive-key", "category": "preference|domain_knowledge|system_tuning", "content": "The insight text", "confidence": 1-100}
  ],
  "update": [
    {"key": "existing-key", "content": "updated content if changed", "confidence": 80, "reinforced": true}
  ],
  "remove": [
    {"key": "existing-key", "reason": "why this is no longer valid"}
  ]
}

Rules:
- Only extract genuinely useful, reusable insights — not conversation-specific details
- "preference": how the user likes things done (e.g., "prefers action verbs in bullet points")
- "domain_knowledge": facts about the user's background not already in their resume data
- "system_tuning": what worked well or poorly in this conversation's approach
- If an existing entry is confirmed by this conversation, update it with "reinforced": true
- If an existing entry is contradicted, update its content or remove it
- Be conservative: only high-confidence insights. Use lower confidence when uncertain
- Return empty arrays if no changes are warranted
- Keys should be lowercase-kebab-case, descriptive, and unique
PROMPT;
    }

    /**
     * Parse the AI response into structured operations.
     *
     * @return array{add: array<int, mixed>, update: array<int, mixed>, remove: array<int, mixed>}
     */
    private function parseAnalysisResponse(array $response): array
    {
        $text = '';

        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }

        $text = trim($text);

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $text, $matches)) {
            $text = $matches[1];
        }

        $parsed = json_decode($text, true);

        if (!is_array($parsed)) {
            Log::warning('AI Memory analysis returned unparseable response', ['raw' => $text]);

            return ['add' => [], 'update' => [], 'remove' => []];
        }

        return [
            'add' => $parsed['add'] ?? [],
            'update' => $parsed['update'] ?? [],
            'remove' => $parsed['remove'] ?? [],
        ];
    }
}
