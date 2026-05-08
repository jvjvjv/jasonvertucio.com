import { Head, Link as InertiaLink, useForm } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import AdminLayout from "../../layouts/AdminLayout";
import PageHeader from "../../components/PageHeader";
import CoverLetterForm from "./Form";
import type { FormData, ResumeVersion } from "./Form";

interface CreateProps {
    resumeVersions: ResumeVersion[];
}

export default function Create({ resumeVersions }: CreateProps) {
    const form = useForm<FormData>({
        resume_version_id: resumeVersions.find((rv) => rv.is_current)?.id ?? "",
        company_name: "",
        position: "",
        date: new Date().toISOString().slice(0, 10),
        company_address: "",
        greeting: "Dear Hiring Manager,",
        message_body: "",
        closing: "Sincerely,",
        signature: "Jason Vertucio",
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post("/admin/cover-letters");
    };

    return (
        <AdminLayout>
            <Head title="New | Cover Letters" />
            <PageHeader
                title="New Cover Letter"
                backHref="/admin/cover-letters"
                backLabel="Back to Cover Letters"
            />

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
                                href="/admin/cover-letters"
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
