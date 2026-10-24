import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Checkbox from "@mui/material/Checkbox";
import Chip from "@mui/material/Chip";
import CircularProgress from "@mui/material/CircularProgress";
import Divider from "@mui/material/Divider";
import List from "@mui/material/List";
import ListItem from "@mui/material/ListItem";
import ListItemText from "@mui/material/ListItemText";
import Tab from "@mui/material/Tab";
import Tabs from "@mui/material/Tabs";
import Typography from "@mui/material/Typography";
import { useEffect, useState } from "react";

import type { McpServerCatalogEntry, McpToolSummary } from "@/types";

import { api } from "@/api";

interface AvailableMcpToolsResponse {
    tools?: McpToolSummary[];
}

interface AvailableMcpToolsProps {
    enabled?: boolean;
    aiSystemId?: number | "";
    selectable?: boolean;
    includeAllTools?: boolean;
    selectedToolNames?: string[];
    onToggleTool?: (_toolName: string) => void;
    description?: string;
    /**
     * The remote MCP server catalog. When given, tools are split into
     * "Built-in" and "External" tabs — external servers stacked, or as a
     * vertical tab list beyond STACKED_SERVER_LIMIT — with stale grants listed
     * below the tabs and unavailable remote tools shown disabled. Without it
     * the list stays flat.
     */
    mcpServers?: McpServerCatalogEntry[];
}

/** Up to this many servers stack as labelled groups; beyond it, vertical tabs. */
const STACKED_SERVER_LIMIT = 2;

interface ToolRow {
    name: string;
    description: string;
    /** Why the tool cannot be granted; the row is shown disabled. */
    unavailableReason?: string;
}

interface ToolGroup {
    key: string;
    label: string;
    rows: ToolRow[];
}

/**
 * Groups the endpoint's grantable tools with the server catalog.
 *
 * The endpoint decides what is grantable; the catalog only adds what the
 * endpoint cannot list — unavailable tools, and the server each belongs to.
 * A selected name that appears nowhere grantable is a stale grant (its server
 * was deleted or disabled, or the tool disappeared), listed last so it can be
 * unchecked.
 */
function groupTools(
    tools: McpToolSummary[],
    mcpServers: McpServerCatalogEntry[],
    selectedToolNames: string[],
): ToolGroup[] {
    const grantableNames = new Set(tools.map((tool) => tool.name));
    const shownNames = new Set<string>();

    const groups: ToolGroup[] = [
        {
            key: "internal",
            label: "Built-in tools",
            rows: tools
                .filter((tool) => tool.source !== "remote")
                .map((tool) => ({
                    name: tool.name,
                    description: tool.description,
                })),
        },
    ];

    for (const server of mcpServers) {
        if (!server.enabled) {
            continue;
        }

        const rows: ToolRow[] = tools
            .filter(
                (tool) =>
                    tool.source === "remote" &&
                    tool.server_slug === server.slug,
            )
            .map((tool) => ({
                name: tool.name,
                description: tool.description,
            }));

        for (const catalogTool of server.tools) {
            if (
                catalogTool.available ||
                catalogTool.exposed_name === null ||
                grantableNames.has(catalogTool.exposed_name)
            ) {
                continue;
            }

            rows.push({
                name: catalogTool.exposed_name,
                description: catalogTool.description ?? "",
                unavailableReason:
                    catalogTool.unavailable_reason ?? "Unavailable",
            });
        }

        groups.push({ key: `server-${server.slug}`, label: server.name, rows });
    }

    for (const group of groups) {
        for (const row of group.rows) {
            shownNames.add(row.name);
        }
    }

    const disabledServerByToolName = new Map<string, string>();

    for (const server of mcpServers) {
        if (server.enabled) {
            continue;
        }

        for (const catalogTool of server.tools) {
            if (catalogTool.exposed_name !== null) {
                disabledServerByToolName.set(
                    catalogTool.exposed_name,
                    server.name,
                );
            }
        }
    }

    const staleRows: ToolRow[] = selectedToolNames
        .filter((name) => !shownNames.has(name))
        .map((name) => {
            const disabledServer = disabledServerByToolName.get(name);

            return {
                name,
                description: "",
                unavailableReason: disabledServer
                    ? `Server "${disabledServer}" is disabled`
                    : "No longer available",
            };
        });

    if (staleRows.length > 0) {
        groups.push({
            key: "stale",
            label: "No longer available",
            rows: staleRows,
        });
    }

    return groups.filter((group) => group.rows.length > 0);
}

export default function AvailableMcpTools({
    enabled = true,
    aiSystemId,
    selectable = false,
    includeAllTools = false,
    selectedToolNames = [],
    onToggleTool,
    description = "The bot can call these tools when tool use is enabled.",
    mcpServers,
}: AvailableMcpToolsProps) {
    const [tools, setTools] = useState<McpToolSummary[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [errorMessage, setErrorMessage] = useState("");
    const [sourceTab, setSourceTab] = useState(0);
    const [activeServerKey, setActiveServerKey] = useState<string | null>(null);

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
                const params: { [key: string]: string } = {};

                if (aiSystemId !== "" && aiSystemId !== undefined) {
                    params.ai_system_id = String(aiSystemId);
                }

                if (includeAllTools) {
                    params.include_all = "1";
                }

                const result = await api.get<AvailableMcpToolsResponse>(
                    "/api/admin/ai/personas/mcp-tools",
                    params,
                );

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
    }, [aiSystemId, enabled, includeAllTools]);

    if (!enabled) {
        return null;
    }

    const renderRow = (row: ToolRow, index: number) => {
        const isSelected = selectedToolNames.includes(row.name);
        const isUnavailable = row.unavailableReason !== undefined;

        return (
            <div key={row.name}>
                {index > 0 ? <Divider component="li" /> : null}
                <ListItem disableGutters alignItems="flex-start">
                    {selectable ? (
                        <Checkbox
                            checked={isSelected}
                            // An unavailable tool can't be granted, but a
                            // stale grant stays uncheckable so it can be removed.
                            disabled={isUnavailable && !isSelected}
                            onChange={() => {
                                onToggleTool?.(row.name);
                            }}
                            edge="start"
                            sx={{ mt: 0.25, mr: 1 }}
                        />
                    ) : null}
                    <ListItemText
                        primary={
                            <Box
                                component="span"
                                sx={{
                                    display: "flex",
                                    alignItems: "center",
                                    gap: 1,
                                    flexWrap: "wrap",
                                }}
                            >
                                <span>{row.name}</span>
                                {isUnavailable ? (
                                    <Chip
                                        label={row.unavailableReason}
                                        size="small"
                                        color="warning"
                                        variant="outlined"
                                        sx={{ fontFamily: "inherit" }}
                                    />
                                ) : null}
                            </Box>
                        }
                        secondary={row.description}
                        slotProps={{
                            primary: {
                                variant: "subtitle2",
                                sx: {
                                    fontFamily: "monospace",
                                    color: isUnavailable
                                        ? "text.disabled"
                                        : undefined,
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
        );
    };

    const renderGroup = (group: ToolGroup, showLabel: boolean) => (
        <Box key={group.key} sx={{ mb: 2 }}>
            {showLabel ? (
                <Typography
                    variant="overline"
                    color="text.secondary"
                    component="div"
                >
                    {group.label}
                </Typography>
            ) : null}
            <List disablePadding>{group.rows.map(renderRow)}</List>
        </Box>
    );

    /** "Label (granted/grantable)" — so a collapsed tab still shows its state. */
    const tabLabel = (label: string, groupsInTab: ToolGroup[]) => {
        const rows = groupsInTab.flatMap((group) => group.rows);
        const granted = rows.filter((row) =>
            selectedToolNames.includes(row.name),
        ).length;
        const grantable = rows.filter(
            (row) => row.unavailableReason === undefined,
        ).length;

        return `${label} (${granted}/${grantable})`;
    };

    const renderGrouped = (servers: McpServerCatalogEntry[]) => {
        const groups = groupTools(tools, servers, selectedToolNames);
        const internalGroups = groups.filter(
            (group) => group.key === "internal",
        );
        const serverGroups = groups.filter((group) =>
            group.key.startsWith("server-"),
        );
        const staleGroups = groups.filter((group) => group.key === "stale");
        const activeServerGroup =
            serverGroups.find((group) => group.key === activeServerKey) ??
            serverGroups[0];

        return (
            <>
                <Tabs
                    value={sourceTab}
                    onChange={(_, value: number) => {
                        setSourceTab(value);
                    }}
                    sx={{ mb: 2, borderBottom: 1, borderColor: "divider" }}
                >
                    <Tab label={tabLabel("Built-in", internalGroups)} />
                    <Tab label={tabLabel("External", serverGroups)} />
                </Tabs>

                {sourceTab === 0 ? (
                    internalGroups.length > 0 ? (
                        internalGroups.map((group) => renderGroup(group, false))
                    ) : (
                        <Alert severity="info" sx={{ mb: 2 }}>
                            No built-in tools are available.
                        </Alert>
                    )
                ) : null}

                {sourceTab === 1 ? (
                    serverGroups.length === 0 ? (
                        <Alert severity="info" sx={{ mb: 2 }}>
                            No external MCP servers have tools to grant. Add one
                            under MCP Servers.
                        </Alert>
                    ) : serverGroups.length <= STACKED_SERVER_LIMIT ? (
                        serverGroups.map((group) => renderGroup(group, true))
                    ) : (
                        <Box sx={{ display: "flex", gap: 2, mb: 2 }}>
                            <Tabs
                                orientation="vertical"
                                variant="scrollable"
                                value={activeServerGroup.key}
                                onChange={(_, value: string) => {
                                    setActiveServerKey(value);
                                }}
                                sx={{
                                    borderRight: 1,
                                    borderColor: "divider",
                                    minWidth: 180,
                                    flexShrink: 0,
                                }}
                            >
                                {serverGroups.map((group) => (
                                    <Tab
                                        key={group.key}
                                        value={group.key}
                                        label={tabLabel(group.label, [group])}
                                        sx={{
                                            alignItems: "flex-start",
                                            textAlign: "left",
                                        }}
                                    />
                                ))}
                            </Tabs>
                            <Box sx={{ flexGrow: 1, minWidth: 0 }}>
                                {renderGroup(activeServerGroup, false)}
                            </Box>
                        </Box>
                    )
                ) : null}

                {/* Outside the tabs: a stale grant is something to clean up,
                    and must not hide behind whichever tab isn't selected. */}
                {staleGroups.map((group) => renderGroup(group, true))}
            </>
        );
    };

    return (
        <Card>
            <CardContent>
                <Typography variant="h6" sx={{ mb: 0.5 }}>
                    {mcpServers !== undefined
                        ? "Available Tools"
                        : "Available MCP Tools"}
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
                    mcpServers !== undefined ? (
                        renderGrouped(mcpServers)
                    ) : tools.length > 0 ? (
                        <List disablePadding>
                            {tools
                                .map((tool) => ({
                                    name: tool.name,
                                    description: tool.description,
                                }))
                                .map(renderRow)}
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
