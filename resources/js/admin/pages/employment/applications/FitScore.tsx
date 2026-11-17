import SpeakerNotesOffOutlinedIcon from "@mui/icons-material/SpeakerNotesOffOutlined";
import Box from "@mui/material/Box";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";

import type { CompanyGhosting } from "@/types";

const EM_DASH = "—";

/** Read by screen readers, not shown. */
const visuallyHidden = {
    position: "absolute",
    width: "1px",
    height: "1px",
    overflow: "hidden",
    clip: "rect(0 0 0 0)",
    whiteSpace: "nowrap",
} as const;

interface FitScoreProps {
    /** The stored score the AI gave the role. */
    fitScore: number | null;
    ghosting: CompanyGhosting;
    /**
     * `cell` stacks the score over the marker for the Applications list;
     * `inline` keeps everything on one line for the Discussion page's bar.
     */
    layout: "cell" | "inline";
}

function explanation(
    fitScore: number | null,
    ghosting: CompanyGhosting,
): string {
    const silentOn = `This company has gone silent on ${ghosting.count} of your other applications.`;

    return fitScore !== null
        ? `${silentOn} Its fit is shown ${ghosting.penalty} points lower; the AI scored this role ${fitScore}%.`
        : silentOn;
}

/**
 * The fit score as the owner should weigh it. At a company that is ghosting
 * other applications, the lowered score leads, the AI's own score follows
 * struck through, and a marker says how many applications the company is
 * ignoring. Elsewhere it is the plain score, as before.
 */
export default function FitScore({
    fitScore,
    ghosting,
    layout,
}: FitScoreProps) {
    const isGhosting = ghosting.count > 0;
    const prefix = layout === "inline" ? "Fit: " : "";

    if (!isGhosting) {
        if (fitScore === null) {
            return layout === "cell" ? <>{EM_DASH}</> : null;
        }

        return layout === "cell" ? (
            <>{`${fitScore}%`}</>
        ) : (
            <Typography variant="caption" color="text.secondary">
                {`${prefix}${fitScore}%`}
            </Typography>
        );
    }

    const score =
        fitScore !== null && ghosting.adjusted_fit_score !== null ? (
            <Box
                component="span"
                sx={{
                    display: "inline-flex",
                    alignItems: "baseline",
                    gap: 0.75,
                }}
            >
                <Typography
                    component="span"
                    variant={layout === "cell" ? "body2" : "caption"}
                    color={
                        layout === "cell" ? "text.primary" : "text.secondary"
                    }
                    sx={{ fontWeight: 600 }}
                >
                    {`${prefix}${ghosting.adjusted_fit_score}%`}
                </Typography>
                <Typography
                    component="s"
                    variant="caption"
                    color="text.secondary"
                >
                    <Box component="span" sx={visuallyHidden}>
                        AI score{" "}
                    </Box>
                    {`${fitScore}%`}
                </Typography>
            </Box>
        ) : layout === "cell" ? (
            <Typography component="span" variant="body2">
                {EM_DASH}
            </Typography>
        ) : null;

    return (
        <Box
            component="span"
            sx={{
                display: "inline-flex",
                flexDirection: layout === "cell" ? "column" : "row",
                alignItems: layout === "cell" ? "flex-start" : "center",
                gap: layout === "cell" ? 0.25 : 1.5,
            }}
        >
            {score}
            <Tooltip
                title={explanation(fitScore, ghosting)}
                describeChild
                arrow
            >
                <Box
                    component="span"
                    tabIndex={0}
                    sx={{
                        display: "inline-flex",
                        alignItems: "center",
                        gap: 0.5,
                        whiteSpace: "nowrap",
                        cursor: "help",
                        borderRadius: 1,
                    }}
                >
                    <SpeakerNotesOffOutlinedIcon
                        aria-hidden
                        sx={{ fontSize: 14, color: "warning.main" }}
                    />
                    <Typography
                        component="span"
                        variant="caption"
                        color="text.secondary"
                    >
                        {`Ghosted you ${ghosting.count}×`}
                    </Typography>
                </Box>
            </Tooltip>
        </Box>
    );
}
