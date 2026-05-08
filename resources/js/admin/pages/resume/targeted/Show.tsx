import { useState, useRef, useEffect, useCallback } from "react";
import { Head, Link as InertiaLink, router, useForm } from "@inertiajs/react";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import IconButton from "@mui/material/IconButton";
import Tab from "@mui/material/Tab";
import Tabs from "@mui/material/Tabs";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import ChatIcon from "@mui/icons-material/Chat";
import EditIcon from "@mui/icons-material/Edit";
import InfoIcon from "@mui/icons-material/Info";
import PictureAsPdfIcon from "@mui/icons-material/PictureAsPdf";
import StickyNote2Icon from "@mui/icons-material/StickyNote2";
import StatusChip from "../../../components/StatusChip";
import AdminLayout from "../../../layouts/AdminLayout";
import PageHeader from "../../../components/PageHeader";
import ChatMessageBubble from "../../../components/ChatMessageBubble";
import ConfirmDialog from "../../../components/ConfirmDialog";
import useConfirmDialog from "../../../hooks/useConfirmDialog";
import UsageChip from "../../../components/UsageChip";
import type {
    Conversation,
    CoverLetter,
    Message,
    TargetedResume,
} from "../../../types";
import BuilderStatusCard from "./BuilderStatusCard";
import TargetedBuilderStatusBar from "./TargetedBuilderStatusBar";

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
        applied_at: targetedResume?.applied_at || "",
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
                    {
                        role: "user",
                        content: text,
                        created_at: new Date().toISOString(),
                    },
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
                        {
                            role: "assistant",
                            content: accumulated,
                            created_at: new Date().toISOString(),
                        },
                    ]);
                }
            } catch (err) {
                setMessages((prev) => [
                    ...prev,
                    {
                        role: "assistant",
                        content: `**Error:** ${(err as Error).message}`,
                        created_at: new Date().toISOString(),
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
    const canFinalizeCoverLetter = latestCoverLetterContent !== null;

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

    const handleApplied = () => {
        confirm(
            "Mark this job as applied?",
            () => {
                router.post(
                    `/admin/resume/targeted-builder/${conversation.id}/applied`,
                );
            },
            { confirmLabel: "Applied", confirmColor: "success" },
        );
    };

    const handleMetadataSave = (e: React.FormEvent) => {
        e.preventDefault();
        metadataForm.put(
            `/admin/resume/targeted-builder/${conversation.id}/metadata`,
        );
    };

    const companyName: string =
        targetedResume?.company_name ||
        (conversation.context?.company_name as string) ||
        "Conversation";
    const position: string =
        targetedResume?.position ||
        (conversation.context?.job_title as string) ||
        "";
    const pageTitle: string =
        conversation?.title ??
        (position ? `${companyName} - ${position}` : companyName);

    return (
        <AdminLayout>
            <Head title={`${pageTitle} | Targeted Resumes`} />
            <PageHeader title={pageTitle} />

            <TargetedBuilderStatusBar
                conversation={conversation}
                targetedResume={targetedResume}
                coverLetter={coverLetter}
            />

            <Box
                sx={{
                    position: "sticky",
                    top: { xs: 56, md: 64 },
                    zIndex: 10,
                    mb: 2,
                    display: "flex",
                    alignItems: "center",
                    gap: 1,
                    bgcolor: "background.paper",
                    borderBottom: 1,
                    borderColor: "divider",
                }}
            >
                <IconButton
                    component={InertiaLink}
                    href="/admin/resume/targeted-builder"
                    aria-label="Back to Targeted Resumes"
                    size="small"
                    sx={{ ml: 0.5 }}
                >
                    <ArrowBackIcon fontSize="small" />
                </IconButton>
                <Tabs
                    value={activeTab}
                    onChange={(_, v) => setActiveTab(v)}
                    aria-label="Targeted resume tabs"
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
                <Box
                    sx={{
                        display: "flex",
                        alignItems: "center",
                        gap: 1,
                        pr: 1,
                    }}
                >
                    <Button
                        size="small"
                        color="success"
                        variant="outlined"
                        onClick={handleApplied}
                        disabled={
                            !targetedResume ||
                            targetedResume.status === "applied"
                        }
                    >
                        {targetedResume?.status === "applied"
                            ? "Applied"
                            : "Mark Applied"}
                    </Button>
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
                </Box>
            </Box>

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

            {activeTab === 0 && (
                <>
                    <Box
                        sx={{
                            display: "grid",
                            gap: 2,
                            gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
                            mb: 2,
                        }}
                    >
                        <BuilderStatusCard
                            label="Resume"
                            isFinalized={!!targetedResume}
                            color="success"
                            canFinalize={canFinalizeResume}
                            isFinalizing={isFinalizing}
                            hasUpdate={hasNewerResume}
                            finalizeTitle={
                                !latestResumeData
                                    ? "Finalize is available after the assistant returns a tailored resume block"
                                    : !canFinalizeResume
                                      ? "Resume already finalized with the latest content"
                                      : hasNewerResume
                                        ? "Update resume and regenerate documents"
                                        : "Save the tailored resume and generate documents"
                            }
                            onFinalize={handleFinalizeResume}
                            caption={
                                targetedResume
                                    ? `${targetedResume.company_name} — ${targetedResume.position}${targetedResume.fit_score != null ? ` · Fit: ${targetedResume.fit_score}%` : ""}`
                                    : undefined
                            }
                            extraActions={
                                targetedResume ? (
                                    <>
                                        {targetedResume.docx_path && (
                                            <IconButton
                                                size="small"
                                                component="a"
                                                href={`/admin/resume/targeted-resume/${targetedResume.id}/StickyNote2/docx`}
                                                title="Download resume DOCX"
                                                color="success"
                                            >
                                                <StickyNote2Icon fontSize="small" />
                                            </IconButton>
                                        )}
                                        {targetedResume.pdf_path && (
                                            <IconButton
                                                size="small"
                                                component="a"
                                                href={`/admin/resume/targeted-resume/${targetedResume.id}/StickyNote2/pdf`}
                                                title="Download resume PDF"
                                                color="success"
                                            >
                                                <PictureAsPdfIcon fontSize="small" />
                                            </IconButton>
                                        )}
                                    </>
                                ) : undefined
                            }
                        />
                        <BuilderStatusCard
                            label="Cover Letter"
                            isFinalized={!!coverLetter}
                            color="secondary"
                            canFinalize={canFinalizeCoverLetter}
                            isFinalizing={isFinalizingCoverLetter}
                            hasUpdate={!!coverLetter}
                            finalizeTitle={
                                !latestCoverLetterContent
                                    ? "Finalize is available after the assistant returns a cover letter block"
                                    : coverLetter
                                      ? "Update the cover letter from the latest chat content"
                                      : "Extract and save the cover letter from the conversation"
                            }
                            onFinalize={handleFinalizeCoverLetter}
                            caption={
                                coverLetter
                                    ? `${coverLetter.company_name ?? ""} ${coverLetter.position ?? ""}`.trim() ||
                                      "Cover letter saved"
                                    : undefined
                            }
                            extraActions={
                                coverLetter ? (
                                    <>
                                        {coverLetter.docx_path && (
                                            <IconButton
                                                size="small"
                                                component="a"
                                                href={`/admin/cover-letters/${coverLetter.id}/StickyNote2/docx`}
                                                title="Download cover letter DOCX"
                                                color="secondary"
                                            >
                                                <StickyNote2Icon fontSize="small" />
                                            </IconButton>
                                        )}
                                        {coverLetter.pdf_path && (
                                            <IconButton
                                                size="small"
                                                component="a"
                                                href={`/admin/cover-letters/${coverLetter.id}/StickyNote2/pdf`}
                                                title="Download cover letter PDF"
                                                color="secondary"
                                            >
                                                <PictureAsPdfIcon fontSize="small" />
                                            </IconButton>
                                        )}
                                        <IconButton
                                            size="small"
                                            component={InertiaLink}
                                            href={`/admin/cover-letters/${coverLetter.id}`}
                                            title="Edit cover letter"
                                        >
                                            <EditIcon fontSize="small" />
                                        </IconButton>
                                    </>
                                ) : undefined
                            }
                        />
                    </Box>
                    <Card>
                        <CardContent sx={{ p: 0, "&:last-child": { pb: 0 } }}>
                            <Box
                                sx={{
                                    height: "60vh",
                                    overflowY: "auto",
                                    p: 2,
                                    display: "flex",
                                    flexDirection: "column",
                                    gap: 2,
                                    code: { textWrapMode: "wrap" },
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
                                    <ChatMessageBubble
                                        key={idx}
                                        role={msg.role}
                                        content={msg.content}
                                        variant="chat"
                                        sentAt={msg.created_at ?? null}
                                    />
                                ))}
                                {isStreaming && streamingContent && (
                                    <ChatMessageBubble
                                        role="assistant"
                                        content={streamingContent}
                                        variant="chat"
                                        isStreaming
                                    />
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
                            <Box sx={{ mb: 2 }}>
                                <Typography
                                    variant="caption"
                                    color="text.secondary"
                                    sx={{ display: "block" }}
                                >
                                    AI System
                                </Typography>
                                <Typography variant="body2">
                                    {conversation.ai_system_name || "Unknown"}
                                </Typography>
                            </Box>
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
                            <TextField
                                label="Applied Date"
                                type="date"
                                size="small"
                                fullWidth
                                value={metadataForm.data.applied_at}
                                onChange={(e) =>
                                    metadataForm.setData(
                                        "applied_at",
                                        e.target.value,
                                    )
                                }
                                error={!!metadataForm.errors.applied_at}
                                helperText={
                                    metadataForm.errors.applied_at ||
                                    "Leave blank if you have not applied yet."
                                }
                                slotProps={{ inputLabel: { shrink: true } }}
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
                        <Box
                            sx={{
                                mt: 3,
                                pt: 3,
                                borderTop: 1,
                                borderColor: "divider",
                            }}
                        >
                            <Typography variant="subtitle2" gutterBottom>
                                Chat Usage
                            </Typography>
                            <UsageChip usage={conversation.usage} />
                        </Box>
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
                                    component={InertiaLink}
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
