import { Head, Link, useForm } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import AdminLayout from '../../../layouts/AdminLayout';
import PageHeader from '../../../components/PageHeader';
import Form, { type FormData } from './Form';

interface CreateProps {
    systems: Array<{ id: number; name: string; model: string }>;
    roles: string[];
}

export default function Create({ systems, roles }: CreateProps) {
    const form = useForm<FormData>({
        name: '',
        slug: '',
        access_path: 'chat',
        description: '',
        ai_system_id: '',
        prompt_template: 'You are {{bot_name}}. {{bot_description}}',
        allowed_roles: [],
        is_active: true,
        is_public: false,
        require_visitor_identity: false,
    });

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/admin/ai/chat-bots');
    };

    return (
        <AdminLayout>
            <Head title="Add AI Chat Bot" />
            <PageHeader title="Add AI Chat Bot" backHref="/admin/ai/chat-bots" backLabel="Back to AI Chat Bots" />

            <Card>
                <CardContent>
                    <Box component="form" onSubmit={handleSubmit}>
                        <Form data={form.data} setData={form.setData} errors={form.errors} systems={systems} roles={roles} />

                        <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 2, mt: 3 }}>
                            <Button component={Link} href="/admin/ai/chat-bots" color="inherit">
                                Cancel
                            </Button>
                            <Button type="submit" variant="contained" disabled={form.processing}>
                                Save Bot
                            </Button>
                        </Box>
                    </Box>
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
