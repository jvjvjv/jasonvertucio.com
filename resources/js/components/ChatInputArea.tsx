import SendIcon from "@mui/icons-material/Send";
import Box from "@mui/material/Box";
import IconButton from "@mui/material/IconButton";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";

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
    slots?: ChatInputAreaSlots;
}

export default function ChatInputArea({
    messageText,
    onChange,
    onKeyDown,
    onSubmit,
    disabled = false,
    slots,
}: ChatInputAreaProps) {
    return (
        <Box
            component="form"
            sx={{ px: { xs: 1.5, md: 3 }, py: { xs: 1, md: 1.5 } }}
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
                        minRows={2}
                        value={messageText}
                        onChange={(e) => {
                            onChange(e.target.value);
                        }}
                        onKeyDown={onKeyDown}
                        fullWidth
                        sx={{
                            "& .MuiInputBase-inputMultiline": {
                                paddingBottom: "44px",
                            },
                        }}
                    />
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
