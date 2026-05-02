import { Head, router } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import Checkbox from "@mui/material/Checkbox";
import FormControlLabel from "@mui/material/FormControlLabel";
import Link from "@mui/material/Link";
import Table from "@mui/material/Table";
import TableBody from "@mui/material/TableBody";
import TableCell from "@mui/material/TableCell";
import TableContainer from "@mui/material/TableContainer";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import AdminLayout from "../../../layouts/AdminLayout";
import PageHeader from "../../../components/PageHeader";
import EmptyTableRow from "../../../components/EmptyTableRow";
import StatusChip from "../../../components/StatusChip";
import type { Conversation } from "../../../types";
import ConfirmDialog from "../../../components/ConfirmDialog";
import useConfirmDialog from "../../../hooks/useConfirmDialog";

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

    const handleStatusToggle = (value: string) => {
        const current = filters.statuses ?? [];
        const updated = current.includes(value)
            ? current.filter((s) => s !== value)
            : [...current, value];
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
            <Head title="Targeted Resume Builder" />
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
                    onChange={(e) => handleSearch(e.target.value)}
                    placeholder="Company, job title, or message..."
                    sx={{ minWidth: 250 }}
                />
                {allStatuses.map((s) => (
                    <FormControlLabel
                        key={s.value}
                        control={
                            <Checkbox
                                size="small"
                                checked={(filters.statuses ?? []).includes(
                                    s.value,
                                )}
                                onChange={() => handleStatusToggle(s.value)}
                            />
                        }
                        label={s.label}
                    />
                ))}
                <Box sx={{ flexGrow: 1 }} />
                <Button
                    component={Link}
                    href="/admin/resume/targeted-builder/new"
                    variant="contained"
                >
                    New Session
                </Button>
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
                                    colSpan={7}
                                    message="No conversations found."
                                    actionLabel="Start one"
                                    actionHref="/admin/resume/targeted-builder/new"
                                />
                            ) : (
                                conversations.map((conv) => {
                                    const resume = conv.targeted_resume;
                                    const companyName =
                                        resume?.company_name ||
                                        conv.context?.company_name ||
                                        "—";
                                    const position =
                                        resume?.position ||
                                        conv.context?.job_title ||
                                        "";
                                    const displayStatus =
                                        resume?.status === "finalized"
                                            ? "finalized"
                                            : conv.status;

                                    return (
                                        <TableRow key={conv.id} hover>
                                            <TableCell>
                                                <Link
                                                    href={`/admin/resume/targeted-builder/${conv.id}`}
                                                >
                                                    <Typography
                                                        variant="body2"
                                                        fontWeight={600}
                                                    >
                                                        {(companyName as string) ??
                                                            "—"}
                                                    </Typography>
                                                </Link>
                                                {position && (
                                                    <Typography
                                                        variant="caption"
                                                        color="text.secondary"
                                                    >
                                                        {(position as string) ??
                                                            "—"}
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
                                                <Typography
                                                    variant="caption"
                                                    color="text.secondary"
                                                >
                                                    {conv.usage?.total_tokens !=
                                                    null
                                                        ? `${conv.usage.total_tokens.toLocaleString()} tok`
                                                        : "—"}
                                                </Typography>
                                                <br />
                                                <Typography
                                                    variant="caption"
                                                    color="text.secondary"
                                                >
                                                    {conv.usage?.cost_usd !=
                                                    null
                                                        ? `$${conv.usage.cost_usd.toFixed(4)}`
                                                        : "—"}
                                                </Typography>
                                            </TableCell>
                                            <TableCell>
                                                <StatusChip
                                                    status={displayStatus}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Typography variant="caption">
                                                    {conv.updated_at}
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
                                                    <Button
                                                        component={Link}
                                                        href={`/admin/resume/targeted-builder/${conv.id}`}
                                                        size="small"
                                                    >
                                                        View
                                                    </Button>
                                                    {conv.status ===
                                                        "active" && (
                                                        <Button
                                                            size="small"
                                                            color="warning"
                                                            onClick={() =>
                                                                handlePass(
                                                                    conv.id,
                                                                )
                                                            }
                                                        >
                                                            Pass
                                                        </Button>
                                                    )}
                                                    <Button
                                                        size="small"
                                                        color="error"
                                                        onClick={() =>
                                                            handleDelete(
                                                                conv.id,
                                                            )
                                                        }
                                                    >
                                                        Delete
                                                    </Button>
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
