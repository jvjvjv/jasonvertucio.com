<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Data only. Every targeted resume, and every targeted-resume AI session that
 * never produced one, becomes an Application; status history and cover letters
 * are relinked to it.
 *
 * Nothing is removed here: the legacy columns survive until
 * `strip_targeted_resumes`. The whole backfill runs in one transaction and
 * ends by asserting its own counts, so a bad backfill rolls back and throws
 * before the destructive migration that follows can run.
 *
 * Uses the query builder rather than models, so it stays valid after the
 * models stop describing the legacy columns. The source table names are
 * parameters only so the logic can be exercised against temporary tables once
 * the legacy columns are gone.
 */
return new class extends Migration
{
    private const PIPELINE_STATUSES = [
        'applied', 'interviewing', 'interviewed', 'offered', 'accepted', 'hired', 'rejected',
    ];

    private const CHUNK_SIZE = 200;

    /**
     * Sessions that could not become applications because the database holds
     * no resume version at all — an application requires one. Only a database
     * with no resume (a test database carrying stray sessions) reaches this.
     *
     * @var array<int, int>
     */
    private array $unattachableConversationIds = [];

    public function up(): void
    {
        $this->backfill();
    }

    /**
     * The legacy rows are still intact at this point, so undoing the backfill
     * is discarding what it derived from them.
     */
    public function down(): void
    {
        DB::transaction(function (): void {
            DB::table('cover_letters')->whereNotNull('application_id')->update(['application_id' => null]);
            DB::table('application_status_updates')->delete();
            DB::table('applications')->delete();
        });
    }

    /**
     * @return array{applications: int, status_updates: int, cover_letters: int} rows written by this run
     */
    public function backfill(
        string $resumeTable = 'targeted_resumes',
        string $statusUpdateTable = 'targeted_resume_status_updates',
        string $coverLetterLinkTable = 'cover_letters',
    ): array {
        return DB::transaction(function () use ($resumeTable, $statusUpdateTable, $coverLetterLinkTable): array {
            $this->unattachableConversationIds = [];
            $written = ['applications' => 0, 'status_updates' => 0, 'cover_letters' => 0];
            $applicationByResume = [];
            $createdForResume = [];

            DB::table($resumeTable)->orderBy('id')->chunkById(self::CHUNK_SIZE, function (Collection $resumes) use (&$written, &$applicationByResume, &$createdForResume): void {
                $conversations = DB::table('ai_conversations')
                    ->whereIn('id', $resumes->pluck('ai_conversation_id')->filter()->all())
                    ->get()
                    ->keyBy('id');

                foreach ($resumes as $resume) {
                    $conversation = $conversations->get($resume->ai_conversation_id);
                    $existingId = $this->existingApplicationForResume($resume);

                    if ($existingId !== null) {
                        $applicationByResume[$resume->id] = $existingId;

                        continue;
                    }

                    $applicationByResume[$resume->id] = DB::table('applications')->insertGetId([
                        'resume_version_id' => $resume->resume_version_id,
                        'targeted_resume_id' => $this->hasContent($resume->tailored_data) ? $resume->id : null,
                        'ai_conversation_id' => $this->unclaimedConversationId($conversation?->id),
                        'job_url_id' => $resume->job_url_id,
                        'company_name' => $resume->company_name,
                        'position' => $resume->position,
                        'location' => null,
                        'job_description' => $resume->job_description,
                        'fit_score' => $resume->fit_score,
                        'fit_summary' => $resume->fit_summary,
                        'status' => $this->applicationStatus($resume->status, $conversation?->status),
                        'created_at' => $conversation?->created_at ?? $resume->created_at,
                        'updated_at' => max($resume->updated_at, $conversation?->updated_at),
                        'deleted_at' => $conversation?->deleted_at,
                    ]);
                    $createdForResume[$resume->id] = true;
                    $written['applications']++;
                }
            });

            $written['applications'] += $this->backfillResumelessConversations();
            $written['status_updates'] = $this->copyStatusUpdates($statusUpdateTable, $applicationByResume, $createdForResume);
            $written['cover_letters'] = $this->relinkCoverLetters($coverLetterLinkTable, $applicationByResume);

            $this->assertComplete($resumeTable, $statusUpdateTable, $coverLetterLinkTable, $applicationByResume);

            return $written;
        });
    }

    /**
     * Sessions that were analyzed but never finalized hold their job only in
     * `context`. Includes soft-deleted sessions: the query builder applies no
     * soft-delete scope, and their applications are created already deleted.
     */
    private function backfillResumelessConversations(): int
    {
        $created = 0;
        $currentVersionId = DB::table('resume_versions')->orderByDesc('is_current')->orderByDesc('id')->value('id');

        DB::table('ai_conversations')
            ->where('feature', 'targeted-resume')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('applications')->whereColumn('applications.ai_conversation_id', 'ai_conversations.id');
            })
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function (Collection $conversations) use (&$created, $currentVersionId): void {
                foreach ($conversations as $conversation) {
                    $context = json_decode((string) $conversation->context, true);
                    $context = is_array($context) ? $context : [];

                    $resumeVersionId = $this->existingId('resume_versions', $context['resume_version_id'] ?? null) ?? $currentVersionId;

                    if ($resumeVersionId === null) {
                        $this->unattachableConversationIds[] = $conversation->id;

                        continue;
                    }

                    DB::table('applications')->insert([
                        'resume_version_id' => $resumeVersionId,
                        'targeted_resume_id' => null,
                        'ai_conversation_id' => $conversation->id,
                        'job_url_id' => $this->existingId('job_urls', $context['job_url_id'] ?? null),
                        'company_name' => $this->text($context['company_name'] ?? null, 'Unknown Company'),
                        'position' => $this->text($context['job_title'] ?? null, 'Unknown Position'),
                        'location' => null,
                        'job_description' => is_string($context['job_description'] ?? null) ? $context['job_description'] : '',
                        'fit_score' => $this->fitScore($context['fit_score'] ?? null),
                        'fit_summary' => is_string($context['fit_summary'] ?? null) ? $context['fit_summary'] : null,
                        'status' => $conversation->status === 'pass' ? 'passed' : 'draft',
                        'created_at' => $conversation->created_at,
                        'updated_at' => $conversation->updated_at,
                        'deleted_at' => $conversation->deleted_at,
                    ]);
                    $created++;
                }
            });

        return $created;
    }

    /**
     * History is copied only for applications this run created, so a second
     * run does not duplicate it.
     *
     * @param  array<int, int>  $applicationByResume
     * @param  array<int, bool>  $createdForResume
     */
    private function copyStatusUpdates(string $statusUpdateTable, array $applicationByResume, array $createdForResume): int
    {
        $copied = 0;

        DB::table($statusUpdateTable)->orderBy('id')->chunkById(self::CHUNK_SIZE, function (Collection $updates) use (&$copied, $applicationByResume, $createdForResume): void {
            $rows = [];

            foreach ($updates as $update) {
                if (! isset($createdForResume[$update->targeted_resume_id])) {
                    continue;
                }

                $rows[] = [
                    'application_id' => $applicationByResume[$update->targeted_resume_id],
                    'status' => $update->status,
                    'notes' => $update->notes,
                    'occurred_at' => $update->occurred_at,
                    'created_at' => $update->created_at,
                    'updated_at' => $update->updated_at,
                ];
            }

            if ($rows !== []) {
                DB::table('application_status_updates')->insert($rows);
                $copied += count($rows);
            }
        });

        return $copied;
    }

    /**
     * @param  array<int, int>  $applicationByResume
     */
    private function relinkCoverLetters(string $coverLetterLinkTable, array $applicationByResume): int
    {
        $relinked = 0;

        DB::table($coverLetterLinkTable)->whereNotNull('targeted_resume_id')->orderBy('id')->chunkById(self::CHUNK_SIZE, function (Collection $links) use (&$relinked, $applicationByResume): void {
            foreach ($links as $link) {
                if (! isset($applicationByResume[$link->targeted_resume_id])) {
                    continue;
                }

                $relinked += DB::table('cover_letters')
                    ->where('id', $link->id)
                    ->whereNull('application_id')
                    ->update(['application_id' => $applicationByResume[$link->targeted_resume_id]]);
            }
        });

        return $relinked;
    }

    /**
     * @param  array<int, int>  $applicationByResume
     */
    private function assertComplete(string $resumeTable, string $statusUpdateTable, string $coverLetterLinkTable, array $applicationByResume): void
    {
        $resumes = DB::table($resumeTable)->count();

        if ($resumes !== count($applicationByResume)) {
            throw new RuntimeException("Application backfill mismatch: {$resumes} targeted resumes but ".count($applicationByResume).' mapped to an application.');
        }

        $orphanedSessions = DB::table('ai_conversations')
            ->where('feature', 'targeted-resume')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('applications')->whereColumn('applications.ai_conversation_id', 'ai_conversations.id');
            })
            ->whereNotIn('id', $this->unattachableConversationIds)
            ->count();

        if ($this->unattachableConversationIds !== []) {
            Log::warning('Application backfill skipped targeted-resume sessions: no resume version exists to attach them to.', [
                'ai_conversation_ids' => $this->unattachableConversationIds,
            ]);
        }

        if ($orphanedSessions !== 0) {
            throw new RuntimeException("Application backfill mismatch: {$orphanedSessions} targeted-resume sessions have no application.");
        }

        $sourceUpdates = DB::table($statusUpdateTable)->count();
        $copiedUpdates = 0;

        foreach (array_chunk(array_values($applicationByResume), self::CHUNK_SIZE) as $applicationIds) {
            $copiedUpdates += DB::table('application_status_updates')->whereIn('application_id', $applicationIds)->count();
        }

        if ($sourceUpdates !== $copiedUpdates) {
            throw new RuntimeException("Application backfill mismatch: {$sourceUpdates} status updates before, {$copiedUpdates} after.");
        }

        $linkedLetterIds = DB::table($coverLetterLinkTable)->whereNotNull('targeted_resume_id')->pluck('id');
        $relinkedLetters = 0;

        foreach ($linkedLetterIds->chunk(self::CHUNK_SIZE) as $letterIds) {
            $relinkedLetters += DB::table('cover_letters')->whereIn('id', $letterIds->all())->whereNotNull('application_id')->count();
        }

        if ($linkedLetterIds->count() !== $relinkedLetters) {
            throw new RuntimeException("Application backfill mismatch: {$linkedLetterIds->count()} linked cover letters before, {$relinkedLetters} after.");
        }
    }

    /**
     * Finds the application an earlier run already created for this resume.
     * A placeholder with neither content nor session has no key to be found
     * by, so it falls back to the values the backfill copied onto it.
     */
    private function existingApplicationForResume(object $resume): ?int
    {
        if ($this->hasContent($resume->tailored_data)) {
            $id = DB::table('applications')->where('targeted_resume_id', $resume->id)->value('id');

            if ($id !== null) {
                return (int) $id;
            }
        }

        if ($resume->ai_conversation_id !== null) {
            $id = DB::table('applications')->where('ai_conversation_id', $resume->ai_conversation_id)->value('id');

            return $id !== null ? (int) $id : null;
        }

        $id = DB::table('applications')
            ->whereNull('targeted_resume_id')
            ->whereNull('ai_conversation_id')
            ->where('resume_version_id', $resume->resume_version_id)
            ->where('company_name', $resume->company_name)
            ->where('position', $resume->position)
            ->where('created_at', $resume->created_at)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * `ai_conversation_id` is unique on applications. Should two legacy
     * resumes ever share a session, the first keeps it.
     */
    private function unclaimedConversationId(?int $conversationId): ?int
    {
        if ($conversationId === null) {
            return null;
        }

        return DB::table('applications')->where('ai_conversation_id', $conversationId)->exists() ? null : $conversationId;
    }

    /**
     * The rule is "has content", not the `base_resume` flag: a flagged row
     * that was later finalized is a real document and keeps its application.
     */
    private function hasContent(?string $tailoredData): bool
    {
        return $tailoredData !== null && trim($tailoredData) !== 'null';
    }

    private function applicationStatus(?string $resumeStatus, ?string $conversationStatus): string
    {
        if (in_array($resumeStatus, self::PIPELINE_STATUSES, true)) {
            return $resumeStatus;
        }

        return $conversationStatus === 'pass' ? 'passed' : 'draft';
    }

    private function existingId(string $table, mixed $id): int|string|null
    {
        if (! is_int($id) && ! is_string($id)) {
            return null;
        }

        return DB::table($table)->where('id', $id)->value('id');
    }

    private function text(mixed $value, string $fallback): string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? mb_substr($value, 0, 191) : $fallback;
    }

    private function fitScore(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, min(100, (int) $value)) : null;
    }
};
