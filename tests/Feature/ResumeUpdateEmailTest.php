<?php

namespace Tests\Feature;

use App\Mail\ResumeUpdated;
use App\Models\ResumeShareCode;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ResumeUpdateEmailTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user with unique email
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
        ]);

        Role::findOrCreate('admin', 'web');
        Permission::findOrCreate('edit-resume', 'web');
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo('edit-resume');
    }

    public function test_resume_update_without_notify_recipients(): void
    {
        Mail::fake();

        // Clean up any existing codes
        ResumeShareCode::query()->forceDelete();

        // Create share codes with email
        $code1 = ResumeShareCode::create([
            'id' => ResumeShareCode::generateCode(),
            'name' => 'Recipient 1',
            'email' => 'recipient1@example.com',
            'email_sent' => true,
            'notify_on_update' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.editor.save'), [
                'version' => '2026.1.0',
                'data' => $this->getValidResumeData(),
                'notify_recipients' => false,
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');

        Mail::assertNotQueued(ResumeUpdated::class);
    }

    public function test_resume_update_with_notify_recipients(): void
    {
        Mail::fake();

        // Clean up any existing codes
        ResumeShareCode::query()->forceDelete();

        // Create share codes with email - some should notify, some shouldn't
        $code1 = ResumeShareCode::create([
            'id' => ResumeShareCode::generateCode(),
            'name' => 'Should Notify',
            'email' => 'notify1@example.com',
            'email_sent' => true,
            'notify_on_update' => true,
        ]);

        ResumeShareCode::create([
            'id' => ResumeShareCode::generateCode(),
            'name' => 'Should Not Notify',
            'email' => 'notify2@example.com',
            'email_sent' => true,
            'notify_on_update' => false,
        ]);

        ResumeShareCode::create([
            'id' => ResumeShareCode::generateCode(),
            'name' => 'No Email',
            'email' => null,
            'email_sent' => false,
            'notify_on_update' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.editor.save'), [
                'version' => '2026.1.0',
                'data' => $this->getValidResumeData(),
                'notify_recipients' => true,
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $this->assertStringContainsString('1 recipient', $response->json('message'));

        // Only code1 should receive email
        Mail::assertQueued(ResumeUpdated::class, function ($mail) use ($code1) {
            return $mail->hasTo('notify1@example.com') && $mail->code->id === $code1->id;
        });

        // code2 should not receive email (notify_on_update = false)
        Mail::assertNotQueued(ResumeUpdated::class, function ($mail) {
            return $mail->hasTo('notify2@example.com');
        });
    }

    public function test_resume_update_with_expired_codes_not_notified(): void
    {
        Mail::fake();

        // Clean up any existing codes
        ResumeShareCode::query()->forceDelete();

        // Create code that expired yesterday (should be invalid)
        ResumeShareCode::create([
            'id' => ResumeShareCode::generateCode(),
            'name' => 'Expired Code',
            'email' => 'expired@example.com',
            'expires_at' => now()->subDay()->toDateString(),
            'email_sent' => true,
            'notify_on_update' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.editor.save'), [
                'version' => '2026.1.0',
                'data' => $this->getValidResumeData(),
                'notify_recipients' => true,
            ]);

        $response->assertOk();

        // Expired code should not receive email
        Mail::assertNotQueued(ResumeUpdated::class, function ($mail) {
            return $mail->hasTo('expired@example.com');
        });
    }

    public function test_resume_update_with_soft_deleted_codes_not_notified(): void
    {
        Mail::fake();

        // Clean up any existing codes
        ResumeShareCode::query()->forceDelete();

        // Create code that is soft deleted
        $code1 = ResumeShareCode::create([
            'id' => ResumeShareCode::generateCode(),
            'name' => 'Deleted Code',
            'email' => 'deleted@example.com',
            'email_sent' => true,
            'notify_on_update' => true,
        ]);
        $code1->delete();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.editor.save'), [
                'version' => '2026.1.0',
                'data' => $this->getValidResumeData(),
                'notify_recipients' => true,
            ]);

        $response->assertOk();

        // Soft deleted code should not receive email
        Mail::assertNotQueued(ResumeUpdated::class, function ($mail) {
            return $mail->hasTo('deleted@example.com');
        });
    }

    public function test_multiple_recipients_all_notified(): void
    {
        Mail::fake();

        // Clean up any existing codes
        ResumeShareCode::query()->forceDelete();

        // Create multiple codes that should be notified
        $recipients = [
            ['email' => 'recipient1@example.com', 'name' => 'Recipient 1'],
            ['email' => 'recipient2@example.com', 'name' => 'Recipient 2'],
            ['email' => 'recipient3@example.com', 'name' => 'Recipient 3'],
        ];

        foreach ($recipients as $recipient) {
            ResumeShareCode::create([
                'id' => ResumeShareCode::generateCode(),
                'name' => $recipient['name'],
                'email' => $recipient['email'],
                'email_sent' => true,
                'notify_on_update' => true,
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.editor.save'), [
                'version' => '2026.1.0',
                'data' => $this->getValidResumeData(),
                'notify_recipients' => true,
            ]);

        $response->assertOk();
        $this->assertStringContainsString('3 recipient', $response->json('message'));

        // All three should receive emails
        foreach ($recipients as $recipient) {
            Mail::assertQueued(ResumeUpdated::class, function ($mail) use ($recipient) {
                return $mail->hasTo($recipient['email']);
            });
        }
    }

    protected function getValidResumeData(): array
    {
        return [
            'personal' => [
                'name' => 'Jason Vertucio',
                'title' => 'Software Engineer',
                'email' => 'jason@example.com',
                'phone' => '555-1234',
                'linkedin' => 'linkedin.com/in/jasonvertucio',
                'summary' => 'A software engineer',
            ],
            'skills' => [
                'top' => [
                    [
                        'title' => 'Languages',
                        'list' => ['PHP', 'JavaScript'],
                    ]
                ],
                'other' => [],
            ],
            'experience' => [
                [
                    'jobTitle' => 'Software Engineer',
                    'company' => 'ACME Corp',
                    'location' => 'New York',
                    'dates' => ['2020', '2024'],
                    'bullets' => ['Built APIs', 'Mentored juniors'],
                ]
            ],
            'education' => [
                [
                    'institution' => 'University',
                    'degree' => 'BS Computer Science',
                    'dates' => ['2016', '2020'],
                    'description' => 'Good standing',
                ]
            ],
            'projects' => [
                [
                    'projectName' => 'My Project',
                    'description' => 'An awesome project',
                    'bullets' => ['Built with PHP', 'Used Laravel'],
                ]
            ],
        ];
    }
}
