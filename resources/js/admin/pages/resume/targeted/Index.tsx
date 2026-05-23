import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import AddIcon from "@mui/icons-material/Add";
import AutoFixHighOutlinedIcon from "@mui/icons-material/AutoFixHighOutlined";
import BackHandOutlinedIcon from "@mui/icons-material/BackHandOutlined";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import FormControl from "@mui/material/FormControl";
import IconButton from "@mui/material/IconButton";
import InputLabel from "@mui/material/InputLabel";
import Link from "@mui/material/Link";
import MenuItem from "@mui/material/MenuItem";
import OutlinedInput from "@mui/material/OutlinedInput";
import Select, { type SelectChangeEvent } from "@mui/material/Select";
import Table from "@mui/material/Table";
import TableBody from "@mui/material/TableBody";
import TableCell from "@mui/material/TableCell";
import TableContainer from "@mui/material/TableContainer";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";

import type { Conversation } from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import EmptyTableRow from "@/admin/components/EmptyTableRow";
import PageHeader from "@/admin/components/PageHeader";
import StatusChip from "@/admin/components/StatusChip";
import UsageChip from "@/admin/components/UsageChip";
import AdminLayout from "@/admin/layouts/AdminLayout";
import ResponsiveButton from "@/components/ResponsiveButton";
import useConfirmDialog from "@/hooks/useConfirmDialog";

interface StatusOption {
    value: string;
    label: string;
}

interface IndexProps {
    conversations: Conversation[];
    allStatuses: StatusOption[];
    filters: {
        statuses: string[];
        search: string;
    };
}

export default function Index({
    conversations,
    allStatuses,
    filters,
}: IndexProps) {
    const handleSearch = (search: string) => {
        router.get(
            "/admin/resume/targeted-builder",
            { search, status: filters.statuses },
            { preserveState: true },
        );
    };

    const handleStatusChange = (event: SelectChangeEvent<string[]>) => {
        const value = event.target.value;
        const updated = typeof value === "string" ? value.split(",") : value;
        router.get(
            "/admin/resume/targeted-builder",
            { status: updated, search: filters.search },
            { preserveState: true },
        );
    };

    const { dialogProps, confirm } = useConfirmDialog();

    const handleDelete = (id: number) => {
        confirm("Delete this conversation?", () => {
            router.delete(`/admin/resume/targeted-builder/${id}`);
        });
    };

    const handlePass = (id: number) => {
        confirm(
            "Mark this opportunity as passed?",
            () => {
                router.post(`/admin/resume/targeted-builder/${id}/pass`);
            },
            { confirmLabel: "Pass", confirmColor: "warning" },
        );
    };

    return (
        <AdminLayout>
            <Head title="Targeted Resumes | Resume" />
            <PageHeader
                title="Targeted Resume Builder"
                backHref="/admin/resume"
                backLabel="Back to Resume Management"
            />

            {/* Filters */}
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
                    label="Search"
                    size="small"
                    value={filters.search}
                    onChange={(e) => {
                        handleSearch(e.target.value);
                    }}
                    placeholder="Company, job title, or message..."
                    sx={{ minWidth: 250 }}
                />
                <FormControl size="small" sx={{ minWidth: 240 }}>
                    <InputLabel id="targeted-statuses-label">
                        Statuses
                    </InputLabel>
                    <Select
                        labelId="targeted-statuses-label"
                        multiple
                        value={filters.statuses}
                        onChange={handleStatusChange}
                        input={<OutlinedInput label="Statuses" />}
                        renderValue={(selected) => {
                            if (selected.length === 0) {
                                return "All statuses";
                            }

                            return allStatuses
                                .filter((status) =>
                                    selected.includes(status.value),
                                )
                                .map((status) => status.label)
                                .join(", ");
                        }}
                    >
                        {allStatuses.map((status) => (
                            <MenuItem key={status.value} value={status.value}>
                                {status.label}
                            </MenuItem>
                        ))}
                    </Select>
                </FormControl>
                <Box sx={{ flexGrow: 1 }} />
                <ResponsiveButton
                    icon={<AddIcon />}
                    color="primary"
                    label="New Session"
                    href="/admin/resume/targeted-builder/new"
                    variant="contained"
                />
            </Box>

            <Card>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>Company / Job</TableCell>
                                <TableCell>Base Version</TableCell>
                                <TableCell>Fit Score</TableCell>
                                <TableCell>Usage</TableCell>
                                <TableCell>Status</TableCell>
                                <TableCell>Updated</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {conversations.length === 0 ? (
                                <EmptyTableRow
                                    colSpan={8}
                                    message="No conversations found."
                                    actionLabel="Start one"
                                    actionHref="/admin/resume/targeted-builder/new"
                                />
                            ) : (
                                conversations.map((conv) => {
                                    const resume = conv.targeted_resume;
                                    const companyName =
                                        resume?.company_name ??
                                        (conv.context?.company_name as
                                            | string
                                            | undefined) ??
                                        "—";
                                    const position =
                                        resume?.position ??
                                        (conv.context?.job_title as
                                            | string
                                            | undefined) ??
                                        "";
                                    const displayStatus =
                                        resume?.status &&
                                        resume.status !== "draft"
                                            ? resume.status
                                            : conv.status;

                                    return (
                                        <TableRow key={conv.id} hover>
                                            <TableCell>
                                                <Link
                                                    component={InertiaLink}
                                                    href={`/admin/resume/targeted-builder/${conv.id}`}
                                                    underline="hover"
                                                    color="inherit"
                                                >
                                                    <Typography
                                                        variant="body2"
                                                        fontWeight={600}
                                                        color="primary"
                                                    >
                                                        {companyName}
                                                    </Typography>
                                                </Link>
                                                {position && (
                                                    <Typography
                                                        variant="caption"
                                                        color="text.secondary"
                                                    >
                                                        {position}
                                                    </Typography>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {resume?.resume_version ?? "—"}
                                            </TableCell>
                                            <TableCell>
                                                {resume?.fit_score != null
                                                    ? `${resume.fit_score}%`
                                                    : "—"}
                                            </TableCell>
                                            <TableCell>
                                                <UsageChip usage={conv.usage} />
                                            </TableCell>
                                            <TableCell>
                                                <StatusChip
                                                    status={displayStatus}
                                                    tip={
                                                        resume
                                                            ?.latest_status_update
                                                            ?.occurred_at &&
                                                        new Date(
                                                            resume
                                                                .latest_status_update
                                                                .occurred_at,
                                                        ).toLocaleDateString(
                                                            undefined,
                                                            {
                                                                year: "numeric",
                                                                month: "short",
                                                                day: "numeric",
                                                            },
                                                        )
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Typography variant="caption">
                                                    {conv.last_message_at ??
                                                        "-"}
                                                </Typography>
                                            </TableCell>
                                            <TableCell align="right">
                                                <Box
                                                    sx={{
                                                        display: "flex",
                                                        justifyContent:
                                                            "flex-end",
                                                        gap: 1,
                                                    }}
                                                >
                                                    <IconButton
                                                        component={InertiaLink}
                                                        href={`/admin/resume/targeted-builder/${conv.id}`}
                                                        size="small"
                                                        color="primary"
                                                        title="View"
                                                        aria-label="View"
                                                    >
                                                        <AutoFixHighOutlinedIcon fontSize="small" />
                                                    </IconButton>
                                                    {conv.status ===
                                                        "active" && (
                                                        <IconButton
                                                            size="small"
                                                            color="warning"
                                                            title="Pass"
                                                            aria-label="Pass"
                                                            onClick={() => {
                                                                handlePass(
                                                                    conv.id,
                                                                );
                                                            }}
                                                        >
                                                            <BackHandOutlinedIcon fontSize="small" />
                                                        </IconButton>
                                                    )}
                                                    <IconButton
                                                        size="small"
                                                        color="error"
                                                        title="Delete"
                                                        aria-label="Delete"
                                                        onClick={() => {
                                                            handleDelete(
                                                                conv.id,
                                                            );
                                                        }}
                                                    >
                                                        <DeleteOutlineIcon fontSize="small" />
                                                    </IconButton>
                                                </Box>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>
            </Card>
            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
