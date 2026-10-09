<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationStatusUpdate;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Models\User;
use App\Services\TargetedResumeDocumentService;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use Jvjvjv\CodeTalker\Models\AiLlmMessage;
use Jvjvjv\CodeTalker\Services\LaravelAi\AgentFactory;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Specs `targeted-resume-list` and the `targeted-resume-manual-editing`
 * delta, plus the HTTP side of "Discarding a targeted resume".
 *
 * Fixtures carry the marker "Trlistzq" and lists are read through a search
 * for it, because the database may hold targeted resumes this suite did not
 * create.
 */
class TargetedResumeListTest extends TestCase
{
    use DatabaseTransactions;

    private const string MARKER = 'Trlistzq';

    private User $admin;

    /**
     * @var array<int, string>
     */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AgentFactory::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('forSystem');
        });

        $this->admin = User::factory()->create();
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $this->admin->givePermissionTo('edit-resume');
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    // ---------------------------------------------------------------------
    // Requirement: Targeted Resumes list page
    // ---------------------------------------------------------------------

    public function test_only_applications_with_a_targeted_resume_appear(): void
    {
        $targeted = Application::factory()->withTargetedResume()->create(['company_name' => self::MARKER.' Targeted Co']);
        Application::factory()->applied()->create(['company_name' => self::MARKER.' Main Resume Co']);
        Application::factory()->withConversation()->create(['company_name' => self::MARKER.' Analysed Only Co']);

        $this->assertSame([$targeted->targeted_resume_id], $this->listedIds());

        $this->listPage()->assertInertia(fn (Assert $page) => $page
            ->component('resume/targeted/Index', false)
            ->has('targetedResumes', 1)
            ->where('targetedResumes.0.application_id', $targeted->id)
        );
    }

    public function test_a_deleted_applications_targeted_resume_is_not_listed(): void
    {
        $kept = Application::factory()->withTargetedResume()->create(['company_name' => self::MARKER.' Kept Co']);
        $deleted = Application::factory()->withTargetedResume()->create(['company_name' => self::MARKER.' Deleted Co']);

        $this->actingAs($this->admin)
            ->delete(route('admin.resume.applications.destroy', $deleted))
            ->assertRedirect(route('admin.resume.applications.index'));

        $this->assertSame([$kept->targeted_resume_id], $this->listedIds());
        $this->assertNotNull(TargetedResume::query()->find($deleted->targeted_resume_id));
    }

    public function test_a_targeted_resume_with_no_application_is_not_listed(): void
    {
        $orphan = TargetedResume::factory()->create(['title' => self::MARKER.' Orphan Title']);
        $listed = Application::factory()->withTargetedResume()->create(['company_name' => self::MARKER.' Listed Co']);

        $this->assertSame([$listed->targeted_resume_id], $this->listedIds());
        $this->assertNotContains($orphan->id, $this->listedIds());
    }

    public function test_most_recently_edited_is_listed_first(): void
    {
        $oldest = Application::factory()->withTargetedResume()->create(['company_name' => self::MARKER.' Oldest']);
        $newest = Application::factory()->withTargetedResume()->create(['company_name' => self::MARKER.' Newest']);
        $middle = Application::factory()->withTargetedResume()->create(['company_name' => self::MARKER.' Middle']);

        $this->editedAt($oldest, '2026-03-01 10:00:00');
        $this->editedAt($newest, '2026-03-03 10:00:00');
        $this->editedAt($middle, '2026-03-02 10:00:00');
        Application::query()->whereKey($oldest->id)->update(['updated_at' => '2026-04-01 10:00:00']);

        $this->assertSame(
            [$newest->targeted_resume_id, $middle->targeted_resume_id, $oldest->targeted_resume_id],
            $this->listedIds(),
        );
    }

    public function test_row_shows_the_application_the_resume_and_its_base_version(): void
    {
        $this->travelTo(Carbon::parse('2026-03-05 10:00:00'));
        $version = ResumeVersion::factory()->create();
        $application = Application::factory()->withTargetedResume()->create([
            'resume_version_id' => $version->id,
            'company_name' => self::MARKER.' Row Co',
            'position' => 'Staff Engineer',
        ]);
        $targetedResume = $application->targetedResume;
        $this->editedAt($application, '2026-03-03 10:00:00');

        $this->listPage()->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['search' => self::MARKER])
            ->where('targetedResumes', [[
                'id' => $targetedResume->id,
                'title' => $targetedResume->title,
                'application_id' => $application->id,
                'company_name' => self::MARKER.' Row Co',
                'position' => 'Staff Engineer',
                'resume_version' => $version->version,
                'updated_at' => Carbon::parse('2026-03-03 10:00:00')->toIso8601String(),
                'updated_at_human' => '2 days ago',
                'can_discard' => true,
            ]])
        );

        $this->travelBack();
    }

    public function test_opening_the_editor(): void
    {
        $application = Application::factory()->withTargetedResume()->create(['company_name' => self::MARKER.' Editor Co']);

        $targetedResumeId = $this->listedIds()[0];

        $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.edit', $targetedResumeId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resume/targeted/Edit', false)
                ->where('targetedResume.id', $application->targeted_resume_id)
            );
    }

    public function test_opening_the_application(): void
    {
        $application = Application::factory()->withTargetedResume()->create(['company_name' => self::MARKER.' Link Co']);

        $applicationId = null;
        $this->listPage()->assertInertia(function (Assert $page) use (&$applicationId): Assert {
            $applicationId = $page->toArray()['props']['targetedResumes'][0]['application_id'];

            return $page;
        });

        $this->assertSame($application->id, $applicationId);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $applicationId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resume/applications/Show', false)
                ->where('application.id', $application->id)
                ->where('targetedResume.id', $application->targeted_resume_id)
            );
    }

    public function test_downloading_from_the_list_renders_the_pdf_on_demand(): void
    {
        $version = ResumeVersion::factory()->create();
        $version->personalInfo()->create(['name' => 'Jason Vertucio', 'title' => 'Engineer', 'email' => 'jason@example.com']);
        $application = Application::factory()->withTargetedResume()->create([
            'resume_version_id' => $version->id,
            'company_name' => self::MARKER.' Download Co',
        ]);
        $targetedResume = $application->targetedResume;
        $this->assertNull($targetedResume->pdf_path);
        $this->assertSame([$targetedResume->id], $this->listedIds());

        $renderedPath = $this->temporaryFile('pdf');

        $documentService = $this->createMock(TargetedResumeDocumentService::class);
        $documentService->expects($this->once())
            ->method('ensurePdf')
            ->with($this->callback(fn (TargetedResume $requested): bool => $requested->is($targetedResume)))
            ->willReturn(['success' => true, 'path' => $renderedPath, 'served_cached_document' => false]);
        $documentService->expects($this->never())->method('ensureDocx');
        $this->app->instance(TargetedResumeDocumentService::class, $documentService);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.download', [$targetedResume, 'pdf']))
            ->assertOk();

        $disposition = (string) $response->headers->get('content-disposition');

        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('Jason Vertucio Resume '.self::MARKER.' Download Co', $disposition);
        $this->assertStringContainsString("app-{$application->id}.pdf", $disposition);
        $this->assertDatabaseHas('document_downloads', [
            'targeted_resume_id' => $targetedResume->id,
            'type' => 'pdf',
            'served_cached_document' => false,
        ]);
    }

    public function test_download_is_not_found_when_the_document_cannot_be_rendered_or_the_format_is_unknown(): void
    {
        $application = Application::factory()->withTargetedResume()->create();

        $documentService = $this->createStub(TargetedResumeDocumentService::class);
        $documentService->method('ensurePdf')->willReturn(['success' => false, 'error' => 'Renderer unavailable.']);
        $this->app->instance(TargetedResumeDocumentService::class, $documentService);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.download', [$application->targeted_resume_id, 'pdf']))
            ->assertNotFound();
        $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.download', [$application->targeted_resume_id, 'odt']))
            ->assertNotFound();
        $this->assertDatabaseMissing('document_downloads', ['targeted_resume_id' => $application->targeted_resume_id]);
    }

    public function test_empty_list(): void
    {
        Application::factory()->applied()->create();
        Application::withTrashed()->update(['targeted_resume_id' => null]);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resume/targeted/Index', false)
                ->where('targetedResumes', [])
                ->where('filters', ['search' => ''])
            );

        $this->actingAs($this->admin)->get(route('admin.resume.applications.create'))->assertOk();
    }

    // ---------------------------------------------------------------------
    // Requirement: Targeted Resumes list search
    // ---------------------------------------------------------------------

    public function test_searching_by_company(): void
    {
        $match = Application::factory()->withTargetedResume()->create(['company_name' => 'Northwindzq Traders', 'position' => 'Engineer']);
        Application::factory()->withTargetedResume()->create(['company_name' => 'Contosozq Ltd', 'position' => 'Engineer']);

        $this->assertSame([$match->targeted_resume_id], $this->listedIds('Northwindzq'));
        $this->assertSame([$match->targeted_resume_id], $this->listedIds('  northwindzq trad  '));
    }

    public function test_searching_by_position(): void
    {
        $match = Application::factory()->withTargetedResume()->create(['company_name' => 'Acme', 'position' => 'Platformzq Architect']);
        Application::factory()->withTargetedResume()->create(['company_name' => 'Acme', 'position' => 'Designzq Lead']);

        $this->assertSame([$match->targeted_resume_id], $this->listedIds('Platformzq'));
    }

    public function test_searching_by_resume_title(): void
    {
        $match = Application::factory()->withTargetedResume()->create(['company_name' => 'Acme', 'position' => 'Engineer']);
        $other = Application::factory()->withTargetedResume()->create(['company_name' => 'Acme', 'position' => 'Engineer']);
        TargetedResume::query()->whereKey($match->targeted_resume_id)->update(['title' => 'Headlinezq Principal Engineer']);
        TargetedResume::query()->whereKey($other->targeted_resume_id)->update(['title' => 'Something Else']);

        $this->assertSame([$match->targeted_resume_id], $this->listedIds('Headlinezq'));
    }

    public function test_searching_never_reveals_main_resume_or_deleted_applications(): void
    {
        Application::factory()->applied()->create(['company_name' => 'Hiddenzq Main Co']);
        $deleted = Application::factory()->withTargetedResume()->create(['company_name' => 'Hiddenzq Deleted Co']);
        $deleted->delete();
        TargetedResume::factory()->create(['title' => 'Hiddenzq Orphan']);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', ['search' => 'Hiddenzq']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('targetedResumes', [])
                ->where('filters.search', 'Hiddenzq')
            );
    }

    public function test_searching_with_no_match_shows_an_empty_list(): void
    {
        Application::factory()->withTargetedResume()->create(['company_name' => self::MARKER.' Co']);

        $this->assertSame([], $this->listedIds('no-such-company-zqzqzq'));
    }

    // ---------------------------------------------------------------------
    // Requirement: Manual markdown editor on the Edit Targeted Resume page
    // ---------------------------------------------------------------------

    public function test_editor_available_after_a_chat_finalize(): void
    {
        $version = ResumeVersion::factory()->create();
        $application = Application::factory()->withConversation()->create([
            'resume_version_id' => $version->id,
            'company_name' => self::MARKER.' Finalized Co',
            'position' => 'Staff Engineer',
        ]);

        $targetedResumeId = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.finalize', $application), [
                'tailored_content' => "Title: Staff Platform Engineer\n\n# Summary\nTailored in chat",
            ])
            ->assertOk()
            ->json('targeted_resume_id');

        $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.edit', $targetedResumeId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resume/targeted/Edit', false)
                ->where('targetedResume', [
                    'id' => $targetedResumeId,
                    'title' => 'Staff Platform Engineer',
                    'tailored_content' => "# Summary\nTailored in chat",
                    'resume_version' => $version->version,
                    'docx_path' => false,
                    'pdf_path' => false,
                    'can_discard' => true,
                ])
                ->where('application', [
                    'id' => $application->id,
                    'company_name' => self::MARKER.' Finalized Co',
                    'position' => 'Staff Engineer',
                ])
            );

        $this->actingAs($this->admin)->get(route('admin.resume.applications.show', $application->id))->assertOk();
    }

    public function test_editor_falls_back_to_the_stored_content_when_there_is_no_markdown_key(): void
    {
        $application = Application::factory()->withTargetedResume()->create();
        TargetedResume::query()->findOrFail($application->targeted_resume_id)
            ->update(['tailored_data' => ['title' => 'Engineer', 'content' => "# Summary\nLegacy content", 'format' => 'markdown']]);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.edit', $application->targeted_resume_id))
            ->assertInertia(fn (Assert $page) => $page->where('targetedResume.tailored_content', "# Summary\nLegacy content"));
    }

    public function test_editor_unavailable_before_any_finalize(): void
    {
        $application = Application::factory()->withConversation()->create(['company_name' => self::MARKER.' Unfinalized Co']);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertInertia(fn (Assert $page) => $page->where('targetedResume', null));

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['search' => self::MARKER.' Unfinalized']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('applications', 1)
                ->where('applications.0.id', $application->id)
                ->where('applications.0.targeted_resume_id', null)
            );

        $this->assertSame([], $this->listedIds());
    }

    public function test_edit_button_in_the_resume_card(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->create();

        $cardResumeId = null;
        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertInertia(function (Assert $page) use (&$cardResumeId): Assert {
                $cardResumeId = $page->toArray()['props']['targetedResume']['id'];

                return $page;
            });

        $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.edit', $cardResumeId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('targetedResume.id', $application->targeted_resume_id)
                ->where('application.id', $application->id)
            );
    }

    public function test_applications_list_row_carries_the_resume_to_edit(): void
    {
        $application = Application::factory()->withTargetedResume()->create(['company_name' => self::MARKER.' Listed Co']);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['search' => self::MARKER]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('applications', 1)
                ->where('applications.0.targeted_resume_id', $application->targeted_resume_id)
            );
    }

    public function test_unknown_targeted_resume(): void
    {
        $orphan = TargetedResume::factory()->create();

        $this->actingAs($this->admin)->get('/admin/resume/targeted-resumes/999999999/edit')->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/resume/targeted-resumes/not-a-number/edit')->assertNotFound();
        $this->actingAs($this->admin)->get(route('admin.resume.targeted.edit', $orphan))->assertNotFound();
    }

    public function test_edit_page_of_a_deleted_applications_resume_is_not_found(): void
    {
        $application = Application::factory()->withTargetedResume()->create();
        $application->delete();

        $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.edit', $application->targeted_resume_id))
            ->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // Requirement: Discarding a targeted resume (HTTP)
    // ---------------------------------------------------------------------

    public function test_discarding_before_applying(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->create([
            'company_name' => self::MARKER.' Discard Co',
            'fit_score' => 70,
        ]);
        $targetedResumeId = $application->targeted_resume_id;
        $recordedVersionId = $application->resume_version_id;
        $conversationId = $application->ai_conversation_id;
        [$docxPath, $pdfPath] = $this->renderedDocuments($application->targetedResume);
        $messageIds = AiConversationMessage::query()->where('ai_conversation_id', $conversationId)->pluck('id');
        $this->assertSame([$targetedResumeId], $this->listedIds());

        $this->actingAs($this->admin)
            ->delete(route('admin.resume.targeted.destroy', $targetedResumeId))
            ->assertRedirect(route('admin.resume.applications.show', $application))
            ->assertSessionHas('success', 'Targeted resume discarded.');

        $this->assertDatabaseMissing('targeted_resumes', ['id' => $targetedResumeId]);
        $this->assertFileDoesNotExist($docxPath);
        $this->assertFileDoesNotExist($pdfPath);

        $application->refresh();

        $this->assertNull($application->targeted_resume_id);
        $this->assertSame($recordedVersionId, $application->resume_version_id);
        $this->assertSame(ApplicationStatus::Draft, $application->status);
        $this->assertSame(0, $application->statusUpdates()->count());
        $this->assertSame($conversationId, $application->ai_conversation_id);
        $this->assertSame(70, $application->fit_score);

        $this->assertSame([], $this->listedIds());

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['search' => self::MARKER]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('applications', 1)
                ->where('applications.0.targeted_resume_id', null)
                ->where('applications.0.status', 'draft')
            );

        $added = AiConversationMessage::query()
            ->where('ai_conversation_id', $conversationId)
            ->whereNotIn('id', $messageIds)
            ->get();

        $this->assertCount(1, $added);
        $this->assertSame('resume_discarded', data_get($added[0]->metadata, 'origin'));
        $this->assertSame($targetedResumeId, data_get($added[0]->metadata, 'targeted_resume_id'));
        $this->assertSame(0, AiLlmMessage::query()->where('ai_conversation_id', $conversationId)->count());

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('targetedResume', null)
                ->where('application.resume_version.id', $recordedVersionId)
                ->where('messages', fn ($messages): bool => collect($messages)->contains(fn (array $message): bool => data_get($message, 'metadata.origin') === 'resume_discarded'))
            );

        $this->actingAs($this->admin)->get(route('admin.resume.targeted.edit', $targetedResumeId))->assertNotFound();
    }

    public function test_discarding_a_resume_of_an_application_with_no_session_records_no_message(): void
    {
        $application = Application::factory()->withTargetedResume()->create();
        $targetedResumeId = $application->targeted_resume_id;
        $messages = AiConversationMessage::query()->count();

        $this->actingAs($this->admin)
            ->delete(route('admin.resume.targeted.destroy', $targetedResumeId))
            ->assertRedirect(route('admin.resume.applications.show', $application));

        $this->assertDatabaseMissing('targeted_resumes', ['id' => $targetedResumeId]);
        $this->assertSame($messages, AiConversationMessage::query()->count());
    }

    public function test_discarding_a_resume_with_no_application_returns_to_the_list(): void
    {
        $orphan = TargetedResume::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.resume.targeted.destroy', $orphan))
            ->assertRedirect(route('admin.resume.targeted.index'));

        $this->assertDatabaseMissing('targeted_resumes', ['id' => $orphan->id]);
    }

    public function test_discarding_an_unknown_resume_is_not_found(): void
    {
        $this->actingAs($this->admin)->delete('/admin/resume/targeted-resumes/999999999')->assertNotFound();
    }

    public function test_discard_refused_after_applying(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->applied(Carbon::parse('2026-03-01 09:00:00'))->create([
            'company_name' => self::MARKER.' Sent Co',
        ]);
        ApplicationStatusUpdate::factory()->create([
            'application_id' => $application->id,
            'status' => ApplicationStatus::Interviewing,
            'occurred_at' => Carbon::parse('2026-03-09 09:00:00'),
        ]);
        $application->update(['status' => ApplicationStatus::Interviewing]);
        $targetedResumeId = $application->targeted_resume_id;
        [$docxPath, $pdfPath] = $this->renderedDocuments($application->targetedResume);
        $messages = AiConversationMessage::query()->where('ai_conversation_id', $application->ai_conversation_id)->count();

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.resume.targeted.destroy', $targetedResumeId))
            ->assertStatus(409)
            ->assertJsonStructure(['message']);

        $stored = TargetedResume::query()->find($targetedResumeId);

        $this->assertNotNull($stored);
        $this->assertSame($docxPath, $stored->docx_path);
        $this->assertSame($pdfPath, $stored->pdf_path);
        $this->assertFileExists($docxPath);
        $this->assertFileExists($pdfPath);
        $this->assertSame($targetedResumeId, $application->fresh()->targeted_resume_id);
        $this->assertSame(ApplicationStatus::Interviewing, $application->fresh()->status);
        $this->assertSame($messages, AiConversationMessage::query()->where('ai_conversation_id', $application->ai_conversation_id)->count());
        $this->assertSame([$targetedResumeId], $this->listedIds());
    }

    public function test_discard_is_not_offered_once_applied(): void
    {
        $draft = Application::factory()->withTargetedResume()->create(['company_name' => self::MARKER.' Draft Co']);
        $passed = Application::factory()->passed()->withTargetedResume()->create(['company_name' => self::MARKER.' Passed Co']);
        $applied = Application::factory()->withTargetedResume()->applied()->create(['company_name' => self::MARKER.' Applied Co']);
        $rejected = Application::factory()->withTargetedResume()->applied()->create([
            'company_name' => self::MARKER.' Rejected Co',
            'status' => ApplicationStatus::Rejected,
        ]);

        $this->listPage()->assertInertia(fn (Assert $page) => $page
            ->where('targetedResumes', function ($rows) use ($draft, $passed, $applied, $rejected): bool {
                $canDiscard = collect($rows)->pluck('can_discard', 'id');

                return $canDiscard[$draft->targeted_resume_id] === true
                    && $canDiscard[$passed->targeted_resume_id] === true
                    && $canDiscard[$applied->targeted_resume_id] === false
                    && $canDiscard[$rejected->targeted_resume_id] === false;
            })
        );

        foreach ([[$draft, true], [$passed, true], [$applied, false], [$rejected, false]] as [$application, $expected]) {
            $this->actingAs($this->admin)
                ->get(route('admin.resume.targeted.edit', $application->targeted_resume_id))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('targetedResume.can_discard', $expected));

            $this->actingAs($this->admin)
                ->get(route('admin.resume.applications.show', $application))
                ->assertInertia(fn (Assert $page) => $page->where('application.has_applied', ! $expected));
        }
    }

    public function test_discard_is_offered_again_once_the_applied_entry_is_removed(): void
    {
        $application = Application::factory()->withTargetedResume()->applied()->create();
        $entry = $application->statusUpdates()->sole();

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.resume.applications.status-updates.destroy', [$application, $entry]))
            ->assertOk()
            ->assertJsonPath('status', 'draft');

        $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.edit', $application->targeted_resume_id))
            ->assertInertia(fn (Assert $page) => $page->where('targetedResume.can_discard', true));

        $this->actingAs($this->admin)
            ->delete(route('admin.resume.targeted.destroy', $application->targeted_resume_id))
            ->assertRedirect(route('admin.resume.applications.show', $application));

        $this->assertNull($application->fresh()->targeted_resume_id);
    }

    public function test_building_a_new_one_afterwards(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->create(['company_name' => self::MARKER.' Rebuild Co']);
        $discardedId = $application->targeted_resume_id;

        $this->actingAs($this->admin)
            ->delete(route('admin.resume.targeted.destroy', $discardedId))
            ->assertRedirect(route('admin.resume.applications.show', $application));

        $newId = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.finalize', $application), [
                'tailored_content' => "Title: Rebuilt Engineer\n\n# Summary\nRebuilt",
            ])
            ->assertOk()
            ->json('targeted_resume_id');

        $this->assertNotSame($discardedId, $newId);
        $this->assertSame($newId, $application->fresh()->targeted_resume_id);
        $this->assertSame([$newId], $this->listedIds());
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function listPage(?string $search = null): TestResponse
    {
        return $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', ['search' => $search ?? self::MARKER]))
            ->assertOk();
    }

    /**
     * The ids of the targeted resumes the list shows for a search, in the
     * order it shows them.
     *
     * @return array<int, int>
     */
    private function listedIds(?string $search = null): array
    {
        $ids = [];

        $this->listPage($search)->assertInertia(function (Assert $page) use (&$ids): Assert {
            $ids = array_column($page->toArray()['props']['targetedResumes'], 'id');

            return $page;
        });

        return $ids;
    }

    private function editedAt(Application $application, string $when): void
    {
        TargetedResume::query()->whereKey($application->targeted_resume_id)->update(['updated_at' => $when]);
    }

    private function temporaryFile(string $extension): string
    {
        $path = sys_get_temp_dir().'/targeted-resume-list-test-'.bin2hex(random_bytes(8)).'.'.$extension;
        file_put_contents($path, 'rendered');
        $this->temporaryFiles[] = $path;

        return $path;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function renderedDocuments(TargetedResume $targetedResume): array
    {
        $paths = [$this->temporaryFile('docx'), $this->temporaryFile('pdf')];

        $targetedResume->forceFill(['docx_path' => $paths[0], 'pdf_path' => $paths[1]])->save();

        return $paths;
    }
}
