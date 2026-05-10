import { Head, router } from "@inertiajs/react";
import AddCommentIcon from "@mui/icons-material/AddComment";
import type { MessageBlock } from "@/components/ChatMessageBubble";
import ChatIcon from "@mui/icons-material/Chat";
import InfoIcon from "@mui/icons-material/Info";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Divider from "@mui/material/Divider";
import IconButton from "@mui/material/IconButton";
import List from "@mui/material/List";
import ListItemButton from "@mui/material/ListItemButton";
import ListItemText from "@mui/material/ListItemText";
import Stack from "@mui/material/Stack";
import Tab from "@mui/material/Tab";
import Tabs from "@mui/material/Tabs";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import type { KeyboardEvent } from "react";
import { useEffect, useRef, useState } from "react";
import ChatMessageBubble from "@/components/ChatMessageBubble";

interface HistoryItem {
    handle: string;
    label: string;
    is_current: boolean;
    updated_at: string;
    cost_usd: number | null;
}

interface ChatMessage {
    role: "user" | "assistant" | "system";
    content: string;
    reasoning_content?: string | null;
    blocks?: MessageBlock[] | null;
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
    resetUrl,
    switchUrl,
    chatUrl,
    chatUrlBase,
    showIdentityForm: initialShowIdentityForm,
    chatHash: _chatHash,
}: ChatBotProps) {
    const [messages, setMessages] = useState<ChatMessage[]>(initialMessages);
    const [streamingBlocks, setStreamingBlocks] = useState<MessageBlock[]>([]);
    const [isStreaming, setIsStreaming] = useState(false);
    const [error, setError] = useState("");
    const [showIdentityForm, setShowIdentityForm] = useState(
        initialShowIdentityForm,
    );
    const [visitorName, setVisitorName] = useState("");
    const [visitorEmail, setVisitorEmail] = useState("");
    const [messageText, setMessageText] = useState("");
    const [activeTab, setActiveTab] = useState(0);
    const messagesRef = useRef<HTMLDivElement>(null);

    // Sync state when Inertia re-renders the page with new props (e.g. after
    // a router.reload()). Using setState in an effect is intentional here since
    // these are external prop updates driving local state.
    useEffect(() => {
        setMessages(initialMessages); // eslint-disable-line react-hooks/set-state-in-effect
        setShowIdentityForm(initialShowIdentityForm);
    }, [initialMessages, initialShowIdentityForm]);

    const formatCost = (value: number | null | undefined): string => {
        if (value == null) {
            return "—";
        }

        return `$${value.toFixed(2)}`;
    };

    // Redirect to the hash-based URL after the first message is sent.
    // This enables sharing the chat link from any computer.
    useEffect(() => {
        if (chatUrl && initialMessages.length > 0) {
            const currentPath = window.location.pathname;
            // Only redirect if we're not already on the hash-based URL.
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

    useEffect(() => {
        if (messagesRef.current) {
            messagesRef.current.scrollTop = messagesRef.current.scrollHeight;
        }
    }, [messages, streamingBlocks]);

    const handleKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
        if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            void handleSubmit();
        }
    };

    const handleSubmit = async () => {
        const message = messageText.trim();
        if (!message || isStreaming) {
            return;
        }

        setError("");
        setMessages((prev) => [...prev, { role: "user", content: message }]);
        setMessageText("");
        setIsStreaming(true);
        setStreamingBlocks([]);

        const payload: { [key: string]: string } = { message };
        if (showIdentityForm) {
            payload.name = visitorName;
            payload.email = visitorEmail;
        }

        const csrfToken =
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute("content") ?? "";

        try {
            const response = await fetch(messageUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "text/event-stream",
                    "X-CSRF-TOKEN": csrfToken,
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const reader = response.body?.getReader();
            if (!reader) {
                throw new Error("No response stream available");
            }

            const decoder = new TextDecoder();
            // Local mutable copy — avoids stale closure; synced to state for renders
            let liveBlocks: MessageBlock[] = [];
            let bufferedText = "";

            const appendToBlocks = (
                type: MessageBlock["type"],
                delta: string,
            ): void => {
                const last: MessageBlock | undefined =
                    liveBlocks[liveBlocks.length - 1];
                // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
                if (last?.type === type) {
                    liveBlocks = [
                        ...liveBlocks.slice(0, -1),
                        { type, content: last.content + delta },
                    ];
                } else {
                    liveBlocks = [...liveBlocks, { type, content: delta }];
                }
                setStreamingBlocks([...liveBlocks]);
            };

            const processDataLine = (line: string): void => {
                if (!line.startsWith("data: ")) return;

                const jsonStr = line.slice(6).trim();
                if (!jsonStr || jsonStr === "[DONE]") return;

                let event: {
                    type: string;
                    delta?: { text?: string; reasoning?: string };
                    message?: string;
                };

                try {
                    event = JSON.parse(jsonStr) as typeof event;
                } catch {
                    return;
                }

                if (
                    event.type === "reasoning_block_delta" &&
                    event.delta?.reasoning
                ) {
                    appendToBlocks("reasoning", event.delta.reasoning);
                } else if (
                    event.type === "content_block_delta" &&
                    event.delta?.text
                ) {
                    appendToBlocks("text", event.delta.text);
                } else if (event.type === "error") {
                    throw new Error(event.message ?? "Unknown error");
                }
            };

            let done = false;
            while (!done) {
                const chunk = await reader.read();
                done = chunk.done;

                if (!done) {
                    bufferedText += decoder.decode(chunk.value, {
                        stream: true,
                    });
                    const lines = bufferedText.split("\n");
                    bufferedText = lines.pop() ?? "";

                    for (const line of lines) {
                        processDataLine(line);
                    }
                }
            }

            bufferedText += decoder.decode();
            for (const line of bufferedText.split("\n")) {
                processDataLine(line);
            }

            const finalText = liveBlocks
                .filter((b) => b.type === "text")
                .map((b) => b.content)
                .join("");

            if (finalText || liveBlocks.length > 0) {
                setMessages((prev) => [
                    ...prev,
                    {
                        role: "assistant",
                        content: finalText,
                        blocks: liveBlocks.length > 0 ? liveBlocks : null,
                    },
                ]);
            }

            // Update the browser URL to the permanent hash-based URL after the
            // first message so the conversation can be shared or bookmarked.
            const receivedHash = response.headers.get("X-Chat-Hash");
            if (
                receivedHash &&
                chatUrlBase &&
                window.location.pathname !== chatUrlBase + receivedHash
            ) {
                window.history.replaceState(
                    null,
                    "",
                    chatUrlBase + receivedHash,
                );
            }

            setShowIdentityForm(false);
        } catch (err) {
            setError(
                err instanceof Error
                    ? err.message
                    : "Unable to send message right now.",
            );
        } finally {
            setIsStreaming(false);
            setStreamingBlocks([]);
        }
    };

    const handleReset = () => {
        router.post(resetUrl, {});
    };

    const handleSwitch = (handle: string) => {
        router.post(switchUrl, { conversation: handle });
    };

    return (
        <>
            <Head title={bot.name} />
            <Box
                sx={{ mx: "auto", width: "100%", maxWidth: 1200, px: 2, py: 4 }}
            >
                <Box
                    sx={{
                        display: "flex",
                        flexDirection: "column",
                        gap: 2,
                    }}
                >
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
                            <Tab icon={<ChatIcon />} />
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
                        <Card>
                            <CardContent sx={{ p: 0 }}>
                                <Box
                                    ref={messagesRef}
                                    sx={{
                                        display: "flex",
                                        flexDirection: "column",
                                        gap: 2,
                                        px: 3,
                                        py: 2.5,
                                    }}
                                >
                                    <Card variant="outlined">
                                        <CardContent>
                                            <Stack
                                                direction={{
                                                    xs: "column",
                                                    md: "row",
                                                }}
                                                justifyContent="space-between"
                                                alignItems={{
                                                    xs: "flex-start",
                                                    md: "flex-start",
                                                }}
                                                spacing={2}
                                            >
                                                <Box>
                                                    <Typography
                                                        variant="overline"
                                                        color="text.secondary"
                                                        sx={{
                                                            letterSpacing:
                                                                "0.18em",
                                                        }}
                                                    >
                                                        AI Chat Bot
                                                    </Typography>
                                                    <Typography
                                                        variant="h3"
                                                        sx={{ mt: 0.25 }}
                                                    >
                                                        {bot.name}
                                                    </Typography>
                                                    {bot.description ? (
                                                        <Typography
                                                            sx={{
                                                                mt: 1,
                                                                maxWidth: 840,
                                                            }}
                                                            color="text.secondary"
                                                        >
                                                            {bot.description}
                                                        </Typography>
                                                    ) : null}
                                                </Box>
                                            </Stack>
                                        </CardContent>
                                    </Card>

                                    {messages.length === 0 && !isStreaming ? (
                                        <Box
                                            sx={{
                                                border: "1px dashed",
                                                borderColor: "divider",
                                                py: 3,
                                                px: 2,
                                                textAlign: "center",
                                                color: "text.secondary",
                                            }}
                                        >
                                            Send the first message to start the
                                            conversation.
                                        </Box>
                                    ) : (
                                        messages.map((message, index) => (
                                            <ChatMessageBubble
                                                key={index}
                                                role={message.role}
                                                content={message.content}
                                                blocks={message.blocks ?? null}
                                                reasoningContent={
                                                    message.reasoning_content ??
                                                    null
                                                }
                                            />
                                        ))
                                    )}
                                </Box>

                                {isStreaming ? (
                                    <>
                                        <Divider />
                                        <Box
                                            sx={{
                                                px: 3,
                                                py: 2.5,
                                                bgcolor: "grey.50",
                                            }}
                                        >
                                            <ChatMessageBubble
                                                role="assistant"
                                                content=""
                                                isStreaming
                                                blocks={
                                                    streamingBlocks.length > 0
                                                        ? streamingBlocks
                                                        : null
                                                }
                                                activeBlockType={
                                                    streamingBlocks.length > 0
                                                        ? streamingBlocks[
                                                              streamingBlocks.length -
                                                                  1
                                                          ].type
                                                        : null
                                                }
                                            />
                                        </Box>
                                    </>
                                ) : null}

                                <Divider />
                                <Box
                                    component="form"
                                    sx={{ px: 3, py: 2.5 }}
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        void handleSubmit();
                                    }}
                                >
                                    <Stack spacing={2}>
                                        {showIdentityForm ? (
                                            <Box
                                                sx={{
                                                    display: "grid",
                                                    gap: 2,
                                                    gridTemplateColumns: {
                                                        xs: "1fr",
                                                        md: "1fr 1fr",
                                                    },
                                                }}
                                            >
                                                <TextField
                                                    label="Name"
                                                    value={visitorName}
                                                    onChange={(e) => {
                                                        setVisitorName(
                                                            e.target.value,
                                                        );
                                                    }}
                                                    required
                                                    fullWidth
                                                />
                                                <TextField
                                                    label="Email"
                                                    type="email"
                                                    value={visitorEmail}
                                                    onChange={(e) => {
                                                        setVisitorEmail(
                                                            e.target.value,
                                                        );
                                                    }}
                                                    required
                                                    fullWidth
                                                />
                                            </Box>
                                        ) : null}

                                        <TextField
                                            label="Your message"
                                            multiline
                                            minRows={5}
                                            value={messageText}
                                            onChange={(e) => {
                                                setMessageText(e.target.value);
                                            }}
                                            onKeyDown={handleKeyDown}
                                            required
                                            fullWidth
                                        />

                                        {error ? (
                                            <Alert severity="error">
                                                {error}
                                            </Alert>
                                        ) : null}

                                        <Box
                                            sx={{
                                                display: "flex",
                                                justifyContent: "flex-end",
                                            }}
                                        >
                                            <Typography
                                                variant="caption"
                                                sx={{
                                                    mr: 2,
                                                    alignSelf: "center",
                                                    fontStyle: "italic",
                                                    flexGrow: 1,
                                                }}
                                            >
                                                Chatbots are experimental.
                                                Responses may be inaccurate or
                                                fail to generate. Use with
                                                caution.
                                            </Typography>
                                            <Button
                                                type="submit"
                                                variant="contained"
                                                disabled={isStreaming}
                                            >
                                                Send Message
                                            </Button>
                                        </Box>
                                    </Stack>
                                </Box>
                            </CardContent>
                        </Card>
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
