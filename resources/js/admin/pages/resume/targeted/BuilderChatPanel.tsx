import { Link as InertiaLink, router } from "@inertiajs/react";
import EditIcon from "@mui/icons-material/Edit";
import PictureAsPdfIcon from "@mui/icons-material/PictureAsPdf";
import StickyNote2Icon from "@mui/icons-material/StickyNote2";
import Box from "@mui/material/Box";
import IconButton from "@mui/material/IconButton";

import BuilderStatusCard from "./BuilderStatusCard";

import type { ChatMessage } from "@/components/ChatInterface";
import type { Conversation, CoverLetter, TargetedResume } from "@/types";

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
    return (
        <>
            <Box
                sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
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

            <ChatInterface
                chatEndpoint={`/api/admin/resume/targeted-builder/${conversation.id}/chat`}
                statusUrl={statusUrl}
                warmupUrl={warmupUrl}
                initialMessages={initialMessages}
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
                onMessagesChange={onMessagesChange}
            />
        </>
    );
}
