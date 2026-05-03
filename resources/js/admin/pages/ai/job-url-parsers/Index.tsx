import { Head, Link as InertiaLink, router } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import Chip from '@mui/material/Chip';
import Link from '@mui/material/Link';
import MenuItem from '@mui/material/MenuItem';
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
import EmptyTableRow from '../../../components/EmptyTableRow';
import Pagination from '../../../components/Pagination';
import ConfirmDialog from '../../../components/ConfirmDialog';
import useConfirmDialog from '../../../hooks/useConfirmDialog';
import type { PaginatedResponse } from '../../../types';

interface ParserRow {
    id: number;
    domain: string;
    status: 'active' | 'inactive';
    company_name_selector: string | null;
    job_title_selector: string | null;
    job_location_selector: string | null;
    job_description_selector: string | null;
    reasoning_preview: string | null;
    updated_at: string | null;
}

interface IndexProps {
    parsers: PaginatedResponse<ParserRow>;
    filters: {
        status?: string;
        domain?: string;
        search?: string;
    };
    domains: string[];
}

export default function Index({ parsers, filters, domains }: IndexProps) {
    const { dialogProps, confirm } = useConfirmDialog();

    const handleFilter = (key: 'status' | 'domain' | 'search', value: string) => {
        const next = {
            ...filters,
            [key]: value || undefined,
        };

        router.get('/admin/ai/job-url-parsers', next, {
            preserveState: true,
            replace: true,
        });
    };

    const handleApprove = (id: number, domain: string) => {
        confirm(
            `Approve parser #${id} for ${domain}? This will set all other parsers across all domains to inactive.`,
            () => {
                router.post(`/admin/ai/job-url-parsers/${id}/approve`);
            },
            { confirmLabel: 'Approve', confirmColor: 'primary' },
        );
    };

    const handleReject = (id: number, domain: string) => {
        confirm(
            `Mark parser #${id} for ${domain} as inactive?`,
            () => {
                router.post(`/admin/ai/job-url-parsers/${id}/reject`);
            },
            { confirmLabel: 'Reject', confirmColor: 'warning' },
        );
    };

    return (
        <AdminLayout>
            <Head title="Job URL Parsers" />
            <PageHeader
                title="Job URL Parsers"
                backHref="/admin/ai"
                backLabel="Back to AI Tools"
            />

            <Alert severity="info" sx={{ mb: 2 }}>
                Approving a parser here activates it and marks every other parser across all domains as inactive.
            </Alert>

            <Box
                sx={{
                    display: 'flex',
                    gap: 2,
                    mb: 2,
                    flexWrap: 'wrap',
                    alignItems: 'center',
                }}
            >
                <TextField
                    label="Status"
                    select
                    size="small"
                    value={filters.status ?? ''}
                    onChange={(e) => handleFilter('status', e.target.value)}
                    sx={{ minWidth: 180 }}
                >
                    <MenuItem value="">All Statuses</MenuItem>
                    <MenuItem value="active">Active</MenuItem>
                    <MenuItem value="inactive">Inactive</MenuItem>
                </TextField>

                <TextField
                    label="Domain"
                    select
                    size="small"
                    value={filters.domain ?? ''}
                    onChange={(e) => handleFilter('domain', e.target.value)}
                    sx={{ minWidth: 220 }}
                >
                    <MenuItem value="">All Domains</MenuItem>
                    {domains.map((domain) => (
                        <MenuItem key={domain} value={domain}>
                            {domain}
                        </MenuItem>
                    ))}
                </TextField>

                <TextField
                    label="Search"
                    size="small"
                    value={filters.search ?? ''}
                    onChange={(e) => handleFilter('search', e.target.value)}
                    placeholder="Domain, selector, reasoning..."
                    sx={{ minWidth: 300 }}
                />
            </Box>

            <Card>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>ID</TableCell>
                                <TableCell>Domain</TableCell>
                                <TableCell>Status</TableCell>
                                <TableCell>Selectors</TableCell>
                                <TableCell>Reasoning</TableCell>
                                <TableCell>Updated</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {parsers.data.length === 0 ? (
                                <EmptyTableRow
                                    colSpan={7}
                                    message="No job URL parsers found."
                                />
                            ) : (
                                parsers.data.map((parser) => (
                                    <TableRow key={parser.id} hover>
                                        <TableCell>#{parser.id}</TableCell>
                                        <TableCell>
                                            <Link
                                                component={InertiaLink}
                                                href={`/admin/ai/job-url-parsers/${parser.id}`}
                                                underline="hover"
                                                color="inherit"
                                                sx={{ fontWeight: 500 }}
                                            >
                                                {parser.domain}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            <Chip
                                                size="small"
                                                color={parser.status === 'active' ? 'success' : 'default'}
                                                label={parser.status}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Typography variant="body2" color="text.secondary">
                                                title: {parser.job_title_selector || '-'}
                                            </Typography>
                                            <Typography variant="body2" color="text.secondary">
                                                company: {parser.company_name_selector || '-'}
                                            </Typography>
                                            <Typography variant="body2" color="text.secondary">
                                                location: {parser.job_location_selector || '-'}
                                            </Typography>
                                            <Typography variant="body2" color="text.secondary">
                                                description: {parser.job_description_selector || '-'}
                                            </Typography>
                                        </TableCell>
                                        <TableCell>
                                            <Typography variant="body2" color="text.secondary">
                                                {parser.reasoning_preview || '-'}
                                            </Typography>
                                        </TableCell>
                                        <TableCell>{parser.updated_at || '-'}</TableCell>
                                        <TableCell align="right">
                                            <Box
                                                sx={{
                                                    display: 'flex',
                                                    justifyContent: 'flex-end',
                                                    gap: 1,
                                                }}
                                            >
                                                <Button
                                                    component={InertiaLink}
                                                    href={`/admin/ai/job-url-parsers/${parser.id}`}
                                                    size="small"
                                                >
                                                    Edit
                                                </Button>
                                                <Button
                                                    size="small"
                                                    onClick={() => handleApprove(parser.id, parser.domain)}
                                                >
                                                    Approve
                                                </Button>
                                                <Button
                                                    size="small"
                                                    color="warning"
                                                    onClick={() => handleReject(parser.id, parser.domain)}
                                                >
                                                    Reject
                                                </Button>
                                            </Box>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>
                <Pagination links={parsers.links} lastPage={parsers.last_page} />
            </Card>

            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
