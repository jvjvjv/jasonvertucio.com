<?php

namespace Tests\Feature;

use App\Enums\IntelStartMode;
use App\Models\AiConversation;
use App\Models\Application;
use App\Models\User;
use App\Services\CompanyIntel\CompanyIntelService;
use App\Services\TargetedResumeService;
use App\Support\SessionNotes;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Generator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Services\LaravelAi\AgentFactory;
use Jvjvjv\CodeTalker\Services\LaravelAi\CodeTalkerAgent;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Mockery;
use Tests\TestCase;

class SessionNotesReplayTest extends TestCase
{
    use DatabaseTransactions;

    private const string NOTE = 'Switched AI system from Notezq Old to Notezq New.';

    /** @var array<int, Message> */
    private array $history = [];

    private ?string $prompt = null;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $admin = User::factory()->create();
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $admin->givePermissionTo('edit-resume');
        $this->actingAs($admin);
        $this->bindCapturingAgent();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function bindCapturingAgent(): void
    {
        $agent = Mockery::mock(CodeTalkerAgent::class);
        $agent->shouldReceive('messages')->andReturn([]);
        $agent->shouldReceive('stream')->andReturnUsing(function (string $prompt): StreamableAgentResponse {
            $this->prompt = $prompt;

            return new StreamableAgentResponse(
                'id-1',
                static function (): Generator {
                    yield new StreamStart('id-1', 'anthropic', 'claude-sonnet-4-6', time());
                    yield new TextDelta('e1', 'm1', 'Understood.', time());
                    yield new StreamEnd('id-1', 'stop', new Usage(promptTokens: 10, completionTokens: 5), time());
                },
                new Meta(provider: 'anthropic', model: 'claude-sonnet-4-6'),
            );
        });

        $factory = Mockery::mock(AgentFactory::class);
        $factory->shouldReceive('forSystem')->andReturnUsing(function (AiSystem $system, string $instructions = '', array $messages = []) use ($agent): CodeTalkerAgent {
            $this->history = $messages;

            return $agent;
        });
        $this->app->instance(AgentFactory::class, $factory);
    }

    private function message(int $conversationId, string $role, string $content, ?string $origin = null): void
    {
        AiConversationMessage::create([
            'ai_conversation_id' => $conversationId,
            'role' => $role,
            'content' => $content,
            'metadata' => $origin === null ? null : ['origin' => $origin],
        ]);
    }

    private function assertNoteNotReplayed(): void
    {
        $this->assertNotEmpty($this->history);

        foreach ($this->history as $entry) {
            $this->assertStringNotContainsString(self::NOTE, (string) $entry->content);
        }

        $this->assertStringNotContainsString(self::NOTE, (string) $this->prompt);
    }

    public function test_a_resume_turn_skips_a_note_written_after_the_last_message(): void
    {
        $application = Application::factory()->withConversation()->create();
        $conversation = AiConversation::findOrFail($application->ai_conversation_id);
        $conversation->update(['ai_system_id' => AiSystem::factory()->targetedResume()->create()->id]);
        $this->message($conversation->id, 'system', 'You tailor resumes.');
        $this->message($conversation->id, 'user', 'Earlier question');
        $this->message($conversation->id, 'assistant', 'Earlier answer');
        $this->message($conversation->id, 'user', 'Pending question');
        $this->message($conversation->id, 'user', self::NOTE, SessionNotes::ORIGIN_SYSTEM_SWITCHED);

        iterator_to_array(app(TargetedResumeService::class)->continueConversation($conversation->fresh()), false);

        $this->assertSame('Pending question', $this->prompt);
        $this->assertNoteNotReplayed();
    }

    public function test_a_resume_turn_skips_a_note_before_a_new_message(): void
    {
        $application = Application::factory()->withConversation()->create();
        $conversation = AiConversation::findOrFail($application->ai_conversation_id);
        $conversation->update(['ai_system_id' => AiSystem::factory()->targetedResume()->create()->id]);
        $this->message($conversation->id, 'system', 'You tailor resumes.');
        $this->message($conversation->id, 'user', 'Earlier question');
        $this->message($conversation->id, 'assistant', 'Earlier answer');
        $this->message($conversation->id, 'user', self::NOTE, SessionNotes::ORIGIN_SYSTEM_SWITCHED);

        iterator_to_array(app(TargetedResumeService::class)->continueConversation($conversation->fresh(), 'New question'), false);

        $this->assertSame('New question', $this->prompt);
        $this->assertNoteNotReplayed();
    }

    public function test_an_intel_turn_skips_the_note(): void
    {
        $application = Application::factory()->create(['company_name' => 'Notezq Co', 'job_description' => 'Build notes.']);
        $service = app(CompanyIntelService::class);
        $intel = $service->start($application, AiSystem::factory()->create(), IntelStartMode::Stream);
        iterator_to_array($service->runTurn($intel->fresh()), false);
        $this->message($intel->ai_conversation_id, 'user', self::NOTE, SessionNotes::ORIGIN_SYSTEM_SWITCHED);

        iterator_to_array($service->runTurn($intel->fresh(), 'Follow-up question'), false);

        $this->assertSame('Follow-up question', $this->prompt);
        $this->assertNoteNotReplayed();
    }
}
