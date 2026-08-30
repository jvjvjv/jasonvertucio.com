<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The profile-page toggle for `show_tool_payloads`, which is only offered to —
 * and only settable by — a user holding `manage-ai-tools`.
 */
class ProfileToolVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    private function permittedUser(bool $showToolPayloads = false): User
    {
        Permission::firstOrCreate(['name' => 'manage-ai-tools']);

        $user = User::factory()->create(['show_tool_payloads' => $showToolPayloads]);
        $user->givePermissionTo('manage-ai-tools');

        return $user;
    }

    public function test_permitted_user_sees_the_toggle(): void
    {
        $response = $this->actingAs($this->permittedUser())->get(route('profile.show'));

        $response->assertOk();
        $response->assertSee('show_tool_payloads', false);
        $response->assertSee('Show tool call details in chat', false);
    }

    public function test_permitted_user_sees_the_toggle_reflecting_an_enabled_preference(): void
    {
        $response = $this->actingAs($this->permittedUser(true))->get(route('profile.show'));

        $response->assertOk();
        $response->assertSee('name="show_tool_payloads" value="1"', false);
        $response->assertSee('checked', false);
    }

    public function test_unpermitted_user_never_sees_the_toggle(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('profile.show'));

        $response->assertOk();
        $response->assertDontSee('show_tool_payloads', false);
        $response->assertDontSee('Show tool call details in chat', false);
    }

    public function test_permitted_user_can_enable_the_preference(): void
    {
        $user = $this->permittedUser();

        $this->actingAs($user)
            ->put(route('profile.tool-visibility.update'), ['show_tool_payloads' => '1'])
            ->assertRedirect();

        $this->assertTrue($user->fresh()->show_tool_payloads);
    }

    public function test_permitted_user_can_disable_the_preference(): void
    {
        $user = $this->permittedUser(true);

        // An unchecked checkbox submits nothing at all.
        $this->actingAs($user)
            ->put(route('profile.tool-visibility.update'), [])
            ->assertRedirect();

        $this->assertFalse($user->fresh()->show_tool_payloads);
    }

    public function test_setting_the_preference_without_the_permission_is_rejected(): void
    {
        $user = User::factory()->create(['show_tool_payloads' => false]);

        $this->actingAs($user)
            ->put(route('profile.tool-visibility.update'), ['show_tool_payloads' => '1'])
            ->assertForbidden();

        $this->assertFalse($user->fresh()->show_tool_payloads);
    }

    public function test_the_preference_persists_across_sessions(): void
    {
        $user = $this->permittedUser();

        $this->actingAs($user)
            ->put(route('profile.tool-visibility.update'), ['show_tool_payloads' => '1'])
            ->assertRedirect();

        auth()->logout();
        $this->assertGuest();

        $response = $this->actingAs($user->fresh())->get(route('profile.show'));

        $response->assertOk();
        $this->assertTrue($user->fresh()->show_tool_payloads);
        $response->assertSee('name="show_tool_payloads" value="1"', false);
    }

    public function test_guest_cannot_set_the_preference(): void
    {
        $this->put(route('profile.tool-visibility.update'), ['show_tool_payloads' => '1'])
            ->assertRedirect(route('login'));
    }
}
