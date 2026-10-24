<?php

namespace Tests\Feature;

use App\Models\Application;
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
        $docxPath = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        $pdfPath = tempnam(sys_get_temp_dir(), 'pdf-').'.pdf';
        file_put_contents($docxPath, 'stale');
        file_put_contents($pdfPath, 'stale');

        $conversation = AiConversation::factory()->completed()->create();
        $targetedResume = TargetedResume::factory()->create([
            'docx_path' => $docxPath,
            'pdf_path' => $pdfPath,
            'tailored_data' => [
                'title' => 'Original Title',
                'content' => '# Summary\nOriginal content',
                'format' => 'markdown',
                'markdown' => '# Summary\nOriginal content',
            ],
        ]);
        Application::factory()->create([
            'targeted_resume_id' => $targetedResume->id,
            'ai_conversation_id' => $conversation->id,
        ]);

        $documentService = $this->createMock(TargetedResumeDocumentService::class);
        $documentService->expects($this->never())->method('generateDocx');
        $documentService->expects($this->never())->method('generatePdf');
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

        $this->assertFileDoesNotExist($docxPath);
        $this->assertFileDoesNotExist($pdfPath);
        $this->assertNull($targetedResume->docx_path);
        $this->assertNull($targetedResume->pdf_path);
    }

    public function test_markdown_is_required(): void
    {
        $targetedResume = Application::factory()->withTargetedResume()->create()->targetedResume;

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/resume/targeted-resume/{$targetedResume->id}", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['markdown']);
    }
}
