import { Head, router, usePage } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import { useEffect, useMemo, useState } from "react";

import ChatHistoryPanel from "./ChatHistoryPanel";
import ChatTabs from "./ChatTabs";
import IdentityFormModal from "./IdentityFormModal";

import type { ChatMessage } from "@/components/ChatInterface";
import type { SharedProps } from "@/types";

import BotHeaderCard from "@/chat/components/BotHeaderCard";
import ChatInterface from "@/components/ChatInterface";

interface HistoryItem {
    handle: string;
    label: string;
    is_current: boolean;
    is_stale: boolean;
    updated_at: string;
    cost_usd: number | null;
}

interface Bot {
    name: string;
    description: string | null;
    allowed_roles: string[];
    require_visitor_identity: boolean;
    total_cost_usd: number;
}

interface ChatBotProps {
    bot: Bot;
    messages: ChatMessage[];
    history: HistoryItem[];
    messageUrl: string;
    statusUrl: string;
    warmupUrl: string;
    resetUrl: string;
    switchUrl: string;
    chatUrl?: string | null;
    chatUrlBase?: string | null;
    showIdentityForm: boolean;
    chatHash?: string | null;
}

export default function ChatBot({
    bot,
    messages: initialMessages,
    history,
    messageUrl,
    statusUrl,
    warmupUrl,
    resetUrl,
    switchUrl,
    chatUrl,
    chatUrlBase,
    showIdentityForm: initialShowIdentityForm,
    chatHash: _chatHash,
}: ChatBotProps) {
    const [activeTab, setActiveTab] = useState(0);
    const [showIdentityForm, setShowIdentityForm] = useState(
        initialShowIdentityForm,
    );
    const [visitorName, setVisitorName] = useState("");
    const [visitorEmail, setVisitorEmail] = useState("");

    const page = usePage<SharedProps>();
    const authUser = page.props.auth.user;
    const sessionExpiresAt = page.props.session.expiresAt;

    // Redirect to hash-based URL after first message so the chat is shareable
    useEffect(() => {
        if (chatUrl && initialMessages.length > 0) {
            const currentPath = window.location.pathname;
            if (currentPath !== chatUrl) {
                const timer = setTimeout(() => {
                    window.location.href = chatUrl;
                }, 300);
                return () => {
                    clearTimeout(timer);
                };
            }
        }
    }, [chatUrl, initialMessages]);

    const extraPayload = useMemo(
        () =>
            showIdentityForm
                ? { name: visitorName, email: visitorEmail }
                : undefined,
        [showIdentityForm, visitorName, visitorEmail],
    );

    const formatCost = (value: number | null | undefined): string =>
        value == null ? "—" : `$${value.toFixed(2)}`;

    const handleReset = () => {
        router.post(resetUrl, {});
    };

    const handleSwitch = (handle: string) => {
        router.post(switchUrl, { conversation: handle });
    };

    const identityForm = (
        <IdentityFormModal
            visible={showIdentityForm}
            visitorName={visitorName}
            visitorEmail={visitorEmail}
            onVisitorNameChange={setVisitorName}
            onVisitorEmailChange={setVisitorEmail}
        />
    );

    const disclaimer = (
        <Typography
            variant="caption"
            sx={{
                mr: 2,
                alignSelf: "center",
                fontStyle: "italic",
                flexGrow: 1,
                display: { xs: "none", sm: "block" },
            }}
        >
            Chatbots are experimental. Responses may be inaccurate or fail to
            generate. Use with caution.
        </Typography>
    );

    const botHeaderCard = (
        <BotHeaderCard name={bot.name} description={bot.description} />
    );

    const [badgeColor, setBadgeColor] = useState<
        "success" | "warning" | "error" | "info"
    >("info");

    return (
        <>
            <Head title={bot.name} />
            <Box
                sx={{
                    mx: "auto",
                    width: "100%",
                    px: { xs: 1, md: 2 },
                }}
            >
                <Box sx={{ display: "flex", flexDirection: "column", gap: 2 }}>
                    <ChatTabs
                        activeTab={activeTab}
                        badgeColor={badgeColor}
                        onTabChange={setActiveTab}
                        onReset={handleReset}
                    />

                    {activeTab === 0 ? (
                        <ChatInterface
                            chatEndpoint={messageUrl}
                            statusUrl={statusUrl}
                            warmupUrl={warmupUrl}
                            initialMessages={initialMessages}
                            isAuthenticated={!!authUser}
                            sessionExpiresAt={sessionExpiresAt}
                            extraPayload={extraPayload}
                            slots={{
                                header: botHeaderCard,
                                beforeSend: identityForm,
                                afterSend: disclaimer,
                            }}
                            onModelStatusChange={(status) => {
                                setBadgeColor(
                                    status?.state === "loaded"
                                        ? "success"
                                        : status?.state === "not_loaded"
                                          ? "warning"
                                          : status?.state === "unavailable"
                                            ? "error"
                                            : "info",
                                );
                            }}
                            onStreamResponse={
                                chatUrlBase
                                    ? (res) => {
                                          const hash =
                                              res.headers.get("X-Chat-Hash");
                                          if (
                                              hash &&
                                              window.location.pathname !==
                                                  chatUrlBase + hash
                                          ) {
                                              window.history.replaceState(
                                                  null,
                                                  "",
                                                  chatUrlBase + hash,
                                              );
                                          }
                                      }
                                    : undefined
                            }
                            onStreamEnd={() => {
                                setShowIdentityForm(false);
                            }}
                        />
                    ) : (
                        <ChatHistoryPanel
                            bot={bot}
                            history={history}
                            formatCost={formatCost}
                            onSwitch={handleSwitch}
                        />
                    )}
                </Box>
            </Box>
        </>
    );
}
