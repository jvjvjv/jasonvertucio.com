import { Head, Link as InertiaLink } from "@inertiajs/react";
import EmailIcon from "@mui/icons-material/Email";
import Card from "@mui/material/Card";
import CardActionArea from "@mui/material/CardActionArea";
import CardContent from "@mui/material/CardContent";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";

import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";

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
            <PageHeader
                title="Mail Preview"
                backHref="/admin"
                backLabel="Back to Admin"
            />

            {mailables.length === 0 ? (
                <Typography color="text.secondary">
                    No mailables found.
                </Typography>
            ) : (
                <Stack spacing={2}>
                    {mailables.map((mailable) => (
                        <Card key={mailable.class}>
                            <CardActionArea
                                component={InertiaLink}
                                href={`/admin/mail-preview/${encodeURIComponent(mailable.class)}`}
                            >
                                <CardContent
                                    sx={{
                                        display: "flex",
                                        alignItems: "center",
                                        gap: 2,
                                    }}
                                >
                                    <EmailIcon color="primary" />
                                    <div>
                                        <Typography
                                            variant="subtitle1"
                                            fontWeight="bold"
                                        >
                                            {mailable.name}
                                        </Typography>
                                        <Typography
                                            variant="body2"
                                            color="text.secondary"
                                        >
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
