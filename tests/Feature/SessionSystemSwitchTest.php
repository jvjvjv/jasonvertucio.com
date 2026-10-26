<?php

namespace Tests\Feature;

use App\Enums\RoleIntelStatus;
use App\Models\AiConversation;
use App\Models\Application;
use App\Models\RoleIntel;
use App\Models\User;
use App\Services\AiSession\SessionSystemSwitcher;
use App\Services\CompanyIntel\CompanyIntelService;
use App\Services\TargetedResumeService;
use App\Services\TargetedResumeSystems;
use App\Support\SessionNotes;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Generator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Models\AiSystemFeatureDefault;
use Jvjvjv\CodeTalker\Services\LaravelAi\AgentFactory;
use Jvjvjv\CodeTalker\Services\LaravelAi\CodeTalkerAgent;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Mockery;
use Tests\TestCase;

class SessionSystemSwitchTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        AiSystemFeatureDefault::query()->delete();
        AiSystem::query()
            ->whereIn('system_prompt_id', [...TargetedResumeSystems::PROMPT_IDS, CompanyIntelService::PROMPT_ID_COMPANY_INTEL])
            ->update(['system_prompt_id' => null]);

        $this->admin = User::factory()->create();
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $this->admin->givePermissionTo('edit-resume');
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * An application whose session runs on the given system, with a saved
     * prompt and one exchange.
     */
    private function applicationOn(AiSystem $system, string $prompt = 'You tailor resumes.'): Application
    {
        $application = Application::factory()->withConversation()->create();
        AiConversation::findOrFail($application->ai_conversation_id)->update(['ai_system_id' => $system->id]);
        $this->message($application->ai_conversation_id, 'system', $prompt);
        $this->message($application->ai_conversation_id, 'user', 'Please analyze this job.');
        $this->message($application->ai_conversation_id, 'assistant', 'Fit score: 8/10.');

        return $application->fresh();
    }

    private function intelOn(AiSystem $system, RoleIntelStatus $status = RoleIntelStatus::Ready): RoleIntel
    {
        $conversation = AiConversation::factory()->create(['feature' => CompanyIntelService::FEATURE, 'ai_system_id' => $system->id]);
        $this->message($conversation->id, 'system', 'You research companies.');

        return RoleIntel::factory()->status($status)->create(['ai_conversation_id' => $conversation->id]);
    }

    private function intelSystem(array $attributes = []): AiSystem
    {
        return AiSystem::factory()->create(['system_prompt_id' => CompanyIntelService::PROMPT_ID_COMPANY_INTEL, ...$attributes]);
    }

    private function message(int $conversationId, string $role, string $content, ?array $metadata = null): void
    {
        AiConversationMessage::create([
            'ai_conversation_id' => $conversationId,
            'role' => $role,
            'content' => $content,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return Collection<int, AiConversationMessage>
     */
    private function notes(int $conversationId): Collection
    {
        return AiConversationMessage::query()
            ->where('ai_conversation_id', $conversationId)
            ->get()
            ->filter(fn (AiConversationMessage $message): bool => SessionNotes::isDisplayOnly($message))
            ->values();
    }

    /**
     * Bind an agent factory that records the system and instructions of the
     * next turn.
     *
     * @param  array{system?: AiSystem, instructions?: string}  $captured
     */
    private function captureTurn(array &$captured): void
    {
        $agent = Mockery::mock(CodeTalkerAgent::class);
        $agent->shouldReceive('messages')->andReturn([]);
        $agent->shouldReceive('stream')->andReturn(new StreamableAgentResponse(
            'id-1',
            static function (): Generator {
                yield new StreamStart('id-1', 'anthropic', 'claude-sonnet-4-6', time());
                yield new TextDelta('e1', 'm1', 'Done.', time());
                yield new StreamEnd('id-1', 'stop', new Usage(promptTokens: 10, completionTokens: 5), time());
            },
            new Meta(provider: 'anthropic', model: 'claude-sonnet-4-6'),
        ));

        $factory = Mockery::mock(AgentFactory::class);
        $factory->shouldReceive('forSystem')->andReturnUsing(function (AiSystem $system, string $instructions = '') use ($agent, &$captured): CodeTalkerAgent {
            $captured = ['system' => $system, 'instructions' => $instructions];

            return $agent;
        });
        $this->app->instance(AgentFactory::class, $factory);
    }

    // ---------------------------------------------------------------------
    // Application sessions
    // ---------------------------------------------------------------------

    public function test_options_list_the_other_eligible_systems_and_the_estimate(): void
    {
        $current = AiSystem::factory()->targetedResume()->create(['name' => 'Swzq A current']);
        $configured = AiSystem::factory()->targetedResume()->create(['name' => 'Swzq B configured', 'context_length' => 8192]);
        $modelMax = AiSystem::factory()->targetedResume()->create(['name' => 'Swzq C model max', 'model_capabilities' => ['max_context_length' => 262144]]);
        $unknown = AiSystem::factory()->targetedResume()->create(['name' => 'Swzq D unknown']);
        AiSystem::factory()->create(['name' => 'Swzq E ineligible', 'system_prompt_id' => null]);
        $application = $this->applicationOn($current, str_repeat('a', 400));

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.resume.applications.system-switch.show', $application))
            ->assertOk()
            ->assertJsonPath('current.id', $current->id);

        $systems = collect($response->json('systems'))->keyBy('id');
        $this->assertSame([$configured->id, $modelMax->id, $unknown->id], $systems->keys()->all());
        $this->assertSame(['tokens' => 8192, 'source' => 'configured'], $systems[$configured->id]['context']);
        $this->assertSame(['tokens' => 262144, 'source' => 'model_max'], $systems[$modelMax->id]['context']);
        $this->assertSame(['tokens' => null, 'source' => 'unknown'], $systems[$unknown->id]['context']);

        $expected = (int) ceil((400 + strlen('Please analyze this job.') + strlen('Fit score: 8/10.')) / 4);
        $this->assertSame($expected, $response->json('estimated_tokens'));
    }

    public function test_a_switch_note_does_not_count_toward_the_estimate(): void
    {
        $application = $this->applicationOn(AiSystem::factory()->targetedResume()->create());
        $switcher = app(SessionSystemSwitcher::class);
        $before = $switcher->estimatedTokens($application->conversation);

        $this->message($application->ai_conversation_id, 'user', str_repeat('n', 4000), ['origin' => SessionNotes::ORIGIN_SYSTEM_SWITCHED]);

        $this->assertSame($before, $switcher->estimatedTokens($application->conversation->fresh()));
    }

    public function test_switching_repoints_the_session_and_writes_one_note(): void
    {
        $from = AiSystem::factory()->targetedResume()->create(['name' => 'Swzq Sonnet']);
        $to = AiSystem::factory()->targetedResume()->create(['name' => 'Swzq Bonsai']);
        $application = $this->applicationOn($from);

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.system-switch.update', $application), ['ai_system_id' => $to->id])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame($to->id, $application->conversation->fresh()->ai_system_id);
        $notes = $this->notes($application->ai_conversation_id);
        $this->assertCount(1, $notes);
        $this->assertSame('user', $notes[0]->role);
        $this->assertSame('Switched AI system from Swzq Sonnet to Swzq Bonsai.', $notes[0]->content);
        $this->assertSame($from->id, $notes[0]->metadata['from_ai_system_id']);
        $this->assertSame($to->id, $notes[0]->metadata['to_ai_system_id']);
    }

    public function test_the_next_turn_goes_to_the_new_system_with_the_same_prompt(): void
    {
        $from = AiSystem::factory()->create();
        AiSystemFeatureDefault::create(['ai_system_id' => $from->id, 'feature' => 'targeted-resume']);
        AiSystemFeatureDefault::create(['ai_system_id' => $from->id, 'feature' => 'cover-letter']);
        $to = AiSystem::factory()->targetedResume()->create();
        $prompt = "Resume guidance.\n\n---\n\n## Cover Letter Guidelines\n\nCover letter guidance.";
        $application = $this->applicationOn($from, $prompt);

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.system-switch.update', $application), ['ai_system_id' => $to->id])
            ->assertOk();

        $captured = [];
        $this->captureTurn($captured);
        iterator_to_array(app(TargetedResumeService::class)->continueConversation($application->conversation->fresh(), 'Now the cover letter.'), false);

        $this->assertTrue($captured['system']->is($to));
        $this->assertSame($prompt, $captured['instructions']);
    }

    public function test_switching_to_an_ineligible_system_is_refused(): void
    {
        $from = AiSystem::factory()->targetedResume()->create();
        $application = $this->applicationOn($from);

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.system-switch.update', $application), [
                'ai_system_id' => AiSystem::factory()->create(['system_prompt_id' => null])->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'That AI system cannot run this session.');

        $this->assertSame($from->id, $application->conversation->fresh()->ai_system_id);
        $this->assertCount(0, $this->notes($application->ai_conversation_id));
    }

    public function test_switching_to_the_current_system_is_refused(): void
    {
        $from = AiSystem::factory()->targetedResume()->create();
        $application = $this->applicationOn($from);

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.system-switch.update', $application), ['ai_system_id' => $from->id])
            ->assertStatus(422);

        $this->assertCount(0, $this->notes($application->ai_conversation_id));
    }

    public function test_switching_is_refused_while_a_reply_is_streaming(): void
    {
        $from = AiSystem::factory()->targetedResume()->create();
        $to = AiSystem::factory()->targetedResume()->create();
        $application = $this->applicationOn($from);
        $turnLock = SessionSystemSwitcher::turnLock($application->ai_conversation_id);
        $this->assertTrue($turnLock->get());

        try {
            $this->actingAs($this->admin)
                ->postJson(route('admin.resume.applications.system-switch.update', $application), ['ai_system_id' => $to->id])
                ->assertStatus(409)
                ->assertJsonPath('message', 'A reply is still being written. Wait for it to finish, then switch.');
        } finally {
            $turnLock->release();
        }

        $this->assertSame($from->id, $application->conversation->fresh()->ai_system_id);
        $this->assertCount(0, $this->notes($application->ai_conversation_id));
    }

    public function test_a_streamed_reply_releases_the_turn_lock(): void
    {
        $application = $this->applicationOn(AiSystem::factory()->targetedResume()->create());
        $captured = [];
        $this->captureTurn($captured);

        $this->actingAs($this->admin)
            ->post(route('admin.resume.applications.chat', $application), ['message' => 'One more thing.'])
            ->assertOk()
            ->streamedContent();

        $this->assertNotEmpty($captured);
        $turnLock = SessionSystemSwitcher::turnLock($application->ai_conversation_id);
        $this->assertTrue($turnLock->get());
        $turnLock->release();
    }

    public function test_an_application_without_a_session_cannot_switch(): void
    {
        $application = Application::factory()->create();

        $this->actingAs($this->admin)
            ->getJson(route('admin.resume.applications.system-switch.show', $application))
            ->assertStatus(409);

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.system-switch.update', $application), [
                'ai_system_id' => AiSystem::factory()->targetedResume()->create()->id,
            ])
            ->assertStatus(409);
    }

    public function test_switching_needs_resume_editing_permission(): void
    {
        $from = AiSystem::factory()->targetedResume()->create();
        $to = AiSystem::factory()->targetedResume()->create();
        $application = $this->applicationOn($from);
        $intel = $this->intelOn($this->intelSystem());
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->getJson(route('admin.resume.applications.system-switch.show', $application))->assertForbidden();
        $this->actingAs($outsider)->postJson(route('admin.resume.applications.system-switch.update', $application), ['ai_system_id' => $to->id])->assertForbidden();
        $this->actingAs($outsider)->getJson(route('admin.employment.company-intel.system-switch.show', $intel))->assertForbidden();
        $this->actingAs($outsider)->postJson(route('admin.employment.company-intel.system-switch.update', $intel), ['ai_system_id' => $to->id])->assertForbidden();

        $this->assertSame($from->id, $application->conversation->fresh()->ai_system_id);
    }

    public function test_the_discussion_page_says_whether_switching_is_possible(): void
    {
        $only = AiSystem::factory()->targetedResume()->create();
        $application = $this->applicationOn($only);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertInertia(fn (Assert $page) => $page->where('conversation.can_switch', false));

        AiSystem::factory()->targetedResume()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertInertia(fn (Assert $page) => $page->where('conversation.can_switch', true));

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', Application::factory()->create()))
            ->assertInertia(fn (Assert $page) => $page->where('conversation', null));
    }

    // ---------------------------------------------------------------------
    // Intel sessions
    // ---------------------------------------------------------------------

    public function test_intel_options_include_the_default_and_prompt_six_systems(): void
    {
        $current = $this->intelSystem();
        $default = AiSystem::factory()->create(['system_prompt_id' => null]);
        AiSystemFeatureDefault::create(['ai_system_id' => $default->id, 'feature' => CompanyIntelService::FEATURE]);
        $other = $this->intelSystem();
        AiSystem::factory()->targetedResume()->create();
        $intel = $this->intelOn($current);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.employment.company-intel.system-switch.show', $intel))
            ->assertOk()
            ->assertJsonPath('current.id', $current->id);

        $this->assertEqualsCanonicalizing([$default->id, $other->id], collect($response->json('systems'))->pluck('id')->all());
    }

    public function test_after_an_intel_switch_the_next_turn_runs_on_the_new_system(): void
    {
        $from = $this->intelSystem();
        $to = $this->intelSystem();
        $intel = $this->intelOn($from, RoleIntelStatus::Failed);
        $this->message($intel->ai_conversation_id, 'user', 'Research this company.');

        $this->actingAs($this->admin)
            ->postJson(route('admin.employment.company-intel.system-switch.update', $intel), ['ai_system_id' => $to->id])
            ->assertOk();

        $captured = [];
        $this->captureTurn($captured);
        $this->actingAs($this->admin);
        iterator_to_array(app(CompanyIntelService::class)->runTurn($intel->fresh()), false);

        $this->assertTrue($captured['system']->is($to));
        $this->assertSame('You research companies.', $captured['instructions']);
    }

    public function test_an_intel_switch_is_refused_while_research_is_queued_or_running(): void
    {
        foreach ([RoleIntelStatus::Running, RoleIntelStatus::Queued] as $status) {
            $from = $this->intelSystem();
            $intel = $this->intelOn($from, $status);

            $this->actingAs($this->admin)
                ->postJson(route('admin.employment.company-intel.system-switch.update', $intel), ['ai_system_id' => $this->intelSystem()->id])
                ->assertStatus(409)
                ->assertJsonPath('message', 'Research is still running. Wait for it to finish, then switch.');

            $this->assertSame($from->id, $intel->conversation->fresh()->ai_system_id);
            $this->assertCount(0, $this->notes($intel->ai_conversation_id));
        }
    }

    public function test_an_intel_switch_to_a_resume_system_is_refused(): void
    {
        $intel = $this->intelOn($this->intelSystem());

        $this->actingAs($this->admin)
            ->postJson(route('admin.employment.company-intel.system-switch.update', $intel), [
                'ai_system_id' => AiSystem::factory()->targetedResume()->create()->id,
            ])
            ->assertStatus(422);
    }

    public function test_the_intel_page_says_whether_switching_is_possible(): void
    {
        $intel = $this->intelOn($this->intelSystem(['name' => 'Swzq intel only']));

        $this->actingAs($this->admin)
            ->get(route('admin.employment.company-intel.show', $intel))
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversation.can_switch', false)
                ->where('conversation.ai_system_name', 'Swzq intel only'));

        $this->intelSystem();

        $this->actingAs($this->admin)
            ->get(route('admin.employment.company-intel.show', $intel))
            ->assertInertia(fn (Assert $page) => $page->where('conversation.can_switch', true));
    }
}
