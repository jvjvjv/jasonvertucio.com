<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationContact;
use App\Models\ApplicationFollowUp;
use App\Models\User;
use App\Services\Mcp\Tools\TargetedResume\GetJobDescriptionTool;
use App\Services\Mcp\Tools\TargetedResume\GetTargetedResumeContextTool;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Jvjvjv\CodeTalker\Models\AiConversation as BaseAiConversation;
use Jvjvjv\CodeTalker\Services\Mcp\ToolResultConverter;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Tests\TestCase;

class ApplicationContactTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $this->admin->givePermissionTo('edit-resume');
    }

    public function test_adding_a_recruiter(): void
    {
        $application = Application::factory()->applied()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.employment.applications.contacts.store', $application), [
                'email' => 'pat@example.com',
                'name' => 'Pat Lee',
                'role' => 'Recruiter',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'contacts')
            ->assertJsonPath('contacts.0.email', 'pat@example.com')
            ->assertJsonPath('contacts.0.name', 'Pat Lee')
            ->assertJsonPath('contacts.0.role', 'Recruiter')
            ->assertJsonPath('contacts.0.notes', null);

        $this->assertDatabaseHas('application_contacts', [
            'application_id' => $application->id,
            'email' => 'pat@example.com',
            'name' => 'Pat Lee',
            'role' => 'Recruiter',
        ]);
    }

    public function test_the_email_is_stored_lower_cased(): void
    {
        $application = Application::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.employment.applications.contacts.store', $application), ['email' => '  Pat@Example.COM '])
            ->assertOk()
            ->assertJsonPath('contacts.0.email', 'pat@example.com');
    }

    public function test_a_duplicate_address_is_refused_ignoring_case(): void
    {
        $application = Application::factory()->withContact(['email' => 'pat@example.com'])->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.employment.applications.contacts.store', $application), ['email' => 'Pat@Example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'This application already has a contact with that email address.']);

        $this->assertSame(1, $application->contacts()->count());
    }

    public function test_an_invalid_address_is_refused(): void
    {
        $application = Application::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.employment.applications.contacts.store', $application), ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'That is not a valid email address.']);

        $this->assertSame(0, $application->contacts()->count());
    }

    public function test_an_email_is_required(): void
    {
        $application = Application::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.employment.applications.contacts.store', $application), ['name' => 'Pat'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_the_same_address_may_be_on_two_applications(): void
    {
        $first = Application::factory()->withContact(['email' => 'pat@example.com'])->create();
        $second = Application::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.employment.applications.contacts.store', $second), ['email' => 'pat@example.com'])
            ->assertOk();

        $this->assertSame(1, $first->contacts()->where('email', 'pat@example.com')->count());
        $this->assertSame(1, $second->contacts()->where('email', 'pat@example.com')->count());
    }

    public function test_editing_a_contact_may_keep_its_own_address(): void
    {
        $application = Application::factory()->withContact(['email' => 'pat@example.com', 'name' => 'Pat'])->create();
        $contact = $application->contacts()->sole();

        $this->actingAs($this->admin)
            ->putJson(route('admin.employment.applications.contacts.update', [$application, $contact]), [
                'email' => 'PAT@example.com',
                'name' => 'Pat Lee',
                'role' => 'Hiring Manager',
                'notes' => 'Met at the meetup.',
            ])
            ->assertOk()
            ->assertJsonPath('contacts.0.name', 'Pat Lee')
            ->assertJsonPath('contacts.0.role', 'Hiring Manager')
            ->assertJsonPath('contacts.0.notes', 'Met at the meetup.');

        $this->assertSame('pat@example.com', $contact->fresh()->email);
    }

    public function test_editing_a_contact_to_another_contacts_address_is_refused(): void
    {
        $application = Application::factory()
            ->withContact(['email' => 'pat@example.com'])
            ->withContact(['email' => 'sam@example.com'])
            ->create();
        $sam = $application->contacts()->where('email', 'sam@example.com')->sole();

        $this->actingAs($this->admin)
            ->putJson(route('admin.employment.applications.contacts.update', [$application, $sam]), ['email' => 'Pat@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame('sam@example.com', $sam->fresh()->email);
    }

    public function test_removing_a_contact_keeps_it_among_a_sent_follow_ups_recipients(): void
    {
        $application = Application::factory()->withContact(['email' => 'pat@example.com'])->create();
        $contact = $application->contacts()->sole();
        $sent = ApplicationFollowUp::factory()->sent(null, ['pat@example.com'])->create(['application_id' => $application->id]);

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.employment.applications.contacts.destroy', [$application, $contact]))
            ->assertOk()
            ->assertJsonCount(0, 'contacts')
            ->assertJsonPath('follow_up_history.0.recipients', ['pat@example.com']);

        $this->assertModelMissing($contact);
        $this->assertSame(['pat@example.com'], $sent->fresh()->recipients);
    }

    public function test_a_contact_from_another_application_is_not_found(): void
    {
        $application = Application::factory()->create();
        $other = Application::factory()->withContact(['email' => 'pat@example.com'])->create();
        $contact = $other->contacts()->sole();

        $this->actingAs($this->admin)
            ->putJson(route('admin.employment.applications.contacts.update', [$application, $contact]), ['email' => 'sam@example.com'])
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.employment.applications.contacts.destroy', [$application, $contact]))
            ->assertNotFound();

        $this->assertSame('pat@example.com', $contact->fresh()->email);
    }

    public function test_every_contact_and_follow_up_endpoint_needs_edit_resume(): void
    {
        $outsider = User::factory()->create();
        $application = Application::factory()->withContact()->create();
        $contact = $application->contacts()->sole();
        $followUp = ApplicationFollowUp::factory()->create(['application_id' => $application->id]);

        $this->actingAs($outsider)
            ->postJson(route('admin.employment.applications.contacts.store', $application), ['email' => 'pat@example.com'])
            ->assertForbidden();
        $this->actingAs($outsider)
            ->putJson(route('admin.employment.applications.contacts.update', [$application, $contact]), ['email' => 'pat@example.com'])
            ->assertForbidden();
        $this->actingAs($outsider)
            ->deleteJson(route('admin.employment.applications.contacts.destroy', [$application, $contact]))
            ->assertForbidden();
        $this->actingAs($outsider)
            ->putJson(route('admin.employment.applications.follow-ups.update', [$application, $followUp]), ['subject' => 'S', 'body' => 'B'])
            ->assertForbidden();
        $this->actingAs($outsider)
            ->postJson(route('admin.employment.applications.follow-ups.approve', [$application, $followUp]))
            ->assertForbidden();
        $this->actingAs($outsider)
            ->postJson(route('admin.employment.applications.follow-ups.discard', [$application, $followUp]))
            ->assertForbidden();
        $this->actingAs($outsider)
            ->putJson(route('admin.employment.applications.update', $application), ['follow_ups_enabled' => true])
            ->assertForbidden();

        $this->assertTrue(ApplicationContact::query()->whereKey($contact->id)->exists());
        $this->assertFalse($application->fresh()->follow_ups_enabled);
    }

    public function test_the_session_tools_never_return_contact_addresses(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'save-resume']);
        $user->givePermissionTo('save-resume');
        $application = Application::factory()
            ->withTargetedResume()
            ->withContact(['email' => 'pat@example.com', 'name' => 'Pat Lee'])
            ->create(['ai_conversation_id' => BaseAiConversation::factory()->create(['user_id' => $user->id])->id]);
        $context = ToolContext::forConversation(BaseAiConversation::query()->findOrFail($application->ai_conversation_id));

        foreach ([GetJobDescriptionTool::class, GetTargetedResumeContextTool::class] as $tool) {
            $result = json_encode(ToolResultConverter::toArray(app()->makeWith($tool, ['context' => $context])->handle(new Request([]))));

            $this->assertStringContainsString(json_encode($application->position), $result, $tool);
            $this->assertStringNotContainsString('pat@example.com', $result, $tool);
            $this->assertStringNotContainsString('Pat Lee', $result, $tool);
        }
    }
}
