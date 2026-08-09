import CloseIcon from "@mui/icons-material/Close";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import EditIcon from "@mui/icons-material/Edit";
import SaveIcon from "@mui/icons-material/Save";
import Box from "@mui/material/Box";
import Divider from "@mui/material/Divider";
import IconButton from "@mui/material/IconButton";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";

import type { StatusUpdate } from "@/types";

import StatusChip from "@/admin/components/StatusChip";
import { formatCalendarDate } from "@/utils/date";

interface StatusHistoryListProps {
    statusUpdates: StatusUpdate[];
    editingStatusId: number | null;
    editingStatusOccurredAt: string;
    editingStatusNotes: string;
    isSavingStatusEdit: boolean;
    isDeletingStatusId: number | null;
    onEditingStatusOccurredAtChange: (value: string) => void;
    onEditingStatusNotesChange: (value: string) => void;
    onStartEditingStatus: (statusUpdate: StatusUpdate) => void;
    onCancelEditingStatus: () => void;
    onSaveStatusEdit: (statusUpdateId: number) => void;
    onDeleteStatusUpdate: (statusUpdateId: number) => void;
}

export default function StatusHistoryList({
    statusUpdates,
    editingStatusId,
    editingStatusOccurredAt,
    editingStatusNotes,
    isSavingStatusEdit,
    isDeletingStatusId,
    onEditingStatusOccurredAtChange,
    onEditingStatusNotesChange,
    onStartEditingStatus,
    onCancelEditingStatus,
    onSaveStatusEdit,
    onDeleteStatusUpdate,
}: StatusHistoryListProps) {
    if (statusUpdates.length === 0) {
        return (
            <Typography variant="body2" color="text.secondary">
                No status updates yet.
            </Typography>
        );
    }

    return (
        <Box
            sx={{
                display: "flex",
                flexDirection: "column",
                gap: 1,
            }}
        >
            {statusUpdates.map((statusUpdate, index) => (
                <Box key={statusUpdate.id}>
                    {index > 0 && <Divider sx={{ mb: 1 }} />}
                    <Box
                        sx={{
                            display: "flex",
                            alignItems: "flex-start",
                            justifyContent: "space-between",
                            gap: 1,
                        }}
                    >
                        <Box
                            sx={{
                                display: "flex",
                                gap: 1,
                                alignItems: "flex-start",
                            }}
                        >
                            <StatusChip status={statusUpdate.status} />
                            <Box>
                                {editingStatusId === statusUpdate.id ? (
                                    <TextField
                                        label="Date"
                                        type="date"
                                        size="small"
                                        value={editingStatusOccurredAt}
                                        onChange={(e) => {
                                            onEditingStatusOccurredAtChange(
                                                e.target.value,
                                            );
                                        }}
                                        slotProps={{
                                            inputLabel: {
                                                shrink: true,
                                            },
                                        }}
                                        sx={{ mb: 1, minWidth: 180 }}
                                    />
                                ) : (
                                    <Typography
                                        variant="caption"
                                        color="text.secondary"
                                    >
                                        {formatCalendarDate(
                                            statusUpdate.occurred_at,
                                        )}
                                    </Typography>
                                )}
                                {editingStatusId === statusUpdate.id ? (
                                    <TextField
                                        label="Notes (optional)"
                                        size="small"
                                        fullWidth
                                        multiline
                                        rows={2}
                                        value={editingStatusNotes}
                                        onChange={(e) => {
                                            onEditingStatusNotesChange(
                                                e.target.value,
                                            );
                                        }}
                                        sx={{
                                            mt: 0.25,
                                            minWidth: {
                                                xs: 200,
                                                sm: 320,
                                            },
                                        }}
                                    />
                                ) : (
                                    statusUpdate.notes && (
                                        <Typography
                                            variant="body2"
                                            sx={{ mt: 0.25 }}
                                        >
                                            {statusUpdate.notes}
                                        </Typography>
                                    )
                                )}
                            </Box>
                        </Box>
                        <Box
                            sx={{
                                display: "flex",
                                alignItems: "center",
                                gap: 0.5,
                                ml: 1,
                            }}
                        >
                            {editingStatusId === statusUpdate.id ? (
                                <>
                                    <IconButton
                                        size="small"
                                        title="Save status update"
                                        color="primary"
                                        onClick={() => {
                                            onSaveStatusEdit(statusUpdate.id);
                                        }}
                                        disabled={
                                            isSavingStatusEdit ||
                                            isDeletingStatusId ===
                                                statusUpdate.id
                                        }
                                    >
                                        <SaveIcon fontSize="small" />
                                    </IconButton>
                                    <IconButton
                                        size="small"
                                        title="Cancel editing"
                                        onClick={onCancelEditingStatus}
                                        disabled={
                                            isSavingStatusEdit ||
                                            isDeletingStatusId ===
                                                statusUpdate.id
                                        }
                                    >
                                        <CloseIcon fontSize="small" />
                                    </IconButton>
                                </>
                            ) : (
                                <IconButton
                                    size="small"
                                    title="Edit date and notes"
                                    onClick={() => {
                                        onStartEditingStatus(statusUpdate);
                                    }}
                                    disabled={
                                        isDeletingStatusId === statusUpdate.id
                                    }
                                >
                                    <EditIcon fontSize="small" />
                                </IconButton>
                            )}
                            <IconButton
                                size="small"
                                title="Delete status update"
                                color="error"
                                onClick={() => {
                                    onDeleteStatusUpdate(statusUpdate.id);
                                }}
                                disabled={
                                    isDeletingStatusId === statusUpdate.id ||
                                    isSavingStatusEdit
                                }
                            >
                                <DeleteOutlineIcon fontSize="small" />
                            </IconButton>
                        </Box>
                    </Box>
                </Box>
            ))}
        </Box>
    );
}
