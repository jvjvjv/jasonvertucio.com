import AutoAwesomeIcon from "@mui/icons-material/AutoAwesome";
import AutoFixHighIcon from "@mui/icons-material/AutoFixHigh";
import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import CircularProgress from "@mui/material/CircularProgress";
import IconButton from "@mui/material/IconButton";
import Typography from "@mui/material/Typography";

import type { ReactNode } from "react";

interface BuilderStatusCardProps {
    label: string;
    isFinalized: boolean;
    color: "primary" | "secondary" | "error" | "warning" | "info" | "success";
    canFinalize: boolean;
    isFinalizing: boolean;
    hasUpdate: boolean;
    finalizeTitle: string;
    onFinalize: () => void;
    caption?: string;
    extraActions?: ReactNode;
}

export default function BuilderStatusCard({
    label,
    isFinalized,
    color,
    canFinalize,
    isFinalizing,
    hasUpdate,
    finalizeTitle,
    onFinalize,
    caption,
    extraActions,
}: BuilderStatusCardProps) {
    return (
        <Card
            variant="outlined"
            sx={{
                borderColor: isFinalized ? `${color}.light` : "divider",
                bgcolor: isFinalized ? `${color}.50` : "background.paper",
            }}
        >
            <CardContent sx={{ py: 1.5, "&:last-child": { pb: 1.5 } }}>
                <Box
                    sx={{
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "space-between",
                        gap: 1,
                    }}
                >
                    <Box>
                        <Typography
                            variant="overline"
                            color={
                                isFinalized ? `${color}.dark` : "text.secondary"
                            }
                            sx={{ display: "block", lineHeight: 1.5 }}
                        >
                            {label}
                        </Typography>
                        <Typography variant="subtitle2">
                            {isFinalized
                                ? "Finalized and ready"
                                : "Not finalized yet"}
                        </Typography>
                    </Box>
                    <Box sx={{ display: "flex", gap: 0.5 }}>
                        {extraActions}
                        <IconButton
                            size="small"
                            color="primary"
                            disabled={isFinalizing || !canFinalize}
                            onClick={onFinalize}
                            title={finalizeTitle}
                        >
                            {isFinalizing ? (
                                <CircularProgress size={16} />
                            ) : hasUpdate ? (
                                <AutoFixHighIcon />
                            ) : (
                                <AutoAwesomeIcon />
                            )}
                        </IconButton>
                    </Box>
                </Box>
                {caption && (
                    <Typography variant="caption" color="text.secondary">
                        {caption}
                    </Typography>
                )}
            </CardContent>
        </Card>
    );
}
