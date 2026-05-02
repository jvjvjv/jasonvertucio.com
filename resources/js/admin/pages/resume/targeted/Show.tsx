import { useState, useRef, useEffect, useCallback } from "react";
import { Head, Link, router, useForm } from "@inertiajs/react";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Tab from "@mui/material/Tab";
import Tabs from "@mui/material/Tabs";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import AutoAwesomeIcon from "@mui/icons-material/AutoAwesome";
import AutoFixHighIcon from "@mui/icons-material/AutoFixHigh";
import { marked } from "marked";
import AdminLayout from "../../../layouts/AdminLayout";
import PageHeader from "../../../components/PageHeader";
import type {
    Conversation,
    CoverLetter,
    Message,
    TargetedResume,
} from "../../../types";
import { markdownSx } from "../../../utils/markdownSx";
import ConfirmDialog from "../../../components/ConfirmDialog";
import StatusChip from "../../../components/StatusChip";
import useConfirmDialog from "../../../hooks/useConfirmDialog";

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
    const [userInput, setUserInput] = useState("");
    const [isStreaming, setIsStreaming] = useState(false);
    const [streamingContent, setStreamingContent] = useState("");
    const [isFinalizing, setIsFinalizing] = useState(false);
    const [finalizeError, setFinalizeError] = useState<string | null>(null);
    const [isFinalizingCoverLetter, setIsFinalizingCoverLetter] =
        useState(false);
    const [finalizeCoverLetterError, setFinalizeCoverLetterError] = useState<
        string | null
    >(null);
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const hasAutoStarted = useRef(false);

    const metadataForm = useForm({
        title: conversation.title || "",
        company_name:
            targetedResume?.company_name ||
            conversation.context?.company_name ||
            "",
        job_title:
            targetedResume?.position || conversation.context?.job_title || "",
    });

    const csrfToken =
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? "";

    const scrollToBottom = useCallback(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
    }, []);

    useEffect(() => {
        scrollToBottom();
    }, [messages, streamingContent, scrollToBottom]);

    const sendMessage = useCallback(
        async (messageText?: string) => {
            const text = messageText ?? userInput.trim();
            if (!text && !shouldAutoStart) return;

            if (text) {
                setMessages((prev) => [
                    ...prev,
                    { role: "user", content: text },
                ]);
                setUserInput("");
            }

            setIsStreaming(true);
            setStreamingContent("");

            try {
                const response = await fetch(
                    `/admin/resume/targeted-builder/${conversation.id}/chat`,
                    {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "text/event-stream",
                            "X-CSRF-TOKEN": csrfToken,
                        },
                        body: JSON.stringify({ message: text || null }),
                    },
                );

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const reader = response.body?.getReader();
                if (!reader) throw new Error("No reader available");

                const decoder = new TextDecoder();
                let accumulated = "";

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    const chunk = decoder.decode(value, { stream: true });
                    const lines = chunk.split("\n");

                    for (const line of lines) {
                        if (!line.startsWith("data: ")) continue;
                        const jsonStr = line.slice(6);
                        if (!jsonStr.trim()) continue;

                        try {
                            const event = JSON.parse(jsonStr);

                            if (
                                event.type === "content_block_delta" &&
                                event.delta?.text
                            ) {
                                accumulated += event.delta.text;
                                setStreamingContent(accumulated);
                            } else if (event.type === "error") {
                                accumulated += `\n\n**Error:** ${event.message || "Unknown error"}`;
                                setStreamingContent(accumulated);
                            }
                        } catch {
                            // Skip malformed JSON lines
                        }
                    }
                }

                if (accumulated) {
                    setMessages((prev) => [
                        ...prev,
                        { role: "assistant", content: accumulated },
                    ]);
                }
            } catch (err) {
                setMessages((prev) => [
                    ...prev,
                    {
                        role: "assistant",
                        content: `**Error:** ${(err as Error).message}`,
                    },
                ]);
            } finally {
                setIsStreaming(false);
                setStreamingContent("");
            }
        },
        [userInput, conversation.id, csrfToken, shouldAutoStart],
    );

    // Auto-start initial analysis
    useEffect(() => {
        if (shouldAutoStart && !hasAutoStarted.current) {
            hasAutoStarted.current = true;
            sendMessage("");
        }
    }, [shouldAutoStart, sendMessage]);

    // --- Resume/Cover Letter parsing helpers ---

    function parseTailoredResumeBlock(raw: string): {
        title: string | null;
        content: string;
    } {
        const normalized = raw.trim().replace(/\r\n/g, "\n");
        const titleMatch = normalized.match(/^Title:\s*(.+)\n+/i);
        if (!titleMatch) return { title: null, content: normalized };
        return {
            title: titleMatch[1].trim(),
            content: normalized.replace(/^Title:\s*.+\n+/i, "").trim(),
        };
    }

    function getLatestTailoredResumeData(msgs: Message[]) {
        for (let i = msgs.length - 1; i >= 0; i--) {
            const msg = msgs[i];
            if (msg.role !== "assistant") continue;
            const contentMatch = msg.content.match(
                /```tailored(?:-|\s+)resume\s*\n([\s\S]*?)```/i,
            );
            if (!contentMatch) continue;
            const parsed = parseTailoredResumeBlock(contentMatch[1]);
            let fitScore: number | null = null;
            const scoreMatch = msg.content.match(
                /(?:fit score|score)[:\s]*(\d{1,3})(?:\s*[\/%]|\s*out of\s*100)?/i,
            );
            if (scoreMatch) {
                const s = parseInt(scoreMatch[1]);
                if (s <= 100) fitScore = s;
            }
            return { rawContent: contentMatch[1].trim(), ...parsed, fitScore };
        }
        return null;
    }

    function getLatestCoverLetterContent(msgs: Message[]): string | null {
        for (let i = msgs.length - 1; i >= 0; i--) {
            const msg = msgs[i];
            if (msg.role !== "assistant") continue;
            const m = msg.content.match(
                /```cover[-\s]letter\s*\n([\s\S]*?)```/i,
            );
            if (m) return m[1].trim();
        }
        return null;
    }

    const latestResumeData = getLatestTailoredResumeData(messages);
    const latestCoverLetterContent = getLatestCoverLetterContent(messages);

    const hasNewerResume = (() => {
        if (!targetedResume || !latestResumeData) return false;
        const normalize = (s: string | null | undefined) =>
            (s || "").trim().replace(/\r\n/g, "\n");
        return (
            normalize(latestResumeData.title) !==
                normalize(targetedResume.tailored_title) ||
            normalize(latestResumeData.content) !==
                normalize(targetedResume.tailored_content)
        );
    })();

    const canFinalizeResume =
        latestResumeData !== null && (!targetedResume || hasNewerResume);
    const canFinalizeCoverLetter =
        latestCoverLetterContent !== null && !coverLetter;

    const finalizeResumeLabel = !targetedResume
        ? "Finalize Resume"
        : hasNewerResume
          ? "Update Finalized Resume"
          : "Resume Finalized";

    const handleFinalizeResume = async () => {
        if (!canFinalizeResume || !latestResumeData) return;
        setIsFinalizing(true);
        setFinalizeError(null);
        try {
            const response = await fetch(
                `/admin/resume/targeted-builder/${conversation.id}/finalize`,
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": csrfToken,
                        Accept: "application/json",
                    },
                    body: JSON.stringify({
                        tailored_content: latestResumeData.rawContent,
                        fit_score: latestResumeData.fitScore,
                    }),
                },
            );
            const data = await response.json();
            if (!response.ok) {
                setFinalizeError(
                    data.message || "Failed to save targeted resume.",
                );
                return;
            }
            window.location.reload();
        } catch {
            setFinalizeError("Network error. Please try again.");
        } finally {
            setIsFinalizing(false);
        }
    };

    const handleFinalizeCoverLetter = async () => {
        if (!canFinalizeCoverLetter || !latestCoverLetterContent) return;
        setIsFinalizingCoverLetter(true);
        setFinalizeCoverLetterError(null);
        try {
            const response = await fetch(
                `/admin/resume/targeted-builder/${conversation.id}/finalize-cover-letter`,
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": csrfToken,
                        Accept: "application/json",
                    },
                    body: JSON.stringify({
                        cover_letter_content: latestCoverLetterContent,
                    }),
                },
            );
            const data = await response.json();
            if (!response.ok) {
                setFinalizeCoverLetterError(
                    data.message || "Failed to save cover letter.",
                );
                return;
            }
            window.location.reload();
        } catch {
            setFinalizeCoverLetterError("Network error. Please try again.");
        } finally {
            setIsFinalizingCoverLetter(false);
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLDivElement>) => {
        if ((e.ctrlKey || e.metaKey) && e.key === "Enter") {
            e.preventDefault();
            sendMessage();
        }
    };

    const { dialogProps, confirm } = useConfirmDialog();

    const handlePass = () => {
        confirm(
            "Mark this opportunity as passed?",
            () => {
                router.post(
                    `/admin/resume/targeted-builder/${conversation.id}/pass`,
                );
            },
            { confirmLabel: "Pass", confirmColor: "warning" },
        );
    };

    const handleMetadataSave = (e: React.FormEvent) => {
        e.preventDefault();
        metadataForm.put(
            `/admin/resume/targeted-builder/${conversation.id}/metadata`,
        );
    };

    const companyName =
        targetedResume?.company_name ||
        conversation.context?.company_name ||
        "Conversation";
    const position =
        targetedResume?.position || conversation.context?.job_title || "";
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

                <Box sx={{ display: "flex", gap: 1, flexWrap: "wrap" }}>
                    <Button
                        size="small"
                        variant="contained"
                        color="success"
                        disabled={isFinalizing || !canFinalizeResume}
                        onClick={handleFinalizeResume}
                        title={
                            !latestResumeData
                                ? "Finalize is available after the assistant returns a tailored resume block"
                                : !targetedResume
                                  ? "Extract and save the tailored resume from the conversation"
                                  : hasNewerResume
                                    ? "Extract and save the latest tailored resume"
                                    : "Resume already finalized with the latest content"
                        }
                    >
                        {isFinalizing ? "Saving..." : finalizeResumeLabel}
                    </Button>
                    {targetedResume && (
                        <>
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
                        </>
                    )}
                    {coverLetter && (
                        <>
                            {coverLetter.docx_path && (
                                <Button
                                    size="small"
                                    variant="outlined"
                                    component="a"
                                    href={`/admin/cover-letters/${coverLetter.id}/download/docx`}
                                >
                                    CL DOCX
                                </Button>
                            )}
                            {coverLetter.pdf_path && (
                                <Button
                                    size="small"
                                    variant="outlined"
                                    component="a"
                                    href={`/admin/cover-letters/${coverLetter.id}/download/pdf`}
                                >
                                    CL PDF
                                </Button>
                            )}
                        </>
                    )}
                </Box>
            </Box>

            <Tabs
                value={activeTab}
                onChange={(_, v) => setActiveTab(v)}
                sx={{ mb: 2 }}
            >
                <Tab label="Chat" />
                <Tab label="Details" />
            </Tabs>

            {/* Finalize errors */}
            {(finalizeError || finalizeCoverLetterError) && (
                <Box
                    sx={{
                        mb: 2,
                        display: "flex",
                        flexDirection: "column",
                        gap: 1,
                    }}
                >
                    {finalizeError && (
                        <Alert severity="error">{finalizeError}</Alert>
                    )}
                    {finalizeCoverLetterError && (
                        <Alert severity="error">
                            {finalizeCoverLetterError}
                        </Alert>
                    )}
                </Box>
            )}

            {/* Chat Tab */}
            {activeTab === 0 && (
                <>
                    {/* Resume / Cover Letter status cards */}
                    <Box
                        sx={{
                            display: "grid",
                            gap: 2,
                            gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
                            mb: 2,
                        }}
                    >
                        <Card
                            variant="outlined"
                            sx={{
                                borderColor: targetedResume
                                    ? "success.light"
                                    : "divider",
                                bgcolor: targetedResume
                                    ? "success.50"
                                    : "background.paper",
                            }}
                        >
                            <CardContent
                                sx={{ py: 1.5, "&:last-child": { pb: 1.5 } }}
                            >
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
                                                targetedResume
                                                    ? "success.dark"
                                                    : "text.secondary"
                                            }
                                            sx={{
                                                display: "block",
                                                lineHeight: 1.5,
                                            }}
                                        >
                                            Resume
                                        </Typography>
                                        <Typography variant="subtitle2">
                                            {targetedResume
                                                ? "Finalized and ready"
                                                : "Not finalized yet"}
                                        </Typography>
                                    </Box>
                                    <Button
                                        size="small"
                                        variant="outlined"
                                        disabled={
                                            isFinalizing || !canFinalizeResume
                                        }
                                        onClick={() =>
                                            router.post(
                                                `/admin/resume/targeted-resume/${targetedResume?.id}/regenerate`,
                                            )
                                        }
                                    >
                                        {targetedResume?.docx_path ? (
                                            <AutoFixHighIcon />
                                        ) : (
                                            <AutoAwesomeIcon />
                                        )}
                                    </Button>
                                </Box>
                                <Typography
                                    variant="caption"
                                    color="text.secondary"
                                >
                                    {targetedResume &&
                                        `${targetedResume.company_name} — ${targetedResume.position}${targetedResume.fit_score != null ? ` · Fit: ${targetedResume.fit_score}%` : ""}`}
                                </Typography>
                                {<Box sx={{ mt: 1 }}></Box>}
                            </CardContent>
                        </Card>
                        <Card
                            variant="outlined"
                            sx={{
                                borderColor: coverLetter
                                    ? "primary.light"
                                    : "divider",
                                bgcolor: coverLetter
                                    ? "primary.50"
                                    : "background.paper",
                            }}
                        >
                            <CardContent
                                sx={{ py: 1.5, "&:last-child": { pb: 1.5 } }}
                            >
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
                                                coverLetter
                                                    ? "primary.dark"
                                                    : "text.secondary"
                                            }
                                            sx={{
                                                display: "block",
                                                lineHeight: 1.5,
                                            }}
                                        >
                                            Cover Letter
                                        </Typography>
                                        <Typography variant="subtitle2">
                                            {coverLetter
                                                ? "Finalized and ready"
                                                : "Not finalized yet"}
                                        </Typography>
                                    </Box>
                                    <Button
                                        size="small"
                                        variant="outlined"
                                        disabled={
                                            isFinalizingCoverLetter ||
                                            !canFinalizeCoverLetter
                                        }
                                        onClick={handleFinalizeCoverLetter}
                                        title={
                                            coverLetter
                                                ? "Cover letter already finalized"
                                                : !latestCoverLetterContent
                                                  ? "Finalize is available after the assistant returns a cover letter block"
                                                  : "Extract and save the cover letter from the conversation"
                                        }
                                    >
                                        {isFinalizingCoverLetter ? (
                                            "Saving..."
                                        ) : coverLetter ? (
                                            <AutoFixHighIcon />
                                        ) : (
                                            <AutoAwesomeIcon />
                                        )}
                                    </Button>
                                </Box>
                                <Typography
                                    variant="caption"
                                    color="text.secondary"
                                >
                                    {(coverLetter &&
                                        `${coverLetter.company_name ?? ""} ${coverLetter.position ?? ""}`.trim()) ||
                                        "Cover letter saved"}
                                </Typography>
                            </CardContent>
                        </Card>
                    </Box>
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
                                            ...(msg.role !== "user" &&
                                                markdownSx),
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
                                                style={{
                                                    wordBreak: "break-word",
                                                }}
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
                                    onChange={(e) =>
                                        setUserInput(e.target.value)
                                    }
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
                </>
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
