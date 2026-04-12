<?php

namespace Tests\Feature;

use App\Models\AiChatBot;
use App\Models\AiSystem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AiChatBotControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        Permission::findOrCreate('manage-ai-tools', 'web');
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
            'prompt_template' => 'You are {{bot_name}}.',
            'allowed_roles' => ['admin'],
            'is_active' => true,
            'is_public' => false,
            'require_visitor_identity' => true,
        ]);

        $response->assertRedirect(route('admin.ai.bots.index'));
        $this->assertDatabaseHas('ai_chat_bots', [
            'name' => 'Lead Intake',
            'slug' => 'lead-intake',
            'access_path' => 'root',
            'ai_system_id' => $system->id,
            'is_public' => false,
            'require_visitor_identity' => true,
        ]);
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
            'prompt_template' => 'You are {{bot_name}}.',
            'allowed_roles' => [],
            'is_active' => true,
            'is_public' => true,
            'require_visitor_identity' => false,
        ]);

        $response->assertRedirect(route('admin.ai.bots.create'));
        $response->assertSessionHasErrors(['slug']);
    }
}
