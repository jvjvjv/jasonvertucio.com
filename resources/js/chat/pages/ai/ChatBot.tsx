import { Head, router } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import CircularProgress from '@mui/material/CircularProgress';
import Divider from '@mui/material/Divider';
import LinearProgress from '@mui/material/LinearProgress';
import List from '@mui/material/List';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemText from '@mui/material/ListItemText';
import Stack from '@mui/material/Stack';
import Tab from '@mui/material/Tab';
import Tabs from '@mui/material/Tabs';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { useEffect, useRef, useState } from 'react';
import ChatMessageBubble from "../../../components/ChatMessageBubble";

interface HistoryItem {
    handle: string;
    label: string;
    is_current: boolean;
    updated_at: string;
}

interface ChatMessage {
    role: 'user' | 'assistant' | 'system';
    content: string;
}

interface Bot {
    name: string;
    description: string | null;
    is_public: boolean;
    require_visitor_identity: boolean;
}

interface ChatBotProps {
    bot: Bot;
    messages: ChatMessage[];
    history: HistoryItem[];
    messageUrl: string;
    resetUrl: string;
    switchUrl: string;
    showIdentityForm: boolean;
}

export default function ChatBot({
    bot,
    messages: initialMessages,
    history,
    messageUrl,
    resetUrl,
    switchUrl,
    showIdentityForm: initialShowIdentityForm,
}: ChatBotProps) {
    const [messages, setMessages] = useState<ChatMessage[]>(initialMessages);
    const [streamingContent, setStreamingContent] = useState('');
    const [isStreaming, setIsStreaming] = useState(false);
    const [error, setError] = useState('');
    const [showIdentityForm, setShowIdentityForm] = useState(initialShowIdentityForm);
    const [visitorName, setVisitorName] = useState('');
    const [visitorEmail, setVisitorEmail] = useState('');
    const [messageText, setMessageText] = useState('');
    const [activeTab, setActiveTab] = useState(0);
    const messagesRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        setMessages(initialMessages);
        setShowIdentityForm(initialShowIdentityForm);
    }, [initialMessages, initialShowIdentityForm]);

    useEffect(() => {
        if (messagesRef.current) {
            messagesRef.current.scrollTop = messagesRef.current.scrollHeight;
        }
    }, [messages, streamingContent]);

    const handleKeyDown = (e: React.KeyboardEvent<HTMLDivElement>) => {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            void handleSubmit();
        }
    };

    const handleSubmit = async () => {
        const message = messageText.trim();
        if (!message || isStreaming) {
            return;
        }

        setError('');
        setMessages((prev) => [...prev, { role: 'user', content: message }]);
        setMessageText('');
        setIsStreaming(true);
        setStreamingContent('');

        const payload: Record<string, string> = { message };
        if (showIdentityForm) {
            payload.name = visitorName;
            payload.email = visitorEmail;
        }

        const csrfToken =
            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        try {
            const response = await fetch(messageUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/event-stream',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const reader = response.body?.getReader();
            if (!reader) {
                throw new Error('No response stream available');
            }

            const decoder = new TextDecoder();
            let accumulated = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) {
                    break;
                }

                for (const line of decoder.decode(value, { stream: true }).split('\n')) {
                    if (!line.startsWith('data: ')) {
                        continue;
                    }
                    const jsonStr = line.slice(6).trim();
                    if (!jsonStr || jsonStr === '[DONE]') {
                        continue;
                    }
                    const event = JSON.parse(jsonStr) as {
                        type: string;
                        delta?: { text?: string };
                        message?: string;
                    };
                    if (event.type === 'content_block_delta' && event.delta?.text) {
                        accumulated += event.delta.text;
                        setStreamingContent(accumulated);
                    } else if (event.type === 'error') {
                        throw new Error(event.message ?? 'Unknown error');
                    }
                }
            }

            if (accumulated) {
                setMessages((prev) => [...prev, { role: 'assistant', content: accumulated }]);
            }
            setShowIdentityForm(false);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Unable to send message right now.');
        } finally {
            setIsStreaming(false);
            setStreamingContent('');
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
            <Box sx={{ mx: 'auto', width: '100%', maxWidth: 1200, px: 2, py: 4 }}>
                <Stack spacing={3}>
                    <Card>
                        <CardContent>
                            <Stack
                                direction={{ xs: 'column', md: 'row' }}
                                justifyContent="space-between"
                                alignItems={{ xs: 'flex-start', md: 'flex-start' }}
                                spacing={2}
                            >
                                <Box>
                                    <Typography
                                        variant="overline"
                                        color="text.secondary"
                                        sx={{ letterSpacing: '0.18em' }}
                                    >
                                        AI Chat Bot
                                    </Typography>
                                    <Typography variant="h2" sx={{ mt: 0.25 }}>
                                        {bot.name}
                                    </Typography>
                                    {bot.description ? (
                                        <Typography sx={{ mt: 1, maxWidth: 840 }} color="text.secondary">
                                            {bot.description}
                                        </Typography>
                                    ) : null}
                                </Box>
                                <Button
                                    type="button"
                                    variant="outlined"
                                    onClick={handleReset}
                                    sx={{ alignSelf: { xs: 'stretch', md: 'flex-start' } }}
                                >
                                    New Chat
                                </Button>
                            </Stack>
                        </CardContent>
                    </Card>

                    <Tabs value={activeTab} onChange={(_, v) => setActiveTab(v)}>
                        <Tab label="Chat" />
                        <Tab label="Details" />
                    </Tabs>

                    {activeTab === 0 ? (
                        <Card>
                            <CardContent sx={{ p: 0 }}>
                                <Box sx={{ px: 3, py: 2 }}>
                                    <Typography variant="h4">Conversation</Typography>
                                </Box>
                                <Divider />

                                <Box
                                    ref={messagesRef}
                                    sx={{
                                        maxHeight: '62vh',
                                        overflowY: 'auto',
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 2,
                                        px: 3,
                                        py: 2.5,
                                    }}
                                >
                                    {messages.length === 0 && !isStreaming ? (
                                        <Box
                                            sx={{
                                                border: '1px dashed',
                                                borderColor: 'divider',
                                                py: 3,
                                                px: 2,
                                                textAlign: 'center',
                                                color: 'text.secondary',
                                            }}
                                        >
                                            Send the first message to start the conversation.
                                        </Box>
                                    ) : (
                                        messages.map((message, index) => (
                                            <ChatMessageBubble
                                                key={index}
                                                role={message.role}
                                                content={message.content}
                                            />
                                        ))
                                    )}
                                </Box>

                                {isStreaming ? (
                                    <>
                                        <Divider />
                                        <Box sx={{ px: 3, py: 2.5, bgcolor: 'grey.50' }}>
                                            <Typography
                                                variant="caption"
                                                sx={{
                                                    display: 'block',
                                                    mb: 1,
                                                    textTransform: 'uppercase',
                                                    letterSpacing: '0.16em',
                                                    color: 'text.secondary',
                                                }}
                                            >
                                                assistant
                                            </Typography>
                                            {streamingContent ? (
                                                <ChatMessageBubble
                                                    role="assistant"
                                                    content={streamingContent}
                                                />
                                            ) : (
                                                <Typography color="text.secondary">...</Typography>
                                            )}
                                            <Stack
                                                direction="row"
                                                spacing={1.5}
                                                alignItems="center"
                                                sx={{ mt: 1.5 }}
                                                role="status"
                                                aria-live="polite"
                                                aria-label="Assistant response is still streaming"
                                            >
                                                <CircularProgress size={14} thickness={6} />
                                                <Typography
                                                    variant="caption"
                                                    color="text.secondary"
                                                    sx={{ letterSpacing: '0.12em', textTransform: 'uppercase' }}
                                                >
                                                    Streaming response
                                                </Typography>
                                                <Box sx={{ width: 132 }}>
                                                    <LinearProgress />
                                                </Box>
                                            </Stack>
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
                                                    display: 'grid',
                                                    gap: 2,
                                                    gridTemplateColumns: { xs: '1fr', md: '1fr 1fr' },
                                                }}
                                            >
                                                <TextField
                                                    label="Name"
                                                    value={visitorName}
                                                    onChange={(e) => setVisitorName(e.target.value)}
                                                    required
                                                    fullWidth
                                                />
                                                <TextField
                                                    label="Email"
                                                    type="email"
                                                    value={visitorEmail}
                                                    onChange={(e) => setVisitorEmail(e.target.value)}
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
                                            onChange={(e) => setMessageText(e.target.value)}
                                            onKeyDown={handleKeyDown}
                                            required
                                            fullWidth
                                        />

                                        {error ? <Alert severity="error">{error}</Alert> : null}

                                        <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
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
                                        direction={{ xs: 'column', sm: 'row' }}
                                        alignItems={{ xs: 'flex-start', sm: 'center' }}
                                        justifyContent="space-between"
                                        spacing={1}
                                        sx={{ mb: 1 }}
                                    >
                                        <Typography variant="h5">Your Chats</Typography>
                                        <Typography
                                            variant="caption"
                                            color="text.secondary"
                                            sx={{ textTransform: 'uppercase', letterSpacing: '0.14em' }}
                                        >
                                            Private to this browser
                                        </Typography>
                                    </Stack>

                                    {history.length > 0 ? (
                                        <List disablePadding>
                                            {history.map((item) => (
                                                <ListItemButton
                                                    key={item.handle}
                                                    selected={item.is_current}
                                                    onClick={() => handleSwitch(item.handle)}
                                                    sx={{
                                                        border: '1px solid',
                                                        borderColor: item.is_current
                                                            ? 'primary.main'
                                                            : 'divider',
                                                        mb: 1,
                                                    }}
                                                >
                                                    <ListItemText
                                                        primary={item.label}
                                                        secondary={item.updated_at}
                                                        secondaryTypographyProps={{
                                                            sx: {
                                                                textTransform: 'uppercase',
                                                                letterSpacing: '0.08em',
                                                                fontSize: '0.7rem',
                                                            },
                                                        }}
                                                    />
                                                    {item.is_current ? (
                                                        <Typography
                                                            variant="caption"
                                                            color="primary"
                                                            sx={{ textTransform: 'uppercase', letterSpacing: '0.12em' }}
                                                        >
                                                            Current
                                                        </Typography>
                                                    ) : null}
                                                </ListItemButton>
                                            ))}
                                        </List>
                                    ) : (
                                        <Typography variant="body2" color="text.secondary">
                                            No saved chats in this browser yet. Start a message to create a
                                            private thread.
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
                                        <Typography variant="body2" color="text.secondary">
                                            {bot.is_public ? 'Public bot' : 'Restricted bot'}
                                        </Typography>
                                        <Typography variant="body2" color="text.secondary">
                                            {bot.require_visitor_identity
                                                ? 'Name and email are required before the first guest message.'
                                                : 'No guest identity is required by this bot.'}
                                        </Typography>
                                        <Typography variant="body2" color="text.secondary">
                                            Only chats created in this browser are listed here.
                                        </Typography>
                                    </Stack>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardContent>
                                    <Typography variant="h5" sx={{ mb: 1 }}>
                                        Prompt Notes
                                    </Typography>
                                    <Typography variant="body2" color="text.secondary">
                                        The conversation is saved and can contribute new insights to AI
                                        Memory for this bot.
                                    </Typography>
                                </CardContent>
                            </Card>
                        </Stack>
                    )}
                </Stack>
            </Box>
        </>
    );
}
