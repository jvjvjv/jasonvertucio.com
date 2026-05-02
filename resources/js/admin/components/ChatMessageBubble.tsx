import Box from '@mui/material/Box';
import { marked } from 'marked';
import { markdownSx } from '../utils/markdownSx';

interface ChatMessageBubbleProps {
    content: string;
    role: string;
    maxWidth?: string;
    variant?: 'chat' | 'history';
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
    maxWidth = '80%',
    variant = 'chat',
}: ChatMessageBubbleProps) {
    const isUser = role === 'user';
    const isChatVariant = variant === 'chat';

    const baseBubbleSx = {
        alignSelf: isChatVariant
            ? (isUser ? 'flex-end' : 'flex-start')
            : 'stretch',
        maxWidth: isChatVariant ? maxWidth : '100%',
        bgcolor: isChatVariant
            ? (isUser ? 'primary.main' : 'grey.100')
            : 'transparent',
        color: isChatVariant
            ? (isUser ? 'primary.contrastText' : 'text.primary')
            : 'text.primary',
        borderRadius: isChatVariant ? 2 : 0,
        px: isChatVariant ? 2 : 0,
        py: isChatVariant ? 1 : 0,
    };

    return (
        <Box
            sx={{
                ...baseBubbleSx,
                ...markdownSx,
                ...(isChatVariant && isUser ? userMarkdownOverrides : {}),
            }}
        >
            <div
                style={{ wordBreak: 'break-word' }}
                dangerouslySetInnerHTML={{
                    __html: marked.parse(content, { breaks: true }) as string,
                }}
            />
        </Box>
    );
}
