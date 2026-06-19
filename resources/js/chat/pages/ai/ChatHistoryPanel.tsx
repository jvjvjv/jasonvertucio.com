import WarningAmberIcon from "@mui/icons-material/WarningAmber";
import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Chip from "@mui/material/Chip";
import List from "@mui/material/List";
import ListItemButton from "@mui/material/ListItemButton";
import ListItemText from "@mui/material/ListItemText";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";

interface HistoryItem {
    handle: string;
    label: string;
    is_current: boolean;
    is_stale: boolean;
    updated_at: string;
    cost_usd: number | null;
}

interface Bot {
    allowed_roles: string[];
    require_visitor_identity: boolean;
    total_cost_usd: number;
}

interface ChatHistoryPanelProps {
    bot: Bot;
    history: HistoryItem[];
    formatCost: (_value: number | null | undefined) => string;
    onSwitch: (_handle: string) => void;
}

export default function ChatHistoryPanel({
    bot,
    history,
    formatCost,
    onSwitch,
}: ChatHistoryPanelProps) {
    return (
        <Stack spacing={2}>
            <Card>
                <CardContent>
                    <Stack
                        direction={{ xs: "column", sm: "row" }}
                        alignItems={{ xs: "flex-start", sm: "center" }}
                        justifyContent="space-between"
                        spacing={1}
                        sx={{ mb: 1 }}
                    >
                        <Typography variant="h5">Your Chats</Typography>
                        <Typography
                            variant="caption"
                            color="text.secondary"
                            sx={{
                                textTransform: "uppercase",
                                letterSpacing: "0.14em",
                            }}
                        >
                            Private to this browser
                        </Typography>
                    </Stack>

                    <Box
                        sx={{
                            display: "flex",
                            justifyContent: "space-between",
                            alignItems: "center",
                            mb: 1.5,
                            px: 1,
                        }}
                    >
                        <Typography variant="body2" color="text.secondary">
                            Overall Chatbot Cost
                        </Typography>
                        <Typography variant="body2" sx={{ fontWeight: 700 }}>
                            {formatCost(bot.total_cost_usd)}
                        </Typography>
                    </Box>

                    {history.length > 0 ? (
                        <List disablePadding>
                            {history.map((item) => (
                                <ListItemButton
                                    key={item.handle}
                                    selected={item.is_current}
                                    onClick={() => {
                                        onSwitch(item.handle);
                                    }}
                                    sx={{
                                        border: "1px solid",
                                        borderColor: item.is_current
                                            ? "primary.main"
                                            : "divider",
                                        mb: 1,
                                    }}
                                >
                                    <Box
                                        sx={{
                                            display: "flex",
                                            alignItems: "center",
                                            gap: 1,
                                            minWidth: 0,
                                            flexGrow: 1,
                                        }}
                                    >
                                        <ListItemText
                                            primary={item.label}
                                            secondary={
                                                <Box
                                                    sx={{
                                                        display: "flex",
                                                        alignItems: "center",
                                                        justifyContent:
                                                            "space-between",
                                                        mt: 0.5,
                                                    }}
                                                >
                                                    <Typography
                                                        component="span"
                                                        sx={{
                                                            textTransform:
                                                                "uppercase",
                                                            letterSpacing:
                                                                "0.08em",
                                                            fontSize: "0.7rem",
                                                            color: "text.secondary",
                                                        }}
                                                    >
                                                        {item.updated_at}
                                                    </Typography>
                                                    <Typography
                                                        component="span"
                                                        sx={{
                                                            fontSize: "0.72rem",
                                                            color: "text.secondary",
                                                            fontWeight: 600,
                                                        }}
                                                    >
                                                        Cost:{" "}
                                                        {formatCost(
                                                            item.cost_usd,
                                                        )}
                                                    </Typography>
                                                </Box>
                                            }
                                        />
                                        {item.is_stale && !item.is_current ? (
                                            <Chip
                                                icon={
                                                    <WarningAmberIcon fontSize="small" />
                                                }
                                                size="small"
                                                label="Stale"
                                                sx={{
                                                    flexShrink: 0,
                                                    "& .MuiChip-label": {
                                                        pl: 0.5,
                                                    },
                                                }}
                                            />
                                        ) : null}
                                    </Box>
                                    {item.is_current ? (
                                        <Typography
                                            variant="caption"
                                            color="primary"
                                            sx={{
                                                textTransform: "uppercase",
                                                letterSpacing: "0.12em",
                                            }}
                                        >
                                            Current
                                        </Typography>
                                    ) : null}
                                </ListItemButton>
                            ))}
                        </List>
                    ) : (
                        <Typography variant="body2" color="text.secondary">
                            No saved chats in this browser yet. Start a message
                            to create a private thread.
                        </Typography>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardContent>
                    <Typography variant="h5" sx={{ mb: 1 }}>
                        Access
                    </Typography>
                    <Stack spacing={0.75}>
                        <Typography variant="body2" color="text.secondary">
                            {!(bot.allowed_roles.length > 0) &&
                            bot.require_visitor_identity
                                ? "Public bot"
                                : bot.allowed_roles.length > 0 &&
                                    bot.require_visitor_identity
                                  ? "Mostly public bot"
                                  : "Restricted bot"}
                        </Typography>
                        <Typography variant="body2" color="text.secondary">
                            {bot.require_visitor_identity
                                ? "Name and email are required before the first guest message."
                                : "No guest identity is required by this bot."}
                        </Typography>
                        <Typography variant="body2" color="text.secondary">
                            Only chats created in this browser are listed here.
                        </Typography>
                    </Stack>
                </CardContent>
            </Card>

            <Card>
                <CardContent>
                    <Typography variant="h5" sx={{ mb: 1 }}>
                        Prompt Notes
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                        The conversation is saved and can contribute new
                        insights to AI Memory for this bot.
                    </Typography>
                </CardContent>
            </Card>
        </Stack>
    );
}
