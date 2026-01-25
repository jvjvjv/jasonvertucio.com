<?php

namespace Tests\Feature;

use App\Mail\ResumeShareCodeCreated;
use App\Models\ResumeShareCode;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResumeShareCodeEmailTest extends TestCase
{
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
        $this->admin->assignRole('admin');
    }

    public function test_create_share_code_without_email(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.resume.codes.store'), [
                'name' => 'John Doe',
                'email' => '',
                'expires_at' => null,
                'send_email' => false,
            ]);

        $response->assertRedirect(route('admin.resume.index'));
        $response->assertSessionHas('success');

        // Verify code was created
        $this->assertDatabaseHas('resume_share_codes', [
            'name' => 'John Doe',
            'email' => null,
            'email_sent' => false,
            'notify_on_update' => false,
        ]);

        Mail::assertNotSent(ResumeShareCodeCreated::class);
    }

    public function test_create_share_code_with_email_but_no_send_email_checkbox(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.resume.codes.store'), [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'expires_at' => null,
                'send_email' => false,
            ]);

        $response->assertRedirect(route('admin.resume.index'));
        $response->assertSessionHas('success');

        // Verify code was created without email notification
        $this->assertDatabaseHas('resume_share_codes', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'email_sent' => false,
            'notify_on_update' => false,
        ]);

        Mail::assertNotSent(ResumeShareCodeCreated::class);
    }

    public function test_create_share_code_with_email_and_send_email_checkbox(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.resume.codes.store'), [
                'name' => 'Bob Smith',
                'email' => 'bob@example.com',
                'expires_at' => null,
                'send_email' => true,
            ]);

        $response->assertRedirect(route('admin.resume.index'));
        $response->assertSessionHas('success');
        $this->assertStringContainsString('Email notification sent', $response->getSession()->get('success'));

        // Verify code was created with email notification
        $code = ResumeShareCode::where('email', 'bob@example.com')->first();
        $this->assertNotNull($code);
        $this->assertEquals('Bob Smith', $code->name);
        $this->assertTrue($code->email_sent);
        $this->assertTrue($code->notify_on_update);

        // Verify email was queued
        Mail::assertQueued(ResumeShareCodeCreated::class, function ($mail) use ($code) {
            return $mail->hasTo('bob@example.com') && $mail->code->id === $code->id;
        });
    }

    public function test_share_code_with_email_contains_correct_info(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.resume.codes.store'), [
                'name' => 'Alice Johnson',
                'email' => 'alice@example.com',
                'expires_at' => '2026-12-31',
                'send_email' => true,
            ]);

        $code = ResumeShareCode::where('email', 'alice@example.com')->first();

        Mail::assertQueued(ResumeShareCodeCreated::class, function ($mail) use ($code) {
            return $mail->hasTo('alice@example.com') &&
                   str_contains($mail->code->name, 'Alice Johnson') &&
                   $mail->code->id === $code->id;
        });
    }

    public function test_name_field_is_required(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.resume.codes.store'), [
                'name' => '',
                'email' => 'test@example.com',
                'expires_at' => null,
                'send_email' => false,
            ]);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseMissing('resume_share_codes', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_email_validation(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.resume.codes.store'), [
                'name' => 'John Doe',
                'email' => 'invalid-email',
                'expires_at' => null,
                'send_email' => true,
            ]);

        $response->assertSessionHasErrors('email');
    }
}
