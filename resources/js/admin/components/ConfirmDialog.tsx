import Button from "@mui/material/Button";
import Dialog from "@mui/material/Dialog";
import DialogActions from "@mui/material/DialogActions";
import DialogContent from "@mui/material/DialogContent";
import DialogContentText from "@mui/material/DialogContentText";
import DialogTitle from "@mui/material/DialogTitle";

export interface ConfirmDialogProps {
    open: boolean;
    title?: string;
    message: string;
    confirmLabel?: string;
    confirmColor?:
        | "error"
        | "warning"
        | "primary"
        | "secondary"
        | "info"
        | "success";
    onConfirm: () => void;
    onCancel: () => void;
}

export default function ConfirmDialog({
    open,
    title = "Confirm",
    message,
    confirmLabel = "Confirm",
    confirmColor = "error",
    onConfirm,
    onCancel,
}: ConfirmDialogProps) {
    return (
        <Dialog open={open} onClose={onCancel} maxWidth="xs" fullWidth>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    onConfirm();
                }}
            >
                <DialogTitle>{title}</DialogTitle>
                <DialogContent>
                    <DialogContentText>{message}</DialogContentText>
                </DialogContent>
                <DialogActions>
                    <Button type="button" onClick={onCancel}>
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        color={confirmColor}
                        variant="contained"
                    >
                        {confirmLabel}
                    </Button>
                </DialogActions>
            </form>
        </Dialog>
    );
}
