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
            <ChatHistoryListCard
                history={history}
                totalCostUsd={bot.total_cost_usd}
                formatCost={formatCost}
                onSwitch={onSwitch}
            />
            <BotAccessCard bot={bot} />
            <PromptNotesCard />
        </Stack>
    );
}
