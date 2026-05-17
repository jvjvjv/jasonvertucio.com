import Alert from "@mui/material/Alert";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Checkbox from "@mui/material/Checkbox";
import CircularProgress from "@mui/material/CircularProgress";
import Divider from "@mui/material/Divider";
import List from "@mui/material/List";
import ListItem from "@mui/material/ListItem";
import ListItemText from "@mui/material/ListItemText";
import Typography from "@mui/material/Typography";
import { useEffect, useState } from "react";

import type { McpToolSummary } from "@/types";

interface AvailableMcpToolsResponse {
    tools?: McpToolSummary[];
}

interface AvailableMcpToolsProps {
    enabled?: boolean;
    aiSystemId?: number | "";
    selectable?: boolean;
    selectedToolNames?: string[];
    onToggleTool?: (_toolName: string) => void;
    description?: string;
}

export default function AvailableMcpTools({
    enabled = true,
    aiSystemId,
    selectable = false,
    selectedToolNames = [],
    onToggleTool,
    description = "The bot can call these tools when tool use is enabled.",
}: AvailableMcpToolsProps) {
    const [tools, setTools] = useState<McpToolSummary[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [errorMessage, setErrorMessage] = useState("");

    useEffect(() => {
        if (!enabled) {
            // eslint-disable-next-line react-hooks/set-state-in-effect
            setTools([]);
            setErrorMessage("");
            setIsLoading(false);

            return;
        }

        let isMounted = true;

        const loadTools = async () => {
            try {
                const searchParams = new URLSearchParams();

                if (aiSystemId !== "" && aiSystemId !== undefined) {
                    searchParams.set("ai_system_id", String(aiSystemId));
                }

                const response = await fetch(
                    `/admin/ai/chat-bots/mcp-tools?${searchParams.toString()}`,
                    {
                        headers: {
                            Accept: "application/json",
                        },
                    },
                );

                if (!response.ok) {
                    throw new Error("Unable to load MCP tools.");
                }

                const result =
                    (await response.json()) as AvailableMcpToolsResponse;

                if (!isMounted) {
                    return;
                }

                setTools(result.tools ?? []);
                setErrorMessage("");
            } catch {
                if (!isMounted) {
                    return;
                }

                setTools([]);
                setErrorMessage("Unable to load MCP tools.");
            } finally {
                if (isMounted) {
                    setIsLoading(false);
                }
            }
        };

        void loadTools();

        return () => {
            isMounted = false;
        };
    }, [aiSystemId, enabled]);

    if (!enabled) {
        return null;
    }

    return (
        <Card>
            <CardContent>
                <Typography variant="h6" sx={{ mb: 0.5 }}>
                    Available MCP Tools
                </Typography>
                <Typography
                    variant="body2"
                    color="text.secondary"
                    sx={{ mb: 2 }}
                >
                    {description}
                </Typography>

                {isLoading ? <CircularProgress size={24} /> : null}

                {!isLoading && errorMessage !== "" ? (
                    <Alert severity="error">{errorMessage}</Alert>
                ) : null}

                {!isLoading && errorMessage === "" ? (
                    tools.length > 0 ? (
                        <List disablePadding>
                            {tools.map((tool, index) => (
                                <div key={tool.name}>
                                    {index > 0 ? (
                                        <Divider component="li" />
                                    ) : null}
                                    <ListItem
                                        disableGutters
                                        alignItems="flex-start"
                                    >
                                        {selectable ? (
                                            <Checkbox
                                                checked={selectedToolNames.includes(
                                                    tool.name,
                                                )}
                                                onChange={() => {
                                                    onToggleTool?.(tool.name);
                                                }}
                                                edge="start"
                                                sx={{ mt: 0.25, mr: 1 }}
                                            />
                                        ) : null}
                                        <ListItemText
                                            primary={tool.name}
                                            secondary={tool.description}
                                            slotProps={{
                                                primary: {
                                                    variant: "subtitle2",
                                                    sx: {
                                                        fontFamily: "monospace",
                                                    },
                                                },
                                                secondary: {
                                                    variant: "body2",
                                                    color: "text.secondary",
                                                },
                                            }}
                                        />
                                    </ListItem>
                                </div>
                            ))}
                        </List>
                    ) : (
                        <Alert severity="info">
                            No MCP tools are available for this configuration.
                        </Alert>
                    )
                ) : null}
            </CardContent>
        </Card>
    );
}
