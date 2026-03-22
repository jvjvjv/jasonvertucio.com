import { Head, Link, useForm } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import AdminLayout from '../../../layouts/AdminLayout';
import PageHeader from '../../../components/PageHeader';
import AiSystemForm from './Form';
import type { FormData } from './Form';

interface CreateProps {
    existingDefaults: string[];
}

export default function Create({ existingDefaults }: CreateProps) {
    const form = useForm<FormData>({
        name: '',
        provider: 'anthropic',
        api_key: '',
        model: '',
        base_url: '',
        api_version: '',
        max_tokens: 4096,
        temperature: '',
        config: '',
        is_active: true,
        feature_defaults: [],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/admin/ai/systems');
    };

    return (
        <AdminLayout>
            <Head title="Add AI System" />
            <PageHeader title="Add AI System" backHref="/admin/ai/systems" backLabel="Back to AI Systems" />

            <Card>
                <CardContent>
                    <Box component="form" onSubmit={handleSubmit}>
                        <AiSystemForm
                            data={form.data}
                            setData={form.setData}
                            errors={form.errors}
                            existingDefaults={existingDefaults}
                        />

                        <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 2, mt: 3 }}>
                            <Button component={Link} href="/admin/ai/systems" color="inherit">
                                Cancel
                            </Button>
                            <Button type="submit" variant="contained" disabled={form.processing}>
                                Save System
                            </Button>
                        </Box>
                    </Box>
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
