import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Checkbox from "@mui/material/Checkbox";
import Dialog from "@mui/material/Dialog";
import DialogActions from "@mui/material/DialogActions";
import DialogContent from "@mui/material/DialogContent";
import DialogTitle from "@mui/material/DialogTitle";
import FormControl from "@mui/material/FormControl";
import InputLabel from "@mui/material/InputLabel";
import ListItemText from "@mui/material/ListItemText";
import MenuItem from "@mui/material/MenuItem";
import OutlinedInput from "@mui/material/OutlinedInput";
import Select, { type SelectChangeEvent } from "@mui/material/Select";
import { useTheme } from "@mui/material/styles";
import ToggleButton from "@mui/material/ToggleButton";
import ToggleButtonGroup from "@mui/material/ToggleButtonGroup";
import Typography from "@mui/material/Typography";
import useMediaQuery from "@mui/material/useMediaQuery";
import { useState } from "react";

export type UsesTargetedResume = "any" | "yes" | "no";

export interface StatusOption {
    value: string;
    label: string;
}

/** The filters this dialog edits. Search is not one of them: it stays inline. */
export interface DialogFilters {
    statuses: string[];
    usesTargetedResume: UsesTargetedResume;
}

interface FiltersDialogProps {
    open: boolean;
    allStatuses: StatusOption[];
    /** The filters currently applied, which the dialog opens showing. */
    value: DialogFilters;
    onApply: (filters: DialogFilters) => void;
    onClear: () => void;
    onClose: () => void;
}

const USES_TARGETED_RESUME_OPTIONS: {
    value: UsesTargetedResume;
    label: string;
}[] = [
    { value: "any", label: "Any" },
    { value: "yes", label: "Yes" },
    { value: "no", label: "No" },
];

/** How many of the dialog's filters are active, for the Filter button's badge. */
export function countActiveFilters(filters: DialogFilters): number {
    return (
        (filters.statuses.length > 0 ? 1 : 0) +
        (filters.usesTargetedResume !== "any" ? 1 : 0)
    );
}

type FiltersFormProps = Omit<FiltersDialogProps, "open">;

/**
 * The dialog's contents. Mounted only while the dialog is open, so the draft
 * starts from the applied filters every time without an effect to resync it.
 */
function FiltersForm({
    allStatuses,
    value,
    onApply,
    onClear,
    onClose,
}: FiltersFormProps) {
    const [statuses, setStatuses] = useState<string[]>(value.statuses);
    const [usesTargetedResume, setUsesTargetedResume] =
        useState<UsesTargetedResume>(value.usesTargetedResume);

    const handleStatusChange = (event: SelectChangeEvent<string[]>) => {
        const selected = event.target.value;
        setStatuses(
            typeof selected === "string" ? selected.split(",") : selected,
        );
    };

    return (
        <Box
            component="form"
            onSubmit={(e) => {
                e.preventDefault();
                onApply({ statuses, usesTargetedResume });
            }}
            sx={{
                display: "flex",
                flexDirection: "column",
                flexGrow: 1,
                minHeight: 0,
            }}
        >
            <DialogTitle>Filter applications</DialogTitle>
            <DialogContent
                sx={{ display: "flex", flexDirection: "column", gap: 3 }}
            >
                <FormControl size="small" fullWidth sx={{ mt: 1 }}>
                    <InputLabel id="application-statuses-label" shrink>
                        Statuses
                    </InputLabel>
                    <Select
                        labelId="application-statuses-label"
                        multiple
                        displayEmpty
                        value={statuses}
                        onChange={handleStatusChange}
                        input={<OutlinedInput label="Statuses" notched />}
                        renderValue={(selected) =>
                            selected.length === 0
                                ? "All statuses"
                                : allStatuses
                                      .filter((status) =>
                                          selected.includes(status.value),
                                      )
                                      .map((status) => status.label)
                                      .join(", ")
                        }
                    >
                        {allStatuses.map((status) => (
                            <MenuItem key={status.value} value={status.value}>
                                <Checkbox
                                    size="small"
                                    checked={statuses.includes(status.value)}
                                />
                                <ListItemText primary={status.label} />
                            </MenuItem>
                        ))}
                    </Select>
                </FormControl>

                <Box>
                    <Typography
                        id="uses-targeted-resume-label"
                        variant="body2"
                        color="text.secondary"
                        sx={{ mb: 1 }}
                    >
                        Uses targeted resume
                    </Typography>
                    <ToggleButtonGroup
                        exclusive
                        fullWidth
                        size="small"
                        color="primary"
                        aria-labelledby="uses-targeted-resume-label"
                        value={usesTargetedResume}
                        onChange={(_, next: UsesTargetedResume | null) => {
                            // An exclusive group reports null when the active
                            // button is clicked again; keep the choice.
                            if (next !== null) {
                                setUsesTargetedResume(next);
                            }
                        }}
                    >
                        {USES_TARGETED_RESUME_OPTIONS.map((option) => (
                            <ToggleButton
                                key={option.value}
                                value={option.value}
                            >
                                {option.label}
                            </ToggleButton>
                        ))}
                    </ToggleButtonGroup>
                </Box>
            </DialogContent>
            <DialogActions>
                <Button type="button" color="inherit" onClick={onClear}>
                    Clear
                </Button>
                <Box sx={{ flexGrow: 1 }} />
                <Button type="button" onClick={onClose}>
                    Cancel
                </Button>
                <Button type="submit" variant="contained">
                    Apply
                </Button>
            </DialogActions>
        </Box>
    );
}

/**
 * Every Applications list filter except search. Full-screen below `sm`, which
 * is the action-sheet behaviour on a phone.
 */
export default function FiltersDialog({
    open,
    onClose,
    ...formProps
}: FiltersDialogProps) {
    const theme = useTheme();
    const fullScreen = useMediaQuery(theme.breakpoints.down("sm"));

    return (
        <Dialog
            open={open}
            onClose={onClose}
            fullScreen={fullScreen}
            maxWidth="xs"
            fullWidth
        >
            <FiltersForm onClose={onClose} {...formProps} />
        </Dialog>
    );
}
