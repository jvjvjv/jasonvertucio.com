import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import AddIcon from "@mui/icons-material/Add";
import ChatIcon from "@mui/icons-material/Chat";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import EditNoteIcon from "@mui/icons-material/EditNote";
import PictureAsPdfIcon from "@mui/icons-material/PictureAsPdf";
import StickyNote2Icon from "@mui/icons-material/StickyNote2";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import IconButton from "@mui/material/IconButton";
import Link from "@mui/material/Link";
import Typography from "@mui/material/Typography";

import type { ColumnDef } from "@/admin/components/DataTable";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import DataTable from "@/admin/components/DataTable";
import DebouncedSearchField from "@/admin/components/DebouncedSearchField";
import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";
import ResponsiveButton from "@/components/ResponsiveButton";
import useConfirmDialog from "@/hooks/useConfirmDialog";

const LIST_URL = "/admin/resume/targeted-resumes";
const NEW_SESSION_URL = "/admin/resume/applications/new";
const EM_DASH = "—";

interface TargetedResumeRow {
    id: number;
    title: string | null;
    application_id: number;
    company_name: string;
    position: string;
    /** Label of the main resume version the document was tailored from. */
    resume_version: string | null;
    updated_at: string | null;
    updated_at_human: string | null;
    /** False once the application has an `applied` entry. */
    can_discard: boolean;
}

interface IndexProps {
    targetedResumes: TargetedResumeRow[];
    filters: { search: string };
}

function applicationUrl(row: TargetedResumeRow): string {
    return `/admin/resume/applications/${row.application_id}`;
}

const columns: ColumnDef<TargetedResumeRow>[] = [
    {
        key: "company_name",
        label: "Company / Job",
        render: (row) => (
            <>
                <Link
                    component={InertiaLink}
                    href={applicationUrl(row)}
                    underline="hover"
                    color="primary"
                    sx={{ fontWeight: 600, display: "block" }}
                >
                    {row.company_name}
                </Link>
                {row.position ? (
                    <Typography variant="caption" color="text.secondary">
                        {row.position}
                    </Typography>
                ) : null}
            </>
        ),
    },
    {
        key: "title",
        label: "Title",
        render: (row) => row.title ?? EM_DASH,
    },
    {
        key: "resume_version",
        label: "Base Version",
        render: (row) => row.resume_version ?? EM_DASH,
    },
    {
        key: "updated_at",
        label: "Last Edited",
        render: (row) => (
            <Typography
                variant="caption"
                title={
                    row.updated_at
                        ? new Date(row.updated_at).toLocaleString()
                        : undefined
                }
            >
                {row.updated_at_human ?? EM_DASH}
            </Typography>
        ),
    },
];

function searchTargetedResumes(search: string): void {
    router.get(LIST_URL, search === "" ? {} : { search }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ["targetedResumes", "filters"],
    });
}

export default function Index({ targetedResumes, filters }: IndexProps) {
    const { dialogProps, confirm } = useConfirmDialog();

    const handleDiscard = (row: TargetedResumeRow) => {
        confirm(
            `This permanently deletes the tailored resume for ${row.company_name} and its generated documents. The application itself is kept. This cannot be undone.`,
            () => {
                router.delete(`${LIST_URL}/${row.id}`, {
                    // Discarding normally lands on the application; keep the
                    // scroll position only if it brings us back to this list.
                    preserveScroll: (page) =>
                        page.component === "resume/targeted/Index",
                });
            },
            { title: "Discard targeted resume?", confirmLabel: "Discard" },
        );
    };

    const renderRowActions = (row: TargetedResumeRow) => {
        const downloadBase = `/admin/resume/targeted-resume/${row.id}/download`;

        return (
            <Box
                sx={{
                    display: "flex",
                    justifyContent: "flex-end",
                    gap: 0.5,
                }}
            >
                <IconButton
                    component="a"
                    href={`${downloadBase}/docx`}
                    size="small"
                    title="Download DOCX"
                    aria-label="Download DOCX"
                >
                    <StickyNote2Icon fontSize="small" />
                </IconButton>
                <IconButton
                    component="a"
                    href={`${downloadBase}/pdf`}
                    size="small"
                    title="Download PDF"
                    aria-label="Download PDF"
                >
                    <PictureAsPdfIcon fontSize="small" />
                </IconButton>
                <IconButton
                    component={InertiaLink}
                    href={`${LIST_URL}/${row.id}/edit`}
                    size="small"
                    color="primary"
                    title="Edit targeted resume"
                    aria-label="Edit targeted resume"
                >
                    <EditNoteIcon fontSize="small" />
                </IconButton>
                <IconButton
                    component={InertiaLink}
                    href={applicationUrl(row)}
                    size="small"
                    color="primary"
                    title="Open application"
                    aria-label="Open application"
                >
                    <ChatIcon fontSize="small" />
                </IconButton>
                {row.can_discard ? (
                    <IconButton
                        size="small"
                        color="error"
                        title="Discard targeted resume"
                        aria-label="Discard targeted resume"
                        onClick={() => {
                            handleDiscard(row);
                        }}
                    >
                        <DeleteOutlineIcon fontSize="small" />
                    </IconButton>
                ) : null}
            </Box>
        );
    };

    const isSearching = filters.search !== "";

    return (
        <AdminLayout>
            <Head title="Targeted Resumes | Resume" />
            <PageHeader
                title="Targeted Resumes"
                backHref="/admin/resume"
                backLabel="Back to Resume Management"
            />

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
                    initialValue={filters.search}
                    onSearch={searchTargetedResumes}
                    placeholder="Company, job title, or resume title..."
                    sx={{ minWidth: 250 }}
                />
                <Box sx={{ flexGrow: 1 }} />
                <ResponsiveButton
                    icon={<AddIcon />}
                    color="primary"
                    label="New Session"
                    href={NEW_SESSION_URL}
                    variant="contained"
                />
            </Box>

            <DataTable
                columns={columns}
                data={targetedResumes}
                rowActions={renderRowActions}
                emptyState={
                    <Box sx={{ py: 5, px: 2, textAlign: "center" }}>
                        <Typography color="text.secondary" sx={{ mb: 2 }}>
                            {isSearching
                                ? "No targeted resumes match this search."
                                : "No targeted resumes yet. Start a session to analyse a job and tailor a resume for it."}
                        </Typography>
                        <Button
                            component={InertiaLink}
                            href={NEW_SESSION_URL}
                            variant="contained"
                            startIcon={<AddIcon />}
                        >
                            New Session
                        </Button>
                    </Box>
                }
            />
            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
