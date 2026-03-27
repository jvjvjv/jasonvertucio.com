import { useState, useRef, useEffect, useCallback } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Tab from '@mui/material/Tab';
import Tabs from '@mui/material/Tabs';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { marked } from 'marked';
import AdminLayout from '../../../layouts/AdminLayout';
import PageHeader from '../../../components/PageHeader';
import type { Conversation, CoverLetter, Message, TargetedResume } from '../../../types';
import { markdownSx } from '../../../utils/markdownSx';
import ConfirmDialog from '../../../components/ConfirmDialog';
import StatusChip from '../../../components/StatusChip';
import useConfirmDialog from '../../../hooks/useConfirmDialog';

interface ShowProps {
    conversation: Conversation;
    messages: Message[];
    targetedResume: TargetedResume | null;
    coverLetter: CoverLetter | null;
    shouldAutoStart: boolean;
}

export default function Show({
    conversation,
    messages: initialMessages,
    targetedResume,
    coverLetter,
    shouldAutoStart,
}: ShowProps) {
    const [activeTab, setActiveTab] = useState(0);
    const [messages, setMessages] = useState<Message[]>(initialMessages);
    const [userInput, setUserInput] = useState('');
    const [isStreaming, setIsStreaming] = useState(false);
    const [streamingContent, setStreamingContent] = useState('');
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const hasAutoStarted = useRef(false);

    const metadataForm = useForm({
        title: conversation.title || '',
        company_name: targetedResume?.company_name || conversation.context?.company_name || '',
        job_title: targetedResume?.position || conversation.context?.job_title || '',
    });

    const csrfToken =
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

    const scrollToBottom = useCallback(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, []);

    useEffect(() => {
        scrollToBottom();
    }, [messages, streamingContent, scrollToBottom]);

    const sendMessage = useCallback(
        async (messageText?: string) => {
            const text = messageText ?? userInput.trim();
            if (!text && !shouldAutoStart) return;

            if (text) {
                setMessages((prev) => [...prev, { role: 'user', content: text }]);
                setUserInput('');
            }

            setIsStreaming(true);
            setStreamingContent('');

            try {
                const response = await fetch(
                    `/admin/resume/targeted-builder/${conversation.id}/chat`,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'text/event-stream',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ message: text || null }),
                    },
                );

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const reader = response.body?.getReader();
                if (!reader) throw new Error('No reader available');

                const decoder = new TextDecoder();
                let accumulated = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    const chunk = decoder.decode(value, { stream: true });
                    const lines = chunk.split('\n');

                    for (const line of lines) {
                        if (!line.startsWith('data: ')) continue;
                        const jsonStr = line.slice(6);
                        if (!jsonStr.trim()) continue;

                        try {
                            const event = JSON.parse(jsonStr);

                            if (event.type === 'content_block_delta' && event.delta?.text) {
                                accumulated += event.delta.text;
                                setStreamingContent(accumulated);
                            } else if (event.type === 'error') {
                                accumulated += `\n\n**Error:** ${event.message || 'Unknown error'}`;
                                setStreamingContent(accumulated);
                            }
                        } catch {
                            // Skip malformed JSON lines
                        }
                    }
                }

                if (accumulated) {
                    setMessages((prev) => [...prev, { role: 'assistant', content: accumulated }]);
                }
            } catch (err) {
                setMessages((prev) => [
                    ...prev,
                    { role: 'assistant', content: `**Error:** ${(err as Error).message}` },
                ]);
            } finally {
                setIsStreaming(false);
                setStreamingContent('');
            }
        },
        [userInput, conversation.id, csrfToken, shouldAutoStart],
    );

    // Auto-start initial analysis
    useEffect(() => {
        if (shouldAutoStart && !hasAutoStarted.current) {
            hasAutoStarted.current = true;
            sendMessage('');
        }
    }, [shouldAutoStart, sendMessage]);

    const handleKeyDown = (e: React.KeyboardEvent<HTMLDivElement>) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            sendMessage();
        }
    };

    const { dialogProps, confirm } = useConfirmDialog();

    const handlePass = () => {
        confirm('Mark this opportunity as passed?', () => {
            router.post(`/admin/resume/targeted-builder/${conversation.id}/pass`);
        }, { confirmLabel: 'Pass', confirmColor: 'warning' });
    };

    const handleMetadataSave = (e: React.FormEvent) => {
        e.preventDefault();
        metadataForm.put(`/admin/resume/targeted-builder/${conversation.id}/metadata`);
    };

    const companyName = targetedResume?.company_name || conversation.context?.company_name || 'Conversation';
    const position = targetedResume?.position || conversation.context?.job_title || '';
    const pageTitle = position ? `${companyName} — ${position}` : companyName;

    return (
        <AdminLayout>
            <Head title={pageTitle} />
            <PageHeader
                title={pageTitle}
                backHref="/admin/resume/targeted-builder"
                backLabel="Back to Targeted Resumes"
            />

            {/* Status bar */}
            <Box
                sx={{
                    display: "flex",
                    gap: 2,
                    mb: 2,
                    alignItems: "center",
                    flexWrap: "wrap",
                }}
            >
                <StatusChip
                    status={
                        targetedResume?.status === "finalized"
                            ? "finalized"
                            : conversation.status
                    }
                />
                {conversation.ai_system_name && (
                    <Typography variant="caption" color="text.secondary">
                        AI: {conversation.ai_system_name}
                    </Typography>
                )}
                {targetedResume?.fit_score != null && (
                    <Typography variant="caption" color="text.secondary">
                        Fit: {targetedResume.fit_score}%
                    </Typography>
                )}
                <Box sx={{ flexGrow: 1 }} />
                {conversation.status === "active" && (
                    <Button
                        size="small"
                        color="warning"
                        variant="outlined"
                        onClick={handlePass}
                    >
                        Pass
                    </Button>
                )}
                {targetedResume && (
                    <Box sx={{ display: "flex", gap: 1 }}>
                        {targetedResume.docx_path && (
                            <Button
                                size="small"
                                variant="outlined"
                                component="a"
                                href={`/admin/resume/targeted-resume/${targetedResume.id}/download/docx`}
                            >
                                DOCX
                            </Button>
                        )}
                        {targetedResume.pdf_path && (
                            <Button
                                size="small"
                                variant="outlined"
                                component="a"
                                href={`/admin/resume/targeted-resume/${targetedResume.id}/download/pdf`}
                            >
                                PDF
                            </Button>
                        )}
                    </Box>
                )}
            </Box>

            <Tabs
                value={activeTab}
                onChange={(_, v) => setActiveTab(v)}
                sx={{ mb: 2 }}
            >
                <Tab label="Chat" />
                <Tab label="Details" />
            </Tabs>

            {/* Chat Tab */}
            {activeTab === 0 && (
                <Card>
                    <CardContent sx={{ p: 0, "&:last-child": { pb: 0 } }}>
                        {/* Messages */}
                        <Box
                            sx={{
                                height: "60vh",
                                overflowY: "auto",
                                p: 2,
                                display: "flex",
                                flexDirection: "column",
                                gap: 2,
                                code: {
                                    textWrapMode: "wrap",
                                },
                            }}
                        >
                            {messages.length === 0 && !isStreaming && (
                                <Typography
                                    color="text.secondary"
                                    align="center"
                                    sx={{ py: 4 }}
                                >
                                    {shouldAutoStart
                                        ? "Starting analysis..."
                                        : "Send a message to begin the conversation."}
                                </Typography>
                            )}
                            {messages.map((msg, idx) => (
                                <Box
                                    key={idx}
                                    sx={{
                                        alignSelf:
                                            msg.role === "user"
                                                ? "flex-end"
                                                : "flex-start",
                                        maxWidth: "80%",
                                        bgcolor:
                                            msg.role === "user"
                                                ? "primary.main"
                                                : "grey.100",
                                        color:
                                            msg.role === "user"
                                                ? "primary.contrastText"
                                                : "text.primary",
                                        borderRadius: 2,
                                        px: 2,
                                        py: 1,
                                        ...(msg.role !== "user" && markdownSx),
                                    }}
                                >
                                    {msg.role === "user" ? (
                                        <Typography
                                            variant="body2"
                                            sx={{
                                                whiteSpace: "pre-wrap",
                                                wordBreak: "break-word",
                                            }}
                                        >
                                            {msg.content}
                                        </Typography>
                                    ) : (
                                        <div
                                            style={{ wordBreak: "break-word" }}
                                            dangerouslySetInnerHTML={{
                                                __html: marked.parse(
                                                    msg.content,
                                                    { breaks: true },
                                                ) as string,
                                            }}
                                        />
                                    )}
                                </Box>
                            ))}
                            {isStreaming && streamingContent && (
                                <Box
                                    sx={{
                                        alignSelf: "flex-start",
                                        maxWidth: "80%",
                                        bgcolor: "grey.100",
                                        borderRadius: 2,
                                        px: 2,
                                        py: 1,
                                        ...markdownSx,
                                    }}
                                >
                                    <div
                                        style={{ wordBreak: "break-word" }}
                                        dangerouslySetInnerHTML={{
                                            __html: marked.parse(
                                                streamingContent,
                                                { breaks: true },
                                            ) as string,
                                        }}
                                    />
                                </Box>
                            )}
                            {isStreaming && !streamingContent && (
                                <Typography
                                    variant="body2"
                                    color="text.secondary"
                                >
                                    AI is thinking...
                                </Typography>
                            )}
                            <div ref={messagesEndRef} />
                        </Box>

                        {/* Input */}
                        <Box
                            sx={{
                                p: 2,
                                borderTop: 1,
                                borderColor: "divider",
                                display: "flex",
                                gap: 1,
                            }}
                        >
                            <TextField
                                fullWidth
                                size="small"
                                multiline
                                maxRows={4}
                                placeholder="Type a message... (Ctrl+Enter to send)"
                                value={userInput}
                                onChange={(e) => setUserInput(e.target.value)}
                                onKeyDown={handleKeyDown}
                                disabled={isStreaming}
                            />
                            <Button
                                variant="contained"
                                onClick={() => sendMessage()}
                                disabled={isStreaming || !userInput.trim()}
                                sx={{ alignSelf: "flex-end" }}
                            >
                                Send
                            </Button>
                        </Box>
                    </CardContent>
                </Card>
            )}

            {/* Details Tab */}
            {activeTab === 1 && (
                <Card>
                    <CardContent>
                        <Typography variant="h6" gutterBottom>
                            Conversation Details
                        </Typography>
                        <Box component="form" onSubmit={handleMetadataSave}>
                            <TextField
                                label="Title"
                                size="small"
                                fullWidth
                                value={metadataForm.data.title}
                                onChange={(e) =>
                                    metadataForm.setData(
                                        "title",
                                        e.target.value,
                                    )
                                }
                                error={!!metadataForm.errors.title}
                                helperText={metadataForm.errors.title}
                                sx={{ mb: 2 }}
                            />
                            <TextField
                                label="Company Name"
                                size="small"
                                fullWidth
                                value={metadataForm.data.company_name}
                                onChange={(e) =>
                                    metadataForm.setData(
                                        "company_name",
                                        e.target.value,
                                    )
                                }
                                error={!!metadataForm.errors.company_name}
                                helperText={metadataForm.errors.company_name}
                                sx={{ mb: 2 }}
                            />
                            <TextField
                                label="Job Title"
                                size="small"
                                fullWidth
                                value={metadataForm.data.job_title}
                                onChange={(e) =>
                                    metadataForm.setData(
                                        "job_title",
                                        e.target.value,
                                    )
                                }
                                error={!!metadataForm.errors.job_title}
                                helperText={metadataForm.errors.job_title}
                                sx={{ mb: 3 }}
                            />
                            <Box
                                sx={{
                                    display: "flex",
                                    justifyContent: "flex-end",
                                }}
                            >
                                <Button
                                    type="submit"
                                    variant="contained"
                                    disabled={metadataForm.processing}
                                >
                                    Save Details
                                </Button>
                            </Box>
                        </Box>

                        {/* Resume & Cover Letter Status */}
                        {targetedResume && (
                            <Box
                                sx={{
                                    mt: 4,
                                    pt: 3,
                                    borderTop: 1,
                                    borderColor: "divider",
                                }}
                            >
                                <Typography variant="subtitle2" gutterBottom>
                                    Targeted Resume
                                </Typography>
                                <Box
                                    sx={{
                                        display: "flex",
                                        gap: 2,
                                        alignItems: "center",
                                        mb: 1,
                                    }}
                                >
                                    <StatusChip
                                        status={targetedResume.status}
                                    />
                                    <Typography variant="body2">
                                        {targetedResume.company_name} —{" "}
                                        {targetedResume.position}
                                    </Typography>
                                </Box>
                                <Box sx={{ display: "flex", gap: 1, mt: 1 }}>
                                    <Button
                                        size="small"
                                        variant="outlined"
                                        onClick={() =>
                                            router.post(
                                                `/admin/resume/targeted-resume/${targetedResume.id}/regenerate`,
                                            )
                                        }
                                    >
                                        Regenerate Docs
                                    </Button>
                                </Box>
                            </Box>
                        )}
                        {coverLetter && (
                            <Box
                                sx={{
                                    mt: 3,
                                    pt: 3,
                                    borderTop: 1,
                                    borderColor: "divider",
                                }}
                            >
                                <Typography variant="subtitle2" gutterBottom>
                                    Cover Letter
                                </Typography>
                                <Button
                                    component={Link}
                                    href={`/admin/cover-letters/${coverLetter.id}`}
                                    size="small"
                                    variant="outlined"
                                >
                                    View Cover Letter
                                </Button>
                            </Box>
                        )}
                    </CardContent>
                </Card>
            )}
            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
