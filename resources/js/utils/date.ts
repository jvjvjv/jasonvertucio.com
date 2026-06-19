import dayjs from "dayjs";

/**
 * Format a calendar-date string (e.g. "2026-06-17" or "2026-06-17T00:00:00+00:00")
 * as a localized date WITHOUT timezone conversion, so the displayed day always
 * matches the entered day regardless of viewer timezone.
 */
export function formatCalendarDate(value: string): string {
    return dayjs(value.slice(0, 10)).format("MMM D, YYYY");
}
