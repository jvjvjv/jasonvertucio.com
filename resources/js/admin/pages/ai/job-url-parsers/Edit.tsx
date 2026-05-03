import { Head, Link as InertiaLink, useForm } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import CircularProgress from '@mui/material/CircularProgress';
import MenuItem from '@mui/material/MenuItem';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import AdminLayout from '../../../layouts/AdminLayout';
import PageHeader from '../../../components/PageHeader';

interface ParserEditModel {
    id: number;
    domain: string;
    status: 'active' | 'inactive';
    company_name_selector: string | null;
    job_title_selector: string | null;
    job_location_selector: string | null;
    job_description_selector: string | null;
    ai_reasoning: string | null;
    html: string | null;
}

interface EditProps {
    parser: ParserEditModel;
}

interface PreviewResult {
    job_title: string;
    company_name: string;
    job_location: string;
    job_description: string;
}

interface FormData {
    domain: string;
    status: 'active' | 'inactive';
    company_name_selector: string;
    job_title_selector: string;
    job_location_selector: string;
    job_description_selector: string;
    ai_reasoning: string;
    html: string;
}

export default function Edit({ parser }: EditProps) {
    const [isPreviewing, setIsPreviewing] = useState(false);
    const [previewError, setPreviewError] = useState('');
    const [previewResult, setPreviewResult] = useState<PreviewResult | null>(null);
    const [previewFieldErrors, setPreviewFieldErrors] = useState<Record<string, string>>({});

    const form = useForm<FormData>({
        domain: parser.domain,
        status: parser.status,
        company_name_selector: parser.company_name_selector ?? '',
        job_title_selector: parser.job_title_selector ?? '',
        job_location_selector: parser.job_location_selector ?? '',
        job_description_selector: parser.job_description_selector ?? '',
        ai_reasoning: parser.ai_reasoning ?? '',
        html: parser.html ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/admin/ai/job-url-parsers/${parser.id}`);
    };

    const csrfToken =
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

    const handlePreview = async () => {
        setIsPreviewing(true);
        setPreviewError('');

        try {
            const response = await fetch(`/admin/ai/job-url-parsers/${parser.id}/preview`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    html: form.data.html,
                    company_name_selector: form.data.company_name_selector,
                    job_title_selector: form.data.job_title_selector,
                    job_location_selector: form.data.job_location_selector,
                    job_description_selector: form.data.job_description_selector,
                }),
            });

            const result = await response.json();

            if (!response.ok) {
                setPreviewError(result.message || 'Failed to preview selectors.');
                setPreviewResult(null);
                setPreviewFieldErrors({});
                return;
            }

            setPreviewResult(result.results);
            setPreviewFieldErrors(result.errors || {});
        } catch (error) {
            setPreviewError('Network error: ' + (error as Error).message);
            setPreviewResult(null);
            setPreviewFieldErrors({});
        } finally {
            setIsPreviewing(false);
        }
    };

    return (
        <AdminLayout>
            <Head title={`Edit Parser #${parser.id}`} />
            <PageHeader
                title={`Edit Parser #${parser.id}`}
                backHref="/admin/ai/job-url-parsers"
                backLabel="Back to Job URL Parsers"
            />

            <Card>
                <CardContent>
                    <Box component="form" onSubmit={handleSubmit}>
                        <TextField
                            label="Domain"
                            required
                            size="small"
                            fullWidth
                            value={form.data.domain}
                            onChange={(e) => form.setData('domain', e.target.value)}
                            error={Boolean(form.errors.domain)}
                            helperText={form.errors.domain}
                            sx={{ mb: 3 }}
                        />

                        <TextField
                            label="Status"
                            select
                            size="small"
                            fullWidth
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value as 'active' | 'inactive')
                            }
                            error={Boolean(form.errors.status)}
                            helperText={form.errors.status}
                            sx={{ mb: 3 }}
                        >
                            <MenuItem value="active">active</MenuItem>
                            <MenuItem value="inactive">inactive</MenuItem>
                        </TextField>

                        <TextField
                            label="Job Title Selector"
                            size="small"
                            fullWidth
                            value={form.data.job_title_selector}
                            onChange={(e) => form.setData('job_title_selector', e.target.value)}
                            error={Boolean(form.errors.job_title_selector)}
                            helperText={form.errors.job_title_selector}
                            sx={{ mb: 3 }}
                        />

                        <TextField
                            label="Company Name Selector"
                            size="small"
                            fullWidth
                            value={form.data.company_name_selector}
                            onChange={(e) => form.setData('company_name_selector', e.target.value)}
                            error={Boolean(form.errors.company_name_selector)}
                            helperText={form.errors.company_name_selector}
                            sx={{ mb: 3 }}
                        />

                        <TextField
                            label="Job Location Selector"
                            size="small"
                            fullWidth
                            value={form.data.job_location_selector}
                            onChange={(e) => form.setData('job_location_selector', e.target.value)}
                            error={Boolean(form.errors.job_location_selector)}
                            helperText={form.errors.job_location_selector}
                            sx={{ mb: 3 }}
                        />

                        <TextField
                            label="Job Description Selector"
                            size="small"
                            fullWidth
                            value={form.data.job_description_selector}
                            onChange={(e) => form.setData('job_description_selector', e.target.value)}
                            error={Boolean(form.errors.job_description_selector)}
                            helperText={form.errors.job_description_selector}
                            sx={{ mb: 3 }}
                        />

                        <TextField
                            label="AI Reasoning"
                            size="small"
                            fullWidth
                            multiline
                            rows={5}
                            value={form.data.ai_reasoning}
                            onChange={(e) => form.setData('ai_reasoning', e.target.value)}
                            error={Boolean(form.errors.ai_reasoning)}
                            helperText={form.errors.ai_reasoning}
                            sx={{ mb: 3 }}
                        />

                        <TextField
                            label="Stored HTML"
                            size="small"
                            fullWidth
                            multiline
                            rows={10}
                            value={form.data.html}
                            onChange={(e) => form.setData('html', e.target.value)}
                            error={Boolean(form.errors.html)}
                            helperText={form.errors.html}
                            sx={{ mb: 3 }}
                        />

                        <Box sx={{ display: 'flex', justifyContent: 'flex-end', mb: 3 }}>
                            <Button
                                variant="outlined"
                                onClick={handlePreview}
                                disabled={isPreviewing}
                            >
                                {isPreviewing ? <CircularProgress size={20} /> : 'Test Selectors'}
                            </Button>
                        </Box>

                        {previewError && (
                            <Alert severity="warning" sx={{ mb: 3 }}>
                                {previewError}
                            </Alert>
                        )}

                        {previewResult && (
                            <Card variant="outlined" sx={{ mb: 3 }}>
                                <CardContent>
                                    <Typography variant="h6" sx={{ mb: 2 }}>
                                        Preview Results
                                    </Typography>

                                    {Object.keys(previewFieldErrors).length > 0 && (
                                        <Alert severity="warning" sx={{ mb: 2 }}>
                                            One or more selectors failed. Review selector syntax below.
                                        </Alert>
                                    )}

                                    <Typography variant="subtitle2">Job Title</Typography>
                                    <Typography variant="body2" sx={{ mb: 1, whiteSpace: 'pre-wrap' }}>
                                        {previewResult.job_title || '-'}
                                    </Typography>
                                    {previewFieldErrors.job_title && (
                                        <Typography color="error" variant="caption" sx={{ mb: 1, display: 'block' }}>
                                            {previewFieldErrors.job_title}
                                        </Typography>
                                    )}

                                    <Typography variant="subtitle2">Company Name</Typography>
                                    <Typography variant="body2" sx={{ mb: 1, whiteSpace: 'pre-wrap' }}>
                                        {previewResult.company_name || '-'}
                                    </Typography>
                                    {previewFieldErrors.company_name && (
                                        <Typography color="error" variant="caption" sx={{ mb: 1, display: 'block' }}>
                                            {previewFieldErrors.company_name}
                                        </Typography>
                                    )}

                                    <Typography variant="subtitle2">Job Location</Typography>
                                    <Typography variant="body2" sx={{ mb: 1, whiteSpace: 'pre-wrap' }}>
                                        {previewResult.job_location || '-'}
                                    </Typography>
                                    {previewFieldErrors.job_location && (
                                        <Typography color="error" variant="caption" sx={{ mb: 1, display: 'block' }}>
                                            {previewFieldErrors.job_location}
                                        </Typography>
                                    )}

                                    <Typography variant="subtitle2">Job Description</Typography>
                                    <Typography variant="body2" sx={{ whiteSpace: 'pre-wrap' }}>
                                        {previewResult.job_description || '-'}
                                    </Typography>
                                    {previewFieldErrors.job_description && (
                                        <Typography color="error" variant="caption" sx={{ display: 'block' }}>
                                            {previewFieldErrors.job_description}
                                        </Typography>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 2 }}>
                            <Button
                                component={InertiaLink}
                                href="/admin/ai/job-url-parsers"
                                color="inherit"
                            >
                                Cancel
                            </Button>
                            <Button type="submit" variant="contained" disabled={form.processing}>
                                Save Parser
                            </Button>
                        </Box>
                    </Box>
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
