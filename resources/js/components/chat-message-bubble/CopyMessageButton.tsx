import CheckIcon from "@mui/icons-material/Check";
import ContentCopyIcon from "@mui/icons-material/ContentCopy";
import IconButton from "@mui/material/IconButton";
import Tooltip from "@mui/material/Tooltip";
import { useState } from "react";

interface CopyMessageButtonProps {
    /** Plain text to copy — already includes reasoning content, if any. */
    text: string;
    color?: string;
}

/** Copies `text` to the clipboard, briefly swapping to a checkmark as confirmation. */
export default function CopyMessageButton({
    text,
    color = "text.disabled",
}: CopyMessageButtonProps) {
    const [copied, setCopied] = useState(false);

    if (text.trim() === "") return null;

    const handleCopy = () => {
        void navigator.clipboard.writeText(text).then(() => {
            setCopied(true);
            setTimeout(() => {
                setCopied(false);
            }, 1500);
        });
    };

    return (
        <Tooltip
            title={copied ? "Copied!" : "Copy message"}
            placement="top"
            arrow
        >
            <IconButton
                size="small"
                onClick={handleCopy}
                aria-label="Copy message"
                sx={{
                    position: "absolute",
                    bottom: 4,
                    right: 4,
                    p: 0.5,
                    color: copied ? "success.main" : color,
                    "&:hover": {
                        color: copied ? "success.main" : "primary.main",
                    },
                }}
            >
                {copied ? (
                    <CheckIcon sx={{ fontSize: 16 }} />
                ) : (
                    <ContentCopyIcon sx={{ fontSize: 16 }} />
                )}
            </IconButton>
        </Tooltip>
    );
}
