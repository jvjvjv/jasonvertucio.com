import Box from "@mui/material/Box";
import { useTheme } from "@mui/material/styles";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";

import StatusChip from "@/admin/components/StatusChip";
import { statusChartColor } from "@/admin/utils/statusChartColor";
import { formatCalendarDate } from "@/utils/date";

export interface TimelineSegment {
    status: string;
    from: string;
    to: string;
}

export interface TimelineRow {
    id: number;
    company: string;
    position: string;
    appliedAt: string;
    outcome: string;
    segments: TimelineSegment[];
}

interface ResumeTimelineProps {
    rows: TimelineRow[];
}

const LABEL_WIDTH = 200;

export default function ResumeTimeline({ rows }: ResumeTimelineProps) {
    const theme = useTheme();

    if (rows.length === 0) {
        return (
            <Typography variant="body2" color="text.secondary" sx={{ py: 2 }}>
                No applications yet. Mark a targeted resume as "applied" to
                start tracking its timeline.
            </Typography>
        );
    }

    const times = rows.flatMap((row) =>
        row.segments.flatMap((segment) => [
            new Date(segment.from).getTime(),
            new Date(segment.to).getTime(),
        ]),
    );
    const min = Math.min(...times);
    const max = Math.max(...times);
    const span = Math.max(max - min, 1);

    const positionPercent = (value: string) =>
        ((new Date(value).getTime() - min) / span) * 100;

    return (
        <Box sx={{ display: "flex", flexDirection: "column", gap: 1 }}>
            {rows.map((row) => (
                <Box
                    key={row.id}
                    sx={{ display: "flex", alignItems: "center", gap: 2 }}
                >
                    <Box
                        sx={{
                            width: LABEL_WIDTH,
                            minWidth: LABEL_WIDTH,
                            overflow: "hidden",
                        }}
                    >
                        <Typography
                            variant="body2"
                            fontWeight={600}
                            noWrap
                            title={row.company}
                        >
                            {row.company}
                        </Typography>
                        <Typography
                            variant="caption"
                            color="text.secondary"
                            noWrap
                            display="block"
                            title={row.position}
                        >
                            {row.position}
                        </Typography>
                    </Box>

                    <Box
                        sx={{
                            position: "relative",
                            flexGrow: 1,
                            height: 22,
                            borderRadius: 1,
                            bgcolor: "action.hover",
                        }}
                    >
                        {row.segments.map((segment, index) => {
                            const left = positionPercent(segment.from);
                            const right = positionPercent(segment.to);
                            const width = Math.max(right - left, 0);
                            const isMarker = width === 0;
                            const color = statusChartColor(
                                theme,
                                segment.status,
                            );

                            return (
                                <Tooltip
                                    key={index}
                                    arrow
                                    title={
                                        <Box>
                                            <Typography
                                                variant="caption"
                                                sx={{
                                                    textTransform: "capitalize",
                                                    fontWeight: 600,
                                                }}
                                            >
                                                {segment.status.replace(
                                                    "_",
                                                    " ",
                                                )}
                                            </Typography>
                                            <Typography
                                                variant="caption"
                                                display="block"
                                            >
                                                {formatCalendarDate(
                                                    segment.from,
                                                )}
                                                {!isMarker &&
                                                    ` → ${formatCalendarDate(segment.to)}`}
                                            </Typography>
                                        </Box>
                                    }
                                >
                                    <Box
                                        sx={{
                                            position: "absolute",
                                            top: 0,
                                            bottom: 0,
                                            left: `${left}%`,
                                            width: isMarker ? 10 : `${width}%`,
                                            minWidth: isMarker ? 10 : 4,
                                            ml: isMarker ? "-5px" : 0,
                                            bgcolor: color,
                                            borderRadius: 1,
                                            border: isMarker
                                                ? `2px solid ${theme.palette.background.paper}`
                                                : "none",
                                            transform: isMarker
                                                ? "rotate(45deg) scale(0.8)"
                                                : "none",
                                        }}
                                    />
                                </Tooltip>
                            );
                        })}
                    </Box>

                    <Box sx={{ width: 110, minWidth: 110 }}>
                        <StatusChip
                            status={row.outcome}
                            label={row.outcome.replace("_", " ")}
                        />
                    </Box>
                </Box>
            ))}
        </Box>
    );
}
