import PsychologyIcon from "@mui/icons-material/Psychology";
import Box from "@mui/material/Box";
import Collapse from "@mui/material/Collapse";
import IconButton from "@mui/material/IconButton";
import LinearProgress from "@mui/material/LinearProgress";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";
import { useEffect, useState } from "react";

export interface ReasoningPanelProps {
    content: string;
    /** True while this block is actively receiving streaming tokens. */
    isActive: boolean;
}

export default function ReasoningPanel({ content, isActive }: ReasoningPanelProps) {
    const [expanded, setExpanded] = useState(isActive);

    useEffect(() => {
        if (isActive) setExpanded(true);
    }, [isActive]);

    if (!expanded) {
        return (
            <Box sx={{ display: "flex", justifyContent: "flex-end", my: 0.5 }}>
                <Tooltip title="Show reasoning" placement="top" arrow>
                    <IconButton
                        size="small"
                        onClick={() => setExpanded(true)}
                        aria-label="Show reasoning"
                        sx={{ p: 0.5 }}
                    >
                        <PsychologyIcon sx={{ fontSize: 16, color: "text.disabled" }} />
                    </IconButton>
                </Tooltip>
            </Box>
        );
    }

    return (
        <Box
            sx={{
                mb: 1,
                border: "1px solid",
                borderColor: isActive ? "primary.light" : "divider",
                borderRadius: 1,
                overflow: "hidden",
            }}
        >
            {/* Header */}
            <Box
                sx={{
                    display: "flex",
                    alignItems: "center",
                    gap: 0.75,
                    px: 1.5,
                    py: 0.75,
                    borderBottom: "1px solid",
                    borderColor: isActive ? "primary.light" : "divider",
                    bgcolor: isActive ? "primary.50" : "grey.50",
                    cursor: "pointer",
                    userSelect: "none",
                }}
                onClick={() => setExpanded(false)}
            >
                <PsychologyIcon
                    sx={{
                        fontSize: 14,
                        color: isActive ? "primary.main" : "text.disabled",
                    }}
                />
                <Typography
                    variant="caption"
                    sx={{
                        flexGrow: 1,
                        textTransform: "uppercase",
                        letterSpacing: "0.12em",
                        fontWeight: 600,
                        color: isActive ? "primary.main" : "text.disabled",
                    }}
                >
                    {isActive ? "Thinking…" : "Reasoning"}
                </Typography>
                <Tooltip title="Collapse" placement="top" arrow>
                    <IconButton
                        size="small"
                        sx={{ p: 0.25 }}
                        aria-label="Collapse reasoning"
                        onClick={(e) => {
                            e.stopPropagation();
                            setExpanded(false);
                        }}
                    >
                        <Typography
                            variant="caption"
                            sx={{
                                lineHeight: 1,
                                color: "text.disabled",
                                fontSize: "12px",
                            }}
                        >
                            &#9650;
                        </Typography>
                    </IconButton>
                </Tooltip>
            </Box>

            {/* Content */}
            <Collapse in={expanded}>
                <Box
                    sx={{
                        px: 1.5,
                        py: 1,
                        maxHeight: 240,
                        overflowY: "auto",
                        bgcolor: "background.paper",
                    }}
                >
                    <Typography
                        component="pre"
                        variant="caption"
                        sx={{
                            display: "block",
                            whiteSpace: "pre-wrap",
                            wordBreak: "break-word",
                            fontFamily: "monospace",
                            color: "text.secondary",
                            m: 0,
                            lineHeight: 1.6,
                        }}
                    >
                        {content}
                    </Typography>
                </Box>
                {isActive ? (
                    <LinearProgress
                        sx={{ height: 2 }}
                        aria-label="Model is thinking"
                    />
                ) : null}
            </Collapse>
        </Box>
    );
}
