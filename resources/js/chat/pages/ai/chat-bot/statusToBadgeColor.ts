import type { ModelStatus } from "@/components/ChatInterface";

export type BadgeColor = "success" | "warning" | "error" | "info";

/** Maps a model's readiness state to the color of the chat tab's status badge. */
export function statusToBadgeColor(status: ModelStatus | null): BadgeColor {
    if (status?.state === "loaded") return "success";
    if (status?.state === "not_loaded") return "warning";
    if (status?.state === "unavailable") return "error";
    return "info";
}
