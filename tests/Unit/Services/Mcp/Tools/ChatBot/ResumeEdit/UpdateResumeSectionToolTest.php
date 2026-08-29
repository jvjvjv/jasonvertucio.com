<?php

namespace Tests\Unit\Services\Mcp\Tools\ChatBot\ResumeEdit;

use App\Models\ResumeEditCandidate;
use App\Models\ResumeVersion;
use App\Models\User;
use App\Services\DatabaseResumeDataService;
use App\Services\DatabaseResumeVersionService;
use App\Services\Mcp\Tools\ChatBot\ResumeEdit\UpdateResumeSectionTool;
use App\Services\ResumeEditCandidateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use Jvjvjv\CodeTalker\Services\Mcp\ToolResultConverter;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Tests\TestCase;

class UpdateResumeSectionToolTest extends TestCase
{
    use DatabaseTransactions;

    private function candidateService(): ResumeEditCandidateService
    {
        $dataService = new DatabaseResumeDataService;

        return new ResumeEditCandidateService($dataService, new DatabaseResumeVersionService($dataService));
    }

    private function liveVersion(): ResumeVersion
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);
        $version->personalInfo()->create([
            'name' => 'Jason Vertucio',
            'title' => 'Engineer',
            'email' => 'jason@example.com',
        ]);

        return $version;
    }

    /**
     * @return array<string, mixed>
     */
    private function handle(ToolContext $context, array $input): array
    {
        $tool = new UpdateResumeSectionTool($context, $this->candidateService());

        return ToolResultConverter::toArray($tool->handle(new Request($input)));
    }

    public function test_it_rejects_anonymous_callers(): void
    {
        $this->liveVersion();

        $result = $this->handle(ToolContext::forUser(null), [
            'section' => 'personal',
            'data' => json_encode(['name' => 'New Name', 'title' => 'Engineer', 'email' => 'jason@example.com']),
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(0, ResumeEditCandidate::count());
    }

    public function test_it_rejects_users_without_edit_resume_permission(): void
    {
        $this->liveVersion();
        $user = User::factory()->create();

        $result = $this->handle(ToolContext::forUser($user->id), [
            'section' => 'personal',
            'data' => json_encode(['name' => 'New Name', 'title' => 'Engineer', 'email' => 'jason@example.com']),
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(0, ResumeEditCandidate::count());
    }

    public function test_authorized_user_edit_creates_a_candidate_without_changing_the_live_resume(): void
    {
        $base = $this->liveVersion();
        $user = User::factory()->create();
        $user->givePermissionTo('edit-resume');

        $result = $this->handle(ToolContext::forUser($user->id), [
            'section' => 'personal',
            'data' => json_encode(['name' => 'New Name', 'title' => 'Engineer', 'email' => 'jason@example.com']),
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, ResumeEditCandidate::count());

        $candidate = ResumeEditCandidate::first();
        $this->assertSame('New Name', $candidate->snapshot['personal']['name']);
        $this->assertSame('Jason Vertucio', $base->fresh()->personalInfo->name);
    }

    public function test_it_rejects_invalid_json_payload(): void
    {
        $this->liveVersion();
        $user = User::factory()->create();
        $user->givePermissionTo('edit-resume');

        $result = $this->handle(ToolContext::forUser($user->id), [
            'section' => 'personal',
            'data' => 'not json',
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(0, ResumeEditCandidate::count());
    }

    public function test_successful_edit_records_a_tagged_conversation_message(): void
    {
        $this->liveVersion();
        $user = User::factory()->create();
        $user->givePermissionTo('edit-resume');
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

        $result = $this->handle(ToolContext::forConversation($conversation), [
            'section' => 'personal',
            'data' => json_encode(['name' => 'New Name', 'title' => 'Engineer', 'email' => 'jason@example.com']),
            'summary' => 'Updated my name.',
        ]);

        $this->assertTrue($result['success']);

        $message = AiConversationMessage::where('ai_conversation_id', $conversation->id)->latest('id')->first();
        $this->assertNotNull($message);
        $this->assertSame('user', $message->role);
        $this->assertSame('ai_resume_edit', $message->metadata['origin']);
        $this->assertStringContainsString('Updated my name.', $message->content);
    }

    public function test_it_recovers_a_leading_json_value_when_the_model_leaks_trailing_garbage(): void
    {
        $this->liveVersion();
        $user = User::factory()->create();
        $user->givePermissionTo('edit-resume');

        // Reproduces a real failure: a local model's own tool-call XML syntax
        // ("</parameter><parameter=summary>...") leaked into the `data` string
        // after the valid JSON closed.
        $data = json_encode(['top' => [], 'other' => []])
            ."</parameter>\n<parameter=summary>\nStreamlined the skills section.";

        $result = $this->handle(ToolContext::forUser($user->id), [
            'section' => 'skills',
            'data' => $data,
        ]);

        $this->assertTrue($result['success']);

        $candidate = ResumeEditCandidate::first();
        $this->assertSame(['top' => [], 'other' => []], $candidate->snapshot['skills']);
    }

    public function test_it_rejects_an_unknown_section(): void
    {
        $this->liveVersion();
        $user = User::factory()->create();
        $user->givePermissionTo('edit-resume');

        $result = $this->handle(ToolContext::forUser($user->id), [
            'section' => 'bogus',
            'data' => json_encode(['foo' => 'bar']),
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(0, ResumeEditCandidate::count());
    }
}
