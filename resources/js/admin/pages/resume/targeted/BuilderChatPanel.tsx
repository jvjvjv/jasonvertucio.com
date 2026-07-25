import { Link as InertiaLink, router, usePage } from "@inertiajs/react";
import EditIcon from "@mui/icons-material/Edit";
import PictureAsPdfIcon from "@mui/icons-material/PictureAsPdf";
import StickyNote2Icon from "@mui/icons-material/StickyNote2";
import Box from "@mui/material/Box";
import Container from "@mui/material/Container";
import IconButton from "@mui/material/IconButton";

import BuilderStatusCard from "./BuilderStatusCard";
import TargetedBuilderStatusBar from "./TargetedBuilderStatusBar";
import useStatusUpdates from "./useStatusUpdates";

import type { ChatMessage } from "@/components/ChatInterface";
import type {
    Conversation,
    CoverLetter,
    SharedProps,
    TargetedResume,
} from "@/types";

import ChatInterface from "@/components/ChatInterface";

interface BuilderChatPanelProps {
    authUser: unknown;
    conversation: Conversation;
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
    hasCoverLetterUpdate: boolean;
    onFinalizeCoverLetter: () => void;
    statusUrl: string;
    warmupUrl: string;
    onMessagesChange: (messages: ChatMessage[]) => void;
}

export default function BuilderChatPanel({
    authUser,
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
    hasCoverLetterUpdate,
    onFinalizeCoverLetter,
    statusUrl,
    warmupUrl,
    onMessagesChange,
}: BuilderChatPanelProps) {
    const page = usePage<SharedProps>();
    const sessionExpiresAt = page.props.session.expiresAt;
    const status = useStatusUpdates(conversation.id, targetedResume);

    return (
        <>
            <ChatInterface
                chatEndpoint={`/api/admin/resume/targeted-builder/${conversation.id}/chat`}
                statusUrl={statusUrl}
                warmupUrl={warmupUrl}
                initialMessages={initialMessages}
                isAuthenticated={!!authUser}
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
                                <BuilderStatusCard
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
                                    hasUpdate={hasCoverLetterUpdate}
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
                            <TargetedBuilderStatusBar
                                conversation={conversation}
                                targetedResume={targetedResume}
                                statusUpdates={status.statusUpdates}
                                sx={{
                                    my: 2,
                                }}
                            />
                        </Container>
                    ),
                }}
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
                onMessagesChange={onMessagesChange}
            />
        </>
    );
}
