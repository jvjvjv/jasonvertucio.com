import { Head } from '@inertiajs/react';
import Card from '@mui/material/Card';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import Typography from '@mui/material/Typography';
import AdminLayout from '../../../layouts/AdminLayout';
import PageHeader from '../../../components/PageHeader';
import EmptyTableRow from '../../../components/EmptyTableRow';
import StatusChip from '../../../components/StatusChip';
import Pagination from '../../../components/Pagination';
import type { AiSystem, LogEntry, PaginatedResponse } from '../../../types';

interface LogsProps {
    aiSystem: Pick<AiSystem, 'id' | 'name'>;
    logs: PaginatedResponse<LogEntry>;
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
                                <EmptyTableRow colSpan={4} message="No interaction logs yet. Logs will appear here once this system processes requests." />
                            ) : (
                                logs.data.map((log) => (
                                    <TableRow key={log.id}>
                                        <TableCell>{log.created_at_formatted}</TableCell>
                                        <TableCell>{log.user_name}</TableCell>
                                        <TableCell>{log.feature}</TableCell>
                                        <TableCell>
                                            <StatusChip status={log.status} colorMap={{ success: 'success', error: 'error', failed: 'error' }} />
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>

                <Pagination links={logs.links} lastPage={logs.last_page} />
            </Card>
        </AdminLayout>
    );
}
