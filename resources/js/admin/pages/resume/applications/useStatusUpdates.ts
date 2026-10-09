import { router } from "@inertiajs/react";
import { useState } from "react";

import type { StatusUpdate, TargetedResume } from "@/types";

import { api, apiErrorMessage } from "@/api";

interface StatusUpdateResponse {
    success?: boolean;
    message?: string;
    status_updates?: StatusUpdate[];
    allowed_next_statuses?: string[];
}

function toDateInputValue(isoDate: string): string {
    return isoDate.slice(0, 10);
}

export interface UseStatusUpdatesResult {
    statusUpdates: StatusUpdate[];
    allowedNextStatuses: string[];
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
    markApplied: () => Promise<void>;
    addStatusUpdate: () => Promise<void>;
    startEditingStatus: (statusUpdate: StatusUpdate) => void;
    cancelEditingStatus: () => void;
    saveStatusEdit: (statusUpdateId: number) => Promise<void>;
    deleteStatusUpdate: (statusUpdateId: number) => Promise<void>;
}

export default function useStatusUpdates(
    conversationId: number,
    targetedResume: TargetedResume | null,
): UseStatusUpdatesResult {
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

    const markApplied = async (): Promise<void> => {
        setIsSubmittingStatus(true);
        setStatusError(null);
        try {
            const data = await api.post<StatusUpdateResponse>(
                `/api/admin/resume/targeted-builder/${conversationId}/status-update`,
                { status: "applied" },
            );
            if (!data.success) {
                setStatusError(data.message ?? "Failed to mark as applied.");
                return;
            }
            setStatusUpdates(data.status_updates ?? []);
            setAllowedNextStatuses(data.allowed_next_statuses ?? []);
            router.reload({ only: ["targetedResume"] });
        } catch (error) {
            setStatusError(
                apiErrorMessage(error, "Failed to mark as applied."),
            );
        } finally {
            setIsSubmittingStatus(false);
        }
    };

    const addStatusUpdate = async (): Promise<void> => {
        if (!selectedNextStatus || !targetedResume) {
            return;
        }
        setIsSubmittingStatus(true);
        setStatusError(null);
        try {
            const data = await api.post<StatusUpdateResponse>(
                `/api/admin/resume/targeted-builder/${conversationId}/status-update`,
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
                `/api/admin/resume/targeted-builder/${conversationId}/status-update/${statusUpdateId}`,
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
                `/api/admin/resume/targeted-builder/${conversationId}/status-update/${statusUpdateId}`,
            );

            if (!data.success) {
                setStatusError(
                    data.message ?? "Failed to delete status update entry.",
                );
                return;
            }

            setStatusUpdates(data.status_updates ?? []);
            setAllowedNextStatuses(data.allowed_next_statuses ?? []);
            if (editingStatusId === statusUpdateId) {
                cancelEditingStatus();
            }

            router.reload({ only: ["targetedResume"] });
        } catch (error) {
            setStatusError(
                apiErrorMessage(error, "Failed to delete status update entry."),
            );
        } finally {
            setIsDeletingStatusId(null);
        }
    };

    return {
        statusUpdates,
        allowedNextStatuses,
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
