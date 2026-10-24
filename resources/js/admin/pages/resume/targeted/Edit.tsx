import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import PictureAsPdfIcon from "@mui/icons-material/PictureAsPdf";
import StickyNote2Icon from "@mui/icons-material/StickyNote2";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import Link from "@mui/material/Link";
import Typography from "@mui/material/Typography";

import TailoredResumeEditor from "./TailoredResumeEditor";

import type { TargetedResume } from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";
import useConfirmDialog from "@/hooks/useConfirmDialog";

interface EditProps {
    targetedResume: TargetedResume & {
        /** False once the application has an `applied` entry. */
        can_discard: boolean;
    };
    /** The application this resume was tailored for. */
    application: { id: number; company_name: string; position: string };
}

export default function Edit({ targetedResume, application }: EditProps) {
    const { dialogProps, confirm } = useConfirmDialog();

    const applicationUrl = `/admin/resume/applications/${application.id}`;
    const downloadBase = `/admin/resume/targeted-resume/${targetedResume.id}/download`;
    const heading = `${application.company_name} — ${application.position}`;

    const handleDiscard = () => {
        confirm(
            "This permanently deletes the tailored resume and its generated documents. The application, its status history and its cover letter are kept. This cannot be undone.",
            () => {
                router.delete(
                    `/admin/resume/targeted-resumes/${targetedResume.id}`,
                );
            },
            { title: "Discard targeted resume?", confirmLabel: "Discard" },
        );
    };

    return (
        <AdminLayout>
            <Head title={`${heading} | Targeted Resumes`} />
            <PageHeader
                title={heading}
                backHref={applicationUrl}
                backLabel="Back to application"
            >
                <Box sx={{ display: "flex", flexWrap: "wrap", gap: 1 }}>
                    <Button
                        component="a"
                        href={`${downloadBase}/docx`}
                        variant="outlined"
                        startIcon={<StickyNote2Icon />}
                    >
                        DOCX
                    </Button>
                    <Button
                        component="a"
                        href={`${downloadBase}/pdf`}
                        variant="outlined"
                        startIcon={<PictureAsPdfIcon />}
                    >
                        PDF
                    </Button>
                </Box>
            </PageHeader>

            <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                Targeted resume
                {targetedResume.title ? ` “${targetedResume.title}”` : ""} for
                the{" "}
                <Link
                    component={InertiaLink}
                    href={applicationUrl}
                    underline="hover"
                >
                    {application.position} application at{" "}
                    {application.company_name}
                </Link>
                {targetedResume.resume_version !== null
                    ? `, tailored from version ${targetedResume.resume_version}.`
                    : "."}
            </Typography>

            <Card>
                <TailoredResumeEditor targetedResume={targetedResume} />
            </Card>

            <Box
                sx={{
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "center",
                    gap: 2,
                    mt: 2,
                }}
            >
                {targetedResume.can_discard ? (
                    <Button color="error" onClick={handleDiscard}>
                        Discard
                    </Button>
                ) : (
                    <Typography variant="caption" color="text.secondary">
                        This resume was sent with the application, so it can no
                        longer be discarded.
                    </Typography>
                )}
                <Button
                    component={InertiaLink}
                    href="/admin/resume/targeted-resumes"
                    color="inherit"
                >
                    All targeted resumes
                </Button>
            </Box>
            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
