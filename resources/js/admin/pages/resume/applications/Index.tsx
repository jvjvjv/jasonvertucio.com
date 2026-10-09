import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import AddIcon from "@mui/icons-material/Add";
import AutoFixHighOutlinedIcon from "@mui/icons-material/AutoFixHighOutlined";
import BackHandOutlinedIcon from "@mui/icons-material/BackHandOutlined";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import EditNoteIcon from "@mui/icons-material/EditNote";
import FilterListIcon from "@mui/icons-material/FilterList";
import Alert from "@mui/material/Alert";
import Badge from "@mui/material/Badge";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import IconButton from "@mui/material/IconButton";
import Link from "@mui/material/Link";
import Table from "@mui/material/Table";
import TableBody from "@mui/material/TableBody";
import TableCell from "@mui/material/TableCell";
import TableContainer from "@mui/material/TableContainer";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import Typography from "@mui/material/Typography";
import { memo, useCallback, useRef, useState } from "react";

import FiltersDialog, { countActiveFilters } from "./FiltersDialog";

import type {
    DialogFilters,
    StatusOption,
    UsesTargetedResume,
} from "./FiltersDialog";
import type { DebouncedSearchFieldHandle } from "@/admin/components/DebouncedSearchField";
import type { ApplicationListItem } from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import DebouncedSearchField from "@/admin/components/DebouncedSearchField";
import EmptyTableRow from "@/admin/components/EmptyTableRow";
import PageHeader from "@/admin/components/PageHeader";
import StatusChip from "@/admin/components/StatusChip";
import UsageChip from "@/admin/components/UsageChip";
import AdminLayout from "@/admin/layouts/AdminLayout";
import { resolveApplicationDisplayStatus } from "@/admin/utils/applicationStatus";
import { api, apiErrorMessage } from "@/api";
import ResponsiveButton from "@/components/ResponsiveButton";
import useConfirmDialog from "@/hooks/useConfirmDialog";
import { formatCalendarDate } from "@/utils/date";

const LIST_URL = "/admin/resume/applications";
const NEW_SESSION_URL = "/admin/resume/applications/new";
const COLUMN_COUNT = 8;
const EM_DASH = "—";

interface IndexFilters {
    statuses: string[];
    uses_targeted_resume: UsesTargetedResume;
    search: string;
}

interface IndexProps {
    applications: ApplicationListItem[];
    allStatuses: StatusOption[];
    filters: IndexFilters;
    /** Days without an update after which an applied application shows as ghosted. */
    ghostedAfterDays: number;
}

/**
 * The query string for a set of filters. A filter at its default is left out
 * altogether, so a cleared list has no filter parameters in its URL.
 */
function buildQuery(filters: IndexFilters): {
    [key: string]: string | string[];
} {
    const query: { [key: string]: string | string[] } = {};

    if (filters.statuses.length > 0) {
        query.status = filters.statuses;
    }
    if (filters.uses_targeted_resume !== "any") {
        query.uses_targeted_resume = filters.uses_targeted_resume;
    }
    if (filters.search !== "") {
        query.search = filters.search;
    }

    return query;
}

function visitWithFilters(filters: IndexFilters, onSuccess?: () => void): void {
    router.get(LIST_URL, buildQuery(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ["applications", "filters"],
        onSuccess,
    });
}

interface ApplicationRowProps {
    application: ApplicationListItem;
    ghostedAfterDays: number;
    onPass: (id: number) => void;
    onDelete: (application: ApplicationListItem) => void;
}

/**
 * One row of the list. Memoized, with stable callbacks from the page, so
 * opening a dialog or reporting an error does not re-render every row.
 */
const ApplicationRow = memo(function ApplicationRow({
    application,
    ghostedAfterDays,
    onPass,
    onDelete,
}: ApplicationRowProps) {
    const showUrl = `${LIST_URL}/${application.id}`;
    const latestOccurredAt = application.latest_status_update?.occurred_at;
    const displayStatus = resolveApplicationDisplayStatus(
        application.status,
        latestOccurredAt,
        ghostedAfterDays,
    );

    return (
        <TableRow hover>
            <TableCell>
                <Link
                    component={InertiaLink}
                    href={showUrl}
                    underline="hover"
                    color="inherit"
                >
                    <Typography
                        variant="body2"
                        fontWeight={600}
                        color="primary"
                    >
                        {application.company_name}
                    </Typography>
                </Link>
                {application.position ? (
                    <Typography variant="caption" color="text.secondary">
                        {application.position}
                    </Typography>
                ) : null}
            </TableCell>
            <TableCell align="center">
                {application.targeted_resume_id !== null ? (
                    <IconButton
                        component={InertiaLink}
                        href={`/admin/resume/targeted-resumes/${application.targeted_resume_id}/edit`}
                        size="small"
                        color="primary"
                        title="Edit targeted resume"
                        aria-label="Edit targeted resume"
                    >
                        <EditNoteIcon fontSize="small" />
                    </IconButton>
                ) : (
                    EM_DASH
                )}
            </TableCell>
            <TableCell>{application.resume_version ?? EM_DASH}</TableCell>
            <TableCell>
                {application.fit_score !== null
                    ? `${application.fit_score}%`
                    : EM_DASH}
            </TableCell>
            <TableCell>
                {application.usage ? (
                    <UsageChip usage={application.usage} />
                ) : null}
            </TableCell>
            <TableCell>
                <StatusChip
                    status={displayStatus}
                    tip={
                        latestOccurredAt
                            ? formatCalendarDate(latestOccurredAt)
                            : undefined
                    }
                />
            </TableCell>
            <TableCell>
                <Typography variant="caption">
                    {application.last_activity_at ?? "-"}
                </Typography>
            </TableCell>
            <TableCell align="right">
                <Box
                    sx={{
                        display: "flex",
                        justifyContent: "flex-end",
                        gap: 1,
                    }}
                >
                    <IconButton
                        component={InertiaLink}
                        href={showUrl}
                        size="small"
                        color="primary"
                        title="Open"
                        aria-label="Open"
                    >
                        <AutoFixHighOutlinedIcon fontSize="small" />
                    </IconButton>
                    {application.status === "draft" ? (
                        <IconButton
                            size="small"
                            color="warning"
                            title="Pass"
                            aria-label="Pass"
                            onClick={() => {
                                onPass(application.id);
                            }}
                        >
                            <BackHandOutlinedIcon fontSize="small" />
                        </IconButton>
                    ) : null}
                    <IconButton
                        size="small"
                        color="error"
                        title="Delete"
                        aria-label="Delete"
                        onClick={() => {
                            onDelete(application);
                        }}
                    >
                        <DeleteOutlineIcon fontSize="small" />
                    </IconButton>
                </Box>
            </TableCell>
        </TableRow>
    );
});

export default function Index({
    applications,
    allStatuses,
    filters,
    ghostedAfterDays,
}: IndexProps) {
    const [filtersOpen, setFiltersOpen] = useState(false);
    /** Bumped to remount the search field when the filters are cleared. */
    const [searchResetKey, setSearchResetKey] = useState(0);
    const searchFieldRef = useRef<DebouncedSearchFieldHandle>(null);
    const [actionError, setActionError] = useState<string | null>(null);
    const { dialogProps, confirm } = useConfirmDialog();

    const dialogFilters: DialogFilters = {
        statuses: filters.statuses,
        usesTargetedResume: filters.uses_targeted_resume,
    };
    const activeFilterCount = countActiveFilters(dialogFilters);
    const isFiltered = activeFilterCount > 0 || filters.search !== "";

    const handleSearch = (search: string) => {
        visitWithFilters({ ...filters, search });
    };

    const handleApplyFilters = (next: DialogFilters) => {
        setFiltersOpen(false);
        // Take over any search still waiting out its pause: the text typed is
        // sent with these filters, and the pending search cannot follow.
        const search = searchFieldRef.current?.takeText() ?? filters.search;
        visitWithFilters({
            search,
            statuses: next.statuses,
            uses_targeted_resume: next.usesTargetedResume,
        });
    };

    const handleClearFilters = () => {
        setFiltersOpen(false);
        searchFieldRef.current?.takeText();
        visitWithFilters(
            { statuses: [], uses_targeted_resume: "any", search: "" },
            // The search field seeds itself from `filters.search` on mount,
            // so it is remounted only once the cleared filters have arrived.
            () => {
                setSearchResetKey((key) => key + 1);
            },
        );
    };

    const handleDelete = useCallback(
        (application: ApplicationListItem) => {
            confirm(
                `Delete the ${application.company_name} application?`,
                () => {
                    router.delete(`${LIST_URL}/${application.id}`, {
                        preserveScroll: true,
                    });
                },
                { confirmLabel: "Delete" },
            );
        },
        [confirm],
    );

    const handlePass = useCallback(
        (id: number) => {
            const passApplication = async () => {
                setActionError(null);
                try {
                    await api.post(`/api/admin/resume/applications/${id}/pass`);
                    router.reload({ only: ["applications"] });
                } catch (error) {
                    setActionError(
                        apiErrorMessage(error, "Failed to mark as passed."),
                    );
                }
            };

            confirm(
                "Mark this opportunity as passed?",
                () => {
                    void passApplication();
                },
                { confirmLabel: "Pass", confirmColor: "warning" },
            );
        },
        [confirm],
    );

    return (
        <AdminLayout>
            <Head title="Applications | Resume" />
            <PageHeader
                title="Applications"
                backHref="/admin/resume"
                backLabel="Back to Resume Management"
            />

            {actionError ? (
                <Alert
                    severity="error"
                    sx={{ mb: 2 }}
                    onClose={() => {
                        setActionError(null);
                    }}
                >
                    {actionError}
                </Alert>
            ) : null}

            <Box
                sx={{
                    display: "flex",
                    gap: 2,
                    mb: 2,
                    flexWrap: "wrap",
                    alignItems: "center",
                }}
            >
                <DebouncedSearchField
                    key={searchResetKey}
                    ref={searchFieldRef}
                    initialValue={filters.search}
                    onSearch={handleSearch}
                    placeholder="Company, job title, or message..."
                    sx={{ minWidth: 250 }}
                />
                <Badge
                    badgeContent={activeFilterCount}
                    color="primary"
                    invisible={activeFilterCount === 0}
                >
                    <Button
                        variant="outlined"
                        startIcon={<FilterListIcon />}
                        aria-label={
                            activeFilterCount === 0
                                ? "Filter"
                                : `Filter, ${activeFilterCount} active`
                        }
                        aria-haspopup="dialog"
                        onClick={() => {
                            setFiltersOpen(true);
                        }}
                    >
                        Filter
                    </Button>
                </Badge>
                <Box sx={{ flexGrow: 1 }} />
                <ResponsiveButton
                    icon={<AddIcon />}
                    color="primary"
                    label="New Session"
                    href={NEW_SESSION_URL}
                    variant="contained"
                />
            </Box>

            <Card>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>Company / Job</TableCell>
                                <TableCell align="center">Resume</TableCell>
                                <TableCell>Base Version</TableCell>
                                <TableCell>Fit Score</TableCell>
                                <TableCell>AI Usage</TableCell>
                                <TableCell>Status</TableCell>
                                <TableCell>Updated</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {applications.length === 0 ? (
                                <EmptyTableRow
                                    colSpan={COLUMN_COUNT}
                                    message={
                                        isFiltered
                                            ? "No applications match these filters."
                                            : "No applications yet."
                                    }
                                    actionLabel="Start a new session"
                                    actionHref={NEW_SESSION_URL}
                                />
                            ) : (
                                applications.map((application) => (
                                    <ApplicationRow
                                        key={application.id}
                                        application={application}
                                        ghostedAfterDays={ghostedAfterDays}
                                        onPass={handlePass}
                                        onDelete={handleDelete}
                                    />
                                ))
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>
            </Card>

            <FiltersDialog
                open={filtersOpen}
                allStatuses={allStatuses}
                value={dialogFilters}
                onApply={handleApplyFilters}
                onClear={handleClearFilters}
                onClose={() => {
                    setFiltersOpen(false);
                }}
            />
            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
