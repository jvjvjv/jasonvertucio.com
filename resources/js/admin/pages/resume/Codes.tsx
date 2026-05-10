import { Head, router, useForm } from "@inertiajs/react";
import ContentCopyIcon from "@mui/icons-material/ContentCopy";
import ExpandLessIcon from "@mui/icons-material/ExpandLess";
import ExpandMoreIcon from "@mui/icons-material/ExpandMore";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Checkbox from "@mui/material/Checkbox";
import Chip from "@mui/material/Chip";
import Collapse from "@mui/material/Collapse";
import FormControlLabel from "@mui/material/FormControlLabel";
import IconButton from "@mui/material/IconButton";
import Table from "@mui/material/Table";
import TableBody from "@mui/material/TableBody";
import TableCell from "@mui/material/TableCell";
import TableContainer from "@mui/material/TableContainer";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import ConfirmDialog from "../../components/ConfirmDialog";
import EmptyTableRow from "../../components/EmptyTableRow";
import PageHeader from "../../components/PageHeader";
import AdminLayout from "../../layouts/AdminLayout";

import type { SyntheticEvent } from "react";

import useConfirmDialog from "@/hooks/useConfirmDialog";

interface ViewRecord {
    created_at_formatted: string;
    ip_address: string;
    user_agent: string;
}

interface DownloadRecord {
    created_at_formatted: string;
    version: string;
    ip_address: string;
    user_agent: string;
}

interface ShareCode {
    id: string;
    name: string | null;
    email: string | null;
    email_sent: boolean;
    created_at_formatted: string;
    expires_at_formatted: string | null;
    views_count: number;
    downloads_count: number;
    is_trashed: boolean;
    is_expired: boolean;
    resume_url: string;
    views: ViewRecord[];
    downloads: DownloadRecord[];
}

interface CodesProps {
    codes: ShareCode[];
    mailConfigured: boolean;
    todayDate: string;
}

export default function Codes({
    codes,
    mailConfigured,
    todayDate,
}: CodesProps) {
    const [expanded, setExpanded] = useState<string | null>(null);

    const form = useForm({
        name: "",
        email: "",
        expires_at: "",
        send_email: false,
    });

    const handleSubmit = (e: SyntheticEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.post("/admin/resume/codes", {
            onSuccess: () => {
                form.reset();
            },
        });
    };

    const { dialogProps, confirm } = useConfirmDialog();

    const handleInvalidate = (codeId: string) => {
        confirm("Are you sure you want to invalidate this code?", () => {
            router.delete(`/admin/resume/codes/${codeId}`);
        });
    };

    const toggleExpand = (key: string) => {
        setExpanded((prev) => (prev === key ? null : key));
    };

    const emailProvided = form.data.email.trim() !== "";

    return (
        <AdminLayout>
            <Head title="Share Codes | Resume" />
            <PageHeader
                title="Resume Share Codes"
                backHref="/admin/resume"
                backLabel="Back to Resume Management"
            />

            {/* Create Form */}
            <Card sx={{ mb: 4 }}>
                <CardContent>
                    <Typography variant="h6" sx={{ mb: 2 }}>
                        Create New Share Code
                    </Typography>
                    <Box component="form" onSubmit={handleSubmit}>
                        <Box
                            sx={{
                                display: "grid",
                                gap: 2,
                                gridTemplateColumns: {
                                    xs: "1fr",
                                    md: "1fr 1fr",
                                },
                                mb: 2,
                            }}
                        >
                            <TextField
                                label="Recipient Name"
                                required
                                size="small"
                                value={form.data.name}
                                onChange={(e) => {
                                    form.setData("name", e.target.value);
                                }}
                                error={!!form.errors.name}
                                helperText={form.errors.name}
                            />
                            <TextField
                                label="Recipient Email (optional)"
                                type="email"
                                size="small"
                                value={form.data.email}
                                onChange={(e) => {
                                    form.setData("email", e.target.value);
                                }}
                                error={!!form.errors.email}
                                helperText={form.errors.email}
                            />
                        </Box>
                        <Box
                            sx={{
                                display: "grid",
                                gap: 2,
                                gridTemplateColumns: {
                                    xs: "1fr",
                                    md: "1fr 1fr",
                                },
                                mb: 2,
                            }}
                        >
                            <TextField
                                label="Expiration Date (optional)"
                                type="date"
                                size="small"
                                slotProps={{
                                    inputLabel: { shrink: true },
                                    htmlInput: { min: todayDate },
                                }}
                                value={form.data.expires_at}
                                onChange={(e) => {
                                    form.setData("expires_at", e.target.value);
                                }}
                                error={!!form.errors.expires_at}
                                helperText={form.errors.expires_at}
                            />
                        </Box>
                        <FormControlLabel
                            control={
                                <Checkbox
                                    checked={form.data.send_email}
                                    onChange={(e) => {
                                        form.setData(
                                            "send_email",
                                            e.target.checked,
                                        );
                                    }}
                                    disabled={!mailConfigured || !emailProvided}
                                />
                            }
                            label="Send email notification"
                            sx={{ mb: 1 }}
                        />
                        {!mailConfigured && (
                            <Typography
                                variant="caption"
                                color="text.secondary"
                                display="block"
                                sx={{ mb: 1 }}
                            >
                                (mail not configured)
                            </Typography>
                        )}
                        {emailProvided &&
                            mailConfigured &&
                            form.data.send_email && (
                                <Typography
                                    variant="body2"
                                    color="info.main"
                                    sx={{ mb: 2 }}
                                >
                                    An email will be sent to this address once
                                    the code is created.
                                </Typography>
                            )}
                        <Button
                            type="submit"
                            variant="contained"
                            disabled={form.processing}
                        >
                            Generate Code
                        </Button>
                    </Box>
                </CardContent>
            </Card>

            {/* Codes Table */}
            <Card>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>Code</TableCell>
                                <TableCell>Recipient</TableCell>
                                <TableCell>Email</TableCell>
                                <TableCell>Created</TableCell>
                                <TableCell>Expires</TableCell>
                                <TableCell>Views</TableCell>
                                <TableCell>Downloads</TableCell>
                                <TableCell>Status</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {codes.length === 0 ? (
                                <EmptyTableRow
                                    colSpan={9}
                                    message="No share codes created yet."
                                />
                            ) : (
                                codes.map((code) => (
                                    <CodeRow
                                        key={code.id}
                                        code={code}
                                        expanded={expanded}
                                        onToggleExpand={toggleExpand}
                                        onInvalidate={handleInvalidate}
                                    />
                                ))
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>
            </Card>
            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}

interface CodeRowProps {
    code: ShareCode;
    expanded: string | null;
    onToggleExpand: (_key: string) => void;
    onInvalidate: (_id: string) => void;
}

function CodeRow({
    code,
    expanded,
    onToggleExpand,
    onInvalidate,
}: CodeRowProps) {
    const statusChip = code.is_trashed ? (
        <Chip
            label="Invalidated"
            size="small"
            color="error"
            variant="outlined"
        />
    ) : code.is_expired ? (
        <Chip label="Expired" size="small" color="warning" variant="outlined" />
    ) : (
        <Chip label="Active" size="small" color="success" variant="outlined" />
    );

    const viewsExpanded = expanded === code.id;
    const downloadsExpanded = expanded === `${code.id}-downloads`;

    return (
        <>
            <TableRow sx={{ opacity: code.is_trashed ? 0.5 : 1 }}>
                <TableCell>
                    <Box sx={{ display: "flex", alignItems: "center", gap: 1 }}>
                        <Typography
                            variant="body2"
                            fontFamily="monospace"
                            sx={{
                                bgcolor: "grey.100",
                                px: 1,
                                py: 0.25,
                                borderRadius: 1,
                            }}
                        >
                            {code.id}
                        </Typography>
                        {!code.is_trashed && !code.is_expired && (
                            <IconButton
                                size="small"
                                onClick={() => {
                                    void navigator.clipboard.writeText(
                                        code.resume_url,
                                    );
                                }}
                                title="Copy URL"
                            >
                                <ContentCopyIcon fontSize="small" />
                            </IconButton>
                        )}
                    </Box>
                </TableCell>
                <TableCell>{code.name ?? "-"}</TableCell>
                <TableCell sx={{ maxWidth: 150 }}>
                    {code.email ? (
                        <Box
                            sx={{
                                display: "flex",
                                alignItems: "center",
                                gap: 0.5,
                            }}
                        >
                            {code.email_sent && (
                                <Typography
                                    color="success.main"
                                    variant="caption"
                                >
                                    ✓
                                </Typography>
                            )}
                            <Typography
                                variant="body2"
                                noWrap
                                title={code.email}
                            >
                                {code.email}
                            </Typography>
                        </Box>
                    ) : (
                        "-"
                    )}
                </TableCell>
                <TableCell>
                    <Typography variant="body2">
                        {code.created_at_formatted}
                    </Typography>
                </TableCell>
                <TableCell>
                    <Typography variant="body2">
                        {code.expires_at_formatted ?? "Never"}
                    </Typography>
                </TableCell>
                <TableCell>
                    {code.views_count > 0 ? (
                        <Button
                            size="small"
                            onClick={() => {
                                onToggleExpand(code.id);
                            }}
                            endIcon={
                                viewsExpanded ? (
                                    <ExpandLessIcon />
                                ) : (
                                    <ExpandMoreIcon />
                                )
                            }
                        >
                            {code.views_count}
                        </Button>
                    ) : (
                        "-"
                    )}
                </TableCell>
                <TableCell>
                    {code.downloads_count > 0 ? (
                        <Button
                            size="small"
                            onClick={() => {
                                onToggleExpand(`${code.id}-downloads`);
                            }}
                            endIcon={
                                downloadsExpanded ? (
                                    <ExpandLessIcon />
                                ) : (
                                    <ExpandMoreIcon />
                                )
                            }
                        >
                            {code.downloads_count}
                        </Button>
                    ) : (
                        "-"
                    )}
                </TableCell>
                <TableCell>{statusChip}</TableCell>
                <TableCell align="right">
                    {!code.is_trashed ? (
                        <Button
                            size="small"
                            color="error"
                            onClick={() => {
                                onInvalidate(code.id);
                            }}
                        >
                            Invalidate
                        </Button>
                    ) : (
                        "-"
                    )}
                </TableCell>
            </TableRow>

            {/* Views expansion */}
            {code.views.length > 0 && (
                <TableRow>
                    <TableCell
                        colSpan={9}
                        sx={{
                            p: 0,
                            borderBottom: viewsExpanded ? undefined : "none",
                        }}
                    >
                        <Collapse in={viewsExpanded}>
                            <Box sx={{ px: 3, py: 2, bgcolor: "grey.50" }}>
                                <Typography variant="subtitle2" sx={{ mb: 1 }}>
                                    View History
                                </Typography>
                                <Table size="small">
                                    <TableHead>
                                        <TableRow>
                                            <TableCell>Date</TableCell>
                                            <TableCell>IP Address</TableCell>
                                            <TableCell>User Agent</TableCell>
                                        </TableRow>
                                    </TableHead>
                                    <TableBody>
                                        {code.views.map((view, i) => (
                                            <TableRow key={i}>
                                                <TableCell>
                                                    {view.created_at_formatted}
                                                </TableCell>
                                                <TableCell
                                                    sx={{
                                                        fontFamily: "monospace",
                                                    }}
                                                >
                                                    {view.ip_address}
                                                </TableCell>
                                                <TableCell
                                                    sx={{ maxWidth: 300 }}
                                                    title={view.user_agent}
                                                >
                                                    <Typography
                                                        variant="body2"
                                                        noWrap
                                                    >
                                                        {view.user_agent}
                                                    </Typography>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </Box>
                        </Collapse>
                    </TableCell>
                </TableRow>
            )}

            {/* Downloads expansion */}
            {code.downloads.length > 0 && (
                <TableRow>
                    <TableCell
                        colSpan={9}
                        sx={{
                            p: 0,
                            borderBottom: downloadsExpanded
                                ? undefined
                                : "none",
                        }}
                    >
                        <Collapse in={downloadsExpanded}>
                            <Box sx={{ px: 3, py: 2, bgcolor: "info.50" }}>
                                <Typography variant="subtitle2" sx={{ mb: 1 }}>
                                    Download History
                                </Typography>
                                <Table size="small">
                                    <TableHead>
                                        <TableRow>
                                            <TableCell>Date</TableCell>
                                            <TableCell>Version</TableCell>
                                            <TableCell>IP Address</TableCell>
                                            <TableCell>User Agent</TableCell>
                                        </TableRow>
                                    </TableHead>
                                    <TableBody>
                                        {code.downloads.map((dl, i) => (
                                            <TableRow key={i}>
                                                <TableCell>
                                                    {dl.created_at_formatted}
                                                </TableCell>
                                                <TableCell
                                                    sx={{
                                                        fontFamily: "monospace",
                                                    }}
                                                >
                                                    {dl.version}
                                                </TableCell>
                                                <TableCell
                                                    sx={{
                                                        fontFamily: "monospace",
                                                    }}
                                                >
                                                    {dl.ip_address}
                                                </TableCell>
                                                <TableCell
                                                    sx={{ maxWidth: 300 }}
                                                    title={dl.user_agent}
                                                >
                                                    <Typography
                                                        variant="body2"
                                                        noWrap
                                                    >
                                                        {dl.user_agent}
                                                    </Typography>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </Box>
                        </Collapse>
                    </TableCell>
                </TableRow>
            )}
        </>
    );
}
