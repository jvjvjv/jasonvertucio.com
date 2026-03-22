import { Head, Link } from '@inertiajs/react';
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

interface LogEntry {
    id: number;
    created_at_formatted: string;
    user_name: string;
    feature: string;
    status: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedLogs {
    data: LogEntry[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
}

interface AiSystem {
    id: number;
    name: string;
}

interface LogsProps {
    aiSystem: AiSystem;
    logs: PaginatedLogs;
}

export default function Logs({ aiSystem, logs }: LogsProps) {
    return (
        <AdminLayout>
            <Head title={`Logs — ${aiSystem.name}`} />
            <PageHeader title={`Logs: ${aiSystem.name}`} backHref={`/admin/ai/systems/${aiSystem.id}`} backLabel="Back to System" />

            <Card>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>Date</TableCell>
                                <TableCell>User</TableCell>
                                <TableCell>Feature</TableCell>
                                <TableCell>Status</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {logs.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={4} align="center" sx={{ py: 4 }}>
                                        <Typography color="text.secondary">
                                            No interaction logs yet. Logs will appear here once this system processes requests.
                                        </Typography>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                logs.data.map((log) => (
                                    <TableRow key={log.id}>
                                        <TableCell>{log.created_at_formatted}</TableCell>
                                        <TableCell>{log.user_name}</TableCell>
                                        <TableCell>{log.feature}</TableCell>
                                        <TableCell>
                                            <Chip
                                                label={log.status}
                                                size="small"
                                                color={log.status === 'success' ? 'success' : 'error'}
                                                variant="outlined"
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>

                {/* Pagination */}
                {logs.last_page > 1 && (
                    <Box sx={{ display: 'flex', justifyContent: 'center', gap: 1, py: 2 }}>
                        {logs.links.map((link, i) => (
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
