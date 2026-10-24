import { Head, Link as InertiaLink, useForm } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";

import Form, { toRequestPayload } from "./Form";

import type { FormData } from "./Form";
import type { McpServerAuthType } from "@/types";
import type { SyntheticEvent } from "react";

import { emptyAuthDraft } from "@/admin/components/McpServerAuthEditor";
import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";

interface CreateProps {
    authTypes: McpServerAuthType[];
}

export default function Create({ authTypes }: CreateProps) {
    const form = useForm<FormData>({
        slug: "",
        name: "",
        url: "",
        timeout_seconds: "",
        enabled: true,
        auth: emptyAuthDraft(),
    });

    const handleSubmit = (e: SyntheticEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.transform((data) => toRequestPayload(data, true));
        form.post("/admin/ai/mcp-servers");
    };

    return (
        <AdminLayout>
            <Head title="New | MCP Servers" />
            <PageHeader
                title="Add MCP Server"
                backHref="/admin/ai/mcp-servers"
                backLabel="Back to MCP Servers"
            />

            <Card>
                <CardContent>
                    <Box component="form" onSubmit={handleSubmit}>
                        <Form
                            data={form.data}
                            setData={form.setData}
                            errors={form.errors}
                            authTypes={authTypes}
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
                                href="/admin/ai/mcp-servers"
                                color="inherit"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="contained"
                                disabled={form.processing}
                            >
                                {form.processing
                                    ? "Saving and syncing…"
                                    : "Add Server"}
                            </Button>
                        </Box>
                    </Box>
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
