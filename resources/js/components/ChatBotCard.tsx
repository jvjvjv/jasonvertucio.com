import ChatIcon from "@mui/icons-material/Chat";
import WarningAmberIcon from "@mui/icons-material/WarningAmber";
import Badge from "@mui/material/Badge";
import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Chip from "@mui/material/Chip";
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
                : "info";

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
                                                    <Box
                                                        sx={{
                                                            display: "flex",
                                                            alignItems:
                                                                "center",
                                                            gap: 1,
                                                            minWidth: 0,
                                                            flexGrow: 1,
                                                        }}
                                                    >
                                                        {conversation.is_stale ? (
                                                            <Chip
                                                                icon={
                                                                    <WarningAmberIcon fontSize="small" />
                                                                }
                                                                size="small"
                                                                label="Stale"
                                                                sx={{
                                                                    flexShrink: 0,
                                                                    "& .MuiChip-label":
                                                                        {
                                                                            pl: 0.5,
                                                                        },
                                                                }}
                                                            />
                                                        ) : null}
                                                        <Typography
                                                            variant="body2"
                                                            component="p"
                                                            sx={{
                                                                fontWeight: 500,
                                                                overflow:
                                                                    "hidden",
                                                                textOverflow:
                                                                    "ellipsis",
                                                                whiteSpace:
                                                                    "nowrap",
                                                            }}
                                                        >
                                                            {conversation.title}
                                                        </Typography>
                                                    </Box>
                                                    <Typography
                                                        variant="caption"
                                                        color="text.secondary"
                                                        sx={{
                                                            textTransform:
                                                                "uppercase",
                                                            letterSpacing:
                                                                "0.08em",
                                                            flexShrink: 0,
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
