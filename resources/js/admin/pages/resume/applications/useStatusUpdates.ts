import { router } from "@inertiajs/react";
import { useState } from "react";

import type { Application, StatusUpdate } from "@/types";

import { api, apiErrorMessage } from "@/api";

interface StatusUpdateResponse {
    success?: boolean;
    message?: string;
    status?: string;
    status_updates?: StatusUpdate[];
    allowed_next_statuses?: string[];
}

/** What a status endpoint reports back: the application's status and history. */
interface StatusSnapshot {
    status: string;
    statusUpdates: StatusUpdate[];
    allowedNextStatuses: string[];
}

interface MarkAppliedParams {
    /** Omit when the application's targeted resume is what was sent. */
    resumeVersionId?: number | null;
    occurredAt?: string | null;
}

type StatusSource = Pick<
    Application,
    "status" | "status_updates" | "allowed_next_statuses" | "has_applied"
>;

function toDateInputValue(isoDate: string): string {
    return isoDate.slice(0, 10);
}

export interface UseStatusUpdatesResult {
    /** The application's stored status (not the ghosted display status). */
    status: string;
    statusUpdates: StatusUpdate[];
    allowedNextStatuses: string[];
    /** An `applied` entry exists, so the resume is the record of what was sent. */
    hasApplied: boolean;
    selectedNextStatus: string;
    setSelectedNextStatus: (value: string) => void;
    statusNotes: string;
    setStatusNotes: (value: string) => void;
    statusOccurredAt: string;
    setStatusOccurredAt: (value: string) => void;
    isSubmittingStatus: boolean;
    statusError: string | null;
    editingStatusId: number | null;
    editingStatusNotes: string;
    setEditingStatusNotes: (value: string) => void;
    editingStatusOccurredAt: string;
    setEditingStatusOccurredAt: (value: string) => void;
    isSavingStatusEdit: boolean;
    isDeletingStatusId: number | null;
    showStatusUpdateForm: boolean;
    setShowStatusUpdateForm: (value: boolean) => void;
    /**
     * Resolves null when the application was recorded as applied, otherwise
     * the reason it was not. Reported to the caller rather than through
     * `statusError`, which belongs to the history actions.
     */
    markApplied: (params?: MarkAppliedParams) => Promise<string | null>;
    addStatusUpdate: () => Promise<void>;
    startEditingStatus: (statusUpdate: StatusUpdate) => void;
    cancelEditingStatus: () => void;
    saveStatusEdit: (statusUpdateId: number) => Promise<void>;
    deleteStatusUpdate: (statusUpdateId: number) => Promise<void>;
}

/**
 * Status history actions for one application.
 *
 * The status, history and allowed transitions are read from the `application`
 * prop rather than copied into state. A status endpoint's response is held as
 * a short-lived override so the page updates before the partial reload that
 * follows it lands. The override is dropped when that reload — this hook's
 * own — finishes, not whenever the prop changes: another reload already in
 * flight (a chat turn, a details save) can deliver an `application` rendered
 * before the status write, and must not snap the page back to it.
 */
export default function useStatusUpdates(
    applicationId: number,
    application: StatusSource,
): UseStatusUpdatesResult {
    const [override, setOverride] = useState<StatusSnapshot | null>(null);

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

    const baseUrl = `/api/admin/resume/applications/${applicationId}`;

    const status = override?.status ?? application.status;
    const statusUpdates = override?.statusUpdates ?? application.status_updates;
    const allowedNextStatuses =
        override?.allowedNextStatuses ?? application.allowed_next_statuses;
    const hasApplied = override
        ? override.statusUpdates.some((entry) => entry.status === "applied")
        : application.has_applied;

    /** Show the endpoint's answer now, then let the server refresh the prop. */
    const applyResponse = (data: StatusUpdateResponse): void => {
        const snapshot: StatusSnapshot = {
            status: data.status ?? status,
            statusUpdates: data.status_updates ?? [],
            allowedNextStatuses: data.allowed_next_statuses ?? [],
        };
        setOverride(snapshot);
        router.reload({
            only: ["application"],
            // Only this write's own override: a later write may have
            // replaced it while this reload was still on its way.
            onFinish: () => {
                setOverride((current) =>
                    current === snapshot ? null : current,
                );
            },
        });
    };

    const markApplied = async ({
        resumeVersionId = null,
        occurredAt = null,
    }: MarkAppliedParams = {}): Promise<string | null> => {
        setIsSubmittingStatus(true);
        try {
            const data = await api.post<StatusUpdateResponse>(
                `${baseUrl}/apply`,
                // Both are optional server-side; an absent key (not a null)
                // is what asks for the default.
                {
                    resume_version_id: resumeVersionId ?? undefined,
                    occurred_at: occurredAt ?? undefined,
                },
            );
            if (!data.success) {
                return data.message ?? "Failed to mark as applied.";
            }
            applyResponse(data);
            return null;
        } catch (error) {
            return apiErrorMessage(error, "Failed to mark as applied.");
        } finally {
            setIsSubmittingStatus(false);
        }
    };

    const addStatusUpdate = async (): Promise<void> => {
        if (!selectedNextStatus) {
            return;
        }
        setIsSubmittingStatus(true);
        setStatusError(null);
        try {
            const data = await api.post<StatusUpdateResponse>(
                `${baseUrl}/status-updates`,
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
            applyResponse(data);
            setSelectedNextStatus("");
            setStatusNotes("");
            setStatusOccurredAt("");
        } catch (error) {
            setStatusError(apiErrorMessage(error, "Failed to update status."));
        } finally {
            setIsSubmittingStatus(false);
        }
    };

    const startEditingStatus = (statusUpdate: StatusUpdate): void => {
        setEditingStatusId(statusUpdate.id);
        setEditingStatusNotes(statusUpdate.notes ?? "");
        setEditingStatusOccurredAt(toDateInputValue(statusUpdate.occurred_at));
        setStatusError(null);
    };

    const cancelEditingStatus = (): void => {
        setEditingStatusId(null);
        setEditingStatusNotes("");
        setEditingStatusOccurredAt("");
    };

    const saveStatusEdit = async (statusUpdateId: number): Promise<void> => {
        if (!editingStatusOccurredAt) {
            setStatusError("Date is required.");
            return;
        }

        setIsSavingStatusEdit(true);
        setStatusError(null);

        try {
            const data = await api.put<StatusUpdateResponse>(
                `${baseUrl}/status-updates/${statusUpdateId}`,
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

            applyResponse(data);
            cancelEditingStatus();
        } catch (error) {
            setStatusError(
                apiErrorMessage(error, "Failed to update status entry."),
            );
        } finally {
            setIsSavingStatusEdit(false);
        }
    };

    const deleteStatusUpdate = async (
        statusUpdateId: number,
    ): Promise<void> => {
        setIsDeletingStatusId(statusUpdateId);
        setStatusError(null);

        try {
            const data = await api.del<StatusUpdateResponse>(
                `${baseUrl}/status-updates/${statusUpdateId}`,
            );

            if (!data.success) {
                setStatusError(
                    data.message ?? "Failed to delete status update entry.",
                );
                return;
            }

            applyResponse(data);
            if (editingStatusId === statusUpdateId) {
                cancelEditingStatus();
            }
        } catch (error) {
            setStatusError(
                apiErrorMessage(error, "Failed to delete status update entry."),
            );
        } finally {
            setIsDeletingStatusId(null);
        }
    };

    return {
        status,
        statusUpdates,
        allowedNextStatuses,
        hasApplied,
        selectedNextStatus,
        setSelectedNextStatus,
        statusNotes,
        setStatusNotes,
        statusOccurredAt,
        setStatusOccurredAt,
        isSubmittingStatus,
        statusError,
        editingStatusId,
        editingStatusNotes,
        setEditingStatusNotes,
        editingStatusOccurredAt,
        setEditingStatusOccurredAt,
        isSavingStatusEdit,
        isDeletingStatusId,
        showStatusUpdateForm,
        setShowStatusUpdateForm,
        markApplied,
        addStatusUpdate,
        startEditingStatus,
        cancelEditingStatus,
        saveStatusEdit,
        deleteStatusUpdate,
    };
}
