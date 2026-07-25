import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";

import { getLocaleDateTime, getRelativeSentLabel } from "@/utils/date";

interface SentAtLabelProps {
    sentAt: string | null | undefined;
    color: "text.secondary" | "text.primary" | "primary.contrastText";
}

/** "sent 5 minutes ago"-style caption with a full-timestamp tooltip. Renders nothing if `sentAt` is unset/invalid. */
export default function SentAtLabel({ sentAt, color }: SentAtLabelProps) {
    const relativeSentLabel = getRelativeSentLabel(sentAt);
    const localeDateTime = getLocaleDateTime(sentAt);

    if (!relativeSentLabel || !localeDateTime) return null;

    return (
        <Tooltip title={`Sent: ${localeDateTime}`} arrow>
            <Typography
                variant="caption"
                sx={{
                    display: "inline-block",
                    mt: "2px",
                    lineHeight: 1,
                    color,
                    cursor: "help",
                    opacity: 0.7,
                }}
            >
                {relativeSentLabel}
            </Typography>
        </Tooltip>
    );
}
