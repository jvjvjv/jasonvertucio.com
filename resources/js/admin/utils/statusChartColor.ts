import type { Theme } from "@mui/material/styles";

/**
 * Resolve a concrete hex/CSS color for a status or outcome, drawn from the MUI
 * theme palette so charts (Recharts) and chips (statusColor) stay visually in
 * sync. Mirrors the semantic mapping in statusColor.ts.
 */
export function statusChartColor(theme: Theme, status: string): string {
    switch (status) {
        case "finalized":
        case "offered":
            return theme.palette.primary.main;
        case "applied":
        case "completed":
            return theme.palette.success.main;
        case "accepted":
        case "hired":
            return theme.palette.success.dark;
        case "interviewing":
            return theme.palette.warning.main;
        case "active":
        case "in_progress":
        case "interviewed":
            return theme.palette.info.main;
        case "rejected":
            return theme.palette.error.main;
        case "ghosted":
            return theme.palette.grey[500];
        default:
            return theme.palette.grey[400];
    }
}
