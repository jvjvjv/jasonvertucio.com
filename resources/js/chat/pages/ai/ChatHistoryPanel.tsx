import Stack from "@mui/material/Stack";

import BotAccessCard from "./chat-history-panel/BotAccessCard";
import ChatHistoryListCard from "./chat-history-panel/ChatHistoryListCard";
import PromptNotesCard from "./chat-history-panel/PromptNotesCard";

interface HistoryItem {
    handle: string;
    label: string;
    is_current: boolean;
    is_stale: boolean;
    updated_at: string;
}

interface Bot {
    required_permission: string | null;
    require_visitor_identity: boolean;
}

interface ChatHistoryPanelProps {
    bot: Bot;
    history: HistoryItem[];
    onSwitch: (_handle: string) => void;
}

export default function ChatHistoryPanel({
    bot,
    history,
    onSwitch,
}: ChatHistoryPanelProps) {
    return (
        <Stack spacing={2}>
            <ChatHistoryListCard history={history} onSwitch={onSwitch} />
            <BotAccessCard bot={bot} />
            <PromptNotesCard />
        </Stack>
    );
}
