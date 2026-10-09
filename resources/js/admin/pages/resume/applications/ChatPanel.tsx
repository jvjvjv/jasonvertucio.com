import { Link as InertiaLink, router, usePage } from "@inertiajs/react";
import EditIcon from "@mui/icons-material/Edit";
import PictureAsPdfIcon from "@mui/icons-material/PictureAsPdf";
import StickyNote2Icon from "@mui/icons-material/StickyNote2";
import Box from "@mui/material/Box";
import Container from "@mui/material/Container";
import IconButton from "@mui/material/IconButton";
import { memo } from "react";

import ArtifactStatusCard from "./ArtifactStatusCard";
import StatusBar from "./StatusBar";

import type { ChatMessage, StreamEvent } from "@/components/ChatInterface";
import type {
    Application,
    ApplicationConversation,
    CoverLetter,
    SharedProps,
    StatusUpdate,
    TargetedResume,
} from "@/types";

import ChatInterface from "@/components/ChatInterface";

interface ChatPanelProps {
    isAuthenticated: boolean;
    application: Application;
    /** The application's stored status, including changes not yet reloaded. */
    status: string;
    statusUpdates: StatusUpdate[];
    conversation: ApplicationConversation;
    targetedResume: TargetedResume | null;
    coverLetter: CoverLetter | null;
    initialMessages: ChatMessage[];
    shouldAutoStart: boolean;
    canFinalizeResume: boolean;
    isFinalizing: boolean;
    hasNewerResume: boolean;
    onFinalizeResume: () => void;
    canFinalizeCoverLetter: boolean;
    isFinalizingCoverLetter: boolean;
    onFinalizeCoverLetter: () => void;
    onMessagesChange: (messages: ChatMessage[]) => void;
}

const APPLICATIONS_API = "/api/admin/resume/applications";

/**
 * A turn can change the application (fit, status), the resume, the cover
 * letter and the session itself. `messages` is deliberately left out: the
 * live transcript is ahead of the server's until the turn ends.
 */
function handleStreamEvent(event: StreamEvent): void {
    if (event.type === "page_reload") {
        router.reload({
            only: [
                "application",
                "targetedResume",
                "coverLetter",
                "conversation",
            ],
        });
    }
}

/**
 * The chat tab of an application that has an AI session.
 *
 * Memoized: the Discussion page re-renders on every keystroke in its status
 * form and whenever a dialog opens, none of which concerns the transcript.
 */
export default memo(function ChatPanel({
    isAuthenticated,
    application,
    status,
    statusUpdates,
    conversation,
    targetedResume,
    coverLetter,
    initialMessages,
    shouldAutoStart,
    canFinalizeResume,
    isFinalizing,
    hasNewerResume,
    onFinalizeResume,
    canFinalizeCoverLetter,
    isFinalizingCoverLetter,
    onFinalizeCoverLetter,
    onMessagesChange,
}: ChatPanelProps) {
    const page = usePage<SharedProps>();
    const sessionExpiresAt = page.props.session.expiresAt;

    const aiSystemId = conversation.ai_system_id;
    const statusUrl =
        aiSystemId !== null
            ? `${APPLICATIONS_API}/ai-systems/${aiSystemId}/model-status`
            : "";
    const warmupUrl =
        aiSystemId !== null
            ? `${APPLICATIONS_API}/ai-systems/${aiSystemId}/model-warmup`
            : "";

    return (
        <ChatInterface
            chatEndpoint={`${APPLICATIONS_API}/${application.id}/chat`}
            statusUrl={statusUrl}
            warmupUrl={warmupUrl}
            initialMessages={initialMessages}
            isAuthenticated={isAuthenticated}
            sessionExpiresAt={sessionExpiresAt}
            shouldAutoStart={shouldAutoStart}
            messagePadding={140}
            slots={{
                header: (
                    <Container>
                        <Box
                            sx={{
                                display: "grid",
                                gap: 2,
                                mt: 1,
                                gridTemplateColumns: {
                                    xs: "1fr",
                                    md: "1fr 1fr",
                                },
                            }}
                        >
                            <ArtifactStatusCard
                                label="Resume"
                                isFinalized={!!targetedResume}
                                color="success"
                                canFinalize={
                                    canFinalizeResume || !!targetedResume
                                }
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
                                onFinalize={onFinalizeResume}
                                caption={
                                    targetedResume
                                        ? `${application.company_name} — ${application.position}${application.fit_score !== null ? ` · Fit: ${application.fit_score}%` : ""}`
                                        : undefined
                                }
                                extraActions={
                                    targetedResume ? (
                                        <>
                                            <IconButton
                                                size="small"
                                                component="a"
                                                href={`/admin/resume/targeted-resume/${targetedResume.id}/download/docx`}
                                                title="Download resume DOCX"
                                                aria-label="Download resume DOCX"
                                                color="success"
                                            >
                                                <StickyNote2Icon fontSize="small" />
                                            </IconButton>
                                            <IconButton
                                                size="small"
                                                component="a"
                                                href={`/admin/resume/targeted-resume/${targetedResume.id}/download/pdf`}
                                                title="Download resume PDF"
                                                aria-label="Download resume PDF"
                                                color="success"
                                            >
                                                <PictureAsPdfIcon fontSize="small" />
                                            </IconButton>
                                        </>
                                    ) : undefined
                                }
                            />
                            <ArtifactStatusCard
                                label="Cover Letter"
                                isFinalized={!!coverLetter}
                                color="secondary"
                                canFinalize={canFinalizeCoverLetter}
                                isFinalizing={isFinalizingCoverLetter}
                                hasUpdate={!!coverLetter}
                                finalizeTitle={
                                    !canFinalizeCoverLetter
                                        ? "Finalize is available after the assistant returns a cover letter block"
                                        : coverLetter
                                          ? "Update the cover letter from the latest chat content"
                                          : "Extract and save the cover letter from the conversation"
                                }
                                onFinalize={onFinalizeCoverLetter}
                                caption={
                                    coverLetter
                                        ? `${coverLetter.company_name ?? ""} ${coverLetter.position ?? ""}`.trim() ||
                                          "Cover letter saved"
                                        : undefined
                                }
                                extraActions={
                                    coverLetter ? (
                                        <>
                                            <IconButton
                                                size="small"
                                                component="a"
                                                href={`/admin/cover-letters/${coverLetter.id}/download/docx`}
                                                title="Download cover letter DOCX"
                                                aria-label="Download cover letter DOCX"
                                                color="secondary"
                                            >
                                                <StickyNote2Icon fontSize="small" />
                                            </IconButton>
                                            <IconButton
                                                size="small"
                                                component="a"
                                                href={`/admin/cover-letters/${coverLetter.id}/download/pdf`}
                                                title="Download cover letter PDF"
                                                aria-label="Download cover letter PDF"
                                                color="secondary"
                                            >
                                                <PictureAsPdfIcon fontSize="small" />
                                            </IconButton>
                                            <IconButton
                                                size="small"
                                                component={InertiaLink}
                                                href={`/admin/cover-letters/${coverLetter.id}`}
                                                title="Edit cover letter"
                                                aria-label="Edit cover letter"
                                            >
                                                <EditIcon fontSize="small" />
                                            </IconButton>
                                        </>
                                    ) : undefined
                                }
                            />
                        </Box>
                        <StatusBar
                            status={status}
                            statusUpdates={statusUpdates}
                            fitScore={application.fit_score}
                            usage={conversation.usage}
                            sx={{ my: 2 }}
                        />
                    </Container>
                ),
            }}
            onEvent={handleStreamEvent}
            onMessagesChange={onMessagesChange}
        />
    );
});
