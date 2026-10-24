import { Head, Link as InertiaLink } from "@inertiajs/react";
import TravelExploreIcon from "@mui/icons-material/TravelExplore";
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

import {
    INTEL_PAGES,
    INTEL_STATUS_COLORS,
    INTEL_STATUS_LABELS,
    type IntelStatus,
} from "./types";

import EmptyTableRow from "@/admin/components/EmptyTableRow";
import PageHeader from "@/admin/components/PageHeader";
import StatusChip from "@/admin/components/StatusChip";
import AdminLayout from "@/admin/layouts/AdminLayout";
import { formatCalendarDate } from "@/utils/date";

const COLUMN_COUNT = 6;

interface IntelRow {
    id: number;
    status: IntelStatus;
    company_name: string | null;
    application: {
        id: number;
        company_name: string | null;
        position: string | null;
    };
    ai_system_name: string | null;
    updated_at: string | null;
}

interface IndexProps {
    intel: IntelRow[];
}

/**
 * Every intel session, newest activity first. Intel is started from an
 * application, never from here.
 */
export default function Index({ intel }: IndexProps) {
    return (
        <AdminLayout>
            <Head title="Company Intel" />
            <PageHeader
                title="Company Intel"
                backHref="/admin/employment"
                backLabel="Back to Employment"
            />

            <Card>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>Company</TableCell>
                                <TableCell>Role</TableCell>
                                <TableCell>State</TableCell>
                                <TableCell>AI System</TableCell>
                                <TableCell>Updated</TableCell>
                                <TableCell align="right">Open</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {intel.length === 0 ? (
                                <EmptyTableRow
                                    colSpan={COLUMN_COUNT}
                                    message="No intel yet. Intel starts when you mark an application applied, or from an application's Intel button."
                                    actionLabel="Go to Applications"
                                    actionHref="/admin/resume/applications"
                                />
                            ) : (
                                intel.map((row) => {
                                    const intelUrl = `${INTEL_PAGES}/${row.id}`;

                                    return (
                                        <TableRow key={row.id} hover>
                                            <TableCell>
                                                <Link
                                                    component={InertiaLink}
                                                    href={intelUrl}
                                                    underline="hover"
                                                    fontWeight={600}
                                                >
                                                    {row.company_name ?? (
                                                        <Typography
                                                            component="span"
                                                            variant="inherit"
                                                            color="text.secondary"
                                                            fontWeight={400}
                                                        >
                                                            Unidentified
                                                        </Typography>
                                                    )}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                <Link
                                                    component={InertiaLink}
                                                    href={`/admin/resume/applications/${row.application.id}`}
                                                    underline="hover"
                                                    color="inherit"
                                                >
                                                    {row.application.position ??
                                                        "Unknown position"}
                                                </Link>
                                                {row.application
                                                    .company_name !== null &&
                                                row.application.company_name !==
                                                    row.company_name ? (
                                                    <Typography
                                                        variant="caption"
                                                        color="text.secondary"
                                                        display="block"
                                                    >
                                                        Listed as{" "}
                                                        {
                                                            row.application
                                                                .company_name
                                                        }
                                                    </Typography>
                                                ) : null}
                                            </TableCell>
                                            <TableCell>
                                                <StatusChip
                                                    status={row.status}
                                                    label={
                                                        INTEL_STATUS_LABELS[
                                                            row.status
                                                        ]
                                                    }
                                                    colorMap={
                                                        INTEL_STATUS_COLORS
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell>
                                                {row.ai_system_name ?? "—"}
                                            </TableCell>
                                            <TableCell>
                                                <Typography variant="caption">
                                                    {row.updated_at
                                                        ? formatCalendarDate(
                                                              row.updated_at,
                                                          )
                                                        : "—"}
                                                </Typography>
                                            </TableCell>
                                            <TableCell align="right">
                                                <IconButton
                                                    component={InertiaLink}
                                                    href={intelUrl}
                                                    size="small"
                                                    color="primary"
                                                    title="Open intel"
                                                    aria-label={`Open intel for ${row.company_name ?? row.application.company_name ?? "this role"}`}
                                                >
                                                    <TravelExploreIcon fontSize="small" />
                                                </IconButton>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>
            </Card>
        </AdminLayout>
    );
}
