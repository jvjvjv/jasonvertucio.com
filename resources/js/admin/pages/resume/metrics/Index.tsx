import { Head } from "@inertiajs/react";
import ExpandMoreIcon from "@mui/icons-material/ExpandMore";
import Accordion from "@mui/material/Accordion";
import AccordionDetails from "@mui/material/AccordionDetails";
import AccordionSummary from "@mui/material/AccordionSummary";
import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import { useTheme } from "@mui/material/styles";
import Typography from "@mui/material/Typography";
import {
    Bar,
    BarChart,
    LabelList,
    Legend,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip as RechartsTooltip,
    XAxis,
    YAxis,
} from "recharts";

import MetricsFilterBar, { type MetricsFilter } from "./MetricsFilterBar";
import ResumeTimeline, { type TimelineRow } from "./ResumeTimeline";

import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";
import { statusChartColor } from "@/admin/utils/statusChartColor";

interface FunnelRow {
    stage: string;
    label: string;
    count: number;
}

interface OutcomeRow {
    outcome: string;
    label: string;
    count: number;
}

interface OverTimeRow {
    period: string;
    count: number;
}

interface MetricsProps {
    ghostedAfterDays: number;
    kpis: {
        totalApplied: number;
        responseRate: number | null;
        interviewRate: number | null;
        offerRate: number | null;
        ghostRate: number | null;
    };
    funnel: FunnelRow[];
    outcomes: OutcomeRow[];
    overTime: OverTimeRow[];
    cycleTimes: {
        toFirstResponse: number | null;
        toRejection: number | null;
        toOffer: number | null;
    };
    timeline: TimelineRow[];
    /** The period every section above is restricted to. */
    filter: MetricsFilter;
}

/** Keeps the heavy timeline out of the DOM until the accordion is opened. */
const TIMELINE_ACCORDION_SLOT_PROPS = {
    transition: { unmountOnExit: true },
} as const;

function StatCard({ label, value }: { label: string; value: string }) {
    return (
        <Card variant="outlined" sx={{ flex: "1 1 160px", minWidth: 160 }}>
            <CardContent>
                <Typography variant="h4" component="div" fontWeight="bold">
                    {value}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                    {label}
                </Typography>
            </CardContent>
        </Card>
    );
}

function ChartCard({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <Card variant="outlined" sx={{ flex: "1 1 360px", minWidth: 320 }}>
            <CardContent>
                <Typography variant="h6" gutterBottom>
                    {title}
                </Typography>
                {children}
            </CardContent>
        </Card>
    );
}

const percent = (value: number | null) => (value === null ? "—" : `${value}%`);

const days = (value: number | null) =>
    value === null ? "—" : `${value} ${value === 1 ? "day" : "days"}`;

export default function Index({
    ghostedAfterDays,
    kpis,
    funnel,
    outcomes,
    overTime,
    cycleTimes,
    timeline,
    filter,
}: MetricsProps) {
    const theme = useTheme();

    const funnelData = funnel.map((row) => ({
        ...row,
        fill: statusChartColor(theme, row.stage),
    }));
    const outcomeData = outcomes.map((row) => ({
        ...row,
        fill: statusChartColor(theme, row.outcome),
    }));

    return (
        <AdminLayout>
            <Head title="Application Metrics | Resume" />
            <PageHeader
                title="Application Metrics"
                backHref="/admin/resume"
                backLabel="Back to Resume Management"
            />

            <MetricsFilterBar filter={filter} />

            {/* KPI cards */}
            <Box sx={{ display: "flex", gap: 2, flexWrap: "wrap", mb: 3 }}>
                <StatCard
                    label="Applications"
                    value={String(kpis.totalApplied)}
                />
                <StatCard
                    label="Response rate"
                    value={percent(kpis.responseRate)}
                />
                <StatCard
                    label="Interview rate"
                    value={percent(kpis.interviewRate)}
                />
                <StatCard label="Offer rate" value={percent(kpis.offerRate)} />
                <StatCard
                    label={`Ghosted (>${ghostedAfterDays}d)`}
                    value={percent(kpis.ghostRate)}
                />
            </Box>

            {/* Funnel + Outcomes */}
            <Box sx={{ display: "flex", gap: 2, flexWrap: "wrap", mb: 3 }}>
                <ChartCard title="Pipeline funnel">
                    <ResponsiveContainer width="100%" height={280}>
                        <BarChart
                            layout="vertical"
                            data={funnelData}
                            margin={{ left: 24, right: 24 }}
                        >
                            <XAxis type="number" allowDecimals={false} />
                            <YAxis type="category" dataKey="label" width={90} />
                            <RechartsTooltip
                                cursor={{ fill: theme.palette.action.hover }}
                            />
                            <Bar dataKey="count" radius={[0, 4, 4, 0]}>
                                <LabelList dataKey="count" position="right" />
                            </Bar>
                        </BarChart>
                    </ResponsiveContainer>
                </ChartCard>

                <ChartCard title="Outcomes">
                    {outcomes.length === 0 ? (
                        <Typography
                            variant="body2"
                            color="text.secondary"
                            sx={{ py: 4 }}
                        >
                            No outcomes to show yet.
                        </Typography>
                    ) : (
                        <ResponsiveContainer width="100%" height={280}>
                            <PieChart>
                                <Pie
                                    data={outcomeData}
                                    dataKey="count"
                                    nameKey="label"
                                    innerRadius={60}
                                    outerRadius={100}
                                    paddingAngle={2}
                                />
                                <RechartsTooltip />
                                <Legend />
                            </PieChart>
                        </ResponsiveContainer>
                    )}
                </ChartCard>
            </Box>

            {/* Applications over time */}
            <Box sx={{ mb: 3 }}>
                <ChartCard title="Applications over time">
                    <ResponsiveContainer width="100%" height={260}>
                        <BarChart
                            data={overTime}
                            margin={{ left: 8, right: 8 }}
                        >
                            <XAxis dataKey="period" />
                            <YAxis allowDecimals={false} />
                            <RechartsTooltip
                                cursor={{ fill: theme.palette.action.hover }}
                            />
                            <Bar
                                dataKey="count"
                                fill={theme.palette.primary.main}
                                radius={[4, 4, 0, 0]}
                            />
                        </BarChart>
                    </ResponsiveContainer>
                </ChartCard>
            </Box>

            {/* Cycle times */}
            <Box sx={{ display: "flex", gap: 2, flexWrap: "wrap", mb: 3 }}>
                <StatCard
                    label="Avg days to first response"
                    value={days(cycleTimes.toFirstResponse)}
                />
                <StatCard
                    label="Avg days to rejection"
                    value={days(cycleTimes.toRejection)}
                />
                <StatCard
                    label="Avg days to offer"
                    value={days(cycleTimes.toOffer)}
                />
            </Box>

            {/* Timeline — collapsed until asked for */}
            <Accordion
                variant="outlined"
                disableGutters
                slotProps={TIMELINE_ACCORDION_SLOT_PROPS}
                sx={{ "&::before": { display: "none" } }}
            >
                <AccordionSummary
                    expandIcon={<ExpandMoreIcon />}
                    aria-controls="application-timeline-content"
                    id="application-timeline-header"
                >
                    <Typography variant="h6" component="h2">
                        Application timeline
                    </Typography>
                </AccordionSummary>
                <AccordionDetails>
                    <ResumeTimeline rows={timeline} />
                </AccordionDetails>
            </Accordion>
        </AdminLayout>
    );
}
