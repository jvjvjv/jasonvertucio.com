import { Head, router, usePage } from "@inertiajs/react";
import AddCommentIcon from "@mui/icons-material/AddComment";
import ChatIcon from "@mui/icons-material/Chat";
import InfoIcon from "@mui/icons-material/Info";
import WarningAmberIcon from "@mui/icons-material/WarningAmber";
import Badge from "@mui/material/Badge";
import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Chip from "@mui/material/Chip";
import IconButton from "@mui/material/IconButton";
import List from "@mui/material/List";
import ListItemButton from "@mui/material/ListItemButton";
import ListItemText from "@mui/material/ListItemText";
import Stack from "@mui/material/Stack";
import Tab from "@mui/material/Tab";
import Tabs from "@mui/material/Tabs";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useEffect, useMemo, useState } from "react";

import type { ChatMessage } from "@/components/ChatInterface";
import type { SharedProps } from "@/types";

import ChatInterface from "@/components/ChatInterface";

interface HistoryItem {
    handle: string;
    label: string;
    is_current: boolean;
    is_stale: boolean;
    updated_at: string;
    cost_usd: number | null;
}

interface Bot {
    name: string;
    description: string | null;
    is_public: boolean;
    require_visitor_identity: boolean;
    total_cost_usd: number;
}

interface ChatBotProps {
    bot: Bot;
    messages: ChatMessage[];
    history: HistoryItem[];
    messageUrl: string;
    statusUrl: string;
    warmupUrl: string;
    resetUrl: string;
    switchUrl: string;
    chatUrl?: string | null;
    chatUrlBase?: string | null;
    showIdentityForm: boolean;
    chatHash?: string | null;
}

export default function ChatBot({
    bot,
    messages: initialMessages,
    history,
    messageUrl,
    statusUrl,
    warmupUrl,
    resetUrl,
    switchUrl,
    chatUrl,
    chatUrlBase,
    showIdentityForm: initialShowIdentityForm,
    chatHash: _chatHash,
}: ChatBotProps) {
    const [activeTab, setActiveTab] = useState(0);
    const [showIdentityForm, setShowIdentityForm] = useState(
        initialShowIdentityForm,
    );
    const [visitorName, setVisitorName] = useState("");
    const [visitorEmail, setVisitorEmail] = useState("");

    const page = usePage<SharedProps>();
    const authUser = page.props.auth.user;

    // Redirect to hash-based URL after first message so the chat is shareable
    useEffect(() => {
        if (chatUrl && initialMessages.length > 0) {
            const currentPath = window.location.pathname;
            if (currentPath !== chatUrl) {
                const timer = setTimeout(() => {
                    window.location.href = chatUrl;
                }, 300);
                return () => {
                    clearTimeout(timer);
                };
            }
        }
    }, [chatUrl, initialMessages]);

    const extraPayload = useMemo(
        () =>
            showIdentityForm
                ? { name: visitorName, email: visitorEmail }
                : undefined,
        [showIdentityForm, visitorName, visitorEmail],
    );

    const formatCost = (value: number | null | undefined): string =>
        value == null ? "—" : `$${value.toFixed(2)}`;

    const handleReset = () => {
        router.post(resetUrl, {});
    };

    const handleSwitch = (handle: string) => {
        router.post(switchUrl, { conversation: handle });
    };

    const identityForm = showIdentityForm ? (
        <Box
            sx={{
                display: "grid",
                gap: 2,
                gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
            }}
        >
            <TextField
                label="Name"
                value={visitorName}
                onChange={(e) => {
                    setVisitorName(e.target.value);
                }}
                required
                fullWidth
            />
            <TextField
                label="Email"
                type="email"
                value={visitorEmail}
                onChange={(e) => {
                    setVisitorEmail(e.target.value);
                }}
                required
                fullWidth
            />
        </Box>
    ) : null;

    const disclaimer = (
        <Typography
            variant="caption"
            sx={{
                mr: 2,
                alignSelf: "center",
                fontStyle: "italic",
                flexGrow: 1,
            }}
        >
            Chatbots are experimental. Responses may be inaccurate or fail to
            generate. Use with caution.
        </Typography>
    );

    const botHeaderCard = (
        <Card variant="outlined">
            <CardContent>
                <Stack
                    direction={{ xs: "column", md: "row" }}
                    justifyContent="space-between"
                    alignItems={{ xs: "flex-start", md: "flex-start" }}
                    spacing={2}
                >
                    <Box>
                        <Typography
                            variant="overline"
                            color="text.secondary"
                            sx={{ letterSpacing: "0.18em" }}
                        >
                            AI Chat Bot
                        </Typography>
                        <Typography variant="h3" sx={{ mt: 0.25 }}>
                            {bot.name}
                        </Typography>
                        {bot.description ? (
                            <Typography
                                sx={{ mt: 1, maxWidth: 840 }}
                                color="text.secondary"
                            >
                                {bot.description}
                            </Typography>
                        ) : null}
                    </Box>
                </Stack>
            </CardContent>
        </Card>
    );

    const [badgeColor, setBadgeColor] = useState<
        "success" | "warning" | "error" | "info"
    >("info");

    return (
        <>
            <Head title={bot.name} />
            <Box
                sx={{ mx: "auto", width: "100%", maxWidth: 1200, px: 2, py: 4 }}
            >
                <Box sx={{ display: "flex", flexDirection: "column", gap: 2 }}>
                    <Box
                        sx={{
                            position: "sticky",
                            top: { xs: 56, md: 64 },
                            zIndex: 10,
                            display: "flex",
                            alignItems: "center",
                            gap: 1,
                            bgcolor: "background.paper",
                            borderBottom: 1,
                            borderColor: "divider",
                        }}
                    >
                        <Tabs
                            value={activeTab}
                            onChange={(_, v: number) => {
                                setActiveTab(v);
                            }}
                            aria-label="Chat page tabs"
                            sx={{
                                "& .MuiTab-root": {
                                    minWidth: 0,
                                    px: 2,
                                    py: 1.5,
                                },
                            }}
                        >
                            <Tab
                                icon={
                                    <Badge
                                        variant="dot"
                                        color={badgeColor}
                                        overlap="circular"
                                    >
                                        <ChatIcon />
                                    </Badge>
                                }
                            />
                            <Tab icon={<InfoIcon />} />
                        </Tabs>
                        <Box sx={{ flexGrow: 1 }} />
                        <Box sx={{ pr: 1 }}>
                            <IconButton
                                aria-label="Start a new chat"
                                size="small"
                                onClick={handleReset}
                            >
                                <AddCommentIcon fontSize="small" />
                            </IconButton>
                        </Box>
                    </Box>

                    {activeTab === 0 ? (
                        <ChatInterface
                            chatEndpoint={messageUrl}
                            statusUrl={statusUrl}
                            warmupUrl={warmupUrl}
                            initialMessages={initialMessages}
                            isAuthenticated={!!authUser}
                            extraPayload={extraPayload}
                            slots={{
                                aboveMessages: botHeaderCard,
                                beforeSend: identityForm,
                                afterSend: disclaimer,
                            }}
                            onModelStatusChange={(status) => {
                                setBadgeColor(
                                    status?.state === "loaded"
                                        ? "success"
                                        : status?.state === "not_loaded"
                                          ? "warning"
                                          : status?.state === "unavailable"
                                            ? "error"
                                            : "info",
                                );
                            }}
                            onStreamResponse={
                                chatUrlBase
                                    ? (res) => {
                                          const hash =
                                              res.headers.get("X-Chat-Hash");
                                          if (
                                              hash &&
                                              window.location.pathname !==
                                                  chatUrlBase + hash
                                          ) {
                                              window.history.replaceState(
                                                  null,
                                                  "",
                                                  chatUrlBase + hash,
                                              );
                                          }
                                      }
                                    : undefined
                            }
                            onStreamEnd={() => {
                                setShowIdentityForm(false);
                            }}
                        />
                    ) : (
                        <Stack spacing={2}>
                            <Card>
                                <CardContent>
                                    <Stack
                                        direction={{ xs: "column", sm: "row" }}
                                        alignItems={{
                                            xs: "flex-start",
                                            sm: "center",
                                        }}
                                        justifyContent="space-between"
                                        spacing={1}
                                        sx={{ mb: 1 }}
                                    >
                                        <Typography variant="h5">
                                            Your Chats
                                        </Typography>
                                        <Typography
                                            variant="caption"
                                            color="text.secondary"
                                            sx={{
                                                textTransform: "uppercase",
                                                letterSpacing: "0.14em",
                                            }}
                                        >
                                            Private to this browser
                                        </Typography>
                                    </Stack>

                                    <Box
                                        sx={{
                                            display: "flex",
                                            justifyContent: "space-between",
                                            alignItems: "center",
                                            mb: 1.5,
                                            px: 1,
                                        }}
                                    >
                                        <Typography
                                            variant="body2"
                                            color="text.secondary"
                                        >
                                            Overall Chatbot Cost
                                        </Typography>
                                        <Typography
                                            variant="body2"
                                            sx={{ fontWeight: 700 }}
                                        >
                                            {formatCost(bot.total_cost_usd)}
                                        </Typography>
                                    </Box>

                                    {history.length > 0 ? (
                                        <List disablePadding>
                                            {history.map((item) => (
                                                <ListItemButton
                                                    key={item.handle}
                                                    selected={item.is_current}
                                                    onClick={() => {
                                                        handleSwitch(
                                                            item.handle,
                                                        );
                                                    }}
                                                    sx={{
                                                        border: "1px solid",
                                                        borderColor:
                                                            item.is_current
                                                                ? "primary.main"
                                                                : "divider",
                                                        mb: 1,
                                                    }}
                                                >
                                                    <Box
                                                        sx={{
                                                            display: "flex",
                                                            alignItems:
                                                                "center",
                                                            gap: 1,
                                                            minWidth: 0,
                                                            flexGrow: 1,
                                                        }}
                                                    >
                                                        <ListItemText
                                                            primary={item.label}
                                                            secondary={
                                                                <Box
                                                                    sx={{
                                                                        display:
                                                                            "flex",
                                                                        alignItems:
                                                                            "center",
                                                                        justifyContent:
                                                                            "space-between",
                                                                        mt: 0.5,
                                                                    }}
                                                                >
                                                                    <Typography
                                                                        component="span"
                                                                        sx={{
                                                                            textTransform:
                                                                                "uppercase",
                                                                            letterSpacing:
                                                                                "0.08em",
                                                                            fontSize:
                                                                                "0.7rem",
                                                                            color: "text.secondary",
                                                                        }}
                                                                    >
                                                                        {
                                                                            item.updated_at
                                                                        }
                                                                    </Typography>
                                                                    <Typography
                                                                        component="span"
                                                                        sx={{
                                                                            fontSize:
                                                                                "0.72rem",
                                                                            color: "text.secondary",
                                                                            fontWeight: 600,
                                                                        }}
                                                                    >
                                                                        Cost:{" "}
                                                                        {formatCost(
                                                                            item.cost_usd,
                                                                        )}
                                                                    </Typography>
                                                                </Box>
                                                            }
                                                        />
                                                        {item.is_stale &&
                                                        !item.is_current ? (
                                                            <Chip
                                                                icon={
                                                                    <WarningAmberIcon fontSize="small" />
                                                                }
                                                                size="small"
                                                                label="Stale"
                                                                sx={{
                                                                    flexShrink: 0,
                                                                    "& .MuiChip-label":
                                                                        {
                                                                            pl: 0.5,
                                                                        },
                                                                }}
                                                            />
                                                        ) : null}
                                                    </Box>
                                                    {item.is_current ? (
                                                        <Typography
                                                            variant="caption"
                                                            color="primary"
                                                            sx={{
                                                                textTransform:
                                                                    "uppercase",
                                                                letterSpacing:
                                                                    "0.12em",
                                                            }}
                                                        >
                                                            Current
                                                        </Typography>
                                                    ) : null}
                                                </ListItemButton>
                                            ))}
                                        </List>
                                    ) : (
                                        <Typography
                                            variant="body2"
                                            color="text.secondary"
                                        >
                                            No saved chats in this browser yet.
                                            Start a message to create a private
                                            thread.
                                        </Typography>
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardContent>
                                    <Typography variant="h5" sx={{ mb: 1 }}>
                                        Access
                                    </Typography>
                                    <Stack spacing={0.75}>
                                        <Typography
                                            variant="body2"
                                            color="text.secondary"
                                        >
                                            {bot.is_public
                                                ? "Public bot"
                                                : "Restricted bot"}
                                        </Typography>
                                        <Typography
                                            variant="body2"
                                            color="text.secondary"
                                        >
                                            {bot.require_visitor_identity
                                                ? "Name and email are required before the first guest message."
                                                : "No guest identity is required by this bot."}
                                        </Typography>
                                        <Typography
                                            variant="body2"
                                            color="text.secondary"
                                        >
                                            Only chats created in this browser
                                            are listed here.
                                        </Typography>
                                    </Stack>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardContent>
                                    <Typography variant="h5" sx={{ mb: 1 }}>
                                        Prompt Notes
                                    </Typography>
                                    <Typography
                                        variant="body2"
                                        color="text.secondary"
                                    >
                                        The conversation is saved and can
                                        contribute new insights to AI Memory for
                                        this bot.
                                    </Typography>
                                </CardContent>
                            </Card>
                        </Stack>
                    )}
                </Box>
            </Box>
        </>
    );
}
