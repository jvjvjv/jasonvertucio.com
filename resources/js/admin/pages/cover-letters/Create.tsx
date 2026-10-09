import { Head, Link as InertiaLink, useForm } from "@inertiajs/react";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Link from "@mui/material/Link";

import CoverLetterForm from "./Form";

import type { FormData, ResumeVersion } from "./Form";
import type { SyntheticEvent } from "react";

import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";

interface CreateProps {
    resumeVersions: ResumeVersion[];
    /**
     * The application this letter is being written for, when the page was
     * opened from one (`?application={id}`). The letter is then linked to it.
     */
    application?: { id: number; company_name: string; position: string } | null;
}

export default function Create({
    resumeVersions,
    application = null,
}: CreateProps) {
    const form = useForm<FormData>({
        resume_version_id: resumeVersions.find((rv) => rv.is_current)?.id ?? "",
        company_name: application?.company_name ?? "",
        position: application?.position ?? "",
        date: new Date().toISOString().slice(0, 10),
        company_address: "",
        greeting: "Dear Hiring Manager,",
        message_body: "",
        closing: "Sincerely,",
        signature: "Jason Vertucio",
    });

    const handleSubmit = (e: SyntheticEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (application) {
            form.transform((data) => ({
                ...data,
                application_id: application.id,
            }));
        }
        form.post("/admin/cover-letters");
    };

    const applicationUrl = application
        ? `/admin/resume/applications/${application.id}`
        : null;
    const cancelHref = applicationUrl ?? "/admin/cover-letters";

    return (
        <AdminLayout>
            <Head title="New | Cover Letters" />
            <PageHeader
                title="New Cover Letter"
                backHref={cancelHref}
                backLabel={
                    application
                        ? "Back to application"
                        : "Back to Cover Letters"
                }
            />

            {application && applicationUrl ? (
                <Alert severity="info" sx={{ mb: 2 }}>
                    This cover letter will be attached to the{" "}
                    <Link
                        component={InertiaLink}
                        href={applicationUrl}
                        underline="hover"
                    >
                        {application.position} application at{" "}
                        {application.company_name}
                    </Link>
                    .
                </Alert>
            ) : null}

            <Card>
                <CardContent>
                    <Box component="form" onSubmit={handleSubmit}>
                        <CoverLetterForm
                            data={form.data}
                            setData={form.setData}
                            errors={form.errors}
                            resumeVersions={resumeVersions}
                        />

                        <Box
                            sx={{
                                display: "flex",
                                justifyContent: "flex-end",
                                gap: 2,
                                mt: 3,
                            }}
                        >
                            <Button
                                component={InertiaLink}
                                href={cancelHref}
                                color="inherit"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="contained"
                                disabled={form.processing}
                            >
                                Save &amp; Generate
                            </Button>
                        </Box>
                    </Box>
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
