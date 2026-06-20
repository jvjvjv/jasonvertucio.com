<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Jvjvjv\CodeTalker\Enums\AiProvider;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Tests\TestCase;

class BackfillAiSystemCapabilitiesCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_command_backfills_missing_lm_studio_capabilities(): void
    {
        Http::fake([
            'http://localhost:1234/api/v1/models' => Http::response([
                'models' => [
                    [
                        'type' => 'llm',
                        'key' => 'google/gemma-4-e4b',
                        'display_name' => 'Gemma 4 E4B',
                        'loaded_instances' => [],
                        'max_context_length' => 131072,
                        'capabilities' => [
                            'vision' => true,
                            'trained_for_tool_use' => true,
                            'reasoning' => ['default' => 'on'],
                        ],
                    ],
                ],
            ]),
        ]);

        $system = AiSystem::factory()->create([
            'provider' => AiProvider::LmStudio->value,
            'api_key' => '',
            'model' => 'google/gemma-4-e4b',
            'base_url' => 'http://localhost:1234',
            'model_capabilities' => null,
        ]);

        $this->artisan('ai:backfill-system-capabilities', ['--id' => [$system->id]])
            ->expectsOutputToContain('Capability backfill complete.')
            ->assertExitCode(0);

        $system->refresh();

        $this->assertEquals([
            'reasoning' => true,
            'vision' => true,
            'tools' => true,
            'max_context_length' => 131072,
        ], $system->model_capabilities);
    }

    public function test_command_skips_unsupported_providers(): void
    {
        $system = AiSystem::factory()->create([
            'provider' => AiProvider::Anthropic->value,
            'model' => 'claude-sonnet-4-6',
            'model_capabilities' => null,
        ]);

        $this->artisan('ai:backfill-system-capabilities', ['--provider' => [AiProvider::Anthropic->value], '--id' => [$system->id]])
            ->expectsOutputToContain('Capability backfill is not supported for: anthropic')
            ->expectsOutputToContain('No supported providers selected for capability backfill.')
            ->assertExitCode(0);

        $system->refresh();

        $this->assertNull($system->model_capabilities);
    }
}
