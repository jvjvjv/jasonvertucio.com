import ChatIcon from "@mui/icons-material/Chat";
import Badge from "@mui/material/Badge";
import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Divider from "@mui/material/Divider";
import List from "@mui/material/List";
import ListItem from "@mui/material/ListItem";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";

import ResponsiveButton from "./ResponsiveButton";

import type { BotItem, ModelStatusItem } from "@/chat/pages/ai/types";

interface ChatBotCardProps {
    key: string;
    bot: BotItem;
    isAuthenticated: boolean;
    modelStatus: ModelStatusItem | null;
}

export default function ChatBotCard({
    key,
    bot,
    isAuthenticated,
    modelStatus,
}: ChatBotCardProps) {
    const statusColor =
        modelStatus?.state === "loaded"
            ? "success"
            : modelStatus?.state === "not_loaded"
              ? "warning"
              : modelStatus?.state === "unavailable"
                ? "error"
                : "error";

    return (
        <Badge variant="dot" color={statusColor} overlap="rectangular">
            <Card key={key} variant="outlined" sx={{ width: "100%" }}>
                <CardContent>
                    <Stack spacing={1}>
                        <Box
                            sx={{
                                display: "flex",
                                alignItems: "center",
                                gap: 1,
                            }}
                        >
                            <Typography
                                variant="h5"
                                component="h2"
                                sx={{ flexGrow: 1 }}
                            >
                                {bot.name}
                            </Typography>
                            <ResponsiveButton
                                icon={<ChatIcon />}
                                href={bot.new_chat_url}
                                label="New Chat"
                            />
                        </Box>
                        <Typography
                            variant="body1"
                            component="p"
                            color="text.secondary"
                        >
                            {bot.description ??
                                "No description is available for this chatbot yet."}
                        </Typography>
                    </Stack>

                    {isAuthenticated ? (
                        <>
                            <Divider sx={{ my: 2 }} />
                            <Stack spacing={1}>
                                <Typography variant="subtitle1" component="h3">
                                    Prior Conversations
                                </Typography>

                                {bot.conversations.length > 0 ? (
                                    <List disablePadding>
                                        {bot.conversations.map(
                                            (conversation) => (
                                                <ListItem
                                                    key={`${bot.name}-${conversation.title}-${conversation.updated_at}`}
                                                    disableGutters
                                                    sx={{
                                                        display: "flex",
                                                        justifyContent:
                                                            "space-between",
                                                        py: 0.75,
                                                        gap: 2,
                                                    }}
                                                >
                                                    <Typography
                                                        variant="body2"
                                                        component="p"
                                                        sx={{
                                                            fontWeight: 500,
                                                        }}
                                                    >
                                                        {conversation.title}
                                                    </Typography>
                                                    <Typography
                                                        variant="caption"
                                                        color="text.secondary"
                                                        sx={{
                                                            textTransform:
                                                                "uppercase",
                                                            letterSpacing:
                                                                "0.08em",
                                                        }}
                                                    >
                                                        {
                                                            conversation.updated_at_human
                                                        }
                                                    </Typography>
                                                </ListItem>
                                            ),
                                        )}
                                    </List>
                                ) : (
                                    <Typography
                                        variant="body2"
                                        color="text.secondary"
                                    >
                                        No prior conversations yet.
                                    </Typography>
                                )}
                            </Stack>
                        </>
                    ) : null}
                </CardContent>
            </Card>
        </Badge>
    );
}
