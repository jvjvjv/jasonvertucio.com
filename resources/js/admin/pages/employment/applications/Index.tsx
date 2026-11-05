import { Head, router } from "@inertiajs/react";
import AddIcon from "@mui/icons-material/Add";
import FilterListIcon from "@mui/icons-material/FilterList";
import Alert from "@mui/material/Alert";
import Badge from "@mui/material/Badge";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import Table from "@mui/material/Table";
import TableBody from "@mui/material/TableBody";
import TableCell from "@mui/material/TableCell";
import TableContainer from "@mui/material/TableContainer";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import { useCallback, useRef, useState } from "react";

import ApplicationRow from "./ApplicationRow";
import FiltersDialog, { countActiveFilters } from "./FiltersDialog";

import type {
    DialogFilters,
    StatusOption,
    UsesTargetedResume,
} from "./FiltersDialog";
import type { DebouncedSearchFieldHandle } from "@/admin/components/DebouncedSearchField";
import type { StartedIntel } from "@/admin/pages/employment/company-intel/useBeginIntel";
import type { ApplicationListItem } from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import DebouncedSearchField from "@/admin/components/DebouncedSearchField";
import EmptyTableRow from "@/admin/components/EmptyTableRow";
import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";
import IntelSystemDialog from "@/admin/pages/employment/company-intel/IntelSystemDialog";
import useBeginIntel from "@/admin/pages/employment/company-intel/useBeginIntel";
import { api, apiErrorMessage } from "@/api";
import ResponsiveButton from "@/components/ResponsiveButton";
import useConfirmDialog from "@/hooks/useConfirmDialog";

const LIST_URL = "/admin/employment/applications";
const NEW_SESSION_URL = "/admin/employment/applications/new";
const COLUMN_COUNT = 9;

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
                    await api.post(
                        `/api/admin/employment/applications/${id}/pass`,
                    );
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

    const handleIntelStarted = useCallback((_started: StartedIntel) => {
        router.reload({ only: ["applications"] });
    }, []);
    const beginIntel = useBeginIntel(handleIntelStarted);
    const { begin: beginIntelFor } = beginIntel;
    const handleBeginIntel = useCallback(
        (id: number) => {
            void beginIntelFor(id, "background");
        },
        [beginIntelFor],
    );
    const intelError = beginIntel.chooser === null ? beginIntel.error : null;

    return (
        <AdminLayout>
            <Head title="Applications | Resume" />
            <PageHeader
                title="Applications"
                backHref="/admin/employment"
                backLabel="Back to Employment"
            />

            {intelError ? (
                <Alert
                    severity="error"
                    sx={{ mb: 2 }}
                    onClose={beginIntel.clearError}
                >
                    {intelError}
                </Alert>
            ) : null}
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
                                <TableCell>Company / Role</TableCell>
                                <TableCell>Fit Score</TableCell>
                                <TableCell>Resume</TableCell>
                                <TableCell>Tokens</TableCell>
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
                                        onBeginIntel={handleBeginIntel}
                                        isStartingIntel={
                                            beginIntel.startingId ===
                                            application.id
                                        }
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
            <IntelSystemDialog
                key={beginIntel.chooser?.applicationId ?? "closed"}
                open={beginIntel.chooser !== null}
                systems={beginIntel.chooser?.systems ?? []}
                isStarting={beginIntel.startingId !== null}
                error={beginIntel.chooser !== null ? beginIntel.error : null}
                onChoose={beginIntel.choose}
                onClose={beginIntel.closeChooser}
            />
            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
