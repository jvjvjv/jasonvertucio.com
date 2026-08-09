import { Link as InertiaLink } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Link from "@mui/material/Link";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";

import StatusHistoryList from "./StatusHistoryList";
import StatusUpdateForm from "./StatusUpdateForm";

import type { UseStatusUpdatesResult } from "./useStatusUpdates";
import type { Conversation, CoverLetter, TargetedResume } from "@/types";
import type { InertiaFormProps } from "@inertiajs/react";

import StatusChip from "@/admin/components/StatusChip";
import UsageChip from "@/admin/components/UsageChip";
import { resolveTargetedResumeDisplayStatus } from "@/admin/utils/targetedResumeStatus";

export interface MetadataFormData {
    title: string;
    company_name: string;
    job_title: string;
}

/**
 * Takes the whole `useStatusUpdates` bundle so the caller can spread it, plus
 * the conversation metadata this form owns.
 *
 * `deleteStatusUpdate` is narrowed to a sync callback: the page wraps the
 * hook's action in a confirmation dialog before it ever reaches this form.
 */
interface BuilderMetadataFormProps extends Omit<
    UseStatusUpdatesResult,
    "deleteStatusUpdate"
> {
    conversation: Conversation;
    metadataForm: InertiaFormProps<MetadataFormData>;
    onMetadataSave: () => void;
    targetedResume: TargetedResume | null;
    coverLetter: CoverLetter | null;
    deleteStatusUpdate: (statusUpdateId: number) => void;
}

export default function BuilderMetadataForm({
    conversation,
    metadataForm,
    onMetadataSave,
    targetedResume,
    statusUpdates,
    allowedNextStatuses,
    selectedNextStatus,
    statusOccurredAt,
    statusNotes,
    isSubmittingStatus,
    showStatusUpdateForm,
    editingStatusId,
    editingStatusOccurredAt,
    editingStatusNotes,
    isSavingStatusEdit,
    isDeletingStatusId,
    coverLetter,
    setShowStatusUpdateForm,
    setSelectedNextStatus,
    setStatusOccurredAt,
    setStatusNotes,
    addStatusUpdate,
    startEditingStatus,
    cancelEditingStatus,
    setEditingStatusOccurredAt,
    setEditingStatusNotes,
    saveStatusEdit,
    deleteStatusUpdate,
}: BuilderMetadataFormProps) {
    const latestStatusUpdate =
        statusUpdates.length > 0
            ? statusUpdates[statusUpdates.length - 1]
            : null;

    const displayStatus =
        targetedResume != null
            ? resolveTargetedResumeDisplayStatus({
                  conversationStatus: targetedResume.status,
                  resumeStatus: targetedResume.status,
                  latestStatusOccurredAt: latestStatusUpdate?.occurred_at,
              })
            : "draft";

    return (
        <Card>
            <CardContent>
                <Typography variant="h6" gutterBottom>
                    Conversation Details
                </Typography>
                <Box
                    component="form"
                    onSubmit={(e) => {
                        e.preventDefault();
                        onMetadataSave();
                    }}
                >
                    <TextField
                        label="Title"
                        size="small"
                        fullWidth
                        value={metadataForm.data.title}
                        onChange={(e) => {
                            metadataForm.setData("title", e.target.value);
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
                            metadataForm.setData("job_title", e.target.value);
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
                            <StatusChip status={displayStatus} />
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

                        <StatusHistoryList
                            statusUpdates={statusUpdates}
                            editingStatusId={editingStatusId}
                            editingStatusOccurredAt={editingStatusOccurredAt}
                            editingStatusNotes={editingStatusNotes}
                            isSavingStatusEdit={isSavingStatusEdit}
                            isDeletingStatusId={isDeletingStatusId}
                            onEditingStatusOccurredAtChange={
                                setEditingStatusOccurredAt
                            }
                            onEditingStatusNotesChange={setEditingStatusNotes}
                            onStartEditingStatus={startEditingStatus}
                            onCancelEditingStatus={cancelEditingStatus}
                            onSaveStatusEdit={saveStatusEdit}
                            onDeleteStatusUpdate={deleteStatusUpdate}
                        />

                        <StatusUpdateForm
                            allowedNextStatuses={allowedNextStatuses}
                            selectedNextStatus={selectedNextStatus}
                            statusOccurredAt={statusOccurredAt}
                            statusNotes={statusNotes}
                            isSubmittingStatus={isSubmittingStatus}
                            showStatusUpdateForm={showStatusUpdateForm}
                            onExpandedChange={setShowStatusUpdateForm}
                            onSelectedNextStatusChange={setSelectedNextStatus}
                            onStatusOccurredAtChange={setStatusOccurredAt}
                            onStatusNotesChange={setStatusNotes}
                            onSubmit={addStatusUpdate}
                        />
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
    );
}
