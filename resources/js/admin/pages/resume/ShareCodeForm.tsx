import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Checkbox from "@mui/material/Checkbox";
import FormControlLabel from "@mui/material/FormControlLabel";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";

import type { InertiaFormProps } from "@inertiajs/react";
import type { SyntheticEvent } from "react";

interface ShareCodeFormData {
    name: string;
    email: string;
    expires_at: string;
    send_email: boolean;
}

interface ShareCodeFormProps {
    form: InertiaFormProps<ShareCodeFormData>;
    todayDate: string;
    mailConfigured: boolean;
    onSubmit: (_e: SyntheticEvent<HTMLFormElement>) => void;
}

export default function ShareCodeForm({
    form,
    todayDate,
    mailConfigured,
    onSubmit,
}: ShareCodeFormProps) {
    const emailProvided = form.data.email.trim() !== "";

    return (
        <Box component="form" onSubmit={onSubmit}>
            <Box
                sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: {
                        xs: "1fr",
                        md: "1fr 1fr",
                    },
                    mb: 2,
                }}
            >
                <TextField
                    label="Recipient Name"
                    required
                    size="small"
                    value={form.data.name}
                    onChange={(e) => {
                        form.setData("name", e.target.value);
                    }}
                    error={!!form.errors.name}
                    helperText={form.errors.name}
                />
                <TextField
                    label="Recipient Email (optional)"
                    type="email"
                    size="small"
                    value={form.data.email}
                    onChange={(e) => {
                        form.setData("email", e.target.value);
                    }}
                    error={!!form.errors.email}
                    helperText={form.errors.email}
                />
            </Box>
            <Box
                sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: {
                        xs: "1fr",
                        md: "1fr 1fr",
                    },
                    mb: 2,
                }}
            >
                <TextField
                    label="Expiration Date (optional)"
                    type="date"
                    size="small"
                    slotProps={{
                        inputLabel: { shrink: true },
                        htmlInput: { min: todayDate },
                    }}
                    value={form.data.expires_at}
                    onChange={(e) => {
                        form.setData("expires_at", e.target.value);
                    }}
                    error={!!form.errors.expires_at}
                    helperText={form.errors.expires_at}
                />
            </Box>
            <FormControlLabel
                control={
                    <Checkbox
                        checked={form.data.send_email}
                        onChange={(e) => {
                            form.setData("send_email", e.target.checked);
                        }}
                        disabled={!mailConfigured || !emailProvided}
                    />
                }
                label="Send email notification"
                sx={{ mb: 1 }}
            />
            {!mailConfigured && (
                <Typography
                    variant="caption"
                    color="text.secondary"
                    display="block"
                    sx={{ mb: 1 }}
                >
                    (mail not configured)
                </Typography>
            )}
            {emailProvided && mailConfigured && form.data.send_email && (
                <Typography variant="body2" color="info.main" sx={{ mb: 2 }}>
                    An email will be sent to this address once the code is
                    created.
                </Typography>
            )}
            <Button
                type="submit"
                variant="contained"
                disabled={form.processing}
            >
                Generate Code
            </Button>
        </Box>
    );
}
