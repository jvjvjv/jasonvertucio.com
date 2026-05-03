import { useState } from 'react';
import { Head } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import CircularProgress from '@mui/material/CircularProgress';
import MenuItem from '@mui/material/MenuItem';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import AdminLayout from '../../../layouts/AdminLayout';
import PageHeader from '../../../components/PageHeader';
import type { AiSystem } from '../../../types';

interface CreateProps {
    systems: Pick<AiSystem, 'id' | 'name' | 'model'>[];
    defaultSystemId: number | null;
}

export default function Create({ systems, defaultSystemId }: CreateProps) {
    const [aiSystemId, setAiSystemId] = useState<number | ''>(defaultSystemId ?? '');
    const [jobUrl, setJobUrl] = useState('');
    const [jobTitle, setJobTitle] = useState('');
    const [companyName, setCompanyName] = useState("");
    const [jobLocation, setJobLocation] = useState("");
    const [jobDescription, setJobDescription] = useState('');
    const [isParsing, setIsParsing] = useState(false);
    const [parseError, setParseError] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState('');

    const csrfToken =
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

    const handleParseUrl = async () => {
        if (!jobUrl.trim()) return;
        setIsParsing(true);
        setParseError('');

        try {
            const response = await fetch(
                "/admin/resume/targeted-builder/parse-url",
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-CSRF-TOKEN": csrfToken,
                    },
                    body: JSON.stringify({
                        url: jobUrl,
                        ai_system_id: aiSystemId,
                    }),
                },
            );

            const result = await response.json();

            if (!response.ok) {
                setParseError(result.message || 'Failed to parse URL');
                return;
            }

            if (result.job_title) setJobTitle(result.job_title);
            if (result.company_name) setCompanyName(result.company_name);
            if (result.job_location) setJobLocation(result.job_location);
            if (result.job_description)
                setJobDescription(result.job_description);
        } catch (err) {
            setParseError('Network error: ' + (err as Error).message);
        } finally {
            setIsParsing(false);
        }
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!aiSystemId) {
            setError('Please select an AI system.');
            return;
        }
        if (!jobDescription.trim()) {
            setError('Please provide a job description.');
            return;
        }

        setIsSubmitting(true);
        setError('');

        try {
            const response = await fetch('/admin/resume/targeted-builder/start', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    ai_system_id: aiSystemId,
                    job_title: jobTitle,
                    job_description: jobDescription,
                }),
            });

            const result = await response.json();

            if (!response.ok) {
                setError(result.message || 'Failed to start session');
                return;
            }

            window.location.href = result.redirect;
        } catch (err) {
            setError('Network error: ' + (err as Error).message);
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <AdminLayout>
            <Head title="New Targeted Resume" />
            <PageHeader
                title="New Targeted Resume"
                backHref="/admin/resume/targeted-builder"
                backLabel="Back to Targeted Resumes"
            />

            {error && (
                <Alert severity="error" sx={{ mb: 2 }}>
                    {error}
                </Alert>
            )}

            <Card>
                <CardContent>
                    <Box component="form" onSubmit={handleSubmit}>
                        <TextField
                            label="AI System"
                            select
                            required
                            size="small"
                            fullWidth
                            value={aiSystemId}
                            onChange={(e) =>
                                setAiSystemId(Number(e.target.value))
                            }
                            sx={{ mb: 3 }}
                        >
                            {systems.map((s) => (
                                <MenuItem key={s.id} value={s.id}>
                                    {s.name} ({s.model})
                                </MenuItem>
                            ))}
                        </TextField>

                        <Typography
                            variant="body2"
                            color="text.secondary"
                            sx={{ mb: 1 }}
                        >
                            Paste a job URL to auto-extract the description, or
                            enter it manually below.
                        </Typography>
                        <Box sx={{ display: "flex", gap: 1, mb: 3 }}>
                            <TextField
                                label="Job URL"
                                size="small"
                                fullWidth
                                value={jobUrl}
                                onChange={(e) => setJobUrl(e.target.value)}
                                placeholder="https://..."
                            />
                            <Button
                                variant="outlined"
                                onClick={handleParseUrl}
                                disabled={isParsing || !jobUrl.trim()}
                                sx={{ whiteSpace: "nowrap" }}
                            >
                                {isParsing ? (
                                    <CircularProgress size={20} />
                                ) : (
                                    "Parse"
                                )}
                            </Button>
                        </Box>
                        {parseError && (
                            <Alert severity="warning" sx={{ mb: 2 }}>
                                {parseError}
                            </Alert>
                        )}

                        <TextField
                            label="Job Title"
                            size="small"
                            fullWidth
                            value={jobTitle}
                            onChange={(e) => setJobTitle(e.target.value)}
                            placeholder="(optional)"
                            sx={{ mb: 3 }}
                        />

                        <TextField
                            label="Company Name"
                            size="small"
                            fullWidth
                            value={companyName}
                            onChange={(e) => setCompanyName(e.target.value)}
                            placeholder="(optional)"
                            sx={{ mb: 3 }}
                        />

                        <TextField
                            label="Job Location"
                            size="small"
                            fullWidth
                            value={jobLocation}
                            onChange={(e) => setJobLocation(e.target.value)}
                            placeholder="(optional)"
                            sx={{ mb: 3 }}
                        />

                        <TextField
                            label="Job Description"
                            required
                            size="small"
                            fullWidth
                            multiline
                            rows={12}
                            value={jobDescription}
                            onChange={(e) => setJobDescription(e.target.value)}
                            placeholder="Paste or type the full job description here..."
                            sx={{ mb: 3 }}
                        />

                        <Box
                            sx={{ display: "flex", justifyContent: "flex-end" }}
                        >
                            <Button
                                type="submit"
                                variant="contained"
                                disabled={isSubmitting}
                            >
                                {isSubmitting
                                    ? "Starting..."
                                    : "Start Analysis"}
                            </Button>
                        </Box>
                    </Box>
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
