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
import EditNoteIcon from "@mui/icons-material/EditNote";
import InfoIcon from "@mui/icons-material/Info";
import OpenInNewIcon from "@mui/icons-material/OpenInNew";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import IconButton from "@mui/material/IconButton";
import Tab from "@mui/material/Tab";
import Tabs from "@mui/material/Tabs";
import { useState } from "react";

import BuilderChatPanel from "./BuilderChatPanel";
import BuilderMetadataForm from "./BuilderMetadataForm";
import TailoredResumeEditor from "./TailoredResumeEditor";
import useFinalizeArtifacts from "./useFinalizeArtifacts";
import useLatestGeneratedArtifacts from "./useLatestGeneratedArtifacts";
import useStatusUpdates from "./useStatusUpdates";

import type { MetadataFormData } from "./BuilderMetadataForm";
import type { ChatMessage } from "@/components/ChatInterface";
import type {
    Conversation,
    CoverLetter,
    Message,
    SharedProps,
    TargetedResume,
} from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import AdminLayout from "@/admin/layouts/AdminLayout";
import ResponsiveButton from "@/components/ResponsiveButton";
import useConfirmDialog from "@/hooks/useConfirmDialog";

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

    /** Mirror of ChatInterface's message list, scanned for resume/cover letter blocks. */
    const [liveMessages, setLiveMessages] = useState<ChatMessage[]>(
        initialMessages as ChatMessage[],
    );

    const { latestResumeData, latestCoverLetterContent, hasNewerResume } =
        useLatestGeneratedArtifacts(liveMessages, targetedResume);

    const {
        isFinalizing,
        finalizeError,
        isFinalizingCoverLetter,
        finalizeCoverLetterError,
        canFinalizeResume,
        canFinalizeCoverLetter,
        finalizeResume,
        finalizeCoverLetter,
    } = useFinalizeArtifacts({
        conversationId: conversation.id,
        targetedResume,
        latestResumeData,
        latestCoverLetterContent,
    });

    const status = useStatusUpdates(conversation.id, targetedResume);

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
                void status.markApplied();
            },
            { confirmLabel: "Applied", confirmColor: "success" },
        );
    };

    const handleDeleteStatusUpdate = (statusUpdateId: number) => {
        confirm(
            "Delete this status update entry?",
            () => {
                void status.deleteStatusUpdate(statusUpdateId);
            },
            { confirmLabel: "Delete", confirmColor: "error" },
        );
    };

    const handleMetadataSave = () => {
        metadataForm.put(
            `/admin/resume/targeted-builder/${conversation.id}/metadata`,
        );
    };

    const isApplied =
        targetedResume !== null &&
        targetedResume.status !== "draft" &&
        targetedResume.status !== "finalized";

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
        <>
            <Head title={`${pageTitle} | Targeted Resumes`} />
            <AdminLayout showChrome={false} noMargin>
                <Box
                    sx={{
                        position: "sticky",
                        top: 0,
                        zIndex: 10,

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
                        {targetedResume !== null && (
                            <Tab icon={<EditNoteIcon />} />
                        )}
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
                            disabled={isApplied || status.isSubmittingStatus}
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
                    status.statusError !== null) && (
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
                        {status.statusError && (
                            <Alert severity="error">{status.statusError}</Alert>
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
                        onFinalizeResume={() => {
                            void finalizeResume();
                        }}
                        canFinalizeCoverLetter={canFinalizeCoverLetter}
                        isFinalizingCoverLetter={isFinalizingCoverLetter}
                        hasCoverLetterUpdate={!!coverLetter}
                        onFinalizeCoverLetter={() => {
                            void finalizeCoverLetter();
                        }}
                        statusUrl={statusUrl}
                        warmupUrl={warmupUrl}
                        onMessagesChange={setLiveMessages}
                    />
                </Box>

                <Box sx={{ display: activeTab === 1 ? undefined : "none" }}>
                    {/* `deleteStatusUpdate` must stay after the spread — it
                    overrides the hook's raw action with the confirmed one. */}
                    <BuilderMetadataForm
                        {...status}
                        conversation={conversation}
                        metadataForm={metadataForm}
                        onMetadataSave={handleMetadataSave}
                        targetedResume={targetedResume}
                        coverLetter={coverLetter}
                        deleteStatusUpdate={handleDeleteStatusUpdate}
                    />
                </Box>

                {targetedResume !== null && (
                    <Box sx={{ display: activeTab === 2 ? undefined : "none" }}>
                        <TailoredResumeEditor targetedResume={targetedResume} />
                    </Box>
                )}
                <ConfirmDialog {...dialogProps} />
            </AdminLayout>
        </>
    );
}
