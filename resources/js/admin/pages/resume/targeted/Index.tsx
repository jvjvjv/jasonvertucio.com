import { Head, Link, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import Checkbox from '@mui/material/Checkbox';
import Chip from '@mui/material/Chip';
import FormControlLabel from '@mui/material/FormControlLabel';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import AdminLayout from '../../../layouts/AdminLayout';
import PageHeader from '../../../components/PageHeader';

interface TargetedResume {
    id: number;
    company_name: string;
    position: string;
    fit_score: number | null;
    status: string;
    resume_version: string | null;
}

interface Conversation {
    id: number;
    status: string;
    updated_at: string;
    messages_count: number;
    context: Record<string, string> | null;
    targeted_resume: TargetedResume | null;
}

interface StatusOption {
    value: string;
    label: string;
}

interface IndexProps {
    conversations: Conversation[];
    allStatuses: StatusOption[];
    filters: {
        statuses: string[];
        search: string;
    };
}

function statusColor(status: string): 'success' | 'warning' | 'error' | 'default' {
    switch (status) {
        case 'finalized':
        case 'completed':
            return 'success';
        case 'active':
            return 'warning';
        case 'pass':
            return 'error';
        default:
            return 'default';
    }
}

export default function Index({ conversations, allStatuses, filters }: IndexProps) {
    const handleSearch = (search: string) => {
        router.get(
            '/admin/resume/targeted-builder',
            { search, status: filters.statuses },
            { preserveState: true },
        );
    };

    const handleStatusToggle = (value: string) => {
        const current = filters.statuses ?? [];
        const updated = current.includes(value)
            ? current.filter((s) => s !== value)
            : [...current, value];
        router.get(
            '/admin/resume/targeted-builder',
            { status: updated, search: filters.search },
            { preserveState: true },
        );
    };

    const handleDelete = (id: number) => {
        if (confirm('Delete this conversation?')) {
            router.delete(`/admin/resume/targeted-builder/${id}`);
        }
    };

    const handlePass = (id: number) => {
        if (confirm('Mark this opportunity as passed?')) {
            router.post(`/admin/resume/targeted-builder/${id}/pass`);
        }
    };

    return (
        <AdminLayout>
            <Head title="Targeted Resume Builder" />
            <PageHeader
                title="Targeted Resume Builder"
                backHref="/admin/resume"
                backLabel="Back to Resume Management"
            />

            {/* Filters */}
            <Box sx={{ display: 'flex', gap: 2, mb: 2, flexWrap: 'wrap', alignItems: 'center' }}>
                <TextField
                    label="Search"
                    size="small"
                    value={filters.search}
                    onChange={(e) => handleSearch(e.target.value)}
                    placeholder="Company, job title, or message..."
                    sx={{ minWidth: 250 }}
                />
                {allStatuses.map((s) => (
                    <FormControlLabel
                        key={s.value}
                        control={
                            <Checkbox
                                size="small"
                                checked={(filters.statuses ?? []).includes(s.value)}
                                onChange={() => handleStatusToggle(s.value)}
                            />
                        }
                        label={s.label}
                    />
                ))}
                <Box sx={{ flexGrow: 1 }} />
                <Button component={Link} href="/admin/resume/targeted-builder/new" variant="contained">
                    New Session
                </Button>
            </Box>

            <Card>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>Company / Job</TableCell>
                                <TableCell>Base Version</TableCell>
                                <TableCell>Fit Score</TableCell>
                                <TableCell>Status</TableCell>
                                <TableCell>Updated</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {conversations.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} align="center" sx={{ py: 4 }}>
                                        <Typography color="text.secondary">
                                            No conversations found.{' '}
                                            <Link href="/admin/resume/targeted-builder/new">
                                                Start one
                                            </Link>
                                        </Typography>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                conversations.map((conv) => {
                                    const resume = conv.targeted_resume;
                                    const companyName = resume?.company_name || conv.context?.company_name || '—';
                                    const position = resume?.position || conv.context?.job_title || '';
                                    const displayStatus = resume?.status === 'finalized'
                                        ? 'finalized'
                                        : conv.status;

                                    return (
                                        <TableRow key={conv.id} hover>
                                            <TableCell>
                                                <Link href={`/admin/resume/targeted-builder/${conv.id}`}>
                                                    <Typography variant="body2" fontWeight={600}>
                                                        {companyName}
                                                    </Typography>
                                                </Link>
                                                {position && (
                                                    <Typography variant="caption" color="text.secondary">
                                                        {position}
                                                    </Typography>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {resume?.resume_version ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {resume?.fit_score != null ? `${resume.fit_score}%` : '—'}
                                            </TableCell>
                                            <TableCell>
                                                <Chip
                                                    label={displayStatus}
                                                    size="small"
                                                    color={statusColor(displayStatus)}
                                                    variant="outlined"
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Typography variant="caption">{conv.updated_at}</Typography>
                                            </TableCell>
                                            <TableCell align="right">
                                                <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1 }}>
                                                    <Button
                                                        component={Link}
                                                        href={`/admin/resume/targeted-builder/${conv.id}`}
                                                        size="small"
                                                    >
                                                        View
                                                    </Button>
                                                    {conv.status === 'active' && (
                                                        <Button
                                                            size="small"
                                                            color="warning"
                                                            onClick={() => handlePass(conv.id)}
                                                        >
                                                            Pass
                                                        </Button>
                                                    )}
                                                    <Button
                                                        size="small"
                                                        color="error"
                                                        onClick={() => handleDelete(conv.id)}
                                                    >
                                                        Delete
                                                    </Button>
                                                </Box>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>
            </Card>
        </AdminLayout>
    );
}
