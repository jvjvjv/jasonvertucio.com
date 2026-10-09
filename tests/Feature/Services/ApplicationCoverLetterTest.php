<?php

namespace Tests\Feature\Services;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\CoverLetter;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Services\CoverLetterDocumentService;
use App\Services\TargetedResumeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Jvjvjv\CodeTalker\Services\LaravelAi\AgentFactory;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Spec requirement "Cover letters attach to the Application", exercised
 * through `TargetedResumeService::saveCoverLetter()`. Document rendering is
 * stubbed: it is covered by its own suites and needs WeasyPrint.
 */
class ApplicationCoverLetterTest extends TestCase
{
    use DatabaseTransactions;

    private const string FIRST_LETTER = "Dear Acme hiring team,\n\nI build reliable Laravel applications.\n\nKind regards,";

    private const string SECOND_LETTER = "Dear Acme hiring team,\n\nI lead teams that build reliable Laravel applications.\n\nBest regards,";

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AgentFactory::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('forSystem');
        });

        $documents = $this->createStub(CoverLetterDocumentService::class);
        $documents->method('generateDocx')->willReturn(['success' => true]);
        $documents->method('generatePdf')->willReturn(['success' => true]);
        $this->app->instance(CoverLetterDocumentService::class, $documents);
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_cover_letter_for_a_main_resume_application(): void
    {
        $this->travelTo(Carbon::parse('2026-05-04 14:30:00'));
        $version = ResumeVersion::factory()->create();
        $version->personalInfo()->create([
            'name' => 'Jason Vertucio',
            'title' => 'Lead Front-End Engineer',
            'email' => 'jason@example.com',
            'phone' => '(555) 123-4567',
            'linkedin' => 'linkedin.com/in/jasonvertucio',
            'url' => 'https://jasonvertucio.com',
            'summary' => 'Builds things.',
        ]);
        $application = Application::factory()->withConversation()->create([
            'resume_version_id' => $version->id,
            'company_name' => 'Acme Corp',
            'position' => 'Staff Engineer',
        ]);
        $documents = TargetedResume::query()->count();
        $this->assertNull($application->targeted_resume_id);

        $letter = $this->service()->saveCoverLetter($application, self::FIRST_LETTER);

        $stored = CoverLetter::query()->findOrFail($letter->id);

        $this->assertSame($application->id, $stored->application_id);
        $this->assertTrue($stored->application->is($application));
        $this->assertSame([$stored->id], $application->coverLetters()->pluck('id')->all());
        $this->assertSame($version->id, $stored->resume_version_id);
        $this->assertSame('Acme Corp', $stored->company_name);
        $this->assertSame('Staff Engineer', $stored->position);
        $this->assertSame('Dear Acme hiring team,', $stored->greeting);
        $this->assertSame('I build reliable Laravel applications.', $stored->message_body);
        $this->assertSame('Kind regards,', $stored->closing);
        $this->assertSame('Jason Vertucio', $stored->signature);
        $this->assertSame('2026-05-04', $stored->date->toDateString());

        $this->assertNull($application->fresh()->targeted_resume_id);
        $this->assertSame($documents, TargetedResume::query()->count());
        $this->assertSame(ApplicationStatus::Draft, $application->fresh()->status);
    }

    public function test_cover_letter_for_an_applied_application_with_no_session_and_no_targeted_resume(): void
    {
        $application = Application::factory()->applied()->create();

        $letter = $this->service()->saveCoverLetter($application, self::FIRST_LETTER);

        $this->assertSame($application->id, $letter->application_id);
        $this->assertSame($application->resume_version_id, $letter->resume_version_id);
        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
        $this->assertSame(1, $application->statusUpdates()->count());
    }

    public function test_cover_letter_for_an_application_with_a_targeted_resume_attaches_to_the_application(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->create();

        $letter = $this->service()->saveCoverLetter($application, self::FIRST_LETTER);

        $this->assertSame($application->id, $letter->application_id);
        $this->assertArrayNotHasKey('targeted_resume_id', CoverLetter::query()->findOrFail($letter->id)->getAttributes());
    }

    public function test_re_finalizing_a_cover_letter(): void
    {
        $application = Application::factory()->withConversation()->create(['company_name' => 'Acme Corp']);
        $letters = CoverLetter::query()->count();

        $first = $this->service()->saveCoverLetter($application, self::FIRST_LETTER);
        $second = $this->service()->saveCoverLetter($application->fresh(), self::SECOND_LETTER);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($letters + 1, CoverLetter::query()->count());
        $this->assertSame(1, $application->coverLetters()->count());

        $stored = CoverLetter::query()->findOrFail($first->id);

        $this->assertSame($application->id, $stored->application_id);
        $this->assertSame('I lead teams that build reliable Laravel applications.', $stored->message_body);
        $this->assertSame('Best regards,', $stored->closing);
    }

    public function test_re_finalizing_follows_the_application_current_job_details_and_resume_version(): void
    {
        $application = Application::factory()->withConversation()->create(['company_name' => 'Acme Corp', 'position' => 'Engineer']);
        $first = $this->service()->saveCoverLetter($application, self::FIRST_LETTER);

        $newVersion = ResumeVersion::factory()->create();
        $application->update(['company_name' => 'Acme Corporation', 'position' => 'Staff Engineer', 'resume_version_id' => $newVersion->id]);

        $second = $this->service()->saveCoverLetter($application->fresh(), self::SECOND_LETTER);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('Acme Corporation', $second->company_name);
        $this->assertSame('Staff Engineer', $second->position);
        $this->assertSame($newVersion->id, $second->resume_version_id);
    }

    public function test_each_application_keeps_its_own_cover_letter(): void
    {
        $one = Application::factory()->withConversation()->create();
        $other = Application::factory()->withConversation()->create();

        $first = $this->service()->saveCoverLetter($one, self::FIRST_LETTER);
        $second = $this->service()->saveCoverLetter($other, self::SECOND_LETTER);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($one->id, $first->fresh()->application_id);
        $this->assertSame($other->id, $second->fresh()->application_id);
        $this->assertSame('I build reliable Laravel applications.', $first->fresh()->message_body);
    }

    public function test_standalone_cover_letter(): void
    {
        $standalone = $this->standaloneLetter('Acme Corp');

        $stored = CoverLetter::query()->findOrFail($standalone->id);

        $this->assertNull($stored->application_id);
        $this->assertNull($stored->application);
    }

    public function test_standalone_cover_letter_is_not_adopted_or_replaced_when_an_application_finalizes_its_own(): void
    {
        $standalone = $this->standaloneLetter('Acme Corp');
        $application = Application::factory()->withConversation()->create(['company_name' => 'Acme Corp']);

        $letter = $this->service()->saveCoverLetter($application, self::FIRST_LETTER);
        $this->service()->saveCoverLetter($application->fresh(), self::SECOND_LETTER);

        $stored = CoverLetter::query()->findOrFail($standalone->id);

        $this->assertNotSame($standalone->id, $letter->id);
        $this->assertNull($stored->application_id);
        $this->assertSame('Standalone body.', $stored->message_body);
        $this->assertSame([$letter->id], $application->coverLetters()->pluck('id')->all());
    }

    public function test_cover_letter_survives_its_application_being_soft_deleted(): void
    {
        $application = Application::factory()->withConversation()->create();
        $letter = $this->service()->saveCoverLetter($application, self::FIRST_LETTER);

        $application->delete();

        $this->assertSame($application->id, CoverLetter::query()->findOrFail($letter->id)->application_id);
    }

    private function service(): TargetedResumeService
    {
        return app(TargetedResumeService::class);
    }

    private function standaloneLetter(string $companyName): CoverLetter
    {
        return CoverLetter::create([
            'resume_version_id' => ResumeVersion::factory()->create()->id,
            'company_name' => $companyName,
            'position' => 'Engineer',
            'date' => '2026-03-01',
            'greeting' => 'Hello,',
            'message_body' => 'Standalone body.',
        ]);
    }
}
