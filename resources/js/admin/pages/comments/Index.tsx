import { Head, router } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Link from "@mui/material/Link";
import Typography from "@mui/material/Typography";

import type { ColumnDef } from "@/admin/components/DataTable";
import type { PaginatedResponse } from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import DataTable from "@/admin/components/DataTable";
import PageHeader from "@/admin/components/PageHeader";
import StatusChip from "@/admin/components/StatusChip";
import AdminLayout from "@/admin/layouts/AdminLayout";
import useConfirmDialog from "@/hooks/useConfirmDialog";

interface CommentPost {
    title: string;
    url: string;
}

interface Comment {
    id: number;
    name: string;
    email: string | null;
    message: string;
    depth: number;
    is_spam: boolean;
    approved_at: string | null;
    created_at: string | null;
    ip_address: string | null;
    registered_user: string | null;
    post: CommentPost | null;
}

interface IndexProps {
    comments: PaginatedResponse<Comment>;
}

const formatDate = (value: string | null) =>
    value ? new Date(value).toLocaleString() : "—";

const columns: ColumnDef<Comment>[] = [
    {
        key: "author",
        label: "Author",
        render: (row) => (
            <Box>
                <Typography variant="body2" sx={{ fontWeight: 600 }}>
                    {row.name}
                </Typography>
                <Typography variant="caption" color="text.secondary">
                    {row.registered_user ? "Registered" : "Anonymous"}
                    {row.email ? ` · ${row.email}` : ""}
                </Typography>
            </Box>
        ),
    },
    {
        key: "message",
        label: "Comment",
        render: (row) => (
            <Typography variant="body2" sx={{ maxWidth: 420 }}>
                {row.message.length > 200
                    ? `${row.message.slice(0, 200)}…`
                    : row.message}
            </Typography>
        ),
    },
    {
        key: "post",
        label: "Post",
        render: (row) =>
            row.post ? (
                <Link href={row.post.url} underline="hover" color="primary">
                    {row.post.title}
                </Link>
            ) : (
                "—"
            ),
    },
    {
        key: "created_at",
        label: "Posted",
        render: (row) => (
            <Box>
                <Typography variant="body2">
                    {formatDate(row.created_at)}
                </Typography>
                {row.ip_address && (
                    <Typography variant="caption" color="text.secondary">
                        {row.ip_address}
                    </Typography>
                )}
            </Box>
        ),
    },
    {
        key: "state",
        label: "State",
        render: (row) => (
            <StatusChip
                status={row.is_spam ? "spam" : "approved"}
                label={row.is_spam ? "Spam" : "Approved"}
                colorMap={{ spam: "error", approved: "success" }}
            />
        ),
    },
];

export default function Index({ comments }: IndexProps) {
    const { dialogProps, confirm } = useConfirmDialog();

    const markSpam = (id: number) => {
        confirm(
            "Mark this comment as spam? It will be hidden from the post.",
            () => {
                router.post(`/admin/comments/${id}/spam`, undefined, {
                    preserveScroll: true,
                });
            },
        );
    };

    const markNotSpam = (id: number) => {
        router.post(`/admin/comments/${id}/not-spam`, undefined, {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout>
            <Head title="Comments" />
            <PageHeader
                title="Comments"
                backHref="/admin"
                backLabel="Back to Admin"
            />

            <DataTable
                columns={columns}
                data={comments.data}
                pagination={{
                    links: comments.links,
                    last_page: comments.last_page,
                }}
                emptyMessage="No comments yet."
                rowActions={(comment) => (
                    <Box
                        sx={{
                            display: "flex",
                            justifyContent: "flex-end",
                            gap: 1,
                        }}
                    >
                        {comment.is_spam ? (
                            <Button
                                size="small"
                                onClick={() => {
                                    markNotSpam(comment.id);
                                }}
                            >
                                Not spam
                            </Button>
                        ) : (
                            <Button
                                size="small"
                                color="error"
                                onClick={() => {
                                    markSpam(comment.id);
                                }}
                            >
                                Mark spam
                            </Button>
                        )}
                    </Box>
                )}
            />

            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
