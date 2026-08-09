/**
 * Width of the readable chat column.
 *
 * Shared by the transcript and the composer so the two can't drift — anything
 * rendered as part of a message (bubbles, tool panels) is capped here and
 * centered, which is what keeps the composer aligned with the messages above it.
 */
export const CHAT_COLUMN_MAX_WIDTH = "840px";
