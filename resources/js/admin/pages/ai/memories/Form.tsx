import Box from "@mui/material/Box";
import Checkbox from "@mui/material/Checkbox";
import FormControlLabel from "@mui/material/FormControlLabel";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";

interface FormData {
    feature: string;
    category: string;
    key: string;
    content: string;
    confidence: number;
    is_active: boolean;
}

interface MemoryFormProps {
    data: FormData;
    setData: (key: keyof FormData, value: string | number | boolean) => void;
    errors: Partial<{ [K in keyof FormData]: string }>;
    isEdit?: boolean;
}

const CATEGORIES = [
    { value: "user_preferences", label: "User Preferences" },
    { value: "domain_knowledge", label: "Domain Knowledge" },
    { value: "system_tuning", label: "System Tuning" },
];

export default function MemoryForm({
    data,
    setData,
    errors,
    isEdit = false,
}: MemoryFormProps) {
    return (
        <>
            <Box
                sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
                    mb: 2,
                }}
            >
                <TextField
                    label="Feature"
                    required
                    size="small"
                    value={data.feature}
                    onChange={(e) => {
                        setData("feature", e.target.value);
                    }}
                    error={!!errors.feature}
                    helperText={errors.feature}
                    placeholder="targeted-resume"
                    disabled={isEdit}
                />
                <TextField
                    label="Category"
                    select
                    required
                    size="small"
                    value={data.category}
                    onChange={(e) => {
                        setData("category", e.target.value);
                    }}
                    error={!!errors.category}
                    helperText={errors.category}
                >
                    {CATEGORIES.map((c) => (
                        <MenuItem key={c.value} value={c.value}>
                            {c.label}
                        </MenuItem>
                    ))}
                </TextField>
            </Box>

            <TextField
                label="Key"
                required
                size="small"
                fullWidth
                value={data.key}
                onChange={(e) => {
                    setData("key", e.target.value);
                }}
                error={!!errors.key}
                helperText={errors.key}
                placeholder="preferred-formatting-style"
                slotProps={{ input: { sx: { fontFamily: "monospace" } } }}
                sx={{ mb: 2 }}
            />

            <TextField
                label="Content"
                required
                size="small"
                fullWidth
                multiline
                rows={6}
                value={data.content}
                onChange={(e) => {
                    setData("content", e.target.value);
                }}
                error={!!errors.content}
                helperText={errors.content}
                sx={{ mb: 2 }}
            />

            <Box
                sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
                    mb: 2,
                }}
            >
                <TextField
                    label="Confidence"
                    type="number"
                    required
                    size="small"
                    value={data.confidence}
                    onChange={(e) => {
                        setData("confidence", parseInt(e.target.value) || 0);
                    }}
                    error={!!errors.confidence}
                    helperText={errors.confidence ?? "1–100"}
                    slotProps={{ htmlInput: { min: 1, max: 100 } }}
                />
                <FormControlLabel
                    control={
                        <Checkbox
                            checked={data.is_active}
                            onChange={(e) => {
                                setData("is_active", e.target.checked);
                            }}
                        />
                    }
                    label="Active"
                    sx={{ mt: 1 }}
                />
            </Box>
        </>
    );
}

export type { FormData };
