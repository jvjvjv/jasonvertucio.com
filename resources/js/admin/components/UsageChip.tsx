import Chip from "@mui/material/Chip";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";

import type { ConversationUsage } from "@/types";
import type { ChipProps } from "@mui/material/Chip";

interface UsageChipProps {
    usage?: ConversationUsage | null;
}

function formatTokens(value: number | null | undefined): string {
    return value != null ? value.toLocaleString() : "—";
}

export default function UsageChip({ usage }: UsageChipProps) {
    if (usage?.cost_usd == null) {
        return (
            <Typography variant="caption" color="text.secondary">
                —
            </Typography>
        );
    }

    const chipColor: ChipProps["color"] =
        usage.cost_usd <= 0.1
            ? "success"
            : usage.cost_usd <= 0.35
              ? "primary"
              : usage.cost_usd <= 0.5
                ? "info"
                : usage.cost_usd <= 0.75
                  ? "default"
                  : usage.cost_usd <= 0.99
                    ? "warning"
                    : "error";
    const chipVariant: ChipProps["variant"] =
        usage.cost_usd <= 0.75 ? "outlined" : "filled";

    const tooltip = [
        `Input tokens: ${formatTokens(usage.input_tokens)}`,
        `Output tokens: ${formatTokens(usage.output_tokens)}`,
        `Cost: $${usage.cost_usd.toFixed(4)}`,
    ].join("\n");

    return (
        <Tooltip
            title={<span style={{ whiteSpace: "pre-line" }}>{tooltip}</span>}
            arrow
            placement="left"
        >
            <Chip
                label={`$${usage.cost_usd.toFixed(2)}`}
                size="small"
                color={chipColor}
                variant={chipVariant}
                sx={{
                    display: "inline-flex",
                    fontWeight: 600,
                    userSelect: "none",
                }}
            />
        </Tooltip>
    );
}
