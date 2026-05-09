import { Head, Link as InertiaLink, router, useForm } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import AdminLayout from "../../../layouts/AdminLayout";
import ConfirmDialog from "../../../components/ConfirmDialog";
import PageHeader from "../../../components/PageHeader";
import useConfirmDialog from "../../../hooks/useConfirmDialog";
import type { AiChatBot } from "../../../types";
import Form, { type FormData } from "./Form";

interface EditProps {
    bot: AiChatBot;
    systems: Array<{ id: number; name: string; model: string }>;
    roles: string[];
}

export default function Edit({ bot, systems, roles }: EditProps) {
    const form = useForm<FormData>({
        name: bot.name,
        slug: bot.slug,
        access_path: bot.access_path,
        description: bot.description ?? "",
        ai_system_id: bot.ai_system_id ?? "",
        prompt_template: bot.prompt_template ?? "",
        allowed_roles: bot.allowed_roles ?? [],
        is_active: bot.is_active,
        is_public: bot.is_public,
        require_visitor_identity: bot.require_visitor_identity,
    });

    const { dialogProps, confirm } = useConfirmDialog();

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        form.put(`/admin/ai/chat-bots/${bot.slug}`);
    };

    const handleDelete = () => {
        confirm(`Delete AI chat bot "${bot.name}"?`, () => {
            router.delete(`/admin/ai/chat-bots/${bot.slug}`);
        });
    };

    return (
        <AdminLayout>
            <Head title={`${bot.name} | Chat Bots`} />
            <PageHeader
                title={`Edit: ${bot.name}`}
                backHref="/admin/ai/chat-bots"
                backLabel="Back to AI Chat Bots"
            />

            <Card>
                <CardContent>
                    <Box component="form" onSubmit={handleSubmit}>
                        <Form
                            data={form.data}
                            setData={form.setData}
                            errors={form.errors}
                            systems={systems}
                            roles={roles}
                        />

                        <Box
                            sx={{
                                display: "flex",
                                justifyContent: "space-between",
                                alignItems: "center",
                                mt: 3,
                            }}
                        >
                            <Button color="error" onClick={handleDelete}>
                                Delete
                            </Button>

                            <Box sx={{ display: "flex", gap: 2 }}>
                                <Button
                                    component={InertiaLink}
                                    href="/admin/ai/chat-bots"
                                    color="inherit"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant="contained"
                                    disabled={form.processing}
                                >
                                    Update Bot
                                </Button>
                            </Box>
                        </Box>
                    </Box>
                </CardContent>
            </Card>
            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
