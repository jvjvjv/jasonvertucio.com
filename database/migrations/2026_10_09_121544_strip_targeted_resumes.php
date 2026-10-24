<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The destructive step: a targeted resume is reduced to the document itself.
 * Everything dropped here was copied onto `applications` by
 * `backfill_applications`, which throws on a count mismatch and so stops the
 * run before this migration is reached.
 *
 * `title` stays — it is the headline printed in the document's letterhead.
 */
return new class extends Migration
{
    private const PIPELINE_STATUSES = [
        'applied', 'interviewing', 'interviewed', 'offered', 'accepted', 'hired', 'rejected',
    ];

    /**
     * Job data the builder used to mirror into `ai_conversations.context`.
     * The `*_manual` flags, `step` and `auto_start_pending` are session
     * behaviour and stay.
     */
    private const CONTEXT_JOB_KEYS = [
        'job_title', 'job_description', 'job_url_id', 'resume_version_id', 'company_name', 'fit_score', 'fit_summary',
    ];

    private const CHUNK_SIZE = 200;

    public function up(): void
    {
        $this->assertSafeToStrip();

        DB::table('targeted_resumes')->whereNull('tailored_data')->orWhereRaw("JSON_TYPE(tailored_data) = 'NULL'")->delete();

        $paths = implode(', ', array_map(fn (string $key): string => "'$.{$key}'", self::CONTEXT_JOB_KEYS));
        DB::table('ai_conversations')
            ->where('feature', 'targeted-resume')
            ->whereNotNull('context')
            ->update(['context' => DB::raw("JSON_REMOVE(context, {$paths})")]);

        Schema::table('cover_letters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('targeted_resume_id');
        });

        Schema::dropIfExists('targeted_resume_status_updates');

        Schema::table('targeted_resumes', function (Blueprint $table) {
            $table->dropForeign(['ai_conversation_id']);
            $table->dropForeign(['job_url_id']);
        });

        Schema::table('targeted_resumes', function (Blueprint $table) {
            $table->dropColumn([
                'ai_conversation_id',
                'job_url_id',
                'company_name',
                'position',
                'job_description',
                'fit_score',
                'fit_summary',
                'status',
                'base_resume',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('targeted_resumes', function (Blueprint $table) {
            $table->foreignId('ai_conversation_id')->nullable()->after('resume_version_id')->constrained('ai_conversations')->nullOnDelete();
            $table->foreignUuid('job_url_id')->nullable()->after('ai_conversation_id')->constrained('job_urls')->nullOnDelete();
            $table->string('company_name')->nullable()->after('job_url_id');
            $table->string('position')->nullable()->after('company_name');
            $table->longText('job_description')->nullable()->after('title');
            $table->unsignedTinyInteger('fit_score')->nullable()->after('tailored_data');
            $table->text('fit_summary')->nullable()->after('fit_score');
            $table->boolean('base_resume')->default(false)->after('pdf_path');
            $table->string('status', 50)->default('draft')->after('base_resume');
        });

        Schema::create('targeted_resume_status_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('targeted_resume_id')->constrained('targeted_resumes')->cascadeOnDelete();
            $table->string('status', 50);
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::table('cover_letters', function (Blueprint $table) {
            $table->foreignId('targeted_resume_id')->nullable()->after('resume_version_id')->constrained('targeted_resumes')->nullOnDelete();
        });

        $resumeByApplication = $this->restoreResumes();
        $this->restoreStatusUpdates($resumeByApplication);
        $this->restoreCoverLetterLinks($resumeByApplication);
        $this->restoreConversationContext();

        DB::table('targeted_resumes')->whereNull('company_name')->update(['company_name' => 'Unknown Company']);
        DB::table('targeted_resumes')->whereNull('position')->update(['position' => 'Unknown Position']);
        DB::table('targeted_resumes')->whereNull('job_description')->update(['job_description' => '']);

        Schema::table('targeted_resumes', function (Blueprint $table) {
            $table->string('company_name')->nullable(false)->change();
            $table->string('position')->nullable(false)->change();
            $table->longText('job_description')->nullable(false)->change();
        });
    }

    /**
     * A placeholder about to be deleted must not be the subject of a logged
     * download, and every real document must already hang off an application.
     */
    private function assertSafeToStrip(): void
    {
        $downloaded = DB::table('document_downloads')
            ->join('targeted_resumes', 'targeted_resumes.id', '=', 'document_downloads.targeted_resume_id')
            ->where(function ($query): void {
                $query->whereNull('targeted_resumes.tailored_data')->orWhereRaw("JSON_TYPE(targeted_resumes.tailored_data) = 'NULL'");
            })
            ->count();

        if ($downloaded !== 0) {
            throw new RuntimeException("Refusing to strip targeted_resumes: {$downloaded} document download(s) reference a content-less targeted resume that would be deleted.");
        }

        $unlinked = DB::table('targeted_resumes')
            ->whereNotNull('tailored_data')
            ->whereRaw("JSON_TYPE(tailored_data) <> 'NULL'")
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('applications')->whereColumn('applications.targeted_resume_id', 'targeted_resumes.id');
            })
            ->count();

        if ($unlinked !== 0) {
            throw new RuntimeException("Refusing to strip targeted_resumes: {$unlinked} targeted resume(s) have no application. Run the applications backfill first.");
        }
    }

    /**
     * Copies each application's job back onto its document, and re-creates a
     * `base_resume` placeholder for every main-resume application that has
     * entered the pipeline — the legacy model had nowhere else to hang its
     * status history.
     *
     * @return array<int, int> application id => targeted resume id
     */
    private function restoreResumes(): array
    {
        $resumeByApplication = [];

        DB::table('applications')->orderBy('id')->chunkById(self::CHUNK_SIZE, function (Collection $applications) use (&$resumeByApplication): void {
            $withHistory = DB::table('application_status_updates')
                ->whereIn('application_id', $applications->pluck('id')->all())
                ->distinct()
                ->pluck('application_id')
                ->flip();

            foreach ($applications as $application) {
                $inPipeline = in_array($application->status, self::PIPELINE_STATUSES, true);
                $job = [
                    'ai_conversation_id' => $application->ai_conversation_id,
                    'job_url_id' => $application->job_url_id,
                    'company_name' => $application->company_name,
                    'position' => $application->position,
                    'job_description' => $application->job_description,
                    'fit_score' => $application->fit_score,
                    'fit_summary' => $application->fit_summary,
                ];

                if ($application->targeted_resume_id !== null) {
                    DB::table('targeted_resumes')->where('id', $application->targeted_resume_id)->update($job + [
                        'base_resume' => false,
                        'status' => $inPipeline ? $application->status : 'finalized',
                    ]);
                    $resumeByApplication[$application->id] = $application->targeted_resume_id;

                    continue;
                }

                if (! $inPipeline && ! $withHistory->has($application->id)) {
                    continue;
                }

                $resumeByApplication[$application->id] = DB::table('targeted_resumes')->insertGetId($job + [
                    'resume_version_id' => $application->resume_version_id,
                    'title' => null,
                    'tailored_data' => null,
                    'base_resume' => true,
                    'status' => $inPipeline ? $application->status : 'draft',
                    'created_at' => $application->created_at,
                    'updated_at' => $application->updated_at,
                ]);
            }
        });

        return $resumeByApplication;
    }

    /**
     * @param  array<int, int>  $resumeByApplication
     */
    private function restoreStatusUpdates(array $resumeByApplication): void
    {
        DB::table('application_status_updates')->orderBy('id')->chunkById(self::CHUNK_SIZE, function (Collection $updates) use ($resumeByApplication): void {
            $rows = [];

            foreach ($updates as $update) {
                if (! isset($resumeByApplication[$update->application_id])) {
                    continue;
                }

                $rows[] = [
                    'targeted_resume_id' => $resumeByApplication[$update->application_id],
                    'status' => $update->status,
                    'notes' => $update->notes,
                    'occurred_at' => $update->occurred_at,
                    'created_at' => $update->created_at,
                    'updated_at' => $update->updated_at,
                ];
            }

            if ($rows !== []) {
                DB::table('targeted_resume_status_updates')->insert($rows);
            }
        });
    }

    /**
     * @param  array<int, int>  $resumeByApplication
     */
    private function restoreCoverLetterLinks(array $resumeByApplication): void
    {
        DB::table('cover_letters')->whereNotNull('application_id')->orderBy('id')->chunkById(self::CHUNK_SIZE, function (Collection $letters) use ($resumeByApplication): void {
            foreach ($letters as $letter) {
                if (isset($resumeByApplication[$letter->application_id])) {
                    DB::table('cover_letters')->where('id', $letter->id)->update(['targeted_resume_id' => $resumeByApplication[$letter->application_id]]);
                }
            }
        });
    }

    /**
     * The legacy builder read a session's job from `context` until its first
     * finalize, so the application's current values go back there.
     */
    private function restoreConversationContext(): void
    {
        DB::table('applications')->whereNotNull('ai_conversation_id')->orderBy('id')->chunkById(self::CHUNK_SIZE, function (Collection $applications): void {
            $contexts = DB::table('ai_conversations')
                ->whereIn('id', $applications->pluck('ai_conversation_id')->all())
                ->pluck('context', 'id');

            foreach ($applications as $application) {
                if (! $contexts->has($application->ai_conversation_id)) {
                    continue;
                }

                $context = json_decode((string) $contexts->get($application->ai_conversation_id), true);
                $context = is_array($context) ? $context : [];

                $restored = array_filter([
                    'job_title' => $application->position,
                    'job_description' => $application->job_description,
                    'job_url_id' => $application->job_url_id,
                    'resume_version_id' => $application->resume_version_id,
                    'company_name' => $application->company_name,
                    'fit_score' => $application->fit_score,
                    'fit_summary' => $application->fit_summary,
                ], fn (mixed $value): bool => $value !== null);

                DB::table('ai_conversations')
                    ->where('id', $application->ai_conversation_id)
                    ->update(['context' => json_encode($restored + $context)]);
            }
        });
    }
};
