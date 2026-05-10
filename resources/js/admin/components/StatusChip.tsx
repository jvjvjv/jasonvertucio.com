import { ReactNode } from "react";
import Chip from "@mui/material/Chip";
import Tooltip from "@mui/material/Tooltip";
import { statusColor } from "@/admin/utils/statusColor";
import type { ChipColor } from "@/admin/utils/statusColor";

interface StatusChipProps {
    status: string;
    label?: string;
    colorMap?: Record<string, ChipColor>;
    variant?: "outlined" | "filled";
    size?: "small" | "medium";
    tip?: ReactNode;
}
export default function StatusChip({
    status,
    label,
    colorMap,
    size = "small",
    tip = undefined,
}: StatusChipProps) {
    const color = colorMap?.[status] ?? statusColor(status);
    const variant = ["pass", "finalized", "applied"].includes(status)
        ? "outlined"
        : "filled";
    const chip = (
        <Chip
            label={label ?? status}
            size={size}
            color={color}
            variant={variant}
            sx={{
                cursor: tip ? "pointer" : "default",
                userSelect: "none",
            }}
        />
    );

    return tip ? (
        <Tooltip title={tip} arrow placement="right">
            {chip}
        </Tooltip>
    ) : (
        chip
    );
}
