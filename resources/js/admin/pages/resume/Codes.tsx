import { Head, router, useForm } from "@inertiajs/react";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Table from "@mui/material/Table";
import TableBody from "@mui/material/TableBody";
import TableCell from "@mui/material/TableCell";
import TableContainer from "@mui/material/TableContainer";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import ConfirmDialog from "../../components/ConfirmDialog";
import EmptyTableRow from "../../components/EmptyTableRow";
import PageHeader from "../../components/PageHeader";
import AdminLayout from "../../layouts/AdminLayout";

import ShareCodeForm from "./ShareCodeForm";
import ShareCodeRow from "./ShareCodeRow";

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
                    <ShareCodeForm
                        form={form}
                        todayDate={todayDate}
                        mailConfigured={mailConfigured}
                        onSubmit={handleSubmit}
                    />
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
                                    <ShareCodeRow
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
