import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";

import type { Personal } from "../types";

interface PersonalTabProps {
    personal: Personal;
    onChange: (key: keyof Personal, value: string) => void;
}

export default function PersonalTab({ personal, onChange }: PersonalTabProps) {
    return (
        <Card>
            <CardContent>
                <Typography variant="h6" gutterBottom>
                    Personal Information
                </Typography>
                <Box
                    sx={{
                        display: "grid",
                        gap: 2,
                        gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
                    }}
                >
                    <TextField
                        label="Full Name"
                        required
                        size="small"
                        value={personal.name}
                        onChange={(e) => {
                            onChange("name", e.target.value);
                        }}
                    />
                    <TextField
                        label="Professional Title"
                        required
                        size="small"
                        value={personal.title}
                        onChange={(e) => {
                            onChange("title", e.target.value);
                        }}
                    />
                    <TextField
                        label="Email"
                        required
                        size="small"
                        type="email"
                        value={personal.email}
                        onChange={(e) => {
                            onChange("email", e.target.value);
                        }}
                    />
                    <TextField
                        label="Phone"
                        size="small"
                        value={personal.phone}
                        onChange={(e) => {
                            onChange("phone", e.target.value);
                        }}
                    />
                    <TextField
                        label="LinkedIn URL"
                        size="small"
                        fullWidth
                        value={personal.linkedin}
                        onChange={(e) => {
                            onChange("linkedin", e.target.value);
                        }}
                        sx={{ gridColumn: { md: "1 / -1" } }}
                    />
                    <TextField
                        label="Website URL"
                        size="small"
                        fullWidth
                        value={personal.url}
                        onChange={(e) => {
                            onChange("url", e.target.value);
                        }}
                        sx={{ gridColumn: { md: "1 / -1" } }}
                    />
                    <TextField
                        label="Professional Summary"
                        size="small"
                        fullWidth
                        multiline
                        rows={4}
                        value={personal.summary}
                        onChange={(e) => {
                            onChange("summary", e.target.value);
                        }}
                        sx={{ gridColumn: { md: "1 / -1" } }}
                    />
                </Box>
            </CardContent>
        </Card>
    );
}
