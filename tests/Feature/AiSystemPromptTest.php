<?php

namespace Tests\Feature;

use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Models\AiSystemPrompt;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AiSystemPromptTest extends TestCase
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
        $response = $this->get(route('admin.ai.system-prompts.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_index_requires_manage_ai_tools_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('admin.ai.system-prompts.index'));

        $response->assertForbidden();
    }

    public function test_can_list_system_prompts(): void
    {
        $user = $this->authenticatedUser();
        $this->actingAs($user);

        $prompt = AiSystemPrompt::create([
            'title' => 'Test Prompt',
            'description' => 'A test prompt',
            'content' => 'This is the prompt content.',
        ]);

        $response = $this->get(route('admin.ai.system-prompts.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/system-prompts/Index', false)
            ->where('prompts', fn ($prompts) =>
                collect($prompts)->contains(fn ($p) => $p['id'] === $prompt->id && $p['title'] === 'Test Prompt')
            )
        );
    }

    public function test_can_create_system_prompt(): void
    {
        $user = $this->authenticatedUser();
        $this->actingAs($user);

        $response = $this->post(route('admin.ai.system-prompts.store'), [
            'title' => 'New Prompt',
            'description' => 'A new prompt for testing',
            'content' => 'You are a helpful assistant.',
        ]);

        $response->assertRedirect(route('admin.ai.system-prompts.index'));

        $this->assertDatabaseHas('ai_system_prompts', [
            'title' => 'New Prompt',
            'description' => 'A new prompt for testing',
            'content' => 'You are a helpful assistant.',
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $user = $this->authenticatedUser();
        $this->actingAs($user);

        $response = $this->post(route('admin.ai.system-prompts.store'), []);

        $response->assertSessionHasErrors(['title', 'description', 'content']);
    }

    public function test_create_validates_title_max_length(): void
    {
        $user = $this->authenticatedUser();
        $this->actingAs($user);

        $response = $this->post(route('admin.ai.system-prompts.store'), [
            'title' => str_repeat('a', 65),
            'description' => 'Valid description',
            'content' => 'Valid content',
        ]);

        $response->assertSessionHasErrors(['title']);
    }

    public function test_can_update_system_prompt(): void
    {
        $user = $this->authenticatedUser();
        $this->actingAs($user);

        $prompt = AiSystemPrompt::create([
            'title' => 'Original Title',
            'description' => 'Original description',
            'content' => 'Original content',
        ]);

        $response = $this->put(route('admin.ai.system-prompts.update', $prompt), [
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'content' => 'Updated content',
        ]);

        $response->assertRedirect(route('admin.ai.system-prompts.index'));

        $this->assertDatabaseHas('ai_system_prompts', [
            'id' => $prompt->id,
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'content' => 'Updated content',
        ]);
    }

    public function test_can_delete_system_prompt_and_nullifies_ai_system_references(): void
    {
        $user = $this->authenticatedUser();
        $this->actingAs($user);

        $prompt = AiSystemPrompt::create([
            'title' => 'To Be Deleted',
            'description' => 'Will be removed',
            'content' => 'Some content',
        ]);

        $system = AiSystem::factory()->create(['system_prompt_id' => $prompt->id]);

        $response = $this->delete(route('admin.ai.system-prompts.destroy', $prompt));

        $response->assertRedirect(route('admin.ai.system-prompts.index'));

        $this->assertDatabaseMissing('ai_system_prompts', ['id' => $prompt->id]);
        $this->assertDatabaseHas('ai_systems', [
            'id' => $system->id,
            'system_prompt_id' => null,
        ]);
    }

    public function test_api_update_returns_json(): void
    {
        $user = $this->authenticatedUser();
        $this->actingAs($user);

        $prompt = AiSystemPrompt::create([
            'title' => 'API Prompt',
            'description' => 'Updated via API',
            'content' => 'Original API content',
        ]);

        $response = $this->putJson("/api/admin/ai/system-prompts/{$prompt->id}", [
            'title' => 'API Prompt',
            'description' => 'Updated via API',
            'content' => 'New API content',
        ]);

        $response->assertOk();
        $response->assertJsonPath('prompt.content', 'New API content');
    }
}
