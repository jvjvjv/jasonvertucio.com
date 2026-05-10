import {
    Box,
    Checkbox,
    FormControlLabel,
    MenuItem,
    TextField,
} from "@mui/material";
import { DatePicker } from "@mui/x-date-pickers/DatePicker";
import { LocalizationProvider } from "@mui/x-date-pickers/LocalizationProvider";
import { AdapterDayjs } from "@mui/x-date-pickers/AdapterDayjs";
import type { Dayjs } from "dayjs";
import dayjs from "dayjs";

type DateMode = "year" | "full";

interface DateInputProps {
    label: string;
    value: string | null | undefined;
    onChange: (value: string) => void;
    allowPresent?: boolean;
    size?: "small" | "medium";
}

function detectMode(value: string | null | undefined): DateMode {
    if (value?.includes("-")) {
        return "full";
    }
    return "year";
}

export default function DateInput({
    label,
    value,
    onChange,
    allowPresent = false,
    size = "small",
}: DateInputProps) {
    const isPresent = value === "Present";
    // Derive mode from the current value rather than storing it separately.
    // When the user switches format via handleModeChange, that also transforms
    // the value (e.g. strips day/month or appends -01-01), so the derived mode
    // stays consistent without a separate useEffect sync.
    const mode: DateMode = isPresent ? "year" : detectMode(value);

    const handleModeChange = (newMode: DateMode) => {
        if (isPresent) return;

        if (newMode === "year" && mode === "full" && value) {
            // Keep only the year portion
            onChange(value.split("-")[0]);
        } else if (newMode === "full" && mode === "year" && value) {
            // Default month/day to 01
            onChange(`${value}-01-01`);
        }
    };

    const handlePresentChange = (checked: boolean) => {
        if (checked) {
            onChange("Present");
        } else {
            onChange("");
        }
    };

    const handleYearChange = (yearValue: string) => {
        // Only allow digits, max 4 chars
        const cleaned = yearValue.replace(/\D/g, "").slice(0, 4);
        onChange(cleaned);
    };

    const handleDatePickerChange = (date: Dayjs | null) => {
        if (date?.isValid()) {
            onChange(date.format("YYYY-MM-DD"));
        }
    };

    const datePickerValue =
        mode === "full" && value && !isPresent
            ? dayjs(value, "YYYY-MM-DD")
            : null;

    return (
        <Box
            sx={{ display: "flex", gap: 1, alignItems: "flex-start", flex: 1 }}
        >
            <TextField
                select
                size={size}
                value={mode}
                onChange={(e) => {
                    handleModeChange(e.target.value as DateMode);
                }}
                disabled={isPresent}
                sx={{ minWidth: 105 }}
                label="Format"
            >
                <MenuItem value="year">Year</MenuItem>
                <MenuItem value="full">Full Date</MenuItem>
            </TextField>

            {mode === "year" ? (
                <TextField
                    label={label}
                    size={size}
                    placeholder="YYYY"
                    value={isPresent ? "" : value}
                    onChange={(e) => {
                        handleYearChange(e.target.value);
                    }}
                    disabled={isPresent}
                    slotProps={{
                        htmlInput: { maxLength: 4, inputMode: "numeric" },
                    }}
                    sx={{ flex: 1 }}
                />
            ) : (
                <LocalizationProvider dateAdapter={AdapterDayjs}>
                    <DatePicker
                        label={label}
                        value={datePickerValue}
                        onChange={handleDatePickerChange}
                        disabled={isPresent}
                        format="YYYY-MM-DD"
                        slotProps={{
                            textField: {
                                size,
                                sx: { flex: 1 },
                            },
                        }}
                    />
                </LocalizationProvider>
            )}

            {allowPresent && (
                <FormControlLabel
                    control={
                        <Checkbox
                            checked={isPresent}
                            onChange={(e) => {
                                handlePresentChange(e.target.checked);
                            }}
                            size={size}
                        />
                    }
                    label="Present"
                    sx={{ ml: 0, whiteSpace: "nowrap" }}
                />
            )}
        </Box>
    );
}
