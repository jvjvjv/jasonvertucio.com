import MicIcon from "@mui/icons-material/Mic";
import StopIcon from "@mui/icons-material/Stop";
import Box from "@mui/material/Box";
import CircularProgress from "@mui/material/CircularProgress";
import IconButton from "@mui/material/IconButton";
import Tooltip from "@mui/material/Tooltip";
import { useEffect, useRef } from "react";

import type { ChatStatus } from "@/components/chat-interface/chatStatus";
import type { UseVoiceResult } from "@/hooks/useVoice";

import { MARGIN_FOCUS_SX } from "@/components/chat-interface/composerStyles";
import useDictation from "@/hooks/useDictation";

interface DictationControlProps {
    voice: UseVoiceResult;
    /** Inserts the transcript at the composer's cursor; never sends. */
    onTranscript: (text: string) => void;
    /**
     * Reports recording, transcribing and the last problem so the composer can
     * show them in its status caption; `null` when there is nothing to say.
     */
    onStatusChange?: (status: ChatStatus | null) => void;
}

function formatElapsed(seconds: number): string {
    const minutes = Math.floor(seconds / 60);

    return `${minutes}:${String(seconds % 60).padStart(2, "0")}`;
}

/**
 * The composer's microphone, rendered in its action margin left of Send. Its
 * state (recording time, transcribing, or the last problem) is reported up
 * through `onStatusChange` and shown in the composer's status caption.
 */
export default function DictationControl({
    voice,
    onTranscript,
    onStatusChange,
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

    const recordingText = `Recording ${formatElapsed(elapsedSeconds)} of ${formatElapsed(voice.config.maxRecordingSeconds)}. Esc cancels.`;

    const onStatusChangeRef = useRef(onStatusChange);
    useEffect(() => {
        onStatusChangeRef.current = onStatusChange;
    });

    useEffect(() => {
        onStatusChangeRef.current?.(
            isRecording
                ? {
                      kind: "recording",
                      tone: "working",
                      text: recordingText,
                      moving: true,
                  }
                : isTranscribing
                  ? {
                        kind: "transcribing",
                        tone: "working",
                        text: "Transcribing the recording.",
                        moving: true,
                    }
                  : message
                    ? {
                          kind: "dictation-problem",
                          tone: "error",
                          text: message,
                          moving: false,
                      }
                    : null,
        );
    }, [isRecording, isTranscribing, message, recordingText]);

    useEffect(
        () => () => {
            onStatusChangeRef.current?.(null);
        },
        [],
    );

    return (
        <Tooltip title={tooltip ?? ""} placement="top" arrow>
            <Box component="span" sx={{ display: "inline-flex" }}>
                {isTranscribing ? (
                    <Box
                        sx={{
                            width: 36,
                            height: 36,
                            display: "flex",
                            alignItems: "center",
                            justifyContent: "center",
                        }}
                    >
                        <CircularProgress size={20} aria-label="Transcribing" />
                    </Box>
                ) : (
                    <IconButton
                        type="button"
                        color={isRecording ? "error" : "default"}
                        aria-label={label}
                        aria-pressed={isRecording}
                        disabled={disabled}
                        onClick={isRecording ? stop : start}
                        sx={{ width: 36, height: 36, ...MARGIN_FOCUS_SX }}
                    >
                        {isRecording ? <StopIcon /> : <MicIcon />}
                    </IconButton>
                )}
            </Box>
        </Tooltip>
    );
}
