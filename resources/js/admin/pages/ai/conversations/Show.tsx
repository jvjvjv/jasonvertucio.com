import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Divider from "@mui/material/Divider";
import Link from "@mui/material/Link";
import Typography from "@mui/material/Typography";
import AdminLayout from "@/admin/layouts/AdminLayout";
import ChatMessageBubble from "@/components/ChatMessageBubble";
import ConfirmDialog from "@/admin/components/ConfirmDialog";
import PageHeader from "@/admin/components/PageHeader";
import StatusChip from "@/admin/components/StatusChip";
import UsageChip from "@/admin/components/UsageChip";
import useConfirmDialog from "@/hooks/useConfirmDialog";
import type { ConversationUsage, Memory, Message } from "@/types";

interface ShowProps {
    conversation: {
        id: number;
        title: string | null;
        feature: string;
        status: string;
        context: { [key: string]: unknown } | null;
        visitor_name: string | null;
        visitor_email: string | null;
        user_name: string | null;
        user_email: string | null;
        ai_system_name: string | null;
        ai_chat_bot: { id: number; name: string; slug: string } | null;
        usage: ConversationUsage | null;
        targeted_resume: {
            id: number;
            company_name: string;
            position: string;
        } | null;
    };
    messages: Message[];
    memories: Memory[];
}

export default function Show({ conversation, messages, memories }: ShowProps) {
    const { dialogProps, confirm } = useConfirmDialog();

    const handleDelete = () => {
        confirm("Delete this AI conversation?", () => {
            router.delete(`/admin/ai/conversations/${conversation.id}`);
        });
    };

    return (
        <AdminLayout>
            <Head
                title={`${conversation.title ?? `Conversation #${conversation.id}`} | Conversations`}
            />
            <PageHeader
                title={conversation.title ?? `Conversation #${conversation.id}`}
                backHref="/admin/ai/conversations"
                backLabel="Back to AI Conversations"
            />

            <Box
                sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: {
                        xs: "1fr",
                        lg: "minmax(0, 2fr) minmax(320px, 1fr)",
                    },
                }}
            >
                <Card>
                    <CardContent>
                        <Box
                            sx={{
                                display: "flex",
                                justifyContent: "space-between",
                                alignItems: "center",
                                mb: 2,
                            }}
                        >
                            <Typography variant="h6">
                                Message History
                            </Typography>
                            <Button color="error" onClick={handleDelete}>
                                Delete
                            </Button>
                        </Box>

                        <Box sx={{ display: "grid", gap: 2 }}>
                            {messages.map((message, index) => (
                                <Box
                                    key={message.id ?? index}
                                    sx={{
                                        border: "1px solid",
                                        borderColor: "divider",
                                        borderRadius: 1,
                                        p: 2,
                                        backgroundColor:
                                            message.role === "system"
                                                ? "action.hover"
                                                : "background.paper",
                                    }}
                                >
                                    <Box
                                        sx={{
                                            display: "flex",
                                            alignItems: "center",
                                            mb: 1,
                                        }}
                                    >
                                        <Typography
                                            variant="subtitle2"
                                            sx={{ textTransform: "capitalize" }}
                                        >
                                            {message.role}
                                        </Typography>
                                    </Box>
                                    <ChatMessageBubble
                                        role={message.role}
                                        content={message.content}
                                        variant="history"
                                        sentAt={message.created_at ?? null}
                                    />
                                </Box>
                            ))}
                        </Box>
                    </CardContent>
                </Card>

                <Box sx={{ display: "grid", gap: 2 }}>
                    <Card>
                        <CardContent>
                            <Typography variant="h6" sx={{ mb: 2 }}>
                                Conversation Details
                            </Typography>
                            <Box sx={{ display: "grid", gap: 1 }}>
                                <Box>
                                    <strong>Status:</strong>{" "}
                                    <StatusChip status={conversation.status} />
                                </Box>
                                <Box>
                                    <strong>Feature:</strong>{" "}
                                    {conversation.feature}
                                </Box>
                                <Box>
                                    <strong>AI System:</strong>{" "}
                                    {conversation.ai_system_name ?? "-"}
                                </Box>
                                <Box>
                                    <strong>Usage:</strong>{" "}
                                    <UsageChip usage={conversation.usage} />
                                </Box>
                                <Box>
                                    <strong>Bot:</strong>{" "}
                                    {conversation.ai_chat_bot ? (
                                        <Link
                                            component={InertiaLink}
                                            href={`/admin/ai/chat-bots/${conversation.ai_chat_bot.id}`}
                                            underline="hover"
                                        >
                                            {conversation.ai_chat_bot.name}
                                        </Link>
                                    ) : (
                                        "None"
                                    )}
                                </Box>
                                <Box>
                                    <strong>User:</strong>{" "}
                                    {conversation.user_name ?? "Guest"}
                                </Box>
                                {conversation.user_email ? (
                                    <Box>
                                        <strong>User Email:</strong>{" "}
                                        {conversation.user_email}
                                    </Box>
                                ) : null}
                                {conversation.visitor_name ? (
                                    <Box>
                                        <strong>Visitor:</strong>{" "}
                                        {conversation.visitor_name}
                                    </Box>
                                ) : null}
                                {conversation.visitor_email ? (
                                    <Box>
                                        <strong>Visitor Email:</strong>{" "}
                                        {conversation.visitor_email}
                                    </Box>
                                ) : null}
                            </Box>
                            {conversation.targeted_resume ? (
                                <>
                                    <Divider sx={{ my: 2 }} />
                                    <Typography
                                        variant="subtitle2"
                                        sx={{ mb: 1 }}
                                    >
                                        Targeted Resume
                                    </Typography>
                                    <Typography variant="body2">
                                        {
                                            conversation.targeted_resume
                                                .company_name
                                        }{" "}
                                        —{" "}
                                        {conversation.targeted_resume.position}
                                    </Typography>
                                    <Button
                                        component={InertiaLink}
                                        href={`/admin/resume/targeted-builder/${conversation.id}`}
                                        size="small"
                                        sx={{ mt: 1 }}
                                    >
                                        Open Targeted Builder
                                    </Button>
                                </>
                            ) : null}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent>
                            <Typography variant="h6" sx={{ mb: 2 }}>
                                Conversation Context
                            </Typography>
                            <Box
                                component="pre"
                                sx={{
                                    m: 0,
                                    whiteSpace: "pre-wrap",
                                    overflowX: "auto",
                                    fontSize: 13,
                                }}
                            >
                                {JSON.stringify(
                                    conversation.context ?? {},
                                    null,
                                    2,
                                )}
                            </Box>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent>
                            <Typography variant="h6" sx={{ mb: 2 }}>
                                Learned Insights
                            </Typography>
                            <Box sx={{ display: "grid", gap: 1.5 }}>
                                {memories.length === 0 ? (
                                    <Typography color="text.secondary">
                                        No AI memory entries were sourced from
                                        this conversation yet.
                                    </Typography>
                                ) : (
                                    memories.map((memory) => (
                                        <Box
                                            key={memory.id}
                                            sx={{
                                                border: "1px solid",
                                                borderColor: "divider",
                                                borderRadius: 1,
                                                p: 1.5,
                                            }}
                                        >
                                            <Typography variant="subtitle2">
                                                {memory.key}
                                            </Typography>
                                            <Typography
                                                variant="caption"
                                                color="text.secondary"
                                            >
                                                {memory.feature} ·{" "}
                                                {memory.category} · confidence{" "}
                                                {memory.confidence}
                                            </Typography>
                                            <Typography sx={{ mt: 0.5 }}>
                                                {(
                                                    memory as Memory & {
                                                        content?: string;
                                                    }
                                                ).content ?? ""}
                                            </Typography>
                                        </Box>
                                    ))
                                )}
                            </Box>
                        </CardContent>
                    </Card>
                </Box>
            </Box>
            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
