import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import DeleteIcon from "@mui/icons-material/Delete";
import EditIcon from "@mui/icons-material/Edit";
import ThumbDownIcon from "@mui/icons-material/ThumbDown";
import ThumbUpIcon from "@mui/icons-material/ThumbUp";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import Chip from "@mui/material/Chip";
import IconButton from "@mui/material/IconButton";
import Link from "@mui/material/Link";
import MenuItem from "@mui/material/MenuItem";
import Table from "@mui/material/Table";
import TableBody from "@mui/material/TableBody";
import TableCell from "@mui/material/TableCell";
import TableContainer from "@mui/material/TableContainer";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import TextField from "@mui/material/TextField";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";

import ConfirmDialog from "../../../components/ConfirmDialog";
import EmptyTableRow from "../../../components/EmptyTableRow";
import PageHeader from "../../../components/PageHeader";
import Pagination from "../../../components/Pagination";
import AdminLayout from "../../../layouts/AdminLayout";

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

            <Card>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>ID</TableCell>
                                <TableCell>Domain</TableCell>
                                <TableCell>Status</TableCell>
                                <TableCell>Selectors &amp; Reasoning</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {parsers.data.length === 0 ? (
                                <EmptyTableRow
                                    colSpan={5}
                                    message="No job URL parsers found."
                                />
                            ) : (
                                parsers.data.map((parser) => {
                                    const isActive = parser.status === "active";
                                    const isInactive =
                                        parser.status === "inactive";

                                    return (
                                        <TableRow key={parser.id} hover>
                                            <TableCell>#{parser.id}</TableCell>
                                            <TableCell>
                                                <Link
                                                    component={InertiaLink}
                                                    href={`/admin/ai/job-url-parsers/${parser.id}`}
                                                    underline="hover"
                                                    color="inherit"
                                                    sx={{ fontWeight: 500 }}
                                                >
                                                    {parser.domain}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                <Chip
                                                    size="small"
                                                    color={
                                                        isActive
                                                            ? "success"
                                                            : "default"
                                                    }
                                                    label={parser.status}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Typography
                                                    variant="body2"
                                                    color="text.secondary"
                                                    sx={{
                                                        fontFamily: "monospace",
                                                    }}
                                                >
                                                    title:{" "}
                                                    {parser.job_title_selector ??
                                                        "-"}
                                                </Typography>
                                                <Typography
                                                    variant="body2"
                                                    color="text.secondary"
                                                    sx={{
                                                        fontFamily: "monospace",
                                                    }}
                                                >
                                                    company:{" "}
                                                    {parser.company_name_selector ??
                                                        "-"}
                                                </Typography>
                                                <Typography
                                                    variant="body2"
                                                    color="text.secondary"
                                                    sx={{
                                                        fontFamily: "monospace",
                                                    }}
                                                >
                                                    location:{" "}
                                                    {parser.job_location_selector ??
                                                        "-"}
                                                </Typography>
                                                <Typography
                                                    variant="body2"
                                                    color="text.secondary"
                                                    sx={{
                                                        fontFamily: "monospace",
                                                    }}
                                                >
                                                    description:{" "}
                                                    {parser.job_description_selector ??
                                                        "-"}
                                                </Typography>
                                                {parser.reasoning_preview && (
                                                    <Typography
                                                        variant="body2"
                                                        color="text.disabled"
                                                        sx={{
                                                            mt: 0.5,
                                                            fontStyle: "italic",
                                                        }}
                                                    >
                                                        {
                                                            parser.reasoning_preview
                                                        }
                                                    </Typography>
                                                )}
                                            </TableCell>
                                            <TableCell align="right">
                                                <Box
                                                    sx={{
                                                        display: "flex",
                                                        justifyContent:
                                                            "flex-end",
                                                    }}
                                                >
                                                    <Tooltip title="Edit">
                                                        <IconButton
                                                            size="small"
                                                            component={
                                                                InertiaLink
                                                            }
                                                            href={`/admin/ai/job-url-parsers/${parser.id}`}
                                                        >
                                                            <EditIcon fontSize="small" />
                                                        </IconButton>
                                                    </Tooltip>

                                                    <Tooltip
                                                        title={
                                                            isActive
                                                                ? "Already approved"
                                                                : "Approve"
                                                        }
                                                    >
                                                        <span>
                                                            <IconButton
                                                                size="small"
                                                                color="success"
                                                                disabled={
                                                                    isActive
                                                                }
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
                                                            isInactive
                                                                ? "Already inactive"
                                                                : "Reject"
                                                        }
                                                    >
                                                        <span>
                                                            <IconButton
                                                                size="small"
                                                                color="warning"
                                                                disabled={
                                                                    isInactive
                                                                }
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
                                                                disabled={
                                                                    isActive
                                                                }
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
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>
                <Pagination
                    links={parsers.links}
                    lastPage={parsers.last_page}
                />
            </Card>

            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
