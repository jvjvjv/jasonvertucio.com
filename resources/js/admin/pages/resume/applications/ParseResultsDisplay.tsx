import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import CircularProgress from "@mui/material/CircularProgress";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { marked } from "marked";

interface ParseResultsDisplayProps {
    parseError: string;
    jobUrlId: string | null;
    parseReasoning: string;
    usedExistingParser: boolean | null;
    parserId: number | null;
    reparseFeedback: string;
    isReparsing: boolean;
    onReparseFeedbackChange: (_value: string) => void;
    onReparse: () => void;
}

export default function ParseResultsDisplay({
    parseError,
    jobUrlId,
    parseReasoning,
    usedExistingParser,
    parserId,
    reparseFeedback,
    isReparsing,
    onReparseFeedbackChange,
    onReparse,
}: ParseResultsDisplayProps) {
    return (
        <>
            {parseError && (
                <Alert severity="warning" sx={{ mb: 2 }}>
                    {parseError}
                </Alert>
            )}

            {jobUrlId && !parseError && (
                <Alert severity="success" sx={{ mb: 2 }}>
                    Parsed job URL attached to this session.
                </Alert>
            )}

            {parseReasoning && (
                <Alert severity="info" sx={{ mb: 2 }}>
                    <Typography variant="subtitle2" sx={{ mb: 0.5 }}>
                        Parser reasoning
                    </Typography>
                    <Typography
                        variant="body2"
                        dangerouslySetInnerHTML={{
                            __html: marked.parse(parseReasoning, {
                                breaks: true,
                            }) as string,
                        }}
                    ></Typography>
                    <Typography
                        variant="caption"
                        sx={{ mt: 1, display: "block" }}
                    ></Typography>
                    {usedExistingParser && (
                        <Typography variant="caption" color="secondary.main">
                            <strong>
                                Note: This URL was parsed using an existing
                                parser (id:{parserId}) for this domain. If any
                                information was extracted incorrectly, please
                                provide feedback and re-parse to help improve
                                the parser.
                            </strong>
                        </Typography>
                    )}
                </Alert>
            )}

            {parserId && (
                <Box sx={{ display: "flex", gap: 1, mb: 3 }}>
                    <TextField
                        label="Re-parse feedback"
                        size="small"
                        fullWidth
                        value={reparseFeedback}
                        onChange={(e) => {
                            onReparseFeedbackChange(e.target.value);
                        }}
                        placeholder="Describe what was extracted incorrectly..."
                    />
                    <Button
                        variant="outlined"
                        onClick={onReparse}
                        disabled={isReparsing || !reparseFeedback.trim()}
                        sx={{ whiteSpace: "nowrap" }}
                    >
                        {isReparsing ? (
                            <CircularProgress size={20} />
                        ) : (
                            "Re-parse"
                        )}
                    </Button>
                </Box>
            )}
        </>
    );
}
