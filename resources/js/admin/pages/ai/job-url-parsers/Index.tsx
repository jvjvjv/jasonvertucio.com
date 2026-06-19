import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import DeleteIcon from "@mui/icons-material/Delete";
import EditIcon from "@mui/icons-material/Edit";
import ThumbDownIcon from "@mui/icons-material/ThumbDown";
import ThumbUpIcon from "@mui/icons-material/ThumbUp";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Chip from "@mui/material/Chip";
import IconButton from "@mui/material/IconButton";
import Link from "@mui/material/Link";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";

import ConfirmDialog from "../../../components/ConfirmDialog";
import DataTable from "../../../components/DataTable";
import PageHeader from "../../../components/PageHeader";
import AdminLayout from "../../../layouts/AdminLayout";

import type { ColumnDef } from "../../../components/DataTable";
import type { PaginatedResponse } from "@/types";

import useConfirmDialog from "@/hooks/useConfirmDialog";

interface ParserRow {
    id: number;
    domain: string;
    status: "active" | "inactive";
    company_name_selector: string | null;
    job_title_selector: string | null;
    job_location_selector: string | null;
    job_description_selector: string | null;
    reasoning_preview: string | null;
    updated_at: string | null;
}

interface IndexProps {
    parsers: PaginatedResponse<ParserRow>;
    filters: {
        status?: string;
        domain?: string;
        search?: string;
    };
    domains: string[];
}

const columns: ColumnDef<ParserRow>[] = [
    { key: "id", label: "ID", render: (row) => `#${row.id}` },
    {
        key: "domain",
        label: "Domain",
        render: (row) => (
            <Link
                component={InertiaLink}
                href={`/admin/ai/job-url-parsers/${row.id}`}
                underline="hover"
                color="inherit"
                sx={{ fontWeight: 500 }}
            >
                {row.domain}
            </Link>
        ),
    },
    {
        key: "status",
        label: "Status",
        render: (row) => (
            <Chip
                size="small"
                color={row.status === "active" ? "success" : "default"}
                label={row.status}
            />
        ),
    },
    {
        key: "selectors",
        label: "Selectors & Reasoning",
        render: (row) => (
            <>
                <Typography
                    variant="body2"
                    color="text.secondary"
                    sx={{ fontFamily: "monospace" }}
                >
                    title: {row.job_title_selector ?? "-"}
                </Typography>
                <Typography
                    variant="body2"
                    color="text.secondary"
                    sx={{ fontFamily: "monospace" }}
                >
                    company: {row.company_name_selector ?? "-"}
                </Typography>
                <Typography
                    variant="body2"
                    color="text.secondary"
                    sx={{ fontFamily: "monospace" }}
                >
                    location: {row.job_location_selector ?? "-"}
                </Typography>
                <Typography
                    variant="body2"
                    color="text.secondary"
                    sx={{ fontFamily: "monospace" }}
                >
                    description: {row.job_description_selector ?? "-"}
                </Typography>
                {row.reasoning_preview && (
                    <Typography
                        variant="body2"
                        color="text.disabled"
                        sx={{ mt: 0.5, fontStyle: "italic" }}
                    >
                        {row.reasoning_preview}
                    </Typography>
                )}
            </>
        ),
    },
];

export default function Index({ parsers, filters, domains }: IndexProps) {
    const { dialogProps, confirm } = useConfirmDialog();

    const handleFilter = (
        key: "status" | "domain" | "search",
        value: string,
    ) => {
        const next = {
            ...filters,
            [key]: value || undefined,
        };

        router.get("/admin/ai/job-url-parsers", next, {
            preserveState: true,
            replace: true,
        });
    };

    const handleApprove = (id: number, domain: string) => {
        confirm(
            `Approve parser #${id} for ${domain}? All other parsers for ${domain} will be set to inactive.`,
            () => {
                router.post(`/admin/ai/job-url-parsers/${id}/approve`);
            },
            { confirmLabel: "Approve", confirmColor: "primary" },
        );
    };

    const handleReject = (id: number, domain: string) => {
        confirm(
            `Mark parser #${id} for ${domain} as inactive?`,
            () => {
                router.post(`/admin/ai/job-url-parsers/${id}/reject`);
            },
            { confirmLabel: "Reject", confirmColor: "warning" },
        );
    };

    const handleDelete = (id: number, domain: string) => {
        confirm(
            `Permanently delete parser #${id} for ${domain}? This cannot be undone.`,
            () => {
                router.delete(`/admin/ai/job-url-parsers/${id}`);
            },
            { confirmLabel: "Delete", confirmColor: "error" },
        );
    };

    return (
        <AdminLayout>
            <Head title="Job URL Parsers | AI Tools" />
            <PageHeader
                title="Job URL Parsers"
                backHref="/admin/ai"
                backLabel="Back to AI Tools"
            />

            <Alert severity="info" sx={{ mb: 2 }}>
                Approving a parser activates it and marks every other parser for
                the same domain as inactive. Parsers for other domains are
                unaffected.
            </Alert>

            <Box
                sx={{
                    display: "flex",
                    gap: 2,
                    mb: 2,
                    flexWrap: "wrap",
                    alignItems: "center",
                }}
            >
                <TextField
                    label="Status"
                    select
                    size="small"
                    value={filters.status ?? ""}
                    onChange={(e) => {
                        handleFilter("status", e.target.value);
                    }}
                    sx={{ minWidth: 180 }}
                >
                    <MenuItem value="">All Statuses</MenuItem>
                    <MenuItem value="active">Active</MenuItem>
                    <MenuItem value="inactive">Inactive</MenuItem>
                </TextField>

                <TextField
                    label="Domain"
                    select
                    size="small"
                    value={filters.domain ?? ""}
                    onChange={(e) => {
                        handleFilter("domain", e.target.value);
                    }}
                    sx={{ minWidth: 220 }}
                >
                    <MenuItem value="">All Domains</MenuItem>
                    {domains.map((domain) => (
                        <MenuItem key={domain} value={domain}>
                            {domain}
                        </MenuItem>
                    ))}
                </TextField>

                <TextField
                    label="Search"
                    size="small"
                    value={filters.search ?? ""}
                    onChange={(e) => {
                        handleFilter("search", e.target.value);
                    }}
                    placeholder="Domain, selector, reasoning..."
                    sx={{ minWidth: 300 }}
                />
            </Box>

            <DataTable
                columns={columns}
                data={parsers.data}
                emptyMessage="No job URL parsers found."
                pagination={parsers}
                rowActions={(parser) => {
                    const isActive = parser.status === "active";
                    const isInactive = parser.status === "inactive";
                    return (
                        <Box
                            sx={{ display: "flex", justifyContent: "flex-end" }}
                        >
                            <Tooltip title="Edit">
                                <IconButton
                                    size="small"
                                    component={InertiaLink}
                                    href={`/admin/ai/job-url-parsers/${parser.id}`}
                                >
                                    <EditIcon fontSize="small" />
                                </IconButton>
                            </Tooltip>

                            <Tooltip
                                title={
                                    isActive ? "Already approved" : "Approve"
                                }
                            >
                                <span>
                                    <IconButton
                                        size="small"
                                        color="success"
                                        disabled={isActive}
                                        onClick={() => {
                                            handleApprove(
                                                parser.id,
                                                parser.domain,
                                            );
                                        }}
                                    >
                                        <ThumbUpIcon fontSize="small" />
                                    </IconButton>
                                </span>
                            </Tooltip>

                            <Tooltip
                                title={
                                    isInactive ? "Already inactive" : "Reject"
                                }
                            >
                                <span>
                                    <IconButton
                                        size="small"
                                        color="warning"
                                        disabled={isInactive}
                                        onClick={() => {
                                            handleReject(
                                                parser.id,
                                                parser.domain,
                                            );
                                        }}
                                    >
                                        <ThumbDownIcon fontSize="small" />
                                    </IconButton>
                                </span>
                            </Tooltip>

                            <Tooltip
                                title={
                                    isActive
                                        ? "Reject or supersede before deleting"
                                        : "Delete"
                                }
                            >
                                <span>
                                    <IconButton
                                        size="small"
                                        color="error"
                                        disabled={isActive}
                                        onClick={() => {
                                            handleDelete(
                                                parser.id,
                                                parser.domain,
                                            );
                                        }}
                                    >
                                        <DeleteIcon fontSize="small" />
                                    </IconButton>
                                </span>
                            </Tooltip>
                        </Box>
                    );
                }}
            />

            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
