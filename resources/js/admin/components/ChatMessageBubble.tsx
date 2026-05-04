import Box from '@mui/material/Box';
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";
import { marked } from 'marked';
import { markdownSx } from '../utils/markdownSx';

interface ChatMessageBubbleProps {
    content: string;
    role: string;
    maxWidth?: string;
    variant?: "chat" | "history";
    sentAt?: string | null;
}

function getRelativeSentLabel(
    sentAt: string | null | undefined,
): string | null {
    if (!sentAt) {
        return null;
    }

    const sentAtDate = new Date(sentAt);
    if (Number.isNaN(sentAtDate.getTime())) {
        return null;
    }

    const now = new Date();
    const diffInSeconds = Math.round(
        (sentAtDate.getTime() - now.getTime()) / 1000,
    );
    const absoluteSeconds = Math.abs(diffInSeconds);
    const relativeFormatter = new Intl.RelativeTimeFormat(undefined, {
        numeric: "auto",
    });

    if (absoluteSeconds < 60) {
        return `sent ${relativeFormatter.format(diffInSeconds, "second")}`;
    }

    if (absoluteSeconds < 60 * 60) {
        return `sent ${relativeFormatter.format(Math.round(diffInSeconds / 60), "minute")}`;
    }

    if (absoluteSeconds < 60 * 60 * 24) {
        return `sent ${relativeFormatter.format(Math.round(diffInSeconds / 3600), "hour")}`;
    }

    if (absoluteSeconds < 60 * 60 * 24 * 30) {
        return `sent ${relativeFormatter.format(Math.round(diffInSeconds / 86400), "day")}`;
    }

    if (absoluteSeconds < 60 * 60 * 24 * 365) {
        return `sent ${relativeFormatter.format(Math.round(diffInSeconds / (86400 * 30)), "month")}`;
    }

    return `sent ${relativeFormatter.format(Math.round(diffInSeconds / (86400 * 365)), "year")}`;
}

function getLocaleDateTime(sentAt: string | null | undefined): string | null {
    if (!sentAt) {
        return null;
    }

    const sentAtDate = new Date(sentAt);
    if (Number.isNaN(sentAtDate.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(sentAtDate);
}

const userMarkdownOverrides = {
    '& a': {
        color: 'inherit',
    },
    '& code': {
        bgcolor: 'rgba(255,255,255,0.2)',
    },
    '& pre': {
        bgcolor: 'rgba(255,255,255,0.2)',
    },
    '& blockquote': {
        borderLeftColor: 'rgba(255,255,255,0.55)',
        color: 'inherit',
    },
    '& hr': {
        borderTopColor: 'rgba(255,255,255,0.35)',
    },
};

export default function ChatMessageBubble({
    content,
    role,
    maxWidth = "80%",
    variant = "chat",
    sentAt = null,
}: ChatMessageBubbleProps) {
    const isUser = role === "user";
    const isChatVariant = variant === "chat";
    const relativeSentLabel = getRelativeSentLabel(sentAt);
    const localeDateTime = getLocaleDateTime(sentAt);

    const baseBubbleSx = {
        alignSelf: isChatVariant
            ? isUser
                ? "flex-end"
                : "flex-start"
            : "stretch",
        maxWidth: isChatVariant ? maxWidth : "100%",
        bgcolor: isChatVariant
            ? isUser
                ? "primary.main"
                : "grey.100"
            : "transparent",
        color: isChatVariant
            ? isUser
                ? "primary.contrastText"
                : "text.primary"
            : "text.primary",
        borderRadius: isChatVariant ? 2 : 0,
        px: isChatVariant ? 2 : 0,
        py: isChatVariant ? 1 : 0,
        "& p::first-of-type": {
            mt: 0,
        },
    };

    return (
        <Box sx={baseBubbleSx}>
            <Box
                sx={{
                    ...markdownSx,
                    ...(isChatVariant && isUser ? userMarkdownOverrides : {}),
                }}
            >
                <div
                    style={{ wordBreak: "break-word" }}
                    dangerouslySetInnerHTML={{
                        __html: marked.parse(content, {
                            breaks: true,
                        }) as string,
                    }}
                />
                {relativeSentLabel && localeDateTime ? (
                    <Tooltip title={localeDateTime} arrow>
                        <Typography
                            variant="caption"
                            sx={{
                                display: "inline-block",
                                mt: "2px",
                                lineHeight: 1,
                                color: `${isChatVariant ? (isUser ? "primary.contrastText" : "text.secondary") : "text.primary"}`,
                                cursor: "help",
                            }}
                        >
                            {relativeSentLabel}
                        </Typography>
                    </Tooltip>
                ) : null}
            </Box>
        </Box>
    );
}
