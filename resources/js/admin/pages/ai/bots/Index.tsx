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
import AdminLayout from '../../../layouts/AdminLayout';
import ConfirmDialog from '../../../components/ConfirmDialog';
import EmptyTableRow from '../../../components/EmptyTableRow';
import PageHeader from '../../../components/PageHeader';
import useConfirmDialog from '../../../hooks/useConfirmDialog';
import type { AiChatBot } from '../../../types';

interface IndexProps {
    bots: AiChatBot[];
}

export default function Index({ bots }: IndexProps) {
    const { dialogProps, confirm } = useConfirmDialog();

    const handleDelete = (bot: AiChatBot) => {
        confirm(`Delete AI chat bot "${bot.name}"?`, () => {
            router.delete(`/admin/ai/chat-bots/${bot.id}`);
        });
    };

    return (
        <AdminLayout>
            <Head title="AI Chat Bots" />
            <PageHeader title="AI Chat Bots" backHref="/admin/ai" backLabel="Back to AI Tools" />

            <Box sx={{ display: 'flex', justifyContent: 'flex-end', mb: 2 }}>
                <Button component={Link} href="/admin/ai/chat-bots/new" variant="contained">
                    Add Bot
                </Button>
            </Box>

            <Card>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>Name</TableCell>
                                <TableCell>Slug</TableCell>
                                <TableCell>AI System</TableCell>
                                <TableCell>Access</TableCell>
                                <TableCell>Conversations</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {bots.length === 0 ? (
                                <EmptyTableRow colSpan={6} message="No AI chat bots configured yet." actionLabel="Add your first one" actionHref="/admin/ai/chat-bots/new" />
                            ) : (
                                bots.map((bot) => (
                                    <TableRow key={bot.id} hover sx={{ opacity: bot.is_active ? 1 : 0.5 }}>
                                        <TableCell>
                                            <Link href={`/admin/ai/chat-bots/${bot.id}`} style={{ color: 'inherit', fontWeight: 500 }}>
                                                {bot.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>{bot.public_url ?? (bot.access_path === 'root' ? `/${bot.slug}` : `/chat/${bot.slug}`)}</TableCell>
                                        <TableCell>{bot.ai_system_name ?? '-'}</TableCell>
                                        <TableCell>
                                            <Box sx={{ display: 'flex', gap: 0.5, flexWrap: 'wrap' }}>
                                                <Chip label={bot.access_path === 'root' ? 'Root Path' : 'Chat Path'} size="small" variant="outlined" />
                                                <Chip label={bot.is_public ? 'Public' : 'Role-based'} size="small" color={bot.is_public ? 'success' : 'default'} variant="outlined" />
                                                {bot.require_visitor_identity ? <Chip label="Identity Required" size="small" color="warning" variant="outlined" /> : null}
                                            </Box>
                                        </TableCell>
                                        <TableCell>{bot.conversations_count ?? 0}</TableCell>
                                        <TableCell align="right">
                                            <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1 }}>
                                                <Button component={Link} href={bot.public_url ?? (bot.access_path === 'root' ? `/${bot.slug}` : `/chat/${bot.slug}`)} size="small" target="_blank">
                                                    Open
                                                </Button>
                                                <Button component={Link} href={`/admin/ai/chat-bots/${bot.id}`} size="small">
                                                    Edit
                                                </Button>
                                                <Button size="small" color="error" onClick={() => handleDelete(bot)}>
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
            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
