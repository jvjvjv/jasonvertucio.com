<?php

namespace Tests\Unit\Enums;

use App\Enums\ApplicationStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ApplicationStatusTest extends TestCase
{
    public function test_it_has_exactly_the_nine_statuses_with_their_string_values(): void
    {
        $this->assertSame(
            [
                'Draft' => 'draft',
                'Passed' => 'passed',
                'Applied' => 'applied',
                'Interviewing' => 'interviewing',
                'Interviewed' => 'interviewed',
                'Offered' => 'offered',
                'Accepted' => 'accepted',
                'Hired' => 'hired',
                'Rejected' => 'rejected',
            ],
            array_column(ApplicationStatus::cases(), 'value', 'name'),
        );
    }

    public function test_finalized_is_not_a_status(): void
    {
        $this->assertNull(ApplicationStatus::tryFrom('finalized'));
        $this->assertNull(ApplicationStatus::tryFrom('ghosted'));
    }

    /**
     * @return array<string, array{0: ApplicationStatus, 1: bool}>
     */
    public static function pipelineProvider(): array
    {
        return [
            'draft' => [ApplicationStatus::Draft, false],
            'passed' => [ApplicationStatus::Passed, false],
            'applied' => [ApplicationStatus::Applied, true],
            'interviewing' => [ApplicationStatus::Interviewing, true],
            'interviewed' => [ApplicationStatus::Interviewed, true],
            'offered' => [ApplicationStatus::Offered, true],
            'accepted' => [ApplicationStatus::Accepted, true],
            'hired' => [ApplicationStatus::Hired, true],
            'rejected' => [ApplicationStatus::Rejected, true],
        ];
    }

    #[DataProvider('pipelineProvider')]
    public function test_is_pipeline_is_true_from_applied_onward(ApplicationStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isPipeline());
    }

    /**
     * @return array<string, array{0: ApplicationStatus, 1: bool}>
     */
    public static function terminalProvider(): array
    {
        return [
            'draft' => [ApplicationStatus::Draft, false],
            'passed' => [ApplicationStatus::Passed, false],
            'applied' => [ApplicationStatus::Applied, false],
            'interviewing' => [ApplicationStatus::Interviewing, false],
            'interviewed' => [ApplicationStatus::Interviewed, false],
            'offered' => [ApplicationStatus::Offered, false],
            'accepted' => [ApplicationStatus::Accepted, true],
            'hired' => [ApplicationStatus::Hired, true],
            'rejected' => [ApplicationStatus::Rejected, true],
        ];
    }

    #[DataProvider('terminalProvider')]
    public function test_is_terminal_is_true_only_for_accepted_hired_and_rejected(ApplicationStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isTerminal());
    }

    /**
     * @return array<string, array{0: ApplicationStatus, 1: array<int, ApplicationStatus>}>
     */
    public static function allowedNextProvider(): array
    {
        return [
            'draft' => [ApplicationStatus::Draft, [ApplicationStatus::Applied]],
            'passed' => [ApplicationStatus::Passed, [ApplicationStatus::Applied]],
            'applied' => [ApplicationStatus::Applied, [ApplicationStatus::Interviewing, ApplicationStatus::Offered, ApplicationStatus::Rejected]],
            'interviewing' => [ApplicationStatus::Interviewing, [ApplicationStatus::Interviewed, ApplicationStatus::Rejected]],
            'interviewed' => [ApplicationStatus::Interviewed, [ApplicationStatus::Interviewing, ApplicationStatus::Offered, ApplicationStatus::Rejected]],
            'offered' => [ApplicationStatus::Offered, [ApplicationStatus::Accepted, ApplicationStatus::Hired, ApplicationStatus::Rejected]],
            'accepted' => [ApplicationStatus::Accepted, []],
            'hired' => [ApplicationStatus::Hired, []],
            'rejected' => [ApplicationStatus::Rejected, []],
        ];
    }

    /**
     * @param  array<int, ApplicationStatus>  $expected
     */
    #[DataProvider('allowedNextProvider')]
    public function test_allowed_next(ApplicationStatus $status, array $expected): void
    {
        $this->assertSame($expected, $status->allowedNext());
    }

    public function test_draft_and_passed_allow_applied_as_the_only_next_status(): void
    {
        $this->assertSame([ApplicationStatus::Applied], ApplicationStatus::Draft->allowedNext());
        $this->assertSame([ApplicationStatus::Applied], ApplicationStatus::Passed->allowedNext());
    }

    public function test_every_allowed_next_status_is_a_pipeline_status(): void
    {
        foreach (ApplicationStatus::cases() as $status) {
            foreach ($status->allowedNext() as $next) {
                $this->assertTrue($next->isPipeline(), "{$status->value} → {$next->value} leaves the pipeline");
                $this->assertNotSame($status, $next);
            }
        }
    }

    public function test_terminal_statuses_allow_nothing_and_non_terminal_statuses_allow_something(): void
    {
        foreach (ApplicationStatus::cases() as $status) {
            $this->assertSame($status->isTerminal(), $status->allowedNext() === [], $status->value);
        }
    }

    public function test_pipeline_lists_applied_onward_in_order(): void
    {
        $this->assertSame(
            [
                ApplicationStatus::Applied,
                ApplicationStatus::Interviewing,
                ApplicationStatus::Interviewed,
                ApplicationStatus::Offered,
                ApplicationStatus::Accepted,
                ApplicationStatus::Hired,
                ApplicationStatus::Rejected,
            ],
            ApplicationStatus::pipeline(),
        );
    }
}
