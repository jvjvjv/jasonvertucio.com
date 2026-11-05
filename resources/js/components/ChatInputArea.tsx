import SendIcon from "@mui/icons-material/Send";
import StopIcon from "@mui/icons-material/Stop";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import IconButton from "@mui/material/IconButton";
import InputBase from "@mui/material/InputBase";
import Stack from "@mui/material/Stack";
import { useTheme } from "@mui/material/styles";
import Typography from "@mui/material/Typography";
import useMediaQuery from "@mui/material/useMediaQuery";
import { useRef, useState } from "react";

import { CHAT_COLUMN_MAX_WIDTH } from "./chat-interface/chatColumn";
import { MARGIN_FOCUS_SX } from "./chat-interface/composerStyles";
import StatusCaption from "./chat-interface/StatusCaption";
import StatusRail from "./chat-interface/StatusRail";

import type { ChatStatus } from "./chat-interface/chatStatus";
import type { UseVoiceResult } from "@/hooks/useVoice";
import type { KeyboardEvent, ReactNode } from "react";

import DictationControl from "@/components/voice/DictationControl";
import useMarkdownPaste from "@/hooks/useMarkdownPaste";

export interface ChatInputAreaSlots {
    beforeSend?: ReactNode;
    /** Rendered as a small caption below the composer's status line. */
    afterSend?: ReactNode;
}

export interface ChatInputAreaProps {
    messageText: string;
    onChange: (value: string) => void;
    onKeyDown: (e: KeyboardEvent<HTMLDivElement>) => void;
    onSubmit: () => void;
    disabled?: boolean;
    /** While true, the send button is replaced with an active Stop button. */
    isStreaming?: boolean;
    /** Called when the user cancels an in-flight turn (Stop button or ESC). */
    onStop?: () => void;
    slots?: ChatInputAreaSlots;
    /** Present only for holders of `use-voice`; adds the dictation microphone. */
    voice?: UseVoiceResult | null;
    /**
     * What the chat is doing, shown on the rail and in the caption line.
     * Dictation's own state is shown over it, except when the session expired.
     */
    status?: ChatStatus;
}

const IDLE_STATUS: ChatStatus = {
    kind: "idle",
    tone: "idle",
    text: "",
    moving: false,
};

const BUTTON_SX = {
    width: 36,
    height: 36,
    borderRadius: 1,
    ...MARGIN_FOCUS_SX,
} as const;

/** Whether the viewer is on an Apple platform, where the shortcut uses ⌘. */
function isApplePlatform(): boolean {
    if (typeof navigator === "undefined") {
        return false;
    }

    const platform =
        (navigator as Navigator & { userAgentData?: { platform?: string } })
            .userAgentData?.platform ?? navigator.userAgent;

    return /mac|iphone|ipad|ipod/i.test(platform);
}

/**
 * The message composer: one bordered surface with the status rail across its
 * top, the text column, and an action margin on the right behind a hairline.
 * The caption line underneath says in words what the rail shows. Nothing is
 * positioned over the text, so a long draft can never run under a button.
 */
export default function ChatInputArea({
    messageText,
    onChange,
    onKeyDown,
    onSubmit,
    disabled = false,
    isStreaming = false,
    onStop,
    slots,
    voice = null,
    status = IDLE_STATUS,
}: ChatInputAreaProps) {
    const theme = useTheme();
    const isNarrow = useMediaQuery(theme.breakpoints.down("sm"));
    const [sendShortcut] = useState(() =>
        isApplePlatform() ? "⌘+Enter sends" : "Ctrl+Enter sends",
    );

    // Drives the composer's height: collapsed to one line when the composer is
    // not being used, auto-growing up to a cap while it is.
    const [isFocused, setIsFocused] = useState(false);
    const onMarkdownPaste = useMarkdownPaste();
    const inputRef = useRef<HTMLTextAreaElement>(null);

    const [dictationStatus, setDictationStatus] = useState<ChatStatus | null>(
        null,
    );
    // A dictation problem lingers in `useDictation` until the next recording.
    // Once a reply starts it is old news, and would otherwise hide the
    // reply's progress for the whole turn.
    const [wasStreaming, setWasStreaming] = useState(isStreaming);
    if (isStreaming !== wasStreaming) {
        setWasStreaming(isStreaming);
        if (isStreaming && dictationStatus?.kind === "dictation-problem") {
            setDictationStatus(null);
        }
    }

    const isExpired = status.kind === "expired";
    const shownStatus = isExpired ? status : (dictationStatus ?? status);
    const showShortcut =
        !isNarrow &&
        (shownStatus.tone === "ready" || shownStatus.tone === "idle");

    const insertTranscript = (transcript: string) => {
        const input = inputRef.current;
        // The transcript arrives after an upload, by which time the draft may
        // have changed; the box itself holds the latest text.
        const current = input?.value ?? messageText;
        const start = input?.selectionStart ?? current.length;
        const end = input?.selectionEnd ?? current.length;
        const before = current.slice(0, start);
        const after = current.slice(end);
        const lead = before !== "" && !/\s$/.test(before) ? " " : "";
        const trail = after !== "" && !/^\s/.test(after) ? " " : "";
        const caret = before.length + lead.length + transcript.length;

        onChange(before + lead + transcript + trail + after);

        requestAnimationFrame(() => {
            input?.focus();
            input?.setSelectionRange(caret, caret);
        });
    };

    return (
        <Box
            component="form"
            sx={{
                width: "100%",
                maxWidth: CHAT_COLUMN_MAX_WIDTH,
                margin: "0 auto",
                px: { xs: 1.5, md: 3 },
                py: { xs: 1, md: 1.5 },
            }}
            onSubmit={(e) => {
                e.preventDefault();
                onSubmit();
            }}
        >
            <Stack spacing={1}>
                {slots?.beforeSend}

                <Box>
                    <Box
                        onKeyDown={onKeyDown}
                        sx={{
                            display: "flex",
                            flexDirection: "column",
                            bgcolor: "background.paper",
                            border: 1,
                            borderColor: "divider",
                            borderRadius: 1,
                            overflow: "hidden",
                            // The outline sits outside the border, so the
                            // ring thickens without shifting the text.
                            "&:focus-within": {
                                borderColor: "primary.main",
                                outline: "1px solid",
                                outlineColor: "primary.main",
                            },
                        }}
                    >
                        <StatusRail status={shownStatus} />

                        <Box sx={{ display: "flex", alignItems: "stretch" }}>
                            <InputBase
                                placeholder="Your message"
                                multiline
                                minRows={1}
                                // `maxRows`, not `minRows`, is what clamps the
                                // rendered height to one line while unfocused:
                                // `minRows` only sets a floor, so a multi-line
                                // draft would still show in full. The draft
                                // itself is untouched either way.
                                maxRows={isFocused ? (isNarrow ? 5 : 8) : 1}
                                value={messageText}
                                inputRef={inputRef}
                                inputProps={{ "aria-label": "Your message" }}
                                onChange={(e) => {
                                    onChange(e.target.value);
                                }}
                                onPaste={onMarkdownPaste}
                                onFocus={() => {
                                    setIsFocused(true);
                                }}
                                onBlur={() => {
                                    setIsFocused(false);
                                    // Collapsed to one line, show the draft's
                                    // first line rather than wherever the
                                    // caret left it scrolled.
                                    if (inputRef.current) {
                                        inputRef.current.scrollTop = 0;
                                    }
                                }}
                                sx={{
                                    flex: "1 1 auto",
                                    minWidth: 0,
                                    // The padding lives on the wrapper, not
                                    // the textarea: a scrolled textarea shows
                                    // its content through its own padding,
                                    // which would leave half lines under the
                                    // rail and in a collapsed draft.
                                    px: "14px",
                                    py: "13px",
                                    alignItems: "flex-start",
                                    cursor: "text",
                                    "& .MuiInputBase-inputMultiline": {
                                        p: 0,
                                        lineHeight: "22px",
                                        resize: "none",
                                        // react-textarea-autosize writes
                                        // `height` as an inline style on every
                                        // recalculation, so one transition
                                        // covers both the focus-driven
                                        // collapse/expand and per-keystroke
                                        // growth.
                                        transition: "height 150ms ease",
                                    },
                                }}
                            />

                            <Box
                                sx={{
                                    flex: "none",
                                    display: "flex",
                                    alignItems: "flex-end",
                                    gap: 0.5,
                                    p: "6px",
                                    borderLeft: 1,
                                    borderColor: "divider",
                                }}
                            >
                                {isExpired ? (
                                    <Button
                                        type="button"
                                        variant="outlined"
                                        size="small"
                                        sx={{
                                            minHeight: 36,
                                            ...MARGIN_FOCUS_SX,
                                        }}
                                        onClick={() => {
                                            window.location.reload();
                                        }}
                                    >
                                        Refresh
                                    </Button>
                                ) : (
                                    <>
                                        {voice ? (
                                            <DictationControl
                                                voice={voice}
                                                onTranscript={insertTranscript}
                                                onStatusChange={
                                                    setDictationStatus
                                                }
                                            />
                                        ) : null}
                                        {isStreaming && onStop ? (
                                            <IconButton
                                                type="button"
                                                aria-label="Stop generating"
                                                sx={{
                                                    ...BUTTON_SX,
                                                    border: 1,
                                                    borderColor: "primary.main",
                                                    color: "primary.main",
                                                }}
                                                onClick={onStop}
                                            >
                                                <StopIcon fontSize="small" />
                                            </IconButton>
                                        ) : (
                                            <IconButton
                                                type="submit"
                                                disabled={disabled}
                                                aria-label="Send message"
                                                sx={{
                                                    ...BUTTON_SX,
                                                    bgcolor: "primary.main",
                                                    color: "primary.contrastText",
                                                    "&:hover": {
                                                        bgcolor: "primary.dark",
                                                    },
                                                    "&.Mui-disabled": {
                                                        bgcolor:
                                                            "action.disabledBackground",
                                                        color: "action.disabled",
                                                    },
                                                }}
                                                onClick={() => {
                                                    if (!disabled) onSubmit();
                                                }}
                                            >
                                                <SendIcon fontSize="small" />
                                            </IconButton>
                                        )}
                                    </>
                                )}
                            </Box>
                        </Box>
                    </Box>

                    <StatusCaption
                        status={shownStatus}
                        hint={showShortcut ? sendShortcut : null}
                    />

                    {slots?.afterSend ? (
                        <Typography
                            variant="caption"
                            color="text.secondary"
                            sx={{
                                display: "block",
                                textAlign: "right",
                                mt: 0.5,
                            }}
                        >
                            {slots.afterSend}
                        </Typography>
                    ) : null}
                </Box>
            </Stack>
        </Box>
    );
}
