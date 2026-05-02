import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import Link from "@mui/material/Link";
import Table from "@mui/material/Table";
import TableBody from "@mui/material/TableBody";
import TableCell from "@mui/material/TableCell";
import TableContainer from "@mui/material/TableContainer";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import Typography from "@mui/material/Typography";
import AdminLayout from "../../layouts/AdminLayout";
import PageHeader from "../../components/PageHeader";
import EmptyTableRow from "../../components/EmptyTableRow";
import ConfirmDialog from "../../components/ConfirmDialog";
import useConfirmDialog from "../../hooks/useConfirmDialog";

interface CoverLetter {
    id: number;
    company_name: string;
    position: string;
    date_formatted: string;
    resume_version_label: string;
}

interface IndexProps {
    coverLetters: CoverLetter[];
}

export default function Index({ coverLetters }: IndexProps) {
    const { dialogProps, confirm } = useConfirmDialog();

    const handleDelete = (id: number) => {
        confirm("Delete this cover letter? This cannot be undone.", () => {
            router.delete(`/admin/cover-letters/${id}`);
        });
    };

    return (
        <AdminLayout>
            <Head title="Cover Letters" />
            <PageHeader
                title="Cover Letters"
                backHref="/admin"
                backLabel="Back to Admin"
            />

            <Box sx={{ display: "flex", justifyContent: "flex-end", mb: 2 }}>
                <Button
                    component={InertiaLink}
                    href="/admin/cover-letters/new"
                    variant="contained"
                >
                    Add Cover Letter
                </Button>
            </Box>

            <Card>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>Company</TableCell>
                                <TableCell>Position</TableCell>
                                <TableCell>Resume Version</TableCell>
                                <TableCell>Date</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {coverLetters.length === 0 ? (
                                <EmptyTableRow
                                    colSpan={5}
                                    message="No cover letters yet."
                                    actionLabel="Add your first one"
                                    actionHref="/admin/cover-letters/new"
                                />
                            ) : (
                                coverLetters.map((cl) => (
                                    <TableRow key={cl.id} hover>
                                        <TableCell>
                                            <Link
                                                component={InertiaLink}
                                                href={`/admin/cover-letters/${cl.id}/preview`}
                                                underline="hover"
                                                color="inherit"
                                                sx={{ fontWeight: 500 }}
                                            >
                                                {cl.company_name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            <Link
                                                component={InertiaLink}
                                                href={`/admin/cover-letters/${cl.id}/preview`}
                                                underline="hover"
                                                color="inherit"
                                            >
                                                {cl.position}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {cl.resume_version_label}
                                        </TableCell>
                                        <TableCell>
                                            {cl.date_formatted}
                                        </TableCell>
                                        <TableCell align="right">
                                            <Box
                                                sx={{
                                                    display: "flex",
                                                    justifyContent: "flex-end",
                                                    gap: 1,
                                                }}
                                            >
                                                <Button
                                                    component={InertiaLink}
                                                    href={`/admin/cover-letters/${cl.id}`}
                                                    size="small"
                                                >
                                                    Edit
                                                </Button>
                                                <Button
                                                    size="small"
                                                    color="error"
                                                    onClick={() =>
                                                        handleDelete(cl.id)
                                                    }
                                                >
                                                    Delete
                                                </Button>
                                            </Box>
                                        </TableCell>
                                    </TableRow>
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
