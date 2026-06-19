import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import CircularProgress from "@mui/material/CircularProgress";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";

interface JobURLInputSectionProps {
    jobUrl: string;
    isParsing: boolean;
    onJobUrlChange: (_value: string) => void;
    onParseUrl: () => void;
}

export default function JobURLInputSection({
    jobUrl,
    isParsing,
    onJobUrlChange,
    onParseUrl,
}: JobURLInputSectionProps) {
    return (
        <>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                Paste a job URL to auto-extract the description, or enter it
                manually below.
            </Typography>
            <Box sx={{ display: "flex", gap: 1, mb: 3 }}>
                <TextField
                    label="Job URL"
                    size="small"
                    fullWidth
                    value={jobUrl}
                    onChange={(e) => {
                        onJobUrlChange(e.target.value);
                    }}
                    placeholder="https://..."
                />
                <Button
                    variant="outlined"
                    onClick={onParseUrl}
                    disabled={isParsing || !jobUrl.trim()}
                    sx={{ whiteSpace: "nowrap" }}
                >
                    {isParsing ? <CircularProgress size={20} /> : "Parse"}
                </Button>
            </Box>
        </>
    );
}
