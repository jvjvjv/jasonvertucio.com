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
import InfoIcon from "@mui/icons-material/Info";
import OpenInNewIcon from "@mui/icons-material/OpenInNew";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import IconButton from "@mui/material/IconButton";
import InputLabel from "@mui/material/InputLabel";
import Link from "@mui/material/Link";
import MenuItem from "@mui/material/MenuItem";
import Select from "@mui/material/Select";
import Tab from "@mui/material/Tab";
import Tabs from "@mui/material/Tabs";
import { useState } from "react";

import BuilderChatPanel from "./BuilderChatPanel";
import BuilderMetadataForm from "./BuilderMetadataForm";
import TargetedBuilderStatusBar from "./TargetedBuilderStatusBar";

import type { MetadataFormData } from "./BuilderMetadataForm";
import type { ChatMessage } from "@/components/ChatInterface";
import type {
    Conversation,
    CoverLetter,
    Message,
    SharedProps,
    StatusUpdate,
    TargetedResume,
} from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";
import { api, ApiError } from "@/api";
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

    const metadataForm = useForm<MetadataFormData>({
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
    const [editingStatusId, setEditingStatusId] = useState<number | null>(null);
    const [editingStatusNotes, setEditingStatusNotes] = useState("");
    const [editingStatusOccurredAt, setEditingStatusOccurredAt] = useState("");
    const [isSavingStatusEdit, setIsSavingStatusEdit] = useState(false);
    const [isDeletingStatusId, setIsDeletingStatusId] = useState<number | null>(
        null,
    );
    const [showStatusUpdateForm, setShowStatusUpdateForm] = useState(false);

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

    // Compute fit score from either targeted resume or conversation context.
    // Fit scores are always 1-100, so we can use falsy checks to simplify logic.
    const _hasFitScore = () => {
        if (targetedResume?.fit_score) return true;
        if (conversation.context?.fit_score) return true;
        return false;
    };

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
                    } catch (error) {
                        if (error instanceof ApiError) {
                            const data = error.data as {
                                message?: string;
                                errors?: { [key: string]: string[] };
                            };
                            const firstValidationError = data.errors
                                ? Object.values(data.errors)[0]?.[0]
                                : null;

                            setStatusError(
                                data.message ??
                                    firstValidationError ??
                                    "Failed to mark as applied.",
                            );
                        } else {
                            setStatusError("Failed to mark as applied.");
                        }
                    } finally {
                        setIsSubmittingStatus(false);
                    }
                })();
            },
            { confirmLabel: "Applied", confirmColor: "success" },
        );
    };

    const handleAddStatusUpdate = async () => {
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
        } catch (error) {
            if (error instanceof ApiError) {
                const data = error.data as {
                    message?: string;
                    errors?: { [key: string]: string[] };
                };
                const firstValidationError = data.errors
                    ? Object.values(data.errors)[0]?.[0]
                    : null;

                setStatusError(
                    data.message ??
                        firstValidationError ??
                        "Failed to update status.",
                );
            } else {
                setStatusError("Failed to update status.");
            }
        } finally {
            setIsSubmittingStatus(false);
        }
    };

    const toDateInputValue = (isoDate: string): string => {
        return isoDate.slice(0, 10);
    };

    const startEditingStatus = (statusUpdate: StatusUpdate) => {
        setEditingStatusId(statusUpdate.id);
        setEditingStatusNotes(statusUpdate.notes ?? "");
        setEditingStatusOccurredAt(toDateInputValue(statusUpdate.occurred_at));
        setStatusError(null);
    };

    const cancelEditingStatus = () => {
        setEditingStatusId(null);
        setEditingStatusNotes("");
        setEditingStatusOccurredAt("");
    };

    const handleSaveStatusEdit = async (statusUpdateId: number) => {
        if (!editingStatusOccurredAt) {
            setStatusError("Date is required.");
            return;
        }

        setIsSavingStatusEdit(true);
        setStatusError(null);

        try {
            const data = await api.put<{
                success?: boolean;
                message?: string;
                status_updates?: StatusUpdate[];
                allowed_next_statuses?: string[];
            }>(
                `/api/admin/resume/targeted-builder/${conversation.id}/status-update/${statusUpdateId}`,
                {
                    notes: editingStatusNotes || null,
                    occurred_at: editingStatusOccurredAt,
                },
            );

            if (!data.success) {
                setStatusError(
                    data.message ?? "Failed to update status entry.",
                );
                return;
            }

            setStatusUpdates(data.status_updates ?? []);
            setAllowedNextStatuses(data.allowed_next_statuses ?? []);
            cancelEditingStatus();
        } catch (error) {
            if (error instanceof ApiError) {
                const data = error.data as {
                    message?: string;
                    errors?: { [key: string]: string[] };
                };
                const firstValidationError = data.errors
                    ? Object.values(data.errors)[0]?.[0]
                    : null;

                setStatusError(
                    data.message ??
                        firstValidationError ??
                        "Failed to update status entry.",
                );
            } else {
                setStatusError("Failed to update status entry.");
            }
        } finally {
            setIsSavingStatusEdit(false);
        }
    };

    const handleDeleteStatusUpdate = (statusUpdateId: number) => {
        confirm(
            "Delete this status update entry?",
            () => {
                void (async () => {
                    setIsDeletingStatusId(statusUpdateId);
                    setStatusError(null);

                    try {
                        const data = await api.del<{
                            success?: boolean;
                            message?: string;
                            status_updates?: StatusUpdate[];
                            allowed_next_statuses?: string[];
                        }>(
                            `/api/admin/resume/targeted-builder/${conversation.id}/status-update/${statusUpdateId}`,
                        );

                        if (!data.success) {
                            setStatusError(
                                data.message ??
                                    "Failed to delete status update entry.",
                            );
                            return;
                        }

                        setStatusUpdates(data.status_updates ?? []);
                        setAllowedNextStatuses(
                            data.allowed_next_statuses ?? [],
                        );
                        if (editingStatusId === statusUpdateId) {
                            cancelEditingStatus();
                        }

                        router.reload({ only: ["targetedResume"] });
                    } catch (error) {
                        if (error instanceof ApiError) {
                            const data = error.data as {
                                message?: string;
                                errors?: { [key: string]: string[] };
                            };
                            const firstValidationError = data.errors
                                ? Object.values(data.errors)[0]?.[0]
                                : null;

                            setStatusError(
                                data.message ??
                                    firstValidationError ??
                                    "Failed to delete status update entry.",
                            );
                        } else {
                            setStatusError(
                                "Failed to delete status update entry.",
                            );
                        }
                    } finally {
                        setIsDeletingStatusId(null);
                    }
                })();
            },
            { confirmLabel: "Delete", confirmColor: "error" },
        );
    };

    const handleMetadataSave = () => {
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
                    top: 0,
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

            {(finalizeError !== null ||
                finalizeCoverLetterError !== null ||
                statusError !== null) && (
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
                    {statusError && (
                        <Alert severity="error">{statusError}</Alert>
                    )}
                </Box>
            )}

            <Box sx={{ display: activeTab === 0 ? undefined : "none" }}>
                <BuilderChatPanel
                    authUser={authUser}
                    conversation={conversation}
                    targetedResume={targetedResume}
                    coverLetter={coverLetter}
                    initialMessages={initialMessages as ChatMessage[]}
                    shouldAutoStart={shouldAutoStart}
                    canFinalizeResume={canFinalizeResume}
                    isFinalizing={isFinalizing}
                    hasNewerResume={hasNewerResume}
                    onFinalizeResume={handleFinalizeResume}
                    canFinalizeCoverLetter={canFinalizeCoverLetter}
                    isFinalizingCoverLetter={isFinalizingCoverLetter}
                    hasCoverLetterUpdate={!!coverLetter}
                    onFinalizeCoverLetter={handleFinalizeCoverLetter}
                    statusUrl={statusUrl}
                    warmupUrl={warmupUrl}
                    onMessagesChange={setLiveMessages}
                />
            </Box>

            <Box sx={{ display: activeTab === 1 ? undefined : "none" }}>
                <BuilderMetadataForm
                    conversation={conversation}
                    metadataForm={metadataForm}
                    onMetadataSave={handleMetadataSave}
                    targetedResume={targetedResume}
                    statusUpdates={statusUpdates}
                    allowedNextStatuses={allowedNextStatuses}
                    selectedNextStatus={selectedNextStatus}
                    statusOccurredAt={statusOccurredAt}
                    statusNotes={statusNotes}
                    isSubmittingStatus={isSubmittingStatus}
                    showStatusUpdateForm={showStatusUpdateForm}
                    editingStatusId={editingStatusId}
                    editingStatusOccurredAt={editingStatusOccurredAt}
                    editingStatusNotes={editingStatusNotes}
                    isSavingStatusEdit={isSavingStatusEdit}
                    isDeletingStatusId={isDeletingStatusId}
                    coverLetter={coverLetter}
                    onShowStatusUpdateFormChange={setShowStatusUpdateForm}
                    onSelectedNextStatusChange={setSelectedNextStatus}
                    onStatusOccurredAtChange={setStatusOccurredAt}
                    onStatusNotesChange={setStatusNotes}
                    onAddStatusUpdate={() => {
                        void handleAddStatusUpdate();
                    }}
                    onStartEditingStatus={startEditingStatus}
                    onCancelEditingStatus={cancelEditingStatus}
                    onEditingStatusOccurredAtChange={setEditingStatusOccurredAt}
                    onEditingStatusNotesChange={setEditingStatusNotes}
                    onSaveStatusEdit={(statusUpdateId) => {
                        void handleSaveStatusEdit(statusUpdateId);
                    }}
                    onDeleteStatusUpdate={handleDeleteStatusUpdate}
                />
            </Box>
            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
