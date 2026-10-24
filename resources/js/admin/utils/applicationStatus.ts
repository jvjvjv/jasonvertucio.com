const DEFAULT_GHOSTED_AFTER_DAYS = 30;

/** Statuses an application holds before it enters the pipeline. */
const PRE_PIPELINE_STATUSES = new Set(["draft", "passed"]);

function isOlderThanDays(dateValue: string, days: number): boolean {
    const parsed = new Date(dateValue);

    if (Number.isNaN(parsed.getTime())) {
        return false;
    }

    const threshold = new Date();
    threshold.setDate(threshold.getDate() - days);

    return parsed < threshold;
}

/**
 * The status to display for an application. Mirrors PHP
 * `App\Support\ApplicationStatusResolver`: an `applied` application whose
 * latest status entry is older than the ghosted threshold displays as
 * "ghosted"; otherwise the stored status is returned as-is. The stored status
 * is never changed.
 */
export function resolveApplicationDisplayStatus(
    status: string,
    latestStatusOccurredAt?: string | null,
    ghostedAfterDays: number = DEFAULT_GHOSTED_AFTER_DAYS,
): string {
    if (
        status === "applied" &&
        latestStatusOccurredAt &&
        isOlderThanDays(latestStatusOccurredAt, ghostedAfterDays)
    ) {
        return "ghosted";
    }

    return status;
}

/** Whether the application has entered the pipeline (`applied` onward). */
export function isPipelineStatus(status: string): boolean {
    return !PRE_PIPELINE_STATUSES.has(status);
}
