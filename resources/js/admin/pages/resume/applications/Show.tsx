import { Head, Link as InertiaLink, router, usePage } from "@inertiajs/react";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import BackHandOutlinedIcon from "@mui/icons-material/BackHandOutlined";
import ChatIcon from "@mui/icons-material/Chat";
import DoneIcon from "@mui/icons-material/Done";
import InfoIcon from "@mui/icons-material/Info";
import OpenInNewIcon from "@mui/icons-material/OpenInNew";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Container from "@mui/material/Container";
import IconButton from "@mui/material/IconButton";
import Tab from "@mui/material/Tab";
import Tabs from "@mui/material/Tabs";
import Typography from "@mui/material/Typography";
import { useCallback, useState } from "react";

import AppliedConfirmDialog from "./AppliedConfirmDialog";
import BeginAnalysisPanel from "./BeginAnalysisPanel";
import ChatPanel from "./ChatPanel";
import DetailsForm from "./DetailsForm";
import StatusBar from "./StatusBar";
import useFinalizeArtifacts from "./useFinalizeArtifacts";
import useLatestGeneratedArtifacts from "./useLatestGeneratedArtifacts";
import useStatusUpdates from "./useStatusUpdates";

import type { ChatMessage } from "@/components/ChatInterface";
import type {
    AiSystem,
    Application,
    ApplicationConversation,
    CoverLetter,
    Message,
    ResumeVersionOption,
    SharedProps,
    TargetedResume,
} from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import AdminLayout from "@/admin/layouts/AdminLayout";
import { isPipelineStatus } from "@/admin/utils/applicationStatus";
import { api, apiErrorMessage } from "@/api";
import ResponsiveButton from "@/components/ResponsiveButton";
import useConfirmDialog from "@/hooks/useConfirmDialog";

const TAB_CHAT = 0;
const TAB_DETAILS = 1;

interface ShowProps {
    application: Application;
    /** Null when the application has no AI session. */
    conversation: ApplicationConversation | null;
    messages: Message[];
    targetedResume: TargetedResume | null;
    coverLetter: CoverLetter | null;
    shouldAutoStart: boolean;
    resumeVersions: ResumeVersionOption[];
    currentResumeVersionId: number | null;
    systems: Pick<AiSystem, "id" | "name" | "model">[];
    defaultSystemId: number | null;
}

export default function Show({
    application,
    conversation,
    messages,
    targetedResume,
    coverLetter,
    shouldAutoStart,
    resumeVersions,
    currentResumeVersionId,
    systems,
    defaultSystemId,
}: ShowProps) {
    const page = usePage<SharedProps>();
    const isAuthenticated = !!page.props.auth.user;

    // Server messages carry a wider `role` than the chat's own message type.
    const initialMessages = messages as ChatMessage[];

    const [activeTab, setActiveTab] = useState(TAB_CHAT);
    const [appliedDialogOpen, setAppliedDialogOpen] = useState(false);
    const [actionError, setActionError] = useState<string | null>(null);
    const [appliedError, setAppliedError] = useState<string | null>(null);

    /** Mirror of ChatInterface's message list, scanned for resume/cover letter blocks. */
    const [liveMessages, setLiveMessages] =
        useState<ChatMessage[]>(initialMessages);

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
        applicationId: application.id,
        targetedResumeId: targetedResume?.id ?? null,
        latestResumeData,
        latestCoverLetterContent,
    });

    // Stable wrappers: these are props of the memoized ChatPanel.
    const handleFinalizeResume = useCallback(() => {
        void finalizeResume();
    }, [finalizeResume]);
    const handleFinalizeCoverLetter = useCallback(() => {
        void finalizeCoverLetter();
    }, [finalizeCoverLetter]);

    const status = useStatusUpdates(application.id, application);

    const { dialogProps, confirm } = useConfirmDialog();

    const passApplication = async () => {
        setActionError(null);
        try {
            const data = await api.post<{ redirect?: string }>(
                `/api/admin/resume/applications/${application.id}/pass`,
            );
            if (data.redirect) {
                router.visit(data.redirect);
            } else {
                router.reload({ only: ["application", "conversation"] });
            }
        } catch (error) {
            setActionError(apiErrorMessage(error, "Failed to mark as passed."));
        }
    };

    const handlePass = () => {
        confirm(
            "Mark this opportunity as passed?",
            () => {
                void passApplication();
            },
            { confirmLabel: "Pass", confirmColor: "warning" },
        );
    };

    const handleConfirmApplied = async (resumeVersionId: number | null) => {
        setAppliedError(null);
        const error = await status.markApplied({ resumeVersionId });
        if (error === null) {
            setAppliedDialogOpen(false);
        } else {
            setAppliedError(error);
        }
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

    const handleDiscardResume = () => {
        if (targetedResume === null) {
            return;
        }
        const targetedResumeId = targetedResume.id;
        confirm(
            "This permanently deletes the tailored resume and its generated documents. The application, its status history and its cover letter are kept. This cannot be undone.",
            () => {
                router.delete(
                    `/admin/resume/targeted-resumes/${targetedResumeId}`,
                    { preserveScroll: true },
                );
            },
            { title: "Discard targeted resume?", confirmLabel: "Discard" },
        );
    };

    const isApplied = isPipelineStatus(status.status);
    const canPass = status.status === "draft";

    const pageTitle =
        conversation?.title ??
        `${application.company_name} — ${application.position}`;
    const jobUrl = application.job_url;

    const hasErrors =
        finalizeError !== null ||
        finalizeCoverLetterError !== null ||
        status.statusError !== null ||
        actionError !== null;

    return (
        <>
            <Head title={`${pageTitle} | Applications`} />
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
                        href="/admin/resume/applications"
                        aria-label="Back to Applications"
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
                        aria-label="Application tabs"
                        sx={{
                            flexShrink: 0,
                            "& .MuiTab-root": {
                                minWidth: 0,
                                px: 2,
                                py: 1.5,
                            },
                        }}
                    >
                        <Tab
                            icon={<ChatIcon />}
                            aria-label="Chat"
                            id="application-tab-chat"
                            aria-controls="application-tabpanel-chat"
                        />
                        <Tab
                            icon={<InfoIcon />}
                            aria-label="Details"
                            id="application-tab-details"
                            aria-controls="application-tabpanel-details"
                        />
                    </Tabs>
                    <Typography
                        component="h1"
                        variant="subtitle1"
                        noWrap
                        title={pageTitle}
                        sx={{
                            display: { xs: "none", md: "block" },
                            flexGrow: 1,
                            minWidth: 0,
                            fontWeight: 600,
                        }}
                    >
                        {pageTitle}
                    </Typography>
                    <Box sx={{ flexGrow: { xs: 1, md: 0 } }} />

                    <Box
                        sx={{
                            display: "flex",
                            alignItems: "center",
                            flexShrink: 0,
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
                            onClick={() => {
                                setAppliedDialogOpen(true);
                            }}
                        />
                        <ResponsiveButton
                            size="small"
                            color="warning"
                            icon={<BackHandOutlinedIcon />}
                            label="Pass"
                            title={
                                status.status === "passed"
                                    ? "Already marked as passed"
                                    : canPass
                                      ? "Mark as passed"
                                      : "Already in application flow"
                            }
                            variant="outlined"
                            disabled={!canPass}
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

                {hasErrors ? (
                    <Box
                        sx={{
                            mb: 2,
                            display: "flex",
                            flexDirection: "column",
                            gap: 1,
                        }}
                    >
                        {finalizeError ? (
                            <Alert severity="error">{finalizeError}</Alert>
                        ) : null}
                        {finalizeCoverLetterError ? (
                            <Alert severity="error">
                                {finalizeCoverLetterError}
                            </Alert>
                        ) : null}
                        {status.statusError ? (
                            <Alert severity="error">{status.statusError}</Alert>
                        ) : null}
                        {actionError ? (
                            <Alert severity="error">{actionError}</Alert>
                        ) : null}
                    </Box>
                ) : null}

                <Box
                    role="tabpanel"
                    id="application-tabpanel-chat"
                    aria-labelledby="application-tab-chat"
                    sx={{
                        display: activeTab === TAB_CHAT ? undefined : "none",
                    }}
                >
                    {conversation ? (
                        <ChatPanel
                            isAuthenticated={isAuthenticated}
                            application={application}
                            status={status.status}
                            statusUpdates={status.statusUpdates}
                            conversation={conversation}
                            targetedResume={targetedResume}
                            coverLetter={coverLetter}
                            initialMessages={initialMessages}
                            shouldAutoStart={shouldAutoStart}
                            canFinalizeResume={canFinalizeResume}
                            isFinalizing={isFinalizing}
                            hasNewerResume={hasNewerResume}
                            onFinalizeResume={handleFinalizeResume}
                            canFinalizeCoverLetter={canFinalizeCoverLetter}
                            isFinalizingCoverLetter={isFinalizingCoverLetter}
                            onFinalizeCoverLetter={handleFinalizeCoverLetter}
                            onMessagesChange={setLiveMessages}
                        />
                    ) : (
                        <Container sx={{ py: 2 }}>
                            <StatusBar
                                status={status.status}
                                statusUpdates={status.statusUpdates}
                                fitScore={application.fit_score}
                            />
                            <BeginAnalysisPanel
                                applicationId={application.id}
                                systems={systems}
                                defaultSystemId={defaultSystemId}
                            />
                        </Container>
                    )}
                </Box>

                <Box
                    role="tabpanel"
                    id="application-tabpanel-details"
                    aria-labelledby="application-tab-details"
                    sx={{
                        display: activeTab === TAB_DETAILS ? undefined : "none",
                    }}
                >
                    {/* `deleteStatusUpdate` must stay after the spread — it
                    overrides the hook's raw action with the confirmed one. */}
                    <DetailsForm
                        {...status}
                        application={application}
                        conversation={conversation}
                        targetedResume={targetedResume}
                        coverLetter={coverLetter}
                        deleteStatusUpdate={handleDeleteStatusUpdate}
                        onDiscardResume={handleDiscardResume}
                    />
                </Box>

                <AppliedConfirmDialog
                    open={appliedDialogOpen}
                    hasTargetedResume={targetedResume !== null}
                    resumeVersions={resumeVersions}
                    currentResumeVersionId={currentResumeVersionId}
                    isSubmitting={status.isSubmittingStatus}
                    error={appliedError}
                    onConfirm={(resumeVersionId) => {
                        void handleConfirmApplied(resumeVersionId);
                    }}
                    onCancel={() => {
                        setAppliedDialogOpen(false);
                    }}
                />
                <ConfirmDialog {...dialogProps} />
            </AdminLayout>
        </>
    );
}
