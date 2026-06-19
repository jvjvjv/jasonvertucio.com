import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Link from "@mui/material/Link";

import type { ColumnDef } from "@/admin/components/DataTable";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import DataTable from "@/admin/components/DataTable";
import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";
import useConfirmDialog from "@/hooks/useConfirmDialog";

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

const columns: ColumnDef<CoverLetter>[] = [
    {
        key: "company_name",
        label: "Company",
        render: (row) => (
            <Link
                component={InertiaLink}
                href={`/admin/cover-letters/${row.id}/preview`}
                underline="hover"
                color="primary"
                sx={{ fontWeight: 600 }}
            >
                {row.company_name}
            </Link>
        ),
    },
    {
        key: "position",
        label: "Position",
        render: (row) => (
            <Link
                component={InertiaLink}
                href={`/admin/cover-letters/${row.id}/preview`}
                underline="hover"
                color="inherit"
            >
                {row.position}
            </Link>
        ),
    },
    { key: "resume_version_label", label: "Resume Version" },
    { key: "date_formatted", label: "Date" },
];

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

            <DataTable
                columns={columns}
                data={coverLetters}
                emptyState={
                    <Box sx={{ textAlign: "center", py: 4 }}>
                        <Link
                            component={InertiaLink}
                            href="/admin/cover-letters/new"
                            underline="hover"
                        >
                            Add your first one
                        </Link>
                    </Box>
                }
                rowActions={(cl) => (
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
                            onClick={() => {
                                handleDelete(cl.id);
                            }}
                        >
                            Delete
                        </Button>
                    </Box>
                )}
            />

            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
