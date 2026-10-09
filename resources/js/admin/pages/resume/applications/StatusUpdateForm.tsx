import ExpandMoreIcon from "@mui/icons-material/ExpandMore";
import UpdateIcon from "@mui/icons-material/Update";
import Accordion from "@mui/material/Accordion";
import AccordionDetails from "@mui/material/AccordionDetails";
import AccordionSummary from "@mui/material/AccordionSummary";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import FormControl from "@mui/material/FormControl";
import InputLabel from "@mui/material/InputLabel";
import MenuItem from "@mui/material/MenuItem";
import Select from "@mui/material/Select";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";

interface StatusUpdateFormProps {
    allowedNextStatuses: string[];
    selectedNextStatus: string;
    statusOccurredAt: string;
    statusNotes: string;
    isSubmittingStatus: boolean;
    showStatusUpdateForm: boolean;
    onExpandedChange: (expanded: boolean) => void;
    onSelectedNextStatusChange: (value: string) => void;
    onStatusOccurredAtChange: (value: string) => void;
    onStatusNotesChange: (value: string) => void;
    onSubmit: () => void;
}

export default function StatusUpdateForm({
    allowedNextStatuses,
    selectedNextStatus,
    statusOccurredAt,
    statusNotes,
    isSubmittingStatus,
    showStatusUpdateForm,
    onExpandedChange,
    onSelectedNextStatusChange,
    onStatusOccurredAtChange,
    onStatusNotesChange,
    onSubmit,
}: StatusUpdateFormProps) {
    if (allowedNextStatuses.length === 0) {
        return null;
    }

    return (
        <Accordion
            expanded={showStatusUpdateForm}
            onChange={(_, expanded) => {
                onExpandedChange(expanded);
            }}
        >
            <AccordionSummary expandIcon={<ExpandMoreIcon />}>
                <Typography variant="caption">Log Status Update</Typography>
            </AccordionSummary>
            <AccordionDetails>
                <Box
                    component="form"
                    onSubmit={(e) => {
                        e.preventDefault();
                        onSubmit();
                    }}
                >
                    <FormControl size="small" fullWidth sx={{ mb: 1 }}>
                        <InputLabel id="next-status-label">Status</InputLabel>
                        <Select
                            labelId="next-status-label"
                            label="Status"
                            value={selectedNextStatus}
                            onChange={(e) => {
                                onSelectedNextStatusChange(e.target.value);
                            }}
                        >
                            {allowedNextStatuses.map((status) => (
                                <MenuItem key={status} value={status}>
                                    {status.charAt(0).toUpperCase() +
                                        status.slice(1)}
                                </MenuItem>
                            ))}
                        </Select>
                    </FormControl>
                    <TextField
                        label={
                            selectedNextStatus === "interviewing"
                                ? "Scheduled date"
                                : "Date (optional)"
                        }
                        type="date"
                        size="small"
                        fullWidth
                        value={statusOccurredAt}
                        onChange={(e) => {
                            onStatusOccurredAtChange(e.target.value);
                        }}
                        slotProps={{
                            inputLabel: {
                                shrink: true,
                            },
                        }}
                        sx={{ mb: 1 }}
                    />
                    <TextField
                        label="Notes (optional)"
                        size="small"
                        fullWidth
                        multiline
                        rows={2}
                        value={statusNotes}
                        onChange={(e) => {
                            onStatusNotesChange(e.target.value);
                        }}
                        sx={{ mb: 1 }}
                    />
                    <Button
                        type="submit"
                        variant="outlined"
                        size="small"
                        startIcon={<UpdateIcon />}
                        disabled={!selectedNextStatus || isSubmittingStatus}
                    >
                        Log Status
                    </Button>
                </Box>
            </AccordionDetails>
        </Accordion>
    );
}
