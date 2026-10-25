import StopIcon from "@mui/icons-material/Stop";
import VolumeUpIcon from "@mui/icons-material/VolumeUp";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";

import useSpeech from "@/hooks/useSpeech";
import useVoice from "@/hooks/useVoice";

interface ListenButtonProps {
    /**
     * Returns the Markdown to read, as it is in the editor right now, so
     * unsaved edits are heard too.
     */
    getText: () => string;
}

/**
 * Proofreading by ear: reads a document paragraph by paragraph until it ends
 * or is stopped. Renders nothing for anyone without `use-voice`.
 */
export default function ListenButton({ getText }: ListenButtonProps) {
    const voice = useVoice();

    if (voice === null) {
        return null;
    }

    return <ListenControl voice={voice} getText={getText} />;
}

function ListenControl({
    voice,
    getText,
}: ListenButtonProps & { voice: NonNullable<ReturnType<typeof useVoice>> }) {
    const { isSpeaking, toggle, error } = useSpeech(voice, getText);
    const disabled = !isSpeaking && !voice.speechAvailable;

    return (
        <Box sx={{ display: "inline-flex", alignItems: "center", gap: 1 }}>
            <Tooltip
                title={disabled ? (voice.speechReason ?? "") : ""}
                placement="top"
                arrow
            >
                <span>
                    <Button
                        variant="outlined"
                        onClick={toggle}
                        disabled={disabled}
                        aria-pressed={isSpeaking}
                        sx={
                            isSpeaking
                                ? { bgcolor: "action.selected" }
                                : undefined
                        }
                        startIcon={isSpeaking ? <StopIcon /> : <VolumeUpIcon />}
                    >
                        {isSpeaking ? "Stop" : "Listen"}
                    </Button>
                </span>
            </Tooltip>
            {error ? (
                <Typography variant="caption" color="error" role="status">
                    {error}
                </Typography>
            ) : null}
        </Box>
    );
}
