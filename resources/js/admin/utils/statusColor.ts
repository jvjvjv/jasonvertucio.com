export type ChipColor =
    | "primary"
    | "secondary"
    | "success"
    | "warning"
    | "error"
    | "info"
    | "default";

export function statusColor(status: string): ChipColor {
    switch (status) {
        case "finalized":
        case "offered":
            return "primary";
        case "applied":
        case "completed":
        case "accepted":
        case "hired":
            return "success";
        case "interviewing":
            return "warning";
        case "active":
        case "in_progress":
        case "interviewed":
            return "info";
        case "pass":
            return "secondary";
        case "rejected":
            return "error";
        case "ghosted":
            return "default";
        default:
            return "default";
    }
}
