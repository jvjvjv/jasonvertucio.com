<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use Jvjvjv\CodeTalker\Services\LmStudioService;
use Tests\TestCase;

class LmStudioServiceTest extends TestCase
{
    /**
     * Ensure LM Studio receives the configured context length when loading a model.
     */
    public function test_load_model_includes_configured_context_length(): void
    {
        Http::fake([
            'http://localhost:1234/api/v1/models/load' => Http::response([
                'status' => 'loaded',
                'instance_id' => 'instance-123',
                'load_time_seconds' => 1.25,
            ]),
        ]);

        $service = new LmStudioService(
            serverUrl: 'http://localhost:1234',
            contextLength: 16384,
        );

        $result = $service->loadModel('openai/gpt-oss-20b');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'http://localhost:1234/api/v1/models/load'
                && $request['model'] === 'openai/gpt-oss-20b'
                && $request['context_length'] === 16384;
        });

        $this->assertSame('loaded', $result['status']);
        $this->assertSame('instance-123', $result['instance_id']);
        $this->assertSame(1.25, $result['load_time_seconds']);
    }

    public function test_list_models_exposes_capability_metadata(): void
    {
        Http::fake([
            'http://localhost:1234/api/v1/models' => Http::response([
                'models' => [
                    [
                        'type' => 'llm',
                        'key' => 'google/gemma-4-e4b',
                        'display_name' => 'Gemma 4 E4B',
                        'loaded_instances' => [
                            ['id' => 'instance-1'],
                        ],
                        'max_context_length' => 131072,
                        'capabilities' => [
                            'vision' => true,
                            'trained_for_tool_use' => true,
                            'reasoning' => [
                                'allowed_options' => ['off', 'on'],
                                'default' => 'on',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $service = new LmStudioService(serverUrl: 'http://localhost:1234');

        $models = $service->listModels();

        $this->assertSame([
            [
                'id' => 'google/gemma-4-e4b',
                'display_name' => 'Gemma 4 E4B',
                'loaded' => true,
                'max_context_length' => 131072,
                'capabilities' => [
                    'vision' => true,
                    'tools' => true,
                    'reasoning' => true,
                ],
            ],
        ], $models);
    }
}
