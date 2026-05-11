import { Head } from "@inertiajs/react";
import { usePage } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import { useEffect, useState } from "react";

import type { BotItem, ModelStatusItem } from "./types";

import ChatBotCard from "@/components/ChatBotCard";

interface ChatBotsIndexProps {
    bots: BotItem[];
    statusesUrl: string;
}

interface SharedPageProps {
    [key: string]: unknown;
    auth?: {
        user?: {
            id: number;
            name: string;
            email: string;
        } | null;
    };
}

interface StatusesResponse {
    statuses?: { [key: string]: ModelStatusItem };
}

interface StatusesBySlug {
    [key: string]: ModelStatusItem;
}

export default function ChatBotsIndex({
    bots,
    statusesUrl,
}: ChatBotsIndexProps) {
    const page = usePage<SharedPageProps>();
    const isAuthenticated = Boolean(page.props.auth?.user);
    const [statuses, setStatuses] = useState<StatusesBySlug>({});

    useEffect(() => {
        let mounted = true;

        const loadStatuses = async (): Promise<void> => {
            try {
                const response = await fetch(statusesUrl, {
                    headers: { Accept: "application/json" },
                });

                if (!response.ok) {
                    return;
                }

                const payload = (await response.json()) as StatusesResponse;

                if (!mounted) {
                    return;
                }

                setStatuses(payload.statuses ?? {});
            } catch {
                if (mounted) {
                    setStatuses({});
                }
            }
        };

        void loadStatuses();

        return () => {
            mounted = false;
        };
    }, [statusesUrl]);

    return (
        <>
            <Head title="Chats" />

            <Box sx={{ maxWidth: 1080, mx: "auto", px: 2, py: 4 }}>
                <Stack spacing={1} sx={{ mb: 3 }}>
                    <Typography variant="h3" component="h1">
                        Available Chatbots
                    </Typography>
                    <Typography variant="body1" color="text.secondary">
                        Start a new chat with any chatbot available to you.
                    </Typography>
                </Stack>

                {bots.length === 0 ? (
                    <Card variant="outlined">
                        <CardContent>
                            <Typography variant="body1" color="text.secondary">
                                No chatbots are currently available.
                            </Typography>
                        </CardContent>
                    </Card>
                ) : (
                    <Stack spacing={2}>
                        {bots.map((bot) => (
                            <ChatBotCard
                                key={bot.name}
                                bot={bot}
                                isAuthenticated={isAuthenticated}
                                modelStatus={statuses[bot.slug] ?? null}
                            />
                        ))}
                    </Stack>
                )}
            </Box>
        </>
    );
}
