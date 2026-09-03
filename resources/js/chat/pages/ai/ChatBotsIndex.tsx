import { Head } from "@inertiajs/react";
import { usePage } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import { useEffect, useState } from "react";

import type { ModelStatusItem } from "./types";
import type { ChatBotsIndexProps } from "@/types/code-talker";

import { api } from "@/api";
import ChatBotCard from "@/components/ChatBotCard";

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

interface StatusResponse {
    status?: ModelStatusItem;
}

/**
 * Statuses arrive per bot, asynchronously and independently, so a slug is
 * absent until its own request lands (and stays absent if it fails) — the
 * value type says so rather than claiming every slug resolves.
 */
interface StatusesBySlug {
    [key: string]: ModelStatusItem | undefined;
}

export default function ChatBotsIndex({ bots }: ChatBotsIndexProps) {
    const page = usePage<SharedPageProps>();
    const isAuthenticated = Boolean(page.props.auth?.user);
    const [statuses, setStatuses] = useState<StatusesBySlug>({});

    useEffect(() => {
        const abortController = new AbortController();

        for (const bot of bots) {
            void (async () => {
                try {
                    const payload = await api.get<StatusResponse>(
                        bot.status_url,
                        undefined,
                        abortController.signal,
                    );

                    if (payload.status == null) {
                        return;
                    }

                    setStatuses((current) => ({
                        ...current,
                        [bot.slug]: payload.status,
                    }));
                } catch {
                    return;
                }
            })();
        }

        return () => {
            abortController.abort();
        };
    }, [bots]);

    return (
        <>
            <Head title="Chats" />

            <Box sx={{ maxWidth: 1080, mx: "auto", px: 2, py: 4 }}>
                <Stack spacing={1} sx={{ mb: 3 }}>
                    <Typography variant="h3" component="h1">
                        Available Personas
                    </Typography>
                    <Typography variant="body1" color="text.secondary">
                        Start a new chat with any persona available to you.
                    </Typography>
                </Stack>

                {bots.length === 0 ? (
                    <Card variant="outlined">
                        <CardContent>
                            <Typography variant="body1" color="text.secondary">
                                No personas are currently available.
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
