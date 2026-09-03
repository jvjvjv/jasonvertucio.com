<?php

namespace Tests\Feature;

use App\Models\TargetedResume;
use App\Models\User;
use App\Services\TargetedResumeDocumentService;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use Tests\TestCase;

class TargetedResumeMarkdownUpdateTest extends TestCase
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

    public function test_admin_can_manually_update_targeted_resume_markdown(): void
    {
        $conversation = AiConversation::factory()->completed()->create();
        $targetedResume = TargetedResume::factory()->finalized()->create([
            'ai_conversation_id' => $conversation->id,
            'tailored_data' => [
                'title' => 'Original Title',
                'content' => '# Summary\nOriginal content',
                'format' => 'markdown',
                'markdown' => '# Summary\nOriginal content',
            ],
        ]);

        $documentService = $this->createMock(TargetedResumeDocumentService::class);
        $documentService->expects($this->once())->method('generateDocx')->willReturn(['success' => true]);
        $documentService->expects($this->once())->method('generatePdf')->willReturn(['success' => true]);
        $this->app->instance(TargetedResumeDocumentService::class, $documentService);

        $newMarkdown = "Title: Updated Title\n\n# Summary\nHand-edited content.";

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/resume/targeted-resume/{$targetedResume->id}", [
                'markdown' => $newMarkdown,
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $targetedResume->refresh();

        $this->assertSame('Updated Title', $targetedResume->title);
        $this->assertSame('Hand-edited content.', trim(str_replace('# Summary', '', data_get($targetedResume->tailored_data, 'markdown'))));
        $this->assertSame(
            data_get($targetedResume->tailored_data, 'markdown'),
            data_get($targetedResume->tailored_data, 'content'),
        );

        $this->assertDatabaseHas('ai_conversation_messages', [
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
        ]);

        $manualEditMessage = AiConversationMessage::query()
            ->where('ai_conversation_id', $conversation->id)
            ->where('role', 'user')
            ->latest('id')
            ->first();

        $this->assertNotNull($manualEditMessage);
        $this->assertSame('manual_edit', data_get($manualEditMessage->metadata, 'origin'));
        $this->assertSame($targetedResume->id, data_get($manualEditMessage->metadata, 'targeted_resume_id'));
        $this->assertStringContainsString('Hand-edited content.', $manualEditMessage->content);
    }

    public function test_markdown_is_persisted_even_when_docx_regeneration_fails(): void
    {
        $conversation = AiConversation::factory()->completed()->create();
        $targetedResume = TargetedResume::factory()->finalized()->create([
            'ai_conversation_id' => $conversation->id,
        ]);

        $documentService = $this->createMock(TargetedResumeDocumentService::class);
        $documentService->expects($this->once())
            ->method('generateDocx')
            ->willReturn(['success' => false, 'error' => 'Template missing.']);
        $documentService->expects($this->never())->method('generatePdf');
        $this->app->instance(TargetedResumeDocumentService::class, $documentService);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/resume/targeted-resume/{$targetedResume->id}", [
                'markdown' => 'Updated after failure.',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);

        $targetedResume->refresh();

        $this->assertStringContainsString('Updated after failure.', (string) data_get($targetedResume->tailored_data, 'markdown'));
    }

    public function test_markdown_is_required(): void
    {
        $conversation = AiConversation::factory()->completed()->create();
        $targetedResume = TargetedResume::factory()->finalized()->create([
            'ai_conversation_id' => $conversation->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/resume/targeted-resume/{$targetedResume->id}", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['markdown']);
    }
}
