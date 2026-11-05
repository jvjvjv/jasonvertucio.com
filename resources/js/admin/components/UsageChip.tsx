import Chip from "@mui/material/Chip";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";

import type { ConversationUsage } from "@/types";

interface UsageChipProps {
    usage?: ConversationUsage | null;
}

const compactNumber = new Intl.NumberFormat("en-US", {
    notation: "compact",
    maximumFractionDigits: 1,
});

function formatTokens(value: number | null | undefined): string {
    return value != null ? value.toLocaleString() : "—";
}

function totalTokens(
    usage: ConversationUsage | null | undefined,
): number | null {
    if (usage == null) {
        return null;
    }

    if (usage.total_tokens != null) {
        return usage.total_tokens;
    }

    if (usage.input_tokens != null || usage.output_tokens != null) {
        return (usage.input_tokens ?? 0) + (usage.output_tokens ?? 0);
    }

    return null;
}

export default function UsageChip({ usage }: UsageChipProps) {
    const total = totalTokens(usage);

    if (usage == null || total == null) {
        return (
            <Typography variant="caption" color="text.secondary">
                —
            </Typography>
        );
    }

    const tooltip = [
        `Input tokens: ${formatTokens(usage.input_tokens)}`,
        `Output tokens: ${formatTokens(usage.output_tokens)}`,
    ].join("\n");

    return (
        <Tooltip
            title={<span style={{ whiteSpace: "pre-line" }}>{tooltip}</span>}
            arrow
            placement="left"
        >
            <Chip
                label={compactNumber.format(total)}
                size="small"
                variant="outlined"
                sx={{
                    display: "inline-flex",
                    fontWeight: 600,
                    userSelect: "none",
                }}
            />
        </Tooltip>
    );
}
