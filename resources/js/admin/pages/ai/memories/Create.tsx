import { Head, Link as InertiaLink, useForm } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import AdminLayout from "../../../layouts/AdminLayout";
import PageHeader from "../../../components/PageHeader";
import MemoryForm from "./Form";
import type { FormData } from "./Form";

export default function Create() {
    const form = useForm<FormData>({
        feature: "targeted-resume",
        category: "user_preferences",
        key: "",
        content: "",
        confidence: 50,
        is_active: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post("/admin/ai/memories");
    };

    return (
        <AdminLayout>
            <Head title="Add Memory Entry" />
            <PageHeader
                title="Add Memory Entry"
                backHref="/admin/ai/memories"
                backLabel="Back to AI Memories"
            />

            <Card>
                <CardContent>
                    <Box component="form" onSubmit={handleSubmit}>
                        <MemoryForm
                            data={form.data}
                            setData={form.setData}
                            errors={form.errors}
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
                                href="/admin/ai/memories"
                                color="inherit"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="contained"
                                disabled={form.processing}
                            >
                                Create Memory
                            </Button>
                        </Box>
                    </Box>
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
