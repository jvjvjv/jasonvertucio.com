import Alert from "@mui/material/Alert";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
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
}

export default function AvailableMcpTools({
    enabled = true,
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
                const response = await fetch("/admin/ai/chat-bots/mcp-tools", {
                    headers: {
                        Accept: "application/json",
                    },
                });

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
    }, [enabled]);

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
                    The bot can call these tools when tool use is enabled.
                </Typography>

                {isLoading ? <CircularProgress size={24} /> : null}

                {!isLoading && errorMessage !== "" ? (
                    <Alert severity="error">{errorMessage}</Alert>
                ) : null}

                {!isLoading && errorMessage === "" ? (
                    <List disablePadding>
                        {tools.map((tool, index) => (
                            <div key={tool.name}>
                                {index > 0 ? <Divider component="li" /> : null}
                                <ListItem
                                    disableGutters
                                    alignItems="flex-start"
                                >
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
                ) : null}
            </CardContent>
        </Card>
    );
}
