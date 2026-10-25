import StopIcon from "@mui/icons-material/Stop";
import VolumeUpIcon from "@mui/icons-material/VolumeUp";
import IconButton from "@mui/material/IconButton";
import Tooltip from "@mui/material/Tooltip";

import useSpeech from "@/hooks/useSpeech";
import useVoice from "@/hooks/useVoice";

interface SpeakMessageButtonProps {
    /** The reply's Markdown; reasoning and tool activity are left out by the caller. */
    text: string;
    color?: string;
}

/**
 * Reads a finished reply aloud. Sits beside the copy button and renders
 * nothing for anyone without `use-voice`.
 */
export default function SpeakMessageButton(props: SpeakMessageButtonProps) {
    const voice = useVoice();

    if (voice === null || props.text.trim() === "") {
        return null;
    }

    return <SpeakControl voice={voice} {...props} />;
}

function SpeakControl({
    voice,
    text,
    color = "text.disabled",
}: SpeakMessageButtonProps & {
    voice: NonNullable<ReturnType<typeof useVoice>>;
}) {
    const { isSpeaking, toggle, error } = useSpeech(voice, () => text);
    const disabled = !isSpeaking && !voice.speechAvailable;
    const label = isSpeaking ? "Stop reading" : "Read aloud";
    const title = error ?? (disabled ? voice.speechReason : label);

    return (
        <Tooltip title={title ?? label} placement="top" arrow>
            <span
                style={{ position: "absolute", bottom: 4, right: 28 }}
                // The bubble toggles code wrapping on double-click.
                onDoubleClick={(e) => {
                    e.stopPropagation();
                }}
            >
                <IconButton
                    size="small"
                    onClick={toggle}
                    disabled={disabled}
                    aria-label={label}
                    aria-pressed={isSpeaking}
                    sx={{
                        p: 0.5,
                        color: isSpeaking
                            ? "primary.main"
                            : error
                              ? "error.main"
                              : color,
                        "&:hover": { color: "primary.main" },
                    }}
                >
                    {isSpeaking ? (
                        <StopIcon sx={{ fontSize: 16 }} />
                    ) : (
                        <VolumeUpIcon sx={{ fontSize: 16 }} />
                    )}
                </IconButton>
            </span>
        </Tooltip>
    );
}
