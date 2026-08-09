import dayjs from "dayjs";

/**
 * Format a calendar-date string (e.g. "2026-06-17" or "2026-06-17T00:00:00+00:00")
 * as a localized date WITHOUT timezone conversion, so the displayed day always
 * matches the entered day regardless of viewer timezone.
 */
export function formatCalendarDate(value: string): string {
    return dayjs(value.slice(0, 10)).format("MMM D, YYYY");
}

/** Relative "sent 5 minutes ago"-style label for a chat message timestamp. */
export function getRelativeSentLabel(
    sentAt: string | null | undefined,
): string | null {
    if (!sentAt) return null;
    const sentAtDate = new Date(sentAt);
    if (Number.isNaN(sentAtDate.getTime())) return null;
    const now = new Date();
    const diffInSeconds = Math.round(
        (sentAtDate.getTime() - now.getTime()) / 1000,
    );
    const abs = Math.abs(diffInSeconds);
    const fmt = new Intl.RelativeTimeFormat(undefined, { numeric: "auto" });
    if (abs < 60) return `sent ${fmt.format(diffInSeconds, "second")}`;
    if (abs < 3600)
        return `sent ${fmt.format(Math.round(diffInSeconds / 60), "minute")}`;
    if (abs < 86400)
        return `sent ${fmt.format(Math.round(diffInSeconds / 3600), "hour")}`;
    if (abs < 86400 * 30)
        return `sent ${fmt.format(Math.round(diffInSeconds / 86400), "day")}`;
    if (abs < 86400 * 365)
        return `sent ${fmt.format(Math.round(diffInSeconds / (86400 * 30)), "month")}`;
    return `sent ${fmt.format(Math.round(diffInSeconds / (86400 * 365)), "year")}`;
}

/** Full localized date+time for a chat message timestamp (used in a tooltip). */
export function getLocaleDateTime(
    sentAt: string | null | undefined,
): string | null {
    if (!sentAt) return null;
    const d = new Date(sentAt);
    if (Number.isNaN(d.getTime())) return null;
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(d);
}
