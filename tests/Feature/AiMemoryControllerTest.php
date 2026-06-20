<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Jvjvjv\CodeTalker\Models\AiFeatureMemory;
use Tests\TestCase;

class AiMemoryControllerTest extends TestCase
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
        $response = $this->get(route('admin.ai.memories.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_index_requires_manage_ai_tools_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('admin.ai.memories.index'));

        $response->assertForbidden();
    }

    public function test_index_displays_memories(): void
    {
        $user = $this->authenticatedUser();
        $this->actingAs($user);

        AiFeatureMemory::factory()->create([
            'feature' => 'targeted-resume',
            'key' => 'test-memory-key',
        ]);

        $response = $this->get(route('admin.ai.memories.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/memories/Index', false)
            ->where('memories.data', fn ($memories) => collect($memories)->count() === 1
                && collect($memories)->first()['key'] === 'test-memory-key')
        );
    }

    public function test_index_filters_by_feature(): void
    {
        $user = $this->authenticatedUser();
        $this->actingAs($user);

        AiFeatureMemory::factory()->create([
            'feature' => 'targeted-resume',
            'key' => 'resume-memory',
        ]);

        AiFeatureMemory::factory()->create([
            'feature' => 'other-feature',
            'key' => 'other-memory',
        ]);

        $response = $this->get(route('admin.ai.memories.index', ['feature' => 'targeted-resume']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/memories/Index', false)
            ->where('filters.feature', 'targeted-resume')
            ->where('memories.data', fn ($memories) => collect($memories)->count() === 1
                && collect($memories)->first()['key'] === 'resume-memory')
        );
    }

    public function test_create_displays_form(): void
    {
        $user = $this->authenticatedUser();
        $this->actingAs($user);

        $response = $this->get(route('admin.ai.memories.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ai/memories/Create', false)
        );
    }

    public function test_store_creates_memory(): void
    {
        $user = $this->authenticatedUser();
        $this->actingAs($user);

        $response = $this->post(route('admin.ai.memories.store'), [
            'feature' => 'targeted-resume',
            'category' => 'preference',
            'key' => 'new-test-memory',
            'content' => 'Test memory content',
            'confidence' => 75,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.ai.memories.index'));
        $this->assertDatabaseHas('ai_feature_memories', [
            'key' => 'new-test-memory',
            'content' => 'Test memory content',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = $this->authenticatedUser();
        $this->actingAs($user);

        $response = $this->post(route('admin.ai.memories.store'), []);

        $response->assertSessionHasErrors(['feature', 'category', 'key', 'content', 'confidence']);
    }

    public function test_update_saves_changes(): void
    {
        $user = $this->authenticatedUser();
        $this->actingAs($user);

        $memory = AiFeatureMemory::factory()->create([
            'content' => 'Original content',
        ]);

        $response = $this->put(route('admin.ai.memories.update', $memory), [
            'category' => $memory->category,
            'key' => $memory->key,
            'content' => 'Updated content',
            'confidence' => 90,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.ai.memories.index'));
        $this->assertEquals('Updated content', $memory->fresh()->content);
        $this->assertEquals(90, $memory->fresh()->confidence);
    }

    public function test_destroy_removes_memory(): void
    {
        $user = $this->authenticatedUser();
        $this->actingAs($user);

        $memory = AiFeatureMemory::factory()->create();

        $response = $this->delete(route('admin.ai.memories.destroy', $memory));

        $response->assertRedirect(route('admin.ai.memories.index'));
        $this->assertDatabaseMissing('ai_feature_memories', ['id' => $memory->id]);
    }
}
