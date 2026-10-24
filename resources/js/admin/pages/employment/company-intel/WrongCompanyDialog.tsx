import Alert from "@mui/material/Alert";
import Autocomplete from "@mui/material/Autocomplete";
import Button from "@mui/material/Button";
import Dialog from "@mui/material/Dialog";
import DialogActions from "@mui/material/DialogActions";
import DialogContent from "@mui/material/DialogContent";
import DialogContentText from "@mui/material/DialogContentText";
import DialogTitle from "@mui/material/DialogTitle";
import FormControlLabel from "@mui/material/FormControlLabel";
import Radio from "@mui/material/Radio";
import RadioGroup from "@mui/material/RadioGroup";
import TextField from "@mui/material/TextField";
import { useState } from "react";

import type { CompanyOption } from "./types";

type Correction = "hint" | "existing";

interface WrongCompanyDialogProps {
    open: boolean;
    companyName: string;
    companyOptions: CompanyOption[];
    isSubmitting: boolean;
    error: string | null;
    onSubmit: (correction: { hint: string } | { companyId: number }) => void;
    onClose: () => void;
}

/**
 * Detaches the company the research attached, then either asks the agent to
 * research the right one from a hint, or attaches a company already on file.
 */
export default function WrongCompanyDialog({
    open,
    companyName,
    companyOptions,
    isSubmitting,
    error,
    onSubmit,
    onClose,
}: WrongCompanyDialogProps) {
    const [correction, setCorrection] = useState<Correction>("hint");
    const [hint, setHint] = useState("");
    const [existing, setExisting] = useState<CompanyOption | null>(null);

    const canSubmit =
        correction === "hint" ? hint.trim() !== "" : existing !== null;

    return (
        <Dialog
            open={open}
            onClose={isSubmitting ? undefined : onClose}
            maxWidth="sm"
            fullWidth
        >
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    if (!canSubmit) {
                        return;
                    }
                    if (correction === "hint") {
                        onSubmit({ hint: hint.trim() });
                    } else if (existing !== null) {
                        onSubmit({ companyId: existing.id });
                    }
                }}
            >
                <DialogTitle>Wrong company?</DialogTitle>
                <DialogContent>
                    <DialogContentText sx={{ mb: 2 }}>
                        {companyName} will be detached from this role and
                        won&rsquo;t be suggested for it again. If no other
                        application uses it, it is removed along with its
                        insights.
                    </DialogContentText>

                    <RadioGroup
                        value={correction}
                        onChange={(e) => {
                            setCorrection(e.target.value as Correction);
                        }}
                    >
                        <FormControlLabel
                            value="hint"
                            control={<Radio />}
                            label="Research again with a hint"
                        />
                        {correction === "hint" ? (
                            <TextField
                                autoFocus
                                fullWidth
                                size="small"
                                label="Correct company"
                                placeholder="Name, website, or both"
                                value={hint}
                                onChange={(e) => {
                                    setHint(e.target.value);
                                }}
                                slotProps={{ htmlInput: { maxLength: 500 } }}
                                sx={{
                                    ml: 4,
                                    mb: 1,
                                    width: "calc(100% - 32px)",
                                }}
                            />
                        ) : null}
                        <FormControlLabel
                            value="existing"
                            control={<Radio />}
                            label="Choose a company already on file"
                            disabled={companyOptions.length === 0}
                        />
                        {correction === "existing" ? (
                            <Autocomplete
                                options={companyOptions}
                                value={existing}
                                onChange={(_, value) => {
                                    setExisting(value);
                                }}
                                getOptionLabel={(option) =>
                                    option.website_domain
                                        ? `${option.name} (${option.website_domain})`
                                        : option.name
                                }
                                isOptionEqualToValue={(a, b) => a.id === b.id}
                                renderInput={(params) => (
                                    <TextField
                                        {...params}
                                        autoFocus
                                        size="small"
                                        label="Company"
                                    />
                                )}
                                sx={{ ml: 4, width: "calc(100% - 32px)" }}
                            />
                        ) : null}
                    </RadioGroup>

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
                        disabled={isSubmitting}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        variant="contained"
                        color="warning"
                        disabled={isSubmitting || !canSubmit}
                    >
                        {isSubmitting
                            ? "Detaching..."
                            : correction === "hint"
                              ? "Detach and research"
                              : "Detach and attach"}
                    </Button>
                </DialogActions>
            </form>
        </Dialog>
    );
}
