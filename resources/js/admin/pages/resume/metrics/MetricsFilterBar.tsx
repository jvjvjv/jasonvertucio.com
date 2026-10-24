import { router, usePage } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Chip from "@mui/material/Chip";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import type { SharedProps } from "@/types";

import { formatCalendarDate } from "@/utils/date";

const METRICS_URL = "/admin/resume/metrics";

/** Every prop the selected period changes, for the partial reload. */
const PERIOD_PROPS = [
    "filter",
    "kpis",
    "funnel",
    "outcomes",
    "overTime",
    "cycleTimes",
    "timeline",
];

type PresetRange = "30d" | "90d" | "ytd" | "all";

export interface MetricsFilter {
    range: PresetRange | "custom";
    /** YYYY-MM-DD */
    from: string | null;
    /** YYYY-MM-DD */
    to: string | null;
}

const PRESETS: { value: PresetRange; label: string }[] = [
    { value: "30d", label: "Last 30 days" },
    { value: "90d", label: "Last 90 days" },
    { value: "ytd", label: "This year" },
    { value: "all", label: "All time" },
];

function visit(query: { [key: string]: string }): void {
    router.get(METRICS_URL, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: PERIOD_PROPS,
    });
}

/** Empty, or a YYYY-MM-DD value whose year has been typed in full. */
function isSettledDate(value: string): boolean {
    return value === "" || value >= "1900-01-01";
}

/** What the figures on the page currently cover, in words. */
function describePeriod(filter: MetricsFilter): string {
    if (filter.range !== "custom") {
        return (
            PRESETS.find((preset) => preset.value === filter.range)?.label ??
            "All time"
        );
    }
    if (filter.from && filter.to) {
        return `${formatCalendarDate(filter.from)} – ${formatCalendarDate(filter.to)}`;
    }
    if (filter.from) {
        return `From ${formatCalendarDate(filter.from)}`;
    }
    if (filter.to) {
        return `Up to ${formatCalendarDate(filter.to)}`;
    }
    return "All time";
}

interface MetricsFilterBarProps {
    /** The period the server applied to the figures on the page. */
    filter: MetricsFilter;
}

/**
 * Period selector for the metrics dashboard. The selection lives in the URL —
 * a preset as `range`, a custom range as `from`/`to` — so a reload keeps it.
 *
 * The date fields are local state rather than the server's `filter`: a rejected
 * range (from after to) leaves the page on the last valid period, and the
 * fields must keep showing what was typed, next to the error explaining it.
 */
export default function MetricsFilterBar({ filter }: MetricsFilterBarProps) {
    const { errors } = usePage<SharedProps>().props;
    const [from, setFrom] = useState(filter.from ?? "");
    const [to, setTo] = useState(filter.to ?? "");

    const handlePreset = (range: PresetRange) => {
        setFrom("");
        setTo("");
        // All time is the default, so it needs no parameter at all.
        visit(range === "all" ? {} : { range });
    };

    const applyCustomRange = (nextFrom: string, nextTo: string) => {
        // A date field reports a complete value after every keystroke of the
        // year ("0002", "0020", ...); wait for a real one before asking.
        if (!isSettledDate(nextFrom) || !isSettledDate(nextTo)) {
            return;
        }

        const query: { [key: string]: string } = {};
        if (nextFrom !== "") {
            query.from = nextFrom;
        }
        if (nextTo !== "") {
            query.to = nextTo;
        }
        visit(query);
    };

    const rangeError = errors.range as string | undefined;
    const fromError = errors.from as string | undefined;
    const toError = errors.to as string | undefined;

    return (
        <Box sx={{ mb: 3 }}>
            <Box
                sx={{
                    display: "flex",
                    flexWrap: "wrap",
                    alignItems: "flex-start",
                    gap: 2,
                }}
            >
                <Box
                    role="group"
                    aria-label="Period"
                    sx={{
                        display: "flex",
                        flexWrap: "wrap",
                        gap: 1,
                        // Lines the chips up with the date fields beside them.
                        minHeight: 40,
                        alignItems: "center",
                    }}
                >
                    {PRESETS.map((preset) => {
                        const selected = filter.range === preset.value;

                        return (
                            <Chip
                                key={preset.value}
                                label={preset.label}
                                color={selected ? "primary" : "default"}
                                variant={selected ? "filled" : "outlined"}
                                aria-pressed={selected}
                                onClick={() => {
                                    handlePreset(preset.value);
                                }}
                            />
                        );
                    })}
                </Box>
                <Box sx={{ display: "flex", flexWrap: "wrap", gap: 2 }}>
                    <TextField
                        label="From"
                        type="date"
                        size="small"
                        value={from}
                        onChange={(e) => {
                            setFrom(e.target.value);
                            applyCustomRange(e.target.value, to);
                        }}
                        error={!!fromError}
                        helperText={fromError}
                        slotProps={{ inputLabel: { shrink: true } }}
                        sx={{ minWidth: 170 }}
                    />
                    <TextField
                        label="To"
                        type="date"
                        size="small"
                        value={to}
                        onChange={(e) => {
                            setTo(e.target.value);
                            applyCustomRange(from, e.target.value);
                        }}
                        error={!!toError}
                        helperText={toError}
                        slotProps={{ inputLabel: { shrink: true } }}
                        sx={{ minWidth: 170 }}
                    />
                </Box>
            </Box>
            {rangeError ? (
                <Typography
                    variant="caption"
                    color="error"
                    role="alert"
                    sx={{ display: "block", mt: 1 }}
                >
                    {rangeError}
                </Typography>
            ) : null}
            <Typography
                variant="caption"
                color="text.secondary"
                sx={{ display: "block", mt: 1 }}
            >
                Showing applications applied to: {describePeriod(filter)}
            </Typography>
        </Box>
    );
}
