import SendIcon from "@mui/icons-material/Send";
import StopIcon from "@mui/icons-material/Stop";
import Box from "@mui/material/Box";
import IconButton from "@mui/material/IconButton";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import { CHAT_COLUMN_MAX_WIDTH } from "./chat-interface/chatColumn";

import type { KeyboardEvent, ReactNode } from "react";

export interface ChatInputAreaSlots {
    beforeSend?: ReactNode;
    /** Rendered as a small caption below the input. */
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
}

export default function ChatInputArea({
    messageText,
    onChange,
    onKeyDown,
    onSubmit,
    disabled = false,
    isStreaming = false,
    onStop,
    slots,
}: ChatInputAreaProps) {
    // Drives the composer's height: collapsed to one line when the composer is
    // not being used, auto-growing up to a cap while it is.
    const [isFocused, setIsFocused] = useState(false);

    return (
        <Box
            component="form"
            sx={{
                position: "absolute",
                bottom: "0",
                left: "0",
                right: "0",
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

                {/* TextField with send button overlaid at bottom-right */}
                <Box sx={{ position: "relative" }}>
                    <TextField
                        placeholder="Your message"
                        multiline
                        minRows={1}
                        // `maxRows`, not `minRows`, is what clamps the rendered
                        // height to one line while unfocused: `minRows` only
                        // sets a floor, so a multi-line draft would still show
                        // in full. The draft itself is untouched either way.
                        maxRows={isFocused ? 8 : 1}
                        value={messageText}
                        onChange={(e) => {
                            onChange(e.target.value);
                        }}
                        onKeyDown={onKeyDown}
                        onFocus={() => {
                            setIsFocused(true);
                        }}
                        onBlur={() => {
                            setIsFocused(false);
                        }}
                        fullWidth
                        sx={{
                            backgroundColor: "#ffffff",
                            "& .MuiInputBase-inputMultiline": {
                                paddingBottom: "44px",
                                // react-textarea-autosize writes `height` as an
                                // inline style on every recalculation, so one
                                // transition covers both the focus-driven
                                // collapse/expand and per-keystroke growth.
                                transition: "height 150ms ease",
                            },
                        }}
                    />
                    {isStreaming && onStop ? (
                        <IconButton
                            type="button"
                            color="primary"
                            aria-label="Stop generating"
                            sx={{ position: "absolute", bottom: 8, right: 8 }}
                            onClick={onStop}
                        >
                            <StopIcon />
                        </IconButton>
                    ) : (
                        <IconButton
                            type="submit"
                            color="primary"
                            disabled={disabled}
                            aria-label="Send message"
                            sx={{ position: "absolute", bottom: 8, right: 8 }}
                            onClick={() => {
                                if (!disabled) onSubmit();
                            }}
                        >
                            <SendIcon />
                        </IconButton>
                    )}
                </Box>

                {slots?.afterSend ? (
                    <Typography
                        variant="caption"
                        color="text.secondary"
                        sx={{ display: "block", textAlign: "right" }}
                    >
                        {slots.afterSend}
                    </Typography>
                ) : null}
            </Stack>
        </Box>
    );
}
