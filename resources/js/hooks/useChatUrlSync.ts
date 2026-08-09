import { useEffect } from "react";

/**
 * Redirects to the conversation's hash-based URL shortly after the first
 * message is sent, so the chat becomes shareable. No-op until `chatUrl` is
 * set and at least one message exists.
 */
export default function useChatUrlSync(
    chatUrl: string | null | undefined,
    messageCount: number,
): void {
    useEffect(() => {
        if (chatUrl && messageCount > 0) {
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
    }, [chatUrl, messageCount]);
}
