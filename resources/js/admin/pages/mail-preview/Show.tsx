import { Head } from '@inertiajs/react';
import Typography from '@mui/material/Typography';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import AdminLayout from '../../layouts/AdminLayout';
import PageHeader from '../../components/PageHeader';

interface MailableItem {
    name: string;
    class: string;
    file: string;
}

interface MailPreviewShowProps {
    mailable: MailableItem;
    subject?: string;
    previewUrl?: string | null;
    error?: string;
}

export default function MailPreviewShow({ mailable, subject, previewUrl, error }: MailPreviewShowProps) {
    return (
        <AdminLayout>
            <Head title={`Mail Preview: ${mailable.name}`} />
            <PageHeader
                title={mailable.name}
                backHref="/admin/mail-preview"
                backLabel="Back to Mail Preview"
            />

            {subject && (
                <Typography variant="body1" color="text.secondary" sx={{ mb: 2 }}>
                    Subject: {subject}
                </Typography>
            )}

            {error ? (
                <Alert severity="error" sx={{ mb: 2 }}>
                    {error}
                </Alert>
            ) : previewUrl ? (
                <Card>
                    <Box
                        component="iframe"
                        src={previewUrl}
                        sx={{
                            width: '100%',
                            minHeight: 600,
                            border: 'none',
                            display: 'block',
                        }}
                        title={`Preview of ${mailable.name}`}
                    />
                </Card>
            ) : null}
        </AdminLayout>
    );
}
