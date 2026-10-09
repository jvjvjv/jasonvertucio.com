import { Link as InertiaLink, router } from "@inertiajs/react";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Link from "@mui/material/Link";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import ResumeCard from "./ResumeCard";
import StatusHistoryList from "./StatusHistoryList";
import StatusUpdateForm from "./StatusUpdateForm";
import useGhostedAfterDays from "./useGhostedAfterDays";

import type { UseStatusUpdatesResult } from "./useStatusUpdates";
import type {
    Application,
    ApplicationConversation,
    CoverLetter,
    TargetedResume,
} from "@/types";
import type { SyntheticEvent } from "react";

import StatusChip from "@/admin/components/StatusChip";
import UsageChip from "@/admin/components/UsageChip";
import { resolveApplicationDisplayStatus } from "@/admin/utils/applicationStatus";
import { ApiError, api, apiErrorMessage } from "@/api";

interface DetailsFormData {
    title: string;
    company_name: string;
    job_title: string;
    location: string;
    fit_score: string;
    fit_summary: string;
    job_description: string;
}

type FieldErrors = Partial<{ [K in keyof DetailsFormData]: string }>;

type DetailsEdits = Partial<DetailsFormData>;

/**
 * The request body for a set of edits: only the fields the user changed. The
 * server updates only the keys it is sent, so a field nobody touched here —
 * a fit score the assistant has since rewritten, say — is never overwritten
 * with what this tab happened to be showing. A field emptied on purpose is
 * sent as an explicit null.
 */
function payloadFor(
    edits: DetailsEdits,
    hasConversation: boolean,
): { [field: string]: string | number | null } {
    const payload: { [field: string]: string | number | null } = {};

    // The title belongs to the AI session, so it is only sent when there is
    // one to rename.
    if (edits.title !== undefined && hasConversation) {
        payload.title = edits.title;
    }
    if (edits.company_name !== undefined) {
        payload.company_name = edits.company_name;
    }
    if (edits.job_title !== undefined) {
        payload.job_title = edits.job_title;
    }
    if (edits.job_description !== undefined) {
        payload.job_description = edits.job_description;
    }
    if (edits.location !== undefined) {
        payload.location = edits.location || null;
    }
    if (edits.fit_summary !== undefined) {
        payload.fit_summary = edits.fit_summary || null;
    }
    if (edits.fit_score !== undefined) {
        payload.fit_score =
            edits.fit_score === "" ? null : Number(edits.fit_score);
    }

    return payload;
}

/** First Laravel validation message per field from a 422 response. */
function fieldErrorsFrom(error: unknown): FieldErrors {
    if (!(error instanceof ApiError)) {
        return {};
    }

    const errors = (
        error.data as { errors?: { [field: string]: string[] } } | null
    )?.errors;

    return Object.fromEntries(
        Object.entries(errors ?? {}).map(([field, messages]) => [
            field,
            messages[0],
        ]),
    );
}

const sectionSx = {
    mt: 3,
    pt: 3,
    borderTop: 1,
    borderColor: "divider",
};

/**
 * Takes the whole `useStatusUpdates` bundle so the caller can spread it, plus
 * the application this tab describes.
 *
 * `deleteStatusUpdate` is narrowed to a sync callback: the page wraps the
 * hook's action in a confirmation dialog before it ever reaches this form.
 *
 * Only the user's unsaved edits are state here (not the page's, so typing
 * re-renders this tab only and not the chat beside it). Every field otherwise
 * shows the current `application` prop, so a value the assistant writes
 * mid-conversation appears here instead of a stale mount-time copy.
 */
interface DetailsFormProps extends Omit<
    UseStatusUpdatesResult,
    "deleteStatusUpdate"
> {
    application: Application;
    /** The application's AI session; null when it has none. */
    conversation: ApplicationConversation | null;
    targetedResume: TargetedResume | null;
    coverLetter: CoverLetter | null;
    deleteStatusUpdate: (statusUpdateId: number) => void;
    onDiscardResume: () => void;
}

export default function DetailsForm({
    application,
    conversation,
    targetedResume,
    coverLetter,
    status,
    statusUpdates,
    allowedNextStatuses,
    hasApplied,
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
    onDiscardResume,
}: DetailsFormProps) {
    const [edits, setEdits] = useState<DetailsEdits>({});
    const form: DetailsFormData = {
        title: conversation?.title ?? "",
        company_name: application.company_name,
        job_title: application.position,
        location: application.location ?? "",
        fit_score:
            application.fit_score !== null ? String(application.fit_score) : "",
        fit_summary: application.fit_summary ?? "",
        job_description: application.job_description,
        ...edits,
    };
    const [errors, setErrors] = useState<FieldErrors>({});
    const [saveError, setSaveError] = useState<string | null>(null);
    const [isSaving, setIsSaving] = useState(false);
    const ghostedAfterDays = useGhostedAfterDays();
    const [saved, setSaved] = useState(false);

    const setField = (field: keyof DetailsFormData, value: string) => {
        setEdits((prev) => ({ ...prev, [field]: value }));
        setSaved(false);
    };

    const handleSave = async (e: SyntheticEvent) => {
        e.preventDefault();

        const submitted = edits;
        const payload = payloadFor(submitted, conversation !== null);
        if (Object.keys(payload).length === 0) {
            return;
        }

        setIsSaving(true);
        setSaveError(null);
        setErrors({});
        setSaved(false);

        try {
            await api.put(
                `/api/admin/resume/applications/${application.id}`,
                payload,
            );
            setSaved(true);
            router.reload({
                only: ["application", "conversation"],
                // The saved edits are dropped once the props that replace
                // them have arrived, so the fields never flash the old
                // values. Anything typed since the save is kept.
                onFinish: () => {
                    setEdits((current) =>
                        Object.fromEntries(
                            Object.entries(current).filter(
                                ([field, value]) =>
                                    submitted[
                                        field as keyof DetailsFormData
                                    ] !== value,
                            ),
                        ),
                    );
                },
            });
        } catch (error) {
            const nextErrors = fieldErrorsFrom(error);
            setErrors(nextErrors);
            if (Object.keys(nextErrors).length === 0) {
                setSaveError(apiErrorMessage(error, "Failed to save details."));
            }
        } finally {
            setIsSaving(false);
        }
    };

    const latestStatusUpdate =
        statusUpdates.length > 0
            ? statusUpdates[statusUpdates.length - 1]
            : null;

    const displayStatus = resolveApplicationDisplayStatus(
        status,
        latestStatusUpdate?.occurred_at,
        ghostedAfterDays,
    );

    // `applied` is only ever recorded through Mark Applied, which confirms the
    // resume that was sent; the log form offers every later status.
    const loggableStatuses = allowedNextStatuses.filter(
        (next) => next !== "applied",
    );

    return (
        <Card>
            <CardContent>
                <Typography variant="h6" gutterBottom>
                    Application Details
                </Typography>
                <Box component="form" onSubmit={handleSave}>
                    {conversation ? (
                        <>
                            <TextField
                                label="Session Title"
                                size="small"
                                fullWidth
                                value={form.title}
                                onChange={(e) => {
                                    setField("title", e.target.value);
                                }}
                                error={!!errors.title}
                                helperText={errors.title}
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
                        </>
                    ) : null}
                    <TextField
                        label="Company Name"
                        size="small"
                        fullWidth
                        value={form.company_name}
                        onChange={(e) => {
                            setField("company_name", e.target.value);
                        }}
                        error={!!errors.company_name}
                        helperText={errors.company_name}
                        sx={{ mb: 2 }}
                    />
                    <TextField
                        label="Job Title"
                        size="small"
                        fullWidth
                        value={form.job_title}
                        onChange={(e) => {
                            setField("job_title", e.target.value);
                        }}
                        error={!!errors.job_title}
                        helperText={errors.job_title}
                        sx={{ mb: 2 }}
                    />
                    <Box
                        sx={{
                            display: "grid",
                            gap: 2,
                            gridTemplateColumns: { xs: "1fr", sm: "2fr 1fr" },
                            mb: 2,
                        }}
                    >
                        <TextField
                            label="Location"
                            size="small"
                            value={form.location}
                            onChange={(e) => {
                                setField("location", e.target.value);
                            }}
                            error={!!errors.location}
                            helperText={errors.location}
                        />
                        <TextField
                            label="Fit Score (%)"
                            type="number"
                            size="small"
                            value={form.fit_score}
                            onChange={(e) => {
                                setField("fit_score", e.target.value);
                            }}
                            error={!!errors.fit_score}
                            helperText={errors.fit_score}
                            slotProps={{
                                htmlInput: { min: 0, max: 100, step: 1 },
                            }}
                        />
                    </Box>
                    <TextField
                        label="Fit Summary"
                        size="small"
                        fullWidth
                        multiline
                        minRows={2}
                        value={form.fit_summary}
                        onChange={(e) => {
                            setField("fit_summary", e.target.value);
                        }}
                        error={!!errors.fit_summary}
                        helperText={errors.fit_summary}
                        sx={{ mb: 2 }}
                    />
                    <TextField
                        label="Job Description"
                        size="small"
                        fullWidth
                        multiline
                        rows={8}
                        value={form.job_description}
                        onChange={(e) => {
                            setField("job_description", e.target.value);
                        }}
                        error={!!errors.job_description}
                        helperText={errors.job_description}
                        sx={{ mb: 3 }}
                    />
                    {application.job_url ? (
                        <Box sx={{ mb: 3 }}>
                            <Typography
                                variant="caption"
                                color="text.secondary"
                                sx={{ display: "block" }}
                            >
                                Parsed Job URL
                            </Typography>
                            <Link
                                href={application.job_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                underline="hover"
                                sx={{ wordBreak: "break-all" }}
                            >
                                {application.job_url}
                            </Link>
                        </Box>
                    ) : null}
                    {saveError ? (
                        <Alert severity="error" sx={{ mb: 2 }}>
                            {saveError}
                        </Alert>
                    ) : null}
                    {saved ? (
                        <Alert severity="success" sx={{ mb: 2 }}>
                            Details saved.
                        </Alert>
                    ) : null}
                    <Box
                        sx={{
                            display: "flex",
                            justifyContent: "flex-end",
                        }}
                    >
                        <Button
                            type="submit"
                            variant="contained"
                            disabled={isSaving}
                        >
                            {isSaving ? "Saving..." : "Save Details"}
                        </Button>
                    </Box>
                </Box>

                <Box sx={sectionSx}>
                    <Typography variant="subtitle2" gutterBottom>
                        Resume
                    </Typography>
                    <ResumeCard
                        targetedResume={targetedResume}
                        resumeVersion={
                            application.resume_version?.version ?? null
                        }
                        hasApplied={hasApplied}
                        onDiscard={onDiscardResume}
                    />
                </Box>

                <Box sx={sectionSx}>
                    <Box
                        sx={{
                            display: "flex",
                            gap: 2,
                            alignItems: "center",
                            mb: 1,
                        }}
                    >
                        <Typography variant="subtitle2">
                            Application Status History
                        </Typography>
                        <StatusChip status={displayStatus} />
                    </Box>

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
                        allowedNextStatuses={loggableStatuses}
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

                {conversation ? (
                    <Box sx={sectionSx}>
                        <Typography variant="subtitle2" gutterBottom>
                            Chat Usage
                        </Typography>
                        <UsageChip usage={conversation.usage} />
                    </Box>
                ) : null}

                <Box sx={sectionSx}>
                    <Typography variant="subtitle2" gutterBottom>
                        Cover Letter
                    </Typography>
                    {coverLetter ? (
                        <Box sx={{ display: "flex", flexWrap: "wrap", gap: 1 }}>
                            <Button
                                component={InertiaLink}
                                href={`/admin/cover-letters/${coverLetter.id}`}
                                size="small"
                                variant="outlined"
                            >
                                View Cover Letter
                            </Button>
                            <Button
                                component="a"
                                href={`/admin/cover-letters/${coverLetter.id}/download/docx`}
                                size="small"
                            >
                                DOCX
                            </Button>
                            <Button
                                component="a"
                                href={`/admin/cover-letters/${coverLetter.id}/download/pdf`}
                                size="small"
                            >
                                PDF
                            </Button>
                        </Box>
                    ) : (
                        <>
                            <Typography
                                variant="body2"
                                color="text.secondary"
                                sx={{ mb: 1 }}
                            >
                                No cover letter yet.
                            </Typography>
                            <Button
                                component={InertiaLink}
                                href={`/admin/cover-letters/new?application=${application.id}`}
                                size="small"
                                variant="outlined"
                            >
                                Create cover letter
                            </Button>
                        </>
                    )}
                </Box>
            </CardContent>
        </Card>
    );
}
