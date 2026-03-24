import { Head, Link, useForm, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Typography from '@mui/material/Typography';
import AdminLayout from '../../layouts/AdminLayout';
import PageHeader from '../../components/PageHeader';
import ConfirmDialog from '../../components/ConfirmDialog';
import useConfirmDialog from '../../hooks/useConfirmDialog';
import CoverLetterForm from './Form';
import type { FormData, ResumeVersion } from './Form';

interface CoverLetter extends FormData {
    id: number;
}

interface EditProps {
    coverLetter: CoverLetter;
    resumeVersions: ResumeVersion[];
}

export default function Edit({ coverLetter, resumeVersions }: EditProps) {
    const form = useForm<FormData>({
        resume_version_id: coverLetter.resume_version_id,
        company_name: coverLetter.company_name,
        position: coverLetter.position,
        date: coverLetter.date,
        company_address: coverLetter.company_address ?? '',
        greeting: coverLetter.greeting,
        message_body: coverLetter.message_body,
        closing: coverLetter.closing ?? '',
        signature: coverLetter.signature ?? '',
    });

    const { dialogProps, confirm } = useConfirmDialog();

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/admin/cover-letters/${coverLetter.id}`);
    };

    const handleDelete = () => {
        confirm('Delete this cover letter and its generated files? This cannot be undone.', () => {
            router.delete(`/admin/cover-letters/${coverLetter.id}`);
        });
    };

    return (
        <AdminLayout>
            <Head title={`Edit — ${coverLetter.company_name}`} />
            <PageHeader title={coverLetter.company_name} backHref="/admin/cover-letters" backLabel="Back to Cover Letters" />

            <Box sx={{ display: 'flex', justifyContent: 'flex-end', mb: 2 }}>
                <Button
                    component={Link}
                    href={`/admin/cover-letters/${coverLetter.id}/preview`}
                    variant="outlined"
                >
                    Preview
                </Button>
            </Box>

            <Card>
                <CardContent>
                    <Box component="form" onSubmit={handleSubmit}>
                        <CoverLetterForm
                            data={form.data}
                            setData={form.setData}
                            errors={form.errors}
                            resumeVersions={resumeVersions}
                        />

                        <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mt: 3 }}>
                            <Button color="error" onClick={handleDelete}>
                                Delete
                            </Button>

                            <Box sx={{ display: 'flex', gap: 2 }}>
                                <Button component={Link} href="/admin/cover-letters" color="inherit">
                                    Cancel
                                </Button>
                                <Button type="submit" variant="contained" disabled={form.processing}>
                                    Save &amp; Regenerate
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
