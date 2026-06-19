import Box from "@mui/material/Box";
import TextField from "@mui/material/TextField";

interface IdentityFormModalProps {
    visible: boolean;
    visitorName: string;
    visitorEmail: string;
    onVisitorNameChange: (_value: string) => void;
    onVisitorEmailChange: (_value: string) => void;
}

export default function IdentityFormModal({
    visible,
    visitorName,
    visitorEmail,
    onVisitorNameChange,
    onVisitorEmailChange,
}: IdentityFormModalProps) {
    if (!visible) {
        return null;
    }

    return (
        <Box
            sx={{
                display: "grid",
                gap: 2,
                gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
            }}
        >
            <TextField
                label="Name"
                value={visitorName}
                onChange={(e) => {
                    onVisitorNameChange(e.target.value);
                }}
                required
                fullWidth
            />
            <TextField
                label="Email"
                type="email"
                value={visitorEmail}
                onChange={(e) => {
                    onVisitorEmailChange(e.target.value);
                }}
                required
                fullWidth
            />
        </Box>
    );
}
