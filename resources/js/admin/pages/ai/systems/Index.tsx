import { Head, Link, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import Chip from '@mui/material/Chip';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import Typography from '@mui/material/Typography';
import AdminLayout from '../../../layouts/AdminLayout';
import PageHeader from '../../../components/PageHeader';

interface AiSystem {
    id: number;
    name: string;
    provider: string;
    model: string;
    is_active: boolean;
    interaction_logs_count: number;
    feature_defaults_list: string[];
}

interface IndexProps {
    systems: AiSystem[];
}

export default function Index({ systems }: IndexProps) {
    const handleDelete = (id: number, name: string) => {
        if (confirm(`Delete AI system "${name}"? This cannot be undone.`)) {
            router.delete(`/admin/ai/systems/${id}`);
        }
    };

    const handleDuplicate = (id: number) => {
        if (confirm('Duplicate this AI system?')) {
            router.post(`/admin/ai/systems/${id}/duplicate`);
        }
    };

    return (
        <AdminLayout>
            <Head title="AI Systems" />
            <PageHeader title="AI Systems" backHref="/admin/ai" backLabel="Back to AI Tools" />

            <Box sx={{ display: 'flex', justifyContent: 'flex-end', mb: 2 }}>
                <Button component={Link} href="/admin/ai/systems/new" variant="contained">
                    Add System
                </Button>
            </Box>

            <Card>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>Name</TableCell>
                                <TableCell>Provider</TableCell>
                                <TableCell>Model</TableCell>
                                <TableCell>Default For</TableCell>
                                <TableCell>API Calls</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {systems.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} align="center" sx={{ py: 4 }}>
                                        <Typography color="text.secondary">No AI systems configured yet.</Typography>
                                        <Typography variant="body2" sx={{ mt: 0.5 }}>
                                            <Link href="/admin/ai/systems/new">Add your first one</Link>
                                        </Typography>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                systems.map((system) => (
                                    <TableRow key={system.id} hover sx={{ opacity: system.is_active ? 1 : 0.5 }}>
                                        <TableCell>
                                            <Link href={`/admin/ai/systems/${system.id}`} style={{ color: 'inherit', fontWeight: 500 }}>
                                                {system.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>{system.provider}</TableCell>
                                        <TableCell>
                                            <Typography variant="body2" fontFamily="monospace">{system.model}</Typography>
                                        </TableCell>
                                        <TableCell>
                                            {system.feature_defaults_list.length > 0
                                                ? system.feature_defaults_list.map((f) => (
                                                    <Chip key={f} label={f} size="small" sx={{ mr: 0.5 }} />
                                                ))
                                                : '-'}
                                        </TableCell>
                                        <TableCell>
                                            {system.interaction_logs_count > 0 ? (
                                                <Link href={`/admin/ai/systems/${system.id}/logs`}>
                                                    {system.interaction_logs_count}
                                                </Link>
                                            ) : '-'}
                                        </TableCell>
                                        <TableCell align="right">
                                            <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1 }}>
                                                <Button component={Link} href={`/admin/ai/systems/${system.id}`} size="small">
                                                    Edit
                                                </Button>
                                                <Button component={Link} href={`/admin/ai/systems/${system.id}/logs`} size="small">
                                                    Logs
                                                </Button>
                                                <Button size="small" onClick={() => handleDuplicate(system.id)}>
                                                    Copy
                                                </Button>
                                                <Button size="small" color="error" onClick={() => handleDelete(system.id, system.name)}>
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
            </Card>
        </AdminLayout>
    );
}
