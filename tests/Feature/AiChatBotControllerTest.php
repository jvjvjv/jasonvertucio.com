<?php

namespace Tests\Feature;

use App\Models\AiChatBot;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Tests\TestCase;

class AiChatBotControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        Permission::firstOrCreate(['name' => 'manage-ai-tools']);
        $user = User::factory()->create();
        $user->givePermissionTo('manage-ai-tools');

        return $user;
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('admin.ai.bots.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_index_requires_manage_ai_tools_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.ai.bots.index'));

        $response->assertForbidden();
    }

    public function test_index_displays_existing_bots(): void
    {
        $user = $this->authenticatedUser();
        $bot = AiChatBot::factory()->create(['name' => 'Portfolio Guide']);

        $response = $this->actingAs($user)->get(route('admin.ai.bots.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/bots/Index', false)
            ->has('bots', 1)
            ->where('bots.0.name', 'Portfolio Guide')
            ->where('bots.0.slug', $bot->slug)
            ->where('bots.0.access_path', 'chat')
        );
    }

    public function test_store_creates_a_bot(): void
    {
        $user = $this->authenticatedUser();
        $system = AiSystem::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.ai.bots.store'), [
            'name' => 'Lead Intake',
            'slug' => 'lead-intake',
            'access_path' => 'root',
            'description' => 'Qualify inbound prospects.',
            'ai_system_id' => $system->id,
            'context_length' => 8192,
            'temperature' => 0.45,
            'prompt_template' => 'You are {{persona_name}}.',
            'required_permission' => 'manage-ai-tools',
            'is_active' => true,
            'require_visitor_identity' => true,
        ]);

        $response->assertRedirect(route('admin.ai.bots.index'));
        $this->assertDatabaseHas('ai_personas', [
            'name' => 'Lead Intake',
            'slug' => 'lead-intake',
            'access_path' => 'root',
            'ai_system_id' => $system->id,
            'context_length' => 8192,
            'temperature' => '0.45',
            'require_visitor_identity' => true,
        ]);

        // required_permission is host-only: it exists on the host form request
        // and model, not the package's. The controller extends the package
        // controller, so this guards against the field being silently dropped.
        $bot = AiChatBot::where('slug', 'lead-intake')->firstOrFail();
        $this->assertSame('manage-ai-tools', $bot->required_permission);
    }

    public function test_store_accepts_the_authenticated_bucket(): void
    {
        $user = $this->authenticatedUser();
        $system = AiSystem::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.ai.bots.store'), [
            'name' => 'Member Helper',
            'slug' => 'member-helper',
            'access_path' => 'chat',
            'description' => 'For any signed-in visitor.',
            'ai_system_id' => $system->id,
            'prompt_template' => 'You are {{persona_name}}.',
            'required_permission' => 'authenticated',
            'is_active' => true,
            'require_visitor_identity' => false,
        ]);

        $response->assertRedirect(route('admin.ai.bots.index'));
        $bot = AiChatBot::where('slug', 'member-helper')->firstOrFail();
        $this->assertSame('authenticated', $bot->required_permission);
    }

    public function test_update_preserves_required_permission(): void
    {
        $user = $this->authenticatedUser();
        Permission::firstOrCreate(['name' => 'edit-resume']);

        $bot = AiChatBot::factory()->create([
            'slug' => 'permission-gated',
            'required_permission' => 'manage-ai-tools',
        ]);

        $response = $this->actingAs($user)->put(route('admin.ai.bots.update', $bot), [
            'name' => $bot->name,
            'slug' => 'permission-gated',
            'access_path' => $bot->access_path,
            'ai_system_id' => $bot->ai_system_id,
            'prompt_template' => 'You are {{persona_name}}.',
            'required_permission' => 'edit-resume',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.ai.bots.index'));
        $this->assertSame('edit-resume', $bot->fresh()->required_permission);
    }

    public function test_store_rejects_reserved_root_slug(): void
    {
        $user = $this->authenticatedUser();
        $system = AiSystem::factory()->create();

        $response = $this->actingAs($user)->from(route('admin.ai.bots.create'))->post(route('admin.ai.bots.store'), [
            'name' => 'Resume Bot',
            'slug' => 'resume',
            'access_path' => 'root',
            'description' => 'Conflicts with an existing route.',
            'ai_system_id' => $system->id,
            'prompt_template' => 'You are {{persona_name}}.',
            'required_permission' => null,
            'is_active' => true,
            'require_visitor_identity' => false,
        ]);

        $response->assertRedirect(route('admin.ai.bots.create'));
        $response->assertSessionHasErrors(['slug']);
    }

    public function test_mcp_tools_returns_available_tool_summaries(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->actingAs($user)->getJson(route('admin.ai.bots.mcp-tools', [
            'include_all' => 1,
        ]));

        $response->assertOk();
        $response->assertJsonStructure([
            'tools' => [
                '*' => ['name', 'description'],
            ],
        ]);
        $response->assertJsonFragment([
            'name' => 'get-recent-blog-posts',
            'description' => 'Load recent blog posts with titles, summaries, and URLs. Supports search by keyword in title, summary, or body.',
        ]);
        $response->assertJsonFragment([
            'name' => 'fetch-web-page',
            'description' => 'Fetch a web page by URL and return its readable text content using the JayScraper research user agent.',
        ]);
        $response->assertJsonFragment([
            'name' => 'get-resume-data',
            'description' => "Load the candidate's full resume data (experience, skills, education, projects) before tailoring or editing. "
                .'Includes `resume_version` (the live resume version string) and `pending_revision_number` (the highest-revision '
                .'pending AI-drafted candidate for that version, or null if none exists — tell the user a revision is already in '
                .'progress if this is set, and call update-resume-section to continue it rather than starting a new one). Pass '
                .'`revision_number` to load that specific draft revision\'s data instead of the live resume, e.g. to review what '
                .'a pending revision actually contains.',
        ]);
        $response->assertJsonFragment([
            'name' => 'get-resume-data-for-job',
        ]);
    }

    public function test_mcp_tools_filters_to_the_selected_system_allowlist(): void
    {
        $user = $this->authenticatedUser();
        $system = AiSystem::factory()->create([
            'allowed_tools' => ['get-recent-blog-posts'],
        ]);

        $response = $this->actingAs($user)->getJson(route('admin.ai.bots.mcp-tools', [
            'ai_system_id' => $system->id,
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'tools');
        $response->assertJsonFragment([
            'name' => 'get-recent-blog-posts',
        ]);
        $response->assertJsonMissing([
            'name' => 'get-resume-data',
        ]);
    }
}
