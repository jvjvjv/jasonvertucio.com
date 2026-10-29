<?php

namespace Tests\Feature\Console;

use App\Models\FederatedIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class LinkBspdxIdentityCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_links_an_existing_user(): void
    {
        $user = User::factory()->create();
        $subject = (string) Str::uuid();

        $this->artisan('user:link-bspdx', ['email' => $user->email, 'subject' => $subject])->assertSuccessful();

        $this->assertDatabaseHas('federated_identities', [
            'user_id' => $user->id,
            'provider' => 'bspdx',
            'subject' => $subject,
            'email_at_link' => $user->email,
        ]);
    }

    public function test_it_refuses_an_unknown_user(): void
    {
        $this->artisan('user:link-bspdx', ['email' => 'nobody-'.Str::random(8).'@example.com', 'subject' => (string) Str::uuid()])
            ->assertFailed();
    }

    public function test_it_refuses_an_already_linked_user(): void
    {
        $identity = FederatedIdentity::factory()->create();

        $this->artisan('user:link-bspdx', ['email' => $identity->user->email, 'subject' => (string) Str::uuid()])->assertFailed();

        $this->assertSame(1, $identity->user->federatedIdentities()->count());
    }

    public function test_it_refuses_an_already_linked_subject(): void
    {
        $identity = FederatedIdentity::factory()->create();
        $other = User::factory()->create();

        $this->artisan('user:link-bspdx', ['email' => $other->email, 'subject' => $identity->subject])->assertFailed();

        $this->assertSame(0, $other->federatedIdentities()->count());
    }

    public function test_deleting_a_user_removes_their_identities(): void
    {
        $identity = FederatedIdentity::factory()->create();

        $identity->user->delete();

        $this->assertDatabaseMissing('federated_identities', ['id' => $identity->id]);
    }
}
