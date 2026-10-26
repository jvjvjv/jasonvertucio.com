import { Head, Link as InertiaLink, router, usePage } from "@inertiajs/react";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import ChatIcon from "@mui/icons-material/Chat";
import ReplayIcon from "@mui/icons-material/Replay";
import SyncProblemIcon from "@mui/icons-material/SyncProblem";
import TravelExploreIcon from "@mui/icons-material/TravelExplore";
import WorkOutlineIcon from "@mui/icons-material/WorkOutline";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Container from "@mui/material/Container";
import IconButton from "@mui/material/IconButton";
import LinearProgress from "@mui/material/LinearProgress";
import Tab from "@mui/material/Tab";
import Tabs from "@mui/material/Tabs";
import Typography from "@mui/material/Typography";
import { useCallback, useRef, useState } from "react";

import IntelFindings from "./IntelFindings";
import {
    INTEL_API,
    INTEL_PAGES,
    INTEL_STATUS_COLORS,
    INTEL_STATUS_LABELS,
    isIntelInProgress,
} from "./types";
import useIntelState from "./useIntelState";
import WrongCompanyDialog from "./WrongCompanyDialog";

import type { CompanyOption, RoleIntel } from "./types";
import type {
    ChatInterfaceHandle,
    ChatMessage,
    StreamEvent,
} from "@/components/ChatInterface";
import type { Message, SharedProps } from "@/types";

import SessionSystemSwitch from "@/admin/components/SessionSystemSwitch";
import StatusChip from "@/admin/components/StatusChip";
import AdminLayout from "@/admin/layouts/AdminLayout";
import { api, apiErrorMessage, networkErrorMessage } from "@/api";
import ChatInterface from "@/components/ChatInterface";
import ResponsiveButton from "@/components/ResponsiveButton";

const TAB_FINDINGS = 0;
const TAB_CHAT = 1;

/** The applications chat's model checks; they need the same permission. */
const MODEL_API = "/api/admin/resume/applications/ai-systems";

interface ShowProps {
    intel: RoleIntel;
    conversation: {
        id: number;
        ai_system_id: number | null;
        ai_system_name: string | null;
        can_switch: boolean;
    } | null;
    messages: Message[];
    shouldAutoStart: boolean;
    companyOptions: CompanyOption[];
}

export default function Show({
    intel: initialIntel,
    conversation,
    messages,
    shouldAutoStart,
    companyOptions,
}: ShowProps) {
    const page = usePage<SharedProps>();
    const isAuthenticated = !!page.props.auth.user;
    const sessionExpiresAt = page.props.session.expiresAt;

    const chatRef = useRef<ChatInterfaceHandle>(null);
    // Bumped to remount the chat after the server's transcript changed
    // behind it (a background run finished, or a retry is pending).
    const [chatKey, setChatKey] = useState(0);
    const [activeTab, setActiveTab] = useState(
        shouldAutoStart ? TAB_CHAT : TAB_FINDINGS,
    );
    const [isStreaming, setIsStreaming] = useState(false);
    const [wrongCompanyOpen, setWrongCompanyOpen] = useState(false);
    const [isCorrecting, setIsCorrecting] = useState(false);
    const [correctionError, setCorrectionError] = useState<string | null>(null);
    const [actionError, setActionError] = useState<string | null>(null);

    const reloadTranscript = useCallback(() => {
        router.reload({
            only: [
                "intel",
                "conversation",
                "messages",
                "shouldAutoStart",
                "companyOptions",
            ],
            onSuccess: () => {
                setChatKey((key) => key + 1);
            },
        });
    }, []);

    const { intel, setIntel, refresh } = useIntelState(initialIntel, {
        pollWhileInProgress: !shouldAutoStart && !isStreaming,
        onSettled: reloadTranscript,
    });

    const handleStreamEvent = useCallback(
        (event: StreamEvent) => {
            // Sent after each tool result and at the end of the turn: the
            // findings may have changed.
            if (event.type === "page_reload") {
                void refresh();
            }
        },
        [refresh],
    );

    const handleStreamResponse = useCallback(() => {
        setIsStreaming(true);
        setIntel((current) => ({ ...current, status: "running" }));
    }, [setIntel]);

    const handleStreamEnd = useCallback(() => {
        setIsStreaming(false);
        void refresh();
    }, [refresh]);

    const handleRetry = async () => {
        setActionError(null);
        try {
            await api.post(`${INTEL_API}/${intel.id}/retry`, {
                mode: "stream",
            });
            setActiveTab(TAB_CHAT);
            reloadTranscript();
        } catch (error) {
            setActionError(
                apiErrorMessage(
                    error,
                    "Could not retry.",
                    networkErrorMessage(error),
                ),
            );
        }
    };

    const handleWrongCompany = async (
        correction: { hint: string } | { companyId: number },
    ) => {
        setIsCorrecting(true);
        setCorrectionError(null);
        try {
            const result = await api.post<{
                message: string | null;
                intel: RoleIntel;
            }>(
                `${INTEL_API}/${intel.id}/wrong-company`,
                "hint" in correction
                    ? { hint: correction.hint }
                    : { company_id: correction.companyId },
            );
            setIntel(result.intel);
            setWrongCompanyOpen(false);
            router.reload({ only: ["companyOptions"] });

            if (result.message !== null) {
                setActiveTab(TAB_CHAT);
                void chatRef.current?.sendMessage(result.message);
            }
        } catch (error) {
            setCorrectionError(
                apiErrorMessage(
                    error,
                    "Could not detach the company.",
                    networkErrorMessage(error),
                ),
            );
        } finally {
            setIsCorrecting(false);
        }
    };

    const application = intel.application;
    const companyLabel =
        intel.company?.name ?? application?.company_name ?? "Unknown company";
    const pageTitle = application?.position
        ? `${application.position} at ${companyLabel}`
        : companyLabel;
    const inBackground = isIntelInProgress(intel.status) && !isStreaming;
    const aiSystemId = conversation?.ai_system_id ?? null;

    return (
        <>
            <Head title={`${pageTitle} | Company Intel`} />
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
                        href={INTEL_PAGES}
                        aria-label="Back to Company Intel"
                        size="small"
                        sx={{ ml: 0.5 }}
                    >
                        <ArrowBackIcon fontSize="small" />
                    </IconButton>
                    <Tabs
                        value={activeTab}
                        onChange={(_, value) => {
                            setActiveTab(value as number);
                        }}
                        aria-label="Intel tabs"
                        sx={{
                            flexShrink: 0,
                            "& .MuiTab-root": { minWidth: 0, px: 2, py: 1.5 },
                        }}
                    >
                        <Tab
                            icon={<TravelExploreIcon />}
                            aria-label="Findings"
                            id="intel-tab-findings"
                            aria-controls="intel-tabpanel-findings"
                        />
                        <Tab
                            icon={<ChatIcon />}
                            aria-label="Chat"
                            id="intel-tab-chat"
                            aria-controls="intel-tabpanel-chat"
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
                        <StatusChip
                            status={intel.status}
                            label={INTEL_STATUS_LABELS[intel.status]}
                            colorMap={INTEL_STATUS_COLORS}
                        />
                        {intel.status === "failed" ? (
                            <ResponsiveButton
                                size="small"
                                color="primary"
                                icon={<ReplayIcon />}
                                label="Retry"
                                title="Run the research again"
                                onClick={() => {
                                    void handleRetry();
                                }}
                            />
                        ) : null}
                        {intel.company !== null ? (
                            <ResponsiveButton
                                size="small"
                                color="warning"
                                icon={<SyncProblemIcon />}
                                label="Wrong company"
                                title="Detach this company and find the right one"
                                disabled={isStreaming || inBackground}
                                onClick={() => {
                                    setCorrectionError(null);
                                    setWrongCompanyOpen(true);
                                }}
                            />
                        ) : null}
                        {application !== null ? (
                            <ResponsiveButton
                                size="small"
                                color="primary"
                                icon={<WorkOutlineIcon />}
                                label="Application"
                                title="Open the application"
                                href={`/admin/resume/applications/${application.id}`}
                            />
                        ) : null}
                    </Box>
                </Box>
                {inBackground ? (
                    <LinearProgress aria-label="Research in progress" />
                ) : null}

                {actionError ? (
                    <Alert
                        severity="error"
                        sx={{ m: 2 }}
                        onClose={() => {
                            setActionError(null);
                        }}
                    >
                        {actionError}
                    </Alert>
                ) : null}

                <Box
                    role="tabpanel"
                    id="intel-tabpanel-findings"
                    aria-labelledby="intel-tab-findings"
                    sx={{
                        display:
                            activeTab === TAB_FINDINGS ? undefined : "none",
                    }}
                >
                    <Container sx={{ py: 3 }}>
                        {inBackground ? (
                            <Alert severity="info" sx={{ mb: 3 }}>
                                Researching {companyLabel} in the background.
                                Findings appear here as they are recorded.
                            </Alert>
                        ) : null}
                        {intel.status === "failed" ? (
                            <Alert severity="error" sx={{ mb: 3 }}>
                                The last research run did not finish (
                                {(
                                    intel.failure_reason ?? "unknown error"
                                ).replace(/\.$/, "")}
                                ). Retry to run it again.
                            </Alert>
                        ) : null}
                        <IntelFindings
                            company={intel.company}
                            role={intel.role}
                            position={application?.position ?? null}
                        />
                    </Container>
                </Box>

                <Box
                    role="tabpanel"
                    id="intel-tabpanel-chat"
                    aria-labelledby="intel-tab-chat"
                    sx={{
                        display: activeTab === TAB_CHAT ? undefined : "none",
                    }}
                >
                    <ChatInterface
                        key={chatKey}
                        ref={chatRef}
                        chatEndpoint={`${INTEL_API}/${intel.id}/chat`}
                        statusUrl={
                            aiSystemId !== null
                                ? `${MODEL_API}/${aiSystemId}/model-status`
                                : ""
                        }
                        warmupUrl={
                            aiSystemId !== null
                                ? `${MODEL_API}/${aiSystemId}/model-warmup`
                                : ""
                        }
                        initialMessages={messages as ChatMessage[]}
                        isAuthenticated={isAuthenticated}
                        sessionExpiresAt={sessionExpiresAt}
                        shouldAutoStart={shouldAutoStart}
                        slots={{
                            header:
                                conversation !== null ? (
                                    <Container
                                        sx={{
                                            display: "flex",
                                            justifyContent: "flex-end",
                                            mt: 1,
                                        }}
                                    >
                                        <SessionSystemSwitch
                                            url={`${INTEL_API}/${intel.id}/system-switch`}
                                            currentName={
                                                conversation.ai_system_name
                                            }
                                            canSwitch={conversation.can_switch}
                                            disabled={
                                                isStreaming || inBackground
                                            }
                                            onSwitched={reloadTranscript}
                                        />
                                    </Container>
                                ) : undefined,
                            aboveMessages: inBackground ? (
                                <Container>
                                    <Alert severity="info" sx={{ my: 2 }}>
                                        Researching in the background. The reply
                                        appears here when it finishes.
                                    </Alert>
                                </Container>
                            ) : undefined,
                        }}
                        onEvent={handleStreamEvent}
                        onStreamResponse={handleStreamResponse}
                        onStreamEnd={handleStreamEnd}
                    />
                </Box>

                <WrongCompanyDialog
                    key={intel.company?.id ?? "none"}
                    open={wrongCompanyOpen}
                    companyName={intel.company?.name ?? ""}
                    companyOptions={companyOptions.filter(
                        (option) => option.id !== intel.company?.id,
                    )}
                    isSubmitting={isCorrecting}
                    error={correctionError}
                    onSubmit={(correction) => {
                        void handleWrongCompany(correction);
                    }}
                    onClose={() => {
                        setWrongCompanyOpen(false);
                    }}
                />
            </AdminLayout>
        </>
    );
}
