import { Head, Link } from '@inertiajs/react';
import Card from '@mui/material/Card';
import CardActionArea from '@mui/material/CardActionArea';
import CardContent from '@mui/material/CardContent';
import Typography from '@mui/material/Typography';
import Stack from '@mui/material/Stack';
import EmailIcon from '@mui/icons-material/Email';
import AdminLayout from '../../layouts/AdminLayout';
import PageHeader from '../../components/PageHeader';

interface MailableItem {
    name: string;
    class: string;
    file: string;
}

interface MailPreviewIndexProps {
    mailables: MailableItem[];
}

export default function MailPreviewIndex({ mailables }: MailPreviewIndexProps) {
    return (
        <AdminLayout>
            <Head title="Mail Preview" />
            <PageHeader title="Mail Preview" backHref="/admin" backLabel="Back to Admin" />

            {mailables.length === 0 ? (
                <Typography color="text.secondary">No mailables found.</Typography>
            ) : (
                <Stack spacing={2}>
                    {mailables.map((mailable) => (
                        <Card key={mailable.class}>
                            <CardActionArea
                                component={Link}
                                href={`/admin/mail-preview/${encodeURIComponent(mailable.class)}`}
                            >
                                <CardContent sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                                    <EmailIcon color="primary" />
                                    <div>
                                        <Typography variant="subtitle1" fontWeight="bold">
                                            {mailable.name}
                                        </Typography>
                                        <Typography variant="body2" color="text.secondary">
                                            {mailable.file}
                                        </Typography>
                                    </div>
                                </CardContent>
                            </CardActionArea>
                        </Card>
                    ))}
                </Stack>
            )}
        </AdminLayout>
    );
}
