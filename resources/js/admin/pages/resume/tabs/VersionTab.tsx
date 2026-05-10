import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Chip from "@mui/material/Chip";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";

import type { AvailableVersion } from "../types";

interface VersionTabProps {
    version: string;
    onVersionChange: (v: string) => void;
    docxExists: boolean;
    availableVersions: AvailableVersion[];
}

export default function VersionTab({
    version,
    onVersionChange,
    docxExists,
    availableVersions,
}: VersionTabProps) {
    return (
        <Card>
            <CardContent>
                <Typography variant="h6" gutterBottom>
                    Version Information
                </Typography>
                <Box
                    sx={{
                        display: "grid",
                        gap: 2,
                        gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
                    }}
                >
                    <TextField
                        label="Version Number"
                        size="small"
                        value={version}
                        onChange={(e) => {
                            onVersionChange(e.target.value);
                        }}
                        placeholder="2026.1.0"
                        helperText="Format: YYYY.MAJOR.MINOR (e.g., 2026.1.0)"
                    />
                    <Box sx={{ display: "flex", alignItems: "center" }}>
                        <Chip
                            label={
                                docxExists
                                    ? "DOCX exists for current version"
                                    : "No DOCX for current version"
                            }
                            color={docxExists ? "success" : "warning"}
                            variant="outlined"
                        />
                    </Box>
                </Box>
                {availableVersions.length > 0 && (
                    <Box sx={{ mt: 3 }}>
                        <Typography
                            variant="body2"
                            color="text.secondary"
                            gutterBottom
                        >
                            Available Versions
                        </Typography>
                        <Box sx={{ display: "flex", flexWrap: "wrap", gap: 1 }}>
                            {availableVersions.map((v) => (
                                <Chip
                                    key={v.version}
                                    label={`${v.version} (${new Date(v.created * 1000).toLocaleDateString()})`}
                                    size="small"
                                    variant="outlined"
                                />
                            ))}
                        </Box>
                    </Box>
                )}
            </CardContent>
        </Card>
    );
}
