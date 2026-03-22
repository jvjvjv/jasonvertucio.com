import { Head, Link, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import Chip from '@mui/material/Chip';
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

interface Memory {
    id: number;
    feature: string;
    category: string;
    key: string;
    confidence: number;
    is_active: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedMemories {
    data: Memory[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
}

interface Filters {
    feature?: string;
    category?: string;
    status?: string;
}

interface IndexProps {
    memories: PaginatedMemories;
    features: string[];
    filters: Filters;
}

export default function Index({ memories, features, filters }: IndexProps) {
    const handleFilter = (key: string, value: string) => {
        const params: Record<string, string> = { ...filters, [key]: value };
        // Remove empty values
        Object.keys(params).forEach((k) => {
            if (!params[k]) delete params[k];
        });
        router.get('/admin/ai/memories', params, { preserveState: true });
    };

    const handleDelete = (id: number) => {
        if (confirm('Delete this memory entry?')) {
            router.delete(`/admin/ai/memories/${id}`);
        }
    };

    const handleRebuild = (feature: string) => {
        if (confirm(`Rebuild all memories for "${feature}"? Existing memories will be deactivated and regenerated.`)) {
            router.post(`/admin/ai/memories/rebuild/${feature}`);
        }
    };

    return (
        <AdminLayout>
            <Head title="AI Memories" />
            <PageHeader title="AI Memories" backHref="/admin/ai" backLabel="Back to AI Tools" />

            {/* Filters */}
            <Box sx={{ display: 'flex', gap: 2, mb: 2, flexWrap: 'wrap', alignItems: 'center' }}>
                <TextField
                    label="Feature"
                    select
                    size="small"
                    value={filters.feature ?? ''}
                    onChange={(e) => handleFilter('feature', e.target.value)}
                    sx={{ minWidth: 160 }}
                >
                    <MenuItem value="">All Features</MenuItem>
                    {features.map((f) => (
                        <MenuItem key={f} value={f}>{f}</MenuItem>
                    ))}
                </TextField>

                <TextField
                    label="Category"
                    select
                    size="small"
                    value={filters.category ?? ''}
                    onChange={(e) => handleFilter('category', e.target.value)}
                    sx={{ minWidth: 160 }}
                >
                    <MenuItem value="">All Categories</MenuItem>
                    <MenuItem value="user_preferences">User Preferences</MenuItem>
                    <MenuItem value="domain_knowledge">Domain Knowledge</MenuItem>
                    <MenuItem value="system_tuning">System Tuning</MenuItem>
                </TextField>

                <TextField
                    label="Status"
                    select
                    size="small"
                    value={filters.status ?? ''}
                    onChange={(e) => handleFilter('status', e.target.value)}
                    sx={{ minWidth: 120 }}
                >
                    <MenuItem value="">All</MenuItem>
                    <MenuItem value="active">Active</MenuItem>
                    <MenuItem value="inactive">Inactive</MenuItem>
                </TextField>

                <Box sx={{ flexGrow: 1 }} />

                {features.map((f) => (
                    <Button key={f} size="small" variant="outlined" onClick={() => handleRebuild(f)}>
                        Rebuild {f}
                    </Button>
                ))}

                <Button component={Link} href="/admin/ai/memories/new" variant="contained">
                    Add Memory
                </Button>
            </Box>

            <Card>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>Key</TableCell>
                                <TableCell>Feature</TableCell>
                                <TableCell>Category</TableCell>
                                <TableCell>Confidence</TableCell>
                                <TableCell>Status</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {memories.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} align="center" sx={{ py: 4 }}>
                                        <Typography color="text.secondary">No memory entries found.</Typography>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                memories.data.map((memory) => (
                                    <TableRow key={memory.id} hover sx={{ opacity: memory.is_active ? 1 : 0.5 }}>
                                        <TableCell>
                                            <Typography variant="body2" fontFamily="monospace" fontWeight={600}>
                                                {memory.key}
                                            </Typography>
                                        </TableCell>
                                        <TableCell>{memory.feature}</TableCell>
                                        <TableCell>{memory.category}</TableCell>
                                        <TableCell>{memory.confidence}</TableCell>
                                        <TableCell>
                                            <Chip
                                                label={memory.is_active ? 'Active' : 'Inactive'}
                                                size="small"
                                                color={memory.is_active ? 'success' : 'default'}
                                                variant="outlined"
                                            />
                                        </TableCell>
                                        <TableCell align="right">
                                            <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1 }}>
                                                <Button component={Link} href={`/admin/ai/memories/${memory.id}`} size="small">
                                                    Edit
                                                </Button>
                                                <Button size="small" color="error" onClick={() => handleDelete(memory.id)}>
                                                    Delete
                                                </Button>
                                            </Box>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>

                {/* Pagination */}
                {memories.last_page > 1 && (
                    <Box sx={{ display: 'flex', justifyContent: 'center', gap: 1, py: 2 }}>
                        {memories.links.map((link, i) => (
                            <Button
                                key={i}
                                component={link.url ? Link : 'button'}
                                href={link.url ?? undefined}
                                size="small"
                                variant={link.active ? 'contained' : 'text'}
                                disabled={!link.url}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </Box>
                )}
            </Card>
        </AdminLayout>
    );
}
