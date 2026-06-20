const GHOSTED_AFTER_DAYS = 14;

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
}

export function resolveTargetedResumeDisplayStatus({
    conversationStatus,
    resumeStatus,
    latestStatusOccurredAt,
}: ResolveDisplayStatusParams): string {
    if (
        resumeStatus === "applied" &&
        latestStatusOccurredAt &&
        isOlderThanDays(latestStatusOccurredAt, GHOSTED_AFTER_DAYS)
    ) {
        return "ghosted";
    }

    if (resumeStatus && resumeStatus !== "draft") {
        return resumeStatus;
    }

    return conversationStatus;
}
