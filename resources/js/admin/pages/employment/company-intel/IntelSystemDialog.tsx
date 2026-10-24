import { Link as InertiaLink } from "@inertiajs/react";
import Alert from "@mui/material/Alert";
import Button from "@mui/material/Button";
import Dialog from "@mui/material/Dialog";
import DialogActions from "@mui/material/DialogActions";
import DialogContent from "@mui/material/DialogContent";
import DialogContentText from "@mui/material/DialogContentText";
import DialogTitle from "@mui/material/DialogTitle";
import FormControlLabel from "@mui/material/FormControlLabel";
import Link from "@mui/material/Link";
import Radio from "@mui/material/Radio";
import RadioGroup from "@mui/material/RadioGroup";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import type { IntelSystemOption } from "./types";

interface IntelSystemDialogProps {
    open: boolean;
    systems: IntelSystemOption[];
    isStarting: boolean;
    error: string | null;
    onChoose: (aiSystemId: number) => void;
    onClose: () => void;
}

/**
 * Asked only when no AI system is the `company-intel` default: which of the
 * systems using the Company Intel prompt should do the research.
 */
export default function IntelSystemDialog({
    open,
    systems,
    isStarting,
    error,
    onChoose,
    onClose,
}: IntelSystemDialogProps) {
    const firstId: number | null = systems.length > 0 ? systems[0].id : null;
    const [selectedId, setSelectedId] = useState<number | null>(firstId);
    const effectiveId: number | null =
        selectedId !== null && systems.some((s) => s.id === selectedId)
            ? selectedId
            : firstId;

    return (
        <Dialog
            open={open}
            onClose={isStarting ? undefined : onClose}
            maxWidth="xs"
            fullWidth
        >
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    if (effectiveId !== null) {
                        onChoose(effectiveId);
                    }
                }}
            >
                <DialogTitle>Choose an AI system for intel</DialogTitle>
                <DialogContent>
                    {systems.length === 0 ? (
                        <DialogContentText>
                            No AI system uses the Company Intel prompt yet.
                            Assign it to a system in{" "}
                            <Link
                                component={InertiaLink}
                                href="/admin/ai/systems"
                            >
                                AI Systems
                            </Link>
                            , or make one the company-intel default to skip this
                            step.
                        </DialogContentText>
                    ) : (
                        <>
                            <DialogContentText sx={{ mb: 1 }}>
                                There is no default for intel, so pick the
                                system that will research this company and role.
                            </DialogContentText>
                            <RadioGroup
                                value={effectiveId ?? ""}
                                onChange={(e) => {
                                    setSelectedId(Number(e.target.value));
                                }}
                            >
                                {systems.map((system) => (
                                    <FormControlLabel
                                        key={system.id}
                                        value={system.id}
                                        control={<Radio />}
                                        label={
                                            <>
                                                {system.name}{" "}
                                                <Typography
                                                    component="span"
                                                    variant="body2"
                                                    color="text.secondary"
                                                >
                                                    {system.model}
                                                </Typography>
                                            </>
                                        }
                                    />
                                ))}
                            </RadioGroup>
                        </>
                    )}
                    {error ? (
                        <Alert severity="error" sx={{ mt: 2 }}>
                            {error}
                        </Alert>
                    ) : null}
                </DialogContent>
                <DialogActions>
                    <Button
                        type="button"
                        onClick={onClose}
                        disabled={isStarting}
                    >
                        {systems.length === 0 ? "Close" : "Cancel"}
                    </Button>
                    {systems.length > 0 ? (
                        <Button
                            type="submit"
                            variant="contained"
                            disabled={isStarting || effectiveId === null}
                        >
                            {isStarting ? "Starting..." : "Begin intel"}
                        </Button>
                    ) : null}
                </DialogActions>
            </form>
        </Dialog>
    );
}
