import {
    Head,
    Link as InertiaLink,
    router,
    useForm,
    usePage,
} from "@inertiajs/react";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import BackHandOutlinedIcon from "@mui/icons-material/BackHandOutlined";
import ChatIcon from "@mui/icons-material/Chat";
import DoneIcon from "@mui/icons-material/Done";
import EditIcon from "@mui/icons-material/Edit";
import InfoIcon from "@mui/icons-material/Info";
import OpenInNewIcon from "@mui/icons-material/OpenInNew";
import PictureAsPdfIcon from "@mui/icons-material/PictureAsPdf";
import StickyNote2Icon from "@mui/icons-material/StickyNote2";
import UpdateIcon from "@mui/icons-material/Update";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Divider from "@mui/material/Divider";
import FormControl from "@mui/material/FormControl";
import IconButton from "@mui/material/IconButton";
import InputLabel from "@mui/material/InputLabel";
import Link from "@mui/material/Link";
import MenuItem from "@mui/material/MenuItem";
import Select from "@mui/material/Select";
import Tab from "@mui/material/Tab";
import Tabs from "@mui/material/Tabs";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import BuilderStatusCard from "./BuilderStatusCard";
import TargetedBuilderStatusBar from "./TargetedBuilderStatusBar";

import type { ChatMessage } from "@/components/ChatInterface";
import type {
    Conversation,
    CoverLetter,
    Message,
    SharedProps,
    StatusUpdate,
    TargetedResume,
} from "@/types";
import type { SyntheticEvent } from "react";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import PageHeader from "@/admin/components/PageHeader";
import StatusChip from "@/admin/components/StatusChip";
import UsageChip from "@/admin/components/UsageChip";
import AdminLayout from "@/admin/layouts/AdminLayout";
import { api, ApiError } from "@/api";
import ChatInterface from "@/components/ChatInterface";
import ResponsiveButton from "@/components/ResponsiveButton";
import useConfirmDialog from "@/hooks/useConfirmDialog";

interface FinalizeResponse {
    message?: string;
}

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
    const page = usePage<SharedProps>();
    const authUser = page.props.auth.user;

    const [activeTab, setActiveTab] = useState(0);
    const [isFinalizing, setIsFinalizing] = useState(false);
    const [finalizeError, setFinalizeError] = useState<string | null>(null);
    const [isFinalizingCoverLetter, setIsFinalizingCoverLetter] =
        useState(false);
    const [finalizeCoverLetterError, setFinalizeCoverLetterError] = useState<
        string | null
    >(null);

    const metadataForm = useForm({
        title: conversation.title ?? "",
        company_name:
            targetedResume?.company_name ??
            (conversation.context?.company_name as string | undefined) ??
            "",
        job_title:
            targetedResume?.position ??
            (conversation.context?.job_title as string | undefined) ??
            "",
    });

    const [statusUpdates, setStatusUpdates] = useState<StatusUpdate[]>(
        targetedResume?.status_updates ?? [],
    );
    const [allowedNextStatuses, setAllowedNextStatuses] = useState<string[]>(
        targetedResume?.allowed_next_statuses ?? [],
    );
    const [selectedNextStatus, setSelectedNextStatus] = useState("");
    const [statusNotes, setStatusNotes] = useState("");
    const [statusOccurredAt, setStatusOccurredAt] = useState("");
    const [isSubmittingStatus, setIsSubmittingStatus] = useState(false);
    const [statusError, setStatusError] = useState<string | null>(null);

    // Mirror of ChatInterface's message list, used to scan for resume/cover letter blocks
    const [liveMessages, setLiveMessages] = useState<ChatMessage[]>(
        initialMessages as ChatMessage[],
    );

    // --- Resume/Cover Letter parsing helpers ---

    function parseTailoredResumeBlock(raw: string): {
        title: string | null;
        content: string;
    } {
        const normalized = raw.trim().replace(/\r\n/g, "\n");
        const titleMatch = /^Title:\s*(.+)\n+/i.exec(normalized);
        if (!titleMatch) return { title: null, content: normalized };
        return {
            title: titleMatch[1].trim(),
            content: normalized.replace(/^Title:\s*.+\n+/i, "").trim(),
        };
    }

    function getLatestTailoredResumeData(msgs: ChatMessage[]) {
        for (let i = msgs.length - 1; i >= 0; i--) {
            const msg = msgs[i];
            if (msg.role !== "assistant") continue;
            const contentMatch =
                /```tailored(?:-|\s+)resume\s*\n([\s\S]*?)```/i.exec(
                    msg.content,
                );
            if (!contentMatch) continue;
            const parsed = parseTailoredResumeBlock(contentMatch[1]);
            let fitScore: number | null = null;
            const scoreMatch =
                /(?:fit score|score)[:\s]*(\d{1,3})(?:\s*[/%]|\s*out of\s*100)?/i.exec(
                    msg.content,
                );
            if (scoreMatch) {
                const s = parseInt(scoreMatch[1]);
                if (s <= 100) fitScore = s;
            }
            return { rawContent: contentMatch[1].trim(), ...parsed, fitScore };
        }
        return null;
    }

    function getLatestCoverLetterContent(msgs: ChatMessage[]): string | null {
        for (let i = msgs.length - 1; i >= 0; i--) {
            const msg = msgs[i];
            if (msg.role !== "assistant") continue;
            const m = /```cover[-\s]letter\s*\n([\s\S]*?)```/i.exec(
                msg.content,
            );
            if (m) return m[1].trim();
        }
        return null;
    }

    const latestResumeData = getLatestTailoredResumeData(liveMessages);
    const latestCoverLetterContent = getLatestCoverLetterContent(liveMessages);

    const hasNewerResume = (() => {
        if (!targetedResume || !latestResumeData) return false;
        const normalize = (s: string | null | undefined) =>
            (s ?? "").trim().replace(/\r\n/g, "\n");
        return (
            normalize(latestResumeData.title) !==
                normalize(targetedResume.tailored_title) ||
            normalize(latestResumeData.content) !==
                normalize(targetedResume.tailored_content)
        );
    })();

    const canFinalizeResume = latestResumeData !== null;
    const canFinalizeCoverLetter = latestCoverLetterContent !== null;

    const handleFinalizeResume = async () => {
        if (!canFinalizeResume && targetedResume) {
            router.post(
                `/admin/resume/targeted-resume/${targetedResume.id}/regenerate`,
            );
            return;
        }
        if (!canFinalizeResume) return;
        setIsFinalizing(true);
        setFinalizeError(null);
        try {
            await api.post(
                `/api/admin/resume/targeted-builder/${conversation.id}/finalize`,
                {
                    tailored_content: latestResumeData.rawContent,
                    fit_score: latestResumeData.fitScore,
                },
            );
            window.location.reload();
        } catch (error) {
            if (error instanceof ApiError) {
                const data = error.data as FinalizeResponse;
                setFinalizeError(
                    data.message ?? "Failed to save targeted resume.",
                );
            } else {
                setFinalizeError("Network error. Please try again.");
            }
        } finally {
            setIsFinalizing(false);
        }
    };

    const handleFinalizeCoverLetter = async () => {
        if (!canFinalizeCoverLetter || !latestCoverLetterContent) return;
        setIsFinalizingCoverLetter(true);
        setFinalizeCoverLetterError(null);
        try {
            await api.post(
                `/api/admin/resume/targeted-builder/${conversation.id}/finalize-cover-letter`,
                { cover_letter_content: latestCoverLetterContent },
            );
            window.location.reload();
        } catch (error) {
            if (error instanceof ApiError) {
                const data = error.data as FinalizeResponse;
                setFinalizeCoverLetterError(
                    data.message ?? "Failed to save cover letter.",
                );
            } else {
                setFinalizeCoverLetterError("Network error. Please try again.");
            }
        } finally {
            setIsFinalizingCoverLetter(false);
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

    const isApplied =
        targetedResume !== null &&
        targetedResume.status !== "draft" &&
        targetedResume.status !== "finalized";

    const handleApplied = () => {
        confirm(
            "Mark this job as applied?",
            () => {
                void (async () => {
                    setIsSubmittingStatus(true);
                    setStatusError(null);
                    try {
                        const data = await api.post<{
                            success?: boolean;
                            message?: string;
                            status_updates?: StatusUpdate[];
                            allowed_next_statuses?: string[];
                        }>(
                            `/api/admin/resume/targeted-builder/${conversation.id}/status-update`,
                            { status: "applied" },
                        );
                        if (!data.success) {
                            setStatusError(
                                data.message ?? "Failed to mark as applied.",
                            );
                            return;
                        }
                        setStatusUpdates(data.status_updates ?? []);
                        setAllowedNextStatuses(
                            data.allowed_next_statuses ?? [],
                        );
                        router.reload({ only: ["targetedResume"] });
                    } catch {
                        setStatusError("Failed to mark as applied.");
                    } finally {
                        setIsSubmittingStatus(false);
                    }
                })();
            },
            { confirmLabel: "Applied", confirmColor: "success" },
        );
    };

    const handleAddStatusUpdate = async (e: SyntheticEvent) => {
        e.preventDefault();
        if (!selectedNextStatus || !targetedResume) return;
        setIsSubmittingStatus(true);
        setStatusError(null);
        try {
            const data = await api.post<{
                success?: boolean;
                message?: string;
                status_updates?: StatusUpdate[];
                allowed_next_statuses?: string[];
            }>(
                `/api/admin/resume/targeted-builder/${conversation.id}/status-update`,
                {
                    status: selectedNextStatus,
                    notes: statusNotes || null,
                    occurred_at: statusOccurredAt || null,
                },
            );
            if (!data.success) {
                setStatusError(data.message ?? "Failed to update status.");
                return;
            }
            setStatusUpdates(data.status_updates ?? []);
            setAllowedNextStatuses(data.allowed_next_statuses ?? []);
            setSelectedNextStatus("");
            setStatusNotes("");
            setStatusOccurredAt("");
        } catch {
            setStatusError("Failed to update status.");
        } finally {
            setIsSubmittingStatus(false);
        }
    };

    const handleMetadataSave = (e: SyntheticEvent) => {
        e.preventDefault();
        metadataForm.put(
            `/admin/resume/targeted-builder/${conversation.id}/metadata`,
        );
    };

    const companyName: string =
        targetedResume?.company_name ??
        (conversation.context?.company_name as string | undefined) ??
        "Conversation";
    const position: string =
        targetedResume?.position ??
        (conversation.context?.job_title as string | undefined) ??
        "";
    const pageTitle: string =
        conversation.title ??
        (position ? `${companyName} - ${position}` : companyName);
    const jobUrl = conversation.job_url;

    const aiSystemId = conversation.ai_system_id as number | undefined;
    const statusUrl = aiSystemId
        ? `/api/admin/resume/targeted-builder/ai-systems/${aiSystemId}/model-status`
        : "";
    const warmupUrl = aiSystemId
        ? `/api/admin/resume/targeted-builder/ai-systems/${aiSystemId}/model-warmup`
        : "";

    return (
        <AdminLayout>
            <Head title={`${pageTitle} | Targeted Resumes`} />
            <PageHeader title={pageTitle} />

            <TargetedBuilderStatusBar
                conversation={conversation}
                targetedResume={targetedResume}
                statusUpdates={statusUpdates}
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
                    onChange={(_, v) => {
                        setActiveTab(v as number);
                    }}
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
                    <ResponsiveButton
                        size="small"
                        color="success"
                        icon={<DoneIcon />}
                        label="Mark Applied"
                        title={
                            isApplied
                                ? "Already in application flow"
                                : "Mark as applied"
                        }
                        variant="outlined"
                        disabled={isApplied || isSubmittingStatus}
                        onClick={handleApplied}
                    />
                    <ResponsiveButton
                        size="small"
                        color="warning"
                        icon={<BackHandOutlinedIcon />}
                        label="Pass"
                        title={
                            conversation.status === "pass"
                                ? "Already marked as passed"
                                : "Mark as passed"
                        }
                        variant="outlined"
                        disabled={conversation.status === "pass"}
                        onClick={handlePass}
                    />
                    {jobUrl ? (
                        <ResponsiveButton
                            size="small"
                            color="primary"
                            icon={<OpenInNewIcon />}
                            variant="outlined"
                            label="Job URL"
                            title="Open Job URL in new tab"
                            onClick={() => {
                                window.open(
                                    jobUrl,
                                    "_blank",
                                    "noopener,noreferrer",
                                );
                            }}
                        />
                    ) : null}
                </Box>
            </Box>

            {(finalizeError !== null || finalizeCoverLetterError !== null) && (
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
                            canFinalize={canFinalizeResume || !!targetedResume}
                            isFinalizing={isFinalizing}
                            hasUpdate={hasNewerResume}
                            finalizeTitle={
                                hasNewerResume
                                    ? "Update resume and regenerate documents"
                                    : canFinalizeResume
                                      ? "Save the tailored resume and generate documents"
                                      : targetedResume
                                        ? "Regenerate DOCX and PDF from saved content"
                                        : "Finalize is available after the assistant returns a tailored resume block"
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
                                                href={`/admin/resume/targeted-resume/${targetedResume.id}/download/docx`}
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
                                                href={`/admin/resume/targeted-resume/${targetedResume.id}/download/pdf`}
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
                                                href={`/admin/cover-letters/${coverLetter.id}/download/docx`}
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
                                                href={`/admin/cover-letters/${coverLetter.id}/download/pdf`}
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

                    <ChatInterface
                        chatEndpoint={`/api/admin/resume/targeted-builder/${conversation.id}/chat`}
                        statusUrl={statusUrl}
                        warmupUrl={warmupUrl}
                        initialMessages={initialMessages as ChatMessage[]}
                        isAuthenticated={!!authUser}
                        shouldAutoStart={shouldAutoStart}
                        onEvent={(event) => {
                            if (event.type === "page_reload") {
                                router.reload({
                                    only: [
                                        "targetedResume",
                                        "coverLetter",
                                        "conversation",
                                    ],
                                });
                            }
                        }}
                        onMessagesChange={setLiveMessages}
                    />
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
                                onChange={(e) => {
                                    metadataForm.setData(
                                        "title",
                                        e.target.value,
                                    );
                                }}
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
                                    {conversation.ai_system_name ?? "Unknown"}
                                </Typography>
                            </Box>
                            <TextField
                                label="Company Name"
                                size="small"
                                fullWidth
                                value={metadataForm.data.company_name}
                                onChange={(e) => {
                                    metadataForm.setData(
                                        "company_name",
                                        e.target.value,
                                    );
                                }}
                                error={!!metadataForm.errors.company_name}
                                helperText={metadataForm.errors.company_name}
                                sx={{ mb: 2 }}
                            />
                            <TextField
                                label="Job Title"
                                size="small"
                                fullWidth
                                value={metadataForm.data.job_title}
                                onChange={(e) => {
                                    metadataForm.setData(
                                        "job_title",
                                        e.target.value,
                                    );
                                }}
                                error={!!metadataForm.errors.job_title}
                                helperText={metadataForm.errors.job_title}
                                sx={{ mb: 3 }}
                            />
                            {conversation.job_url && (
                                <Box sx={{ mb: 3 }}>
                                    <Typography
                                        variant="caption"
                                        color="text.secondary"
                                        sx={{ display: "block" }}
                                    >
                                        Parsed Job URL
                                    </Typography>
                                    <Link
                                        href={conversation.job_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        underline="hover"
                                        sx={{ wordBreak: "break-all" }}
                                    >
                                        {conversation.job_url}
                                    </Link>
                                </Box>
                            )}
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
                        {targetedResume && (
                            <Box
                                sx={{
                                    mt: 3,
                                    pt: 3,
                                    borderTop: 1,
                                    borderColor: "divider",
                                }}
                            >
                                <Typography variant="subtitle2" gutterBottom>
                                    Application Status History
                                </Typography>
                                {statusUpdates.length === 0 ? (
                                    <Typography
                                        variant="body2"
                                        color="text.secondary"
                                    >
                                        No status updates yet.
                                    </Typography>
                                ) : (
                                    <Box
                                        sx={{
                                            display: "flex",
                                            flexDirection: "column",
                                            gap: 1,
                                        }}
                                    >
                                        {statusUpdates.map((u, i) => (
                                            <Box key={u.id}>
                                                {i > 0 && (
                                                    <Divider sx={{ mb: 1 }} />
                                                )}
                                                <Box
                                                    sx={{
                                                        display: "flex",
                                                        gap: 1,
                                                        alignItems:
                                                            "flex-start",
                                                    }}
                                                >
                                                    <StatusChip
                                                        status={u.status}
                                                    />
                                                    <Box>
                                                        <Typography
                                                            variant="caption"
                                                            color="text.secondary"
                                                        >
                                                            {new Date(
                                                                u.occurred_at,
                                                            ).toLocaleDateString(
                                                                undefined,
                                                                {
                                                                    year: "numeric",
                                                                    month: "short",
                                                                    day: "numeric",
                                                                },
                                                            )}
                                                        </Typography>
                                                        {u.notes && (
                                                            <Typography
                                                                variant="body2"
                                                                sx={{
                                                                    mt: 0.25,
                                                                }}
                                                            >
                                                                {u.notes}
                                                            </Typography>
                                                        )}
                                                    </Box>
                                                </Box>
                                            </Box>
                                        ))}
                                    </Box>
                                )}
                                {allowedNextStatuses.length > 0 && (
                                    <Box
                                        component="form"
                                        onSubmit={(e) => {
                                            void handleAddStatusUpdate(e);
                                        }}
                                        sx={{ mt: 2 }}
                                    >
                                        <Typography
                                            variant="caption"
                                            color="text.secondary"
                                            sx={{ display: "block", mb: 1 }}
                                        >
                                            Log Status Update
                                        </Typography>
                                        <FormControl
                                            size="small"
                                            fullWidth
                                            sx={{ mb: 1 }}
                                        >
                                            <InputLabel id="next-status-label">
                                                Status
                                            </InputLabel>
                                            <Select
                                                labelId="next-status-label"
                                                label="Status"
                                                value={selectedNextStatus}
                                                onChange={(e) => {
                                                    setSelectedNextStatus(
                                                        e.target.value,
                                                    );
                                                }}
                                            >
                                                {allowedNextStatuses.map(
                                                    (s) => (
                                                        <MenuItem
                                                            key={s}
                                                            value={s}
                                                        >
                                                            {s
                                                                .charAt(0)
                                                                .toUpperCase() +
                                                                s.slice(1)}
                                                        </MenuItem>
                                                    ),
                                                )}
                                            </Select>
                                        </FormControl>
                                        <TextField
                                            label={
                                                selectedNextStatus ===
                                                "interviewing"
                                                    ? "Scheduled date"
                                                    : "Date (optional)"
                                            }
                                            type="date"
                                            size="small"
                                            fullWidth
                                            value={statusOccurredAt}
                                            onChange={(e) => {
                                                setStatusOccurredAt(
                                                    e.target.value,
                                                );
                                            }}
                                            slotProps={{
                                                inputLabel: { shrink: true },
                                            }}
                                            sx={{ mb: 1 }}
                                        />
                                        <TextField
                                            label="Notes (optional)"
                                            size="small"
                                            fullWidth
                                            multiline
                                            rows={2}
                                            value={statusNotes}
                                            onChange={(e) => {
                                                setStatusNotes(e.target.value);
                                            }}
                                            sx={{ mb: 1 }}
                                        />
                                        {statusError && (
                                            <Alert
                                                severity="error"
                                                sx={{ mb: 1 }}
                                            >
                                                {statusError}
                                            </Alert>
                                        )}
                                        <Button
                                            type="submit"
                                            variant="outlined"
                                            size="small"
                                            startIcon={<UpdateIcon />}
                                            disabled={
                                                !selectedNextStatus ||
                                                isSubmittingStatus
                                            }
                                        >
                                            Log Status
                                        </Button>
                                    </Box>
                                )}
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
