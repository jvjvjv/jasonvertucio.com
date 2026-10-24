<?php

namespace Tests\Feature\Migrations;

use App\Models\ResumeVersion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Tests\TestCase;

/**
 * Exercises the `backfill_applications` migration's logic.
 *
 * The legacy columns it reads were dropped by `strip_targeted_resumes`, so the
 * legacy rows are staged in TEMPORARY tables of the pre-migration shape and
 * the backfill is pointed at them. Creating and dropping a temporary table
 * does not implicitly commit in MySQL, so this stays inside the test
 * transaction — a plain `DROP TABLE` would not, hence the raw statements.
 */
class BackfillApplicationsTest extends TestCase
{
    use DatabaseTransactions;

    private const RESUMES = 'legacy_targeted_resumes';

    private const STATUS_UPDATES = 'legacy_status_updates';

    private const COVER_LETTER_LINKS = 'legacy_cover_letter_links';

    private ResumeVersion $resumeVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dropLegacyTables();

        Schema::create(self::RESUMES, function (Blueprint $table) {
            $table->temporary();
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('resume_version_id');
            $table->unsignedBigInteger('ai_conversation_id')->nullable();
            $table->char('job_url_id', 36)->nullable();
            $table->string('company_name');
            $table->string('position');
            $table->string('title')->nullable();
            $table->longText('job_description');
            $table->json('tailored_data')->nullable();
            $table->unsignedTinyInteger('fit_score')->nullable();
            $table->text('fit_summary')->nullable();
            $table->boolean('base_resume')->default(false);
            $table->string('status', 50)->default('draft');
            $table->timestamps();
        });

        Schema::create(self::STATUS_UPDATES, function (Blueprint $table) {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('targeted_resume_id');
            $table->string('status', 50);
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::create(self::COVER_LETTER_LINKS, function (Blueprint $table) {
            $table->temporary();
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('targeted_resume_id')->nullable();
        });

        $this->resumeVersion = ResumeVersion::factory()->create(['is_current' => true]);
    }

    protected function tearDown(): void
    {
        $this->dropLegacyTables();

        parent::tearDown();
    }

    public function test_finalized_resume_becomes_a_draft_application_that_references_it(): void
    {
        $conversation = AiConversation::factory()->completed()->create();
        $resumeId = $this->legacyResume($conversation, [
            'status' => 'finalized',
            'company_name' => 'Acme',
            'position' => 'Staff Engineer',
            'job_description' => 'Build things.',
            'fit_score' => 82,
            'fit_summary' => 'Strong match.',
            'title' => 'Staff Software Engineer',
        ]);

        $this->backfill();

        $application = DB::table('applications')->where('ai_conversation_id', $conversation->id)->first();

        $this->assertNotNull($application);
        $this->assertSame('draft', $application->status);
        $this->assertSame($resumeId, $application->targeted_resume_id);
        $this->assertSame($this->resumeVersion->id, $application->resume_version_id);
        $this->assertSame('Acme', $application->company_name);
        $this->assertSame('Staff Engineer', $application->position);
        $this->assertSame('Build things.', $application->job_description);
        $this->assertSame(82, $application->fit_score);
        $this->assertSame('Strong match.', $application->fit_summary);
        $this->assertNull($application->deleted_at);

        $document = DB::table('targeted_resumes')->where('id', $resumeId)->first();
        $this->assertSame('Staff Software Engineer', $document->title);
        $this->assertSame(['markdown' => '# Summary'], json_decode($document->tailored_data, true));
    }

    public function test_applied_resume_keeps_its_status_and_history(): void
    {
        $conversation = AiConversation::factory()->completed()->create();
        $resumeId = $this->legacyResume($conversation, ['status' => 'interviewing']);

        DB::table(self::STATUS_UPDATES)->insert([
            ['targeted_resume_id' => $resumeId, 'status' => 'applied', 'notes' => 'Sent via referral', 'occurred_at' => '2026-03-01 09:00:00', 'created_at' => now(), 'updated_at' => now()],
            ['targeted_resume_id' => $resumeId, 'status' => 'interviewing', 'notes' => null, 'occurred_at' => '2026-03-10 14:30:00', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $written = $this->backfill();

        $application = DB::table('applications')->where('ai_conversation_id', $conversation->id)->first();
        $history = DB::table('application_status_updates')->where('application_id', $application->id)->orderBy('occurred_at')->get();

        $this->assertSame('interviewing', $application->status);
        $this->assertSame(2, $written['status_updates']);
        $this->assertSame(['applied', 'interviewing'], $history->pluck('status')->all());
        $this->assertSame(['2026-03-01 09:00:00', '2026-03-10 14:30:00'], $history->pluck('occurred_at')->all());
        $this->assertSame(['Sent via referral', null], $history->pluck('notes')->all());
    }

    public function test_base_resume_placeholder_becomes_a_main_resume_application(): void
    {
        $conversation = AiConversation::factory()->active()->create();
        $placeholderId = $this->legacyResume($conversation, [
            'status' => 'applied',
            'base_resume' => true,
            'tailored_data' => null,
        ]);

        DB::table(self::STATUS_UPDATES)->insert([
            'targeted_resume_id' => $placeholderId, 'status' => 'applied', 'notes' => null, 'occurred_at' => '2026-04-02 08:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->backfill();

        $application = DB::table('applications')->where('ai_conversation_id', $conversation->id)->first();

        $this->assertNull($application->targeted_resume_id);
        $this->assertSame('applied', $application->status);
        $this->assertSame($this->resumeVersion->id, $application->resume_version_id);
        $this->assertSame(1, DB::table('application_status_updates')->where('application_id', $application->id)->count());
    }

    public function test_flagged_resume_with_content_still_keeps_its_document(): void
    {
        $conversation = AiConversation::factory()->completed()->create();
        $resumeId = $this->legacyResume($conversation, ['status' => 'applied', 'base_resume' => true]);

        $this->backfill();

        $this->assertSame(
            $resumeId,
            DB::table('applications')->where('ai_conversation_id', $conversation->id)->value('targeted_resume_id'),
        );
    }

    public function test_session_without_a_resume_becomes_an_application_from_its_context(): void
    {
        $olderVersion = ResumeVersion::factory()->create();
        $conversation = AiConversation::factory()->active()->create([
            'context' => [
                'step' => 'analysis',
                'job_title' => 'Platform Engineer',
                'company_name' => 'Globex',
                'job_description' => 'Run the platform.',
                'fit_score' => 71,
                'fit_summary' => 'Decent match.',
                'resume_version_id' => $olderVersion->id,
            ],
        ]);

        $this->backfill();

        $application = DB::table('applications')->where('ai_conversation_id', $conversation->id)->first();

        $this->assertNotNull($application);
        $this->assertSame('draft', $application->status);
        $this->assertNull($application->targeted_resume_id);
        $this->assertSame('Globex', $application->company_name);
        $this->assertSame('Platform Engineer', $application->position);
        $this->assertSame('Run the platform.', $application->job_description);
        $this->assertSame(71, $application->fit_score);
        $this->assertSame('Decent match.', $application->fit_summary);
        $this->assertSame($olderVersion->id, $application->resume_version_id);
        $this->assertSame($conversation->created_at->toDateTimeString(), $application->created_at);
    }

    public function test_passed_session_without_a_resume_becomes_a_passed_application(): void
    {
        $conversation = AiConversation::factory()->pass()->create(['context' => ['job_title' => 'Tester']]);

        $this->backfill();

        $this->assertSame('passed', DB::table('applications')->where('ai_conversation_id', $conversation->id)->value('status'));
    }

    public function test_session_with_sparse_context_falls_back_to_placeholders_and_the_current_version(): void
    {
        $conversation = AiConversation::factory()->active()->create(['context' => ['resume_version_id' => 999999999]]);

        $this->backfill();

        $application = DB::table('applications')->where('ai_conversation_id', $conversation->id)->first();

        $this->assertSame('Unknown Company', $application->company_name);
        $this->assertSame('Unknown Position', $application->position);
        $this->assertSame('', $application->job_description);
        $this->assertSame($this->resumeVersion->id, $application->resume_version_id);
    }

    public function test_passed_session_with_an_unapplied_resume_becomes_passed(): void
    {
        $finalized = AiConversation::factory()->pass()->create();
        $draft = AiConversation::factory()->pass()->create();
        $applied = AiConversation::factory()->pass()->create();
        $this->legacyResume($finalized, ['status' => 'finalized']);
        $this->legacyResume($draft, ['status' => 'draft']);
        $this->legacyResume($applied, ['status' => 'applied']);

        $this->backfill();

        $this->assertSame('passed', DB::table('applications')->where('ai_conversation_id', $finalized->id)->value('status'));
        $this->assertSame('passed', DB::table('applications')->where('ai_conversation_id', $draft->id)->value('status'));
        $this->assertSame('applied', DB::table('applications')->where('ai_conversation_id', $applied->id)->value('status'));
    }

    public function test_deleted_session_yields_an_already_deleted_application(): void
    {
        $withResume = AiConversation::factory()->completed()->create();
        $withoutResume = AiConversation::factory()->active()->create();
        $this->legacyResume($withResume, ['status' => 'finalized']);
        $withResume->delete();
        $withoutResume->delete();

        $this->backfill();

        $this->assertNotNull(DB::table('applications')->where('ai_conversation_id', $withResume->id)->value('deleted_at'));
        $this->assertNotNull(DB::table('applications')->where('ai_conversation_id', $withoutResume->id)->value('deleted_at'));
    }

    public function test_cover_letter_follows_its_targeted_resume_to_the_application(): void
    {
        $conversation = AiConversation::factory()->completed()->create();
        $resumeId = $this->legacyResume($conversation, ['status' => 'finalized']);
        $linkedLetterId = $this->coverLetter();
        $standaloneLetterId = $this->coverLetter();

        DB::table(self::COVER_LETTER_LINKS)->insert([
            ['id' => $linkedLetterId, 'targeted_resume_id' => $resumeId],
            ['id' => $standaloneLetterId, 'targeted_resume_id' => null],
        ]);

        $written = $this->backfill();

        $applicationId = DB::table('applications')->where('ai_conversation_id', $conversation->id)->value('id');

        $this->assertSame(1, $written['cover_letters']);
        $this->assertSame($applicationId, DB::table('cover_letters')->where('id', $linkedLetterId)->value('application_id'));
        $this->assertNull(DB::table('cover_letters')->where('id', $standaloneLetterId)->value('application_id'));
    }

    public function test_every_resume_and_session_yields_exactly_one_application(): void
    {
        $withResume = AiConversation::factory()->completed()->create();
        $withoutResume = AiConversation::factory()->active()->create();
        $this->legacyResume($withResume, ['status' => 'finalized']);

        $this->backfill();

        $this->assertSame(1, DB::table('applications')->where('ai_conversation_id', $withResume->id)->count());
        $this->assertSame(1, DB::table('applications')->where('ai_conversation_id', $withoutResume->id)->count());
    }

    public function test_second_run_writes_nothing(): void
    {
        $conversation = AiConversation::factory()->completed()->create();
        $placeholderConversation = AiConversation::factory()->active()->create();
        AiConversation::factory()->active()->create();
        $resumeId = $this->legacyResume($conversation, ['status' => 'applied']);
        $this->legacyResume($placeholderConversation, ['status' => 'applied', 'tailored_data' => null]);
        $letterId = $this->coverLetter();

        DB::table(self::STATUS_UPDATES)->insert([
            'targeted_resume_id' => $resumeId, 'status' => 'applied', 'notes' => null, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table(self::COVER_LETTER_LINKS)->insert(['id' => $letterId, 'targeted_resume_id' => $resumeId]);

        $this->backfill();
        $applications = DB::table('applications')->count();
        $updates = DB::table('application_status_updates')->count();

        $second = $this->backfill();

        $this->assertSame(['applications' => 0, 'status_updates' => 0, 'cover_letters' => 0], $second);
        $this->assertSame($applications, DB::table('applications')->count());
        $this->assertSame($updates, DB::table('application_status_updates')->count());
    }

    public function test_a_mismatch_throws_and_writes_nothing(): void
    {
        $conversation = AiConversation::factory()->completed()->create();
        $this->legacyResume($conversation, ['status' => 'applied']);

        DB::table(self::STATUS_UPDATES)->insert([
            'targeted_resume_id' => 987654321, 'status' => 'applied', 'notes' => null, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $this->backfill();
            $this->fail('The backfill accepted a status update it could not relink.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('status updates', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('applications')->where('ai_conversation_id', $conversation->id)->count());
    }

    /**
     * @return array{applications: int, status_updates: int, cover_letters: int}
     */
    private function backfill(): array
    {
        $migration = require database_path('migrations/2026_10_09_121542_backfill_applications.php');

        return $migration->backfill(self::RESUMES, self::STATUS_UPDATES, self::COVER_LETTER_LINKS);
    }

    /**
     * Stages a legacy-shaped targeted resume. A row with content also gets
     * its real (already stripped) `targeted_resumes` row, since the
     * application's foreign key points there; a content-less placeholder has
     * no surviving row, exactly as after `strip_targeted_resumes`.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function legacyResume(AiConversation $conversation, array $attributes = []): int
    {
        $attributes += [
            'tailored_data' => json_encode(['markdown' => '# Summary']),
            'title' => 'Software Engineer',
        ];

        $id = $attributes['tailored_data'] !== null
            ? DB::table('targeted_resumes')->insertGetId([
                'resume_version_id' => $this->resumeVersion->id,
                'title' => $attributes['title'],
                'tailored_data' => $attributes['tailored_data'],
                'created_at' => now(),
                'updated_at' => now(),
            ])
            : (int) DB::table('targeted_resumes')->max('id') + 1_000_000 + DB::table(self::RESUMES)->count();

        DB::table(self::RESUMES)->insert($attributes + [
            'id' => $id,
            'resume_version_id' => $this->resumeVersion->id,
            'ai_conversation_id' => $conversation->id,
            'company_name' => 'Initech',
            'position' => 'Engineer',
            'job_description' => 'Do the work.',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function coverLetter(): int
    {
        return DB::table('cover_letters')->insertGetId([
            'resume_version_id' => $this->resumeVersion->id,
            'company_name' => 'Initech',
            'position' => 'Engineer',
            'date' => now()->toDateString(),
            'greeting' => 'Hello,',
            'message_body' => 'Body.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function dropLegacyTables(): void
    {
        foreach ([self::RESUMES, self::STATUS_UPDATES, self::COVER_LETTER_LINKS] as $table) {
            DB::statement("DROP TEMPORARY TABLE IF EXISTS {$table}");
        }
    }
}
