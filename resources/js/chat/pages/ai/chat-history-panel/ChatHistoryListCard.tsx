import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import List from "@mui/material/List";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";

import ChatHistoryListItem from "./ChatHistoryListItem";

interface HistoryItem {
    handle: string;
    label: string;
    is_current: boolean;
    is_stale: boolean;
    updated_at: string;
}

interface ChatHistoryListCardProps {
    history: HistoryItem[];
    onSwitch: (handle: string) => void;
}

export default function ChatHistoryListCard({
    history,
    onSwitch,
}: ChatHistoryListCardProps) {
    return (
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

                {history.length > 0 ? (
                    <List disablePadding>
                        {history.map((item) => (
                            <ChatHistoryListItem
                                key={item.handle}
                                item={item}
                                onSwitch={onSwitch}
                            />
                        ))}
                    </List>
                ) : (
                    <Typography variant="body2" color="text.secondary">
                        No saved chats in this browser yet. Start a message to
                        create a private thread.
                    </Typography>
                )}
            </CardContent>
        </Card>
    );
}
