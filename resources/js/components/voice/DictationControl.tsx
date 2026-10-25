import MicIcon from "@mui/icons-material/Mic";
import StopIcon from "@mui/icons-material/Stop";
import Box from "@mui/material/Box";
import CircularProgress from "@mui/material/CircularProgress";
import IconButton from "@mui/material/IconButton";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";
import { useEffect } from "react";

import type { UseVoiceResult } from "@/hooks/useVoice";

import useDictation from "@/hooks/useDictation";

interface DictationControlProps {
    voice: UseVoiceResult;
    /** Inserts the transcript at the composer's cursor; never sends. */
    onTranscript: (text: string) => void;
}

function formatElapsed(seconds: number): string {
    const minutes = Math.floor(seconds / 60);

    return `${minutes}:${String(seconds % 60).padStart(2, "0")}`;
}

/**
 * The composer's microphone: the button sits left of Send, and the state
 * line (recording time, transcribing, or the last problem) sits in the empty
 * bottom-left corner of the message box.
 */
export default function DictationControl({
    voice,
    onTranscript,
}: DictationControlProps) {
    const { state, elapsedSeconds, message, start, stop, cancel } =
        useDictation({
            transcribeUrl: voice.config.transcribeUrl,
            maxSeconds: voice.config.maxRecordingSeconds,
            onTranscript,
            onUnavailable: voice.refresh,
        });

    const isRecording = state === "recording";
    const isTranscribing = state === "transcribing";
    const disabled = state === "idle" && !voice.dictationAvailable;

    useEffect(() => {
        if (!isRecording) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === "Escape") {
                event.preventDefault();
                event.stopPropagation();
                cancel();
            }
        };

        document.addEventListener("keydown", onKeyDown, true);

        return () => {
            document.removeEventListener("keydown", onKeyDown, true);
        };
    }, [isRecording, cancel]);

    const label = isRecording ? "Stop recording" : "Dictate";
    const tooltip = isTranscribing
        ? ""
        : disabled
          ? voice.dictationReason
          : label;

    const statusLine = isRecording ? (
        <>
            <Box
                component="span"
                aria-hidden="true"
                sx={{
                    display: "inline-block",
                    width: 8,
                    height: 8,
                    borderRadius: "50%",
                    bgcolor: "error.main",
                    mr: 0.75,
                    verticalAlign: "middle",
                }}
            />
            Recording {formatElapsed(elapsedSeconds)} of{" "}
            {formatElapsed(voice.config.maxRecordingSeconds)} · Esc to cancel
        </>
    ) : isTranscribing ? (
        "Transcribing…"
    ) : (
        message
    );

    return (
        <>
            {statusLine ? (
                <Typography
                    variant="caption"
                    role="status"
                    color={
                        message && state === "idle" ? "error" : "text.secondary"
                    }
                    sx={{
                        position: "absolute",
                        bottom: 14,
                        left: 14,
                        right: 100,
                        whiteSpace: "nowrap",
                        overflow: "hidden",
                        textOverflow: "ellipsis",
                        pointerEvents: "none",
                    }}
                >
                    {statusLine}
                </Typography>
            ) : null}
            <Tooltip title={tooltip ?? ""} placement="top" arrow>
                <Box
                    component="span"
                    sx={{ position: "absolute", bottom: 8, right: 52 }}
                >
                    {isTranscribing ? (
                        <Box
                            sx={{
                                width: 40,
                                height: 40,
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "center",
                            }}
                        >
                            <CircularProgress
                                size={20}
                                aria-label="Transcribing"
                            />
                        </Box>
                    ) : (
                        <IconButton
                            type="button"
                            color={isRecording ? "error" : "default"}
                            aria-label={label}
                            aria-pressed={isRecording}
                            disabled={disabled}
                            onClick={isRecording ? stop : start}
                        >
                            {isRecording ? <StopIcon /> : <MicIcon />}
                        </IconButton>
                    )}
                </Box>
            </Tooltip>
        </>
    );
}
