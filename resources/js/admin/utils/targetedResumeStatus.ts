const DEFAULT_GHOSTED_AFTER_DAYS = 30;

function isOlderThanDays(dateValue: string, days: number): boolean {
    const parsed = new Date(dateValue);

    if (Number.isNaN(parsed.getTime())) {
        return false;
    }

    const threshold = new Date();
    threshold.setDate(threshold.getDate() - days);

    return parsed < threshold;
}

interface ResolveDisplayStatusParams {
    conversationStatus: string;
    resumeStatus?: string | null;
    latestStatusOccurredAt?: string | null;
    ghostedAfterDays?: number;
}

export function resolveTargetedResumeDisplayStatus({
    conversationStatus,
    resumeStatus,
    latestStatusOccurredAt,
    ghostedAfterDays = DEFAULT_GHOSTED_AFTER_DAYS,
}: ResolveDisplayStatusParams): string {
    if (
        resumeStatus === "applied" &&
        latestStatusOccurredAt &&
        isOlderThanDays(latestStatusOccurredAt, ghostedAfterDays)
    ) {
        return "ghosted";
    }

    if (resumeStatus && resumeStatus !== "draft") {
        return resumeStatus;
    }

    return conversationStatus;
}
