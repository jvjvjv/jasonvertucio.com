import { Head } from "@inertiajs/react";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Checkbox from "@mui/material/Checkbox";
import FormControlLabel from "@mui/material/FormControlLabel";
import MenuItem from "@mui/material/MenuItem";
import Switch from "@mui/material/Switch";
import Tab from "@mui/material/Tab";
import Tabs from "@mui/material/Tabs";
import TextField from "@mui/material/TextField";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";
import { type ReactNode, useState } from "react";

import ConfirmDialog from "../components/ConfirmDialog";
import DataTable, { type ColumnDef } from "../components/DataTable";
import DateInput from "../components/DateInput";
import NavGrid, { type NavBlock } from "../components/NavGrid";
import PageHeader from "../components/PageHeader";
import Pagination from "../components/Pagination";
import StatusChip from "../components/StatusChip";
import AdminLayout from "../layouts/AdminLayout";
import { color, font, typeScale } from "../theme/tokens";
import { getIcon } from "../utils/iconRegistry";

const THIS_PAGE = "/admin/design-reference";

const STATUSES = [
    "draft",
    "passed",
    "applied",
    "interviewing",
    "interviewed",
    "offered",
    "accepted",
    "hired",
    "rejected",
    "ghosted",
];

const TYPE_SAMPLES = [
    { variant: "h4", label: "Page title" },
    { variant: "h5", label: "Title" },
    { variant: "h6", label: "Section heading" },
    { variant: "subtitle1", label: "Subtitle" },
    { variant: "subtitle2", label: "Small subtitle" },
    { variant: "body1", label: "Body text" },
    { variant: "body2", label: "Small body text" },
    { variant: "caption", label: "Caption" },
] as const;

const SAMPLE_BLOCKS: NavBlock[] = [
    {
        href: THIS_PAGE,
        external: true,
        icon: getIcon("WorkOutline"),
        label: "Applications",
        description: "A destination with a one-line description",
    },
    {
        href: THIS_PAGE,
        external: true,
        icon: getIcon("Insights"),
        label: "Application Metrics",
        description: "Another destination, to show how the rows line up",
    },
];

interface SampleRow {
    id: number;
    company: string;
    status: string;
}

const SAMPLE_ROWS: SampleRow[] = [
    { id: 1, company: "Northwind Traders", status: "applied" },
    { id: 2, company: "Globex", status: "interviewing" },
    { id: 3, company: "Initech", status: "rejected" },
];

const SAMPLE_COLUMNS: ColumnDef<SampleRow>[] = [
    { key: "company", label: "Company", sortable: true },
    {
        key: "status",
        label: "Status",
        render: (row) => <StatusChip status={row.status} />,
    },
];

const SAMPLE_PAGINATION = {
    last_page: 3,
    links: [
        { url: null, label: "&laquo; Previous", active: false },
        { url: THIS_PAGE, label: "1", active: true },
        { url: THIS_PAGE, label: "2", active: false },
        { url: THIS_PAGE, label: "3", active: false },
        { url: THIS_PAGE, label: "Next &raquo;", active: false },
    ],
};

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <Box
            component="section"
            sx={{ pt: 3, pb: 4, borderTop: 1, borderColor: "divider" }}
        >
            <Typography variant="h5" component="h2" sx={{ mb: 2 }}>
                {title}
            </Typography>
            {children}
        </Box>
    );
}

function Row({ children }: { children: ReactNode }) {
    return (
        <Box
            sx={{
                display: "flex",
                flexWrap: "wrap",
                alignItems: "center",
                gap: 2,
            }}
        >
            {children}
        </Box>
    );
}

/**
 * Every token and shared component in its states, on sample data held here, so
 * a design change can be reviewed in one place. Nothing on this page saves.
 */
export default function DesignReference() {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [tab, setTab] = useState(0);
    const [date, setDate] = useState("2026-10-30");
    const [sortDirection, setSortDirection] = useState<"asc" | "desc">("asc");
    const sortedRows =
        sortDirection === "asc" ? SAMPLE_ROWS : [...SAMPLE_ROWS].reverse();

    return (
        <AdminLayout maxContainerWidth="lg">
            <Head title="Design reference" />
            <PageHeader
                title="Design reference"
                backHref="/admin"
                backLabel="Back to Admin"
            />
            <Typography sx={{ mb: 3 }}>
                Every token and shared component in its states, on sample data.
                Nothing here saves.
            </Typography>

            <Section title="Colour">
                <Box
                    sx={{
                        display: "grid",
                        gridTemplateColumns:
                            "repeat(auto-fill, minmax(140px, 1fr))",
                        gap: 2,
                    }}
                >
                    {Object.entries(color).map(([name, value]) => (
                        <Box key={name}>
                            <Box
                                sx={{
                                    height: 56,
                                    mb: 0.5,
                                    border: 1,
                                    borderColor: "divider",
                                    borderRadius: 1,
                                    bgcolor: value,
                                }}
                            />
                            <Typography variant="subtitle2" component="div">
                                {name}
                            </Typography>
                            <Typography
                                variant="caption"
                                color="text.secondary"
                            >
                                {value}
                            </Typography>
                        </Box>
                    ))}
                </Box>
            </Section>

            <Section title="Type">
                {TYPE_SAMPLES.map(({ variant, label }) => (
                    <Typography
                        key={variant}
                        variant={variant}
                        component="p"
                        sx={{ mb: 1 }}
                    >
                        {label}
                    </Typography>
                ))}
                <Typography
                    component="p"
                    sx={{ fontFamily: font.heading, fontSize: typeScale.area }}
                >
                    Navigation area
                </Typography>
            </Section>

            <Section title="Buttons">
                <Row>
                    <Button variant="contained">Save changes</Button>
                    <Button variant="contained" disabled>
                        Disabled
                    </Button>
                    <Button variant="outlined">Filter</Button>
                    <Button>Cancel</Button>
                    <Button variant="contained" color="error">
                        Delete
                    </Button>
                </Row>
            </Section>

            <Section title="Form fields">
                <Row>
                    <TextField label="Empty" size="small" />
                    <TextField
                        label="Filled"
                        size="small"
                        defaultValue="Northwind Traders"
                    />
                    <TextField
                        label="With an error"
                        size="small"
                        error
                        defaultValue="Northwind"
                        helperText="Enter the full company name"
                    />
                    <TextField
                        label="Disabled"
                        size="small"
                        disabled
                        defaultValue="Locked"
                    />
                    <TextField
                        select
                        label="Select"
                        size="small"
                        defaultValue="applied"
                        sx={{ minWidth: 160 }}
                    >
                        <MenuItem value="applied">Applied</MenuItem>
                        <MenuItem value="rejected">Rejected</MenuItem>
                    </TextField>
                    <DateInput label="Date" value={date} onChange={setDate} />
                    <FormControlLabel
                        control={<Checkbox defaultChecked />}
                        label="Checkbox"
                    />
                    <FormControlLabel
                        control={<Switch defaultChecked />}
                        label="Switch"
                    />
                </Row>
            </Section>

            <Section title="Status chips">
                <Row>
                    {STATUSES.map((status) => (
                        <StatusChip key={status} status={status} />
                    ))}
                </Row>
            </Section>

            <Section title="Hub directory">
                <NavGrid blocks={SAMPLE_BLOCKS} />
            </Section>

            <Section title="Data table">
                <Box sx={{ display: "grid", gap: 3 }}>
                    <DataTable
                        columns={SAMPLE_COLUMNS}
                        data={sortedRows}
                        sorting={{
                            column: "company",
                            direction: sortDirection,
                            onChange: (_, direction) => {
                                setSortDirection(direction);
                            },
                        }}
                        pagination={SAMPLE_PAGINATION}
                    />
                    <DataTable columns={SAMPLE_COLUMNS} data={[]} loading />
                    <DataTable
                        columns={SAMPLE_COLUMNS}
                        data={[]}
                        emptyMessage="No applications yet."
                    />
                </Box>
            </Section>

            <Section title="Pagination">
                <Pagination
                    links={SAMPLE_PAGINATION.links}
                    lastPage={SAMPLE_PAGINATION.last_page}
                />
            </Section>

            <Section title="Tabs">
                <Tabs
                    value={tab}
                    onChange={(_, value: number) => {
                        setTab(value);
                    }}
                >
                    <Tab label="Roles" />
                    <Tab label="Permissions" />
                    <Tab label="Disabled" disabled />
                </Tabs>
            </Section>

            <Section title="Messages">
                <Box sx={{ display: "grid", gap: 1 }}>
                    <Alert severity="success">Changes saved.</Alert>
                    <Alert severity="info">
                        Documents generate when you save.
                    </Alert>
                    <Alert severity="warning">
                        This session is close to its context limit.
                    </Alert>
                    <Alert severity="error">
                        The file couldn&apos;t be generated. Check the template
                        and try again.
                    </Alert>
                </Box>
            </Section>

            <Section title="Dialog and tooltip">
                <Row>
                    <Button
                        variant="outlined"
                        onClick={() => {
                            setDialogOpen(true);
                        }}
                    >
                        Open dialog
                    </Button>
                    <Tooltip title="A short explanation" arrow>
                        <Button>Hover for a tooltip</Button>
                    </Tooltip>
                </Row>
                <ConfirmDialog
                    open={dialogOpen}
                    title="Delete this application?"
                    message="This sample dialog doesn't delete anything."
                    confirmLabel="Delete"
                    onConfirm={() => {
                        setDialogOpen(false);
                    }}
                    onCancel={() => {
                        setDialogOpen(false);
                    }}
                />
            </Section>
        </AdminLayout>
    );
}
