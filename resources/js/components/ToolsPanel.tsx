import BuildIcon from "@mui/icons-material/Build";
import CancelOutlinedIcon from "@mui/icons-material/CancelOutlined";
import Box from "@mui/material/Box";
import Chip from "@mui/material/Chip";
import IconButton from "@mui/material/IconButton";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";
import { useState } from "react";

/**
 * One run of tool activity, built from a `tool_use_progress` frame.
 *
 * Host-only streaming state — the package contract has no tool events, so this
 * shape lives with the component that renders it rather than in
 * `@/types/code-talker`.
 */
export interface ToolPanel {
    pretext: string;
    tools: string[];
}

export interface ToolsPanelProps {
    pretext: string;
    tools: string[];
    isActive: boolean;
}

function formatToolName(name: string): string {
    return name.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
}

export default function ToolsPanel({
    pretext,
    tools,
    isActive,
}: ToolsPanelProps) {
    const [expanded, setExpanded] = useState(isActive);

    if (!expanded) {
        return (
            <Box sx={{ display: "flex", justifyContent: "flex-end", my: 0.5 }}>
                <Tooltip title="Show tool calls" placement="top" arrow>
                    <IconButton
                        size="small"
                        onClick={() => {
                            setExpanded(true);
                        }}
                        aria-label="Show tool calls"
                        sx={{ p: 0.5 }}
                    >
                        <BuildIcon
                            sx={{
                                fontSize: 16,
                                color: isActive
                                    ? "warning.main"
                                    : "text.disabled",
                            }}
                        />
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
                borderColor: isActive ? "warning.light" : "divider",
                borderRadius: 2,
                overflow: "hidden",
            }}
        >
            <Box
                sx={{
                    display: "flex",
                    alignItems: "center",
                    gap: 0.75,
                    px: 1.5,
                    py: 0.75,
                    userSelect: "none",
                }}
            >
                <BuildIcon
                    sx={{
                        fontSize: 14,
                        color: isActive ? "warning.main" : "text.disabled",
                    }}
                />
                <Typography
                    variant="caption"
                    sx={{
                        flexGrow: 1,
                        textTransform: "uppercase",
                        letterSpacing: "0.12em",
                        fontWeight: 600,
                        color: isActive ? "warning.main" : "text.disabled",
                    }}
                >
                    {isActive ? "Using tools…" : "Tools"}
                </Typography>
                <Tooltip title="Collapse" placement="top" arrow>
                    <IconButton
                        size="small"
                        sx={{ p: 0.25 }}
                        aria-label="Collapse tool calls"
                        onClick={(e) => {
                            e.stopPropagation();
                            setExpanded(false);
                        }}
                    >
                        <CancelOutlinedIcon fontSize="small" />
                    </IconButton>
                </Tooltip>
            </Box>

            <Box
                sx={{
                    px: 1.5,
                    py: 1,
                    bgcolor: "background.paper",
                }}
            >
                {pretext.trim() && (
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
                            mb: 1,
                            lineHeight: 1.6,
                        }}
                    >
                        {pretext.trim()}
                    </Typography>
                )}
                <Box sx={{ display: "flex", flexWrap: "wrap", gap: 0.5 }}>
                    {tools.map((tool) => (
                        <Chip
                            key={tool}
                            label={formatToolName(tool)}
                            size="small"
                            variant="outlined"
                            sx={{ fontSize: "0.65rem", height: 20 }}
                        />
                    ))}
                </Box>
            </Box>
        </Box>
    );
}
