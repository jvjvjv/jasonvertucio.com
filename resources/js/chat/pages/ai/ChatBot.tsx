import { Head, router } from '@inertiajs/react';
import { marked } from 'marked';
import { useEffect, useRef, useState } from 'react';

interface HistoryItem {
    handle: string;
    label: string;
    is_current: boolean;
    updated_at: string;
}

interface ChatMessage {
    role: 'user' | 'assistant' | 'system';
    content: string;
}

interface Bot {
    name: string;
    description: string | null;
    is_public: boolean;
    require_visitor_identity: boolean;
}

interface ChatBotProps {
    bot: Bot;
    messages: ChatMessage[];
    history: HistoryItem[];
    messageUrl: string;
    resetUrl: string;
    switchUrl: string;
    showIdentityForm: boolean;
}

function MarkdownContent({ content, isUser }: { content: string; isUser: boolean }) {
    return (
        <div
            className={`chat-markdown text-sm leading-6${isUser ? ' chat-markdown--user' : ''}`}
            dangerouslySetInnerHTML={{
                __html: marked.parse(content, { breaks: true }) as string,
            }}
        />
    );
}

function MessageBubble({ message }: { message: ChatMessage }) {
    const isUser = message.role === 'user';
    return (
        <article
            className={`border px-4 py-3 ${isUser ? 'border-slate-800 bg-slate-800 text-white' : 'border-slate-300 bg-slate-50 text-slate-900'}`}
        >
            <p
                className={`mb-2 text-xs uppercase tracking-[0.16em] ${isUser ? 'text-slate-200' : 'text-slate-500'}`}
            >
                {message.role}
            </p>
            <MarkdownContent content={message.content} isUser={isUser} />
        </article>
    );
}

export default function ChatBot({
    bot,
    messages: initialMessages,
    history,
    messageUrl,
    resetUrl,
    switchUrl,
    showIdentityForm: initialShowIdentityForm,
}: ChatBotProps) {
    const [messages, setMessages] = useState<ChatMessage[]>(initialMessages);
    const [streamingContent, setStreamingContent] = useState('');
    const [isStreaming, setIsStreaming] = useState(false);
    const [error, setError] = useState('');
    const [showIdentityForm, setShowIdentityForm] = useState(initialShowIdentityForm);
    const [visitorName, setVisitorName] = useState('');
    const [visitorEmail, setVisitorEmail] = useState('');
    const [messageText, setMessageText] = useState('');
    const messagesRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        setMessages(initialMessages);
        setShowIdentityForm(initialShowIdentityForm);
    }, [initialMessages, initialShowIdentityForm]);

    useEffect(() => {
        if (messagesRef.current) {
            messagesRef.current.scrollTop = messagesRef.current.scrollHeight;
        }
    }, [messages, streamingContent]);

    const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            void handleSubmit();
        }
    };

    const handleSubmit = async () => {
        const message = messageText.trim();
        if (!message || isStreaming) {
            return;
        }

        setError('');
        setMessages((prev) => [...prev, { role: 'user', content: message }]);
        setMessageText('');
        setIsStreaming(true);
        setStreamingContent('');

        const payload: Record<string, string> = { message };
        if (showIdentityForm) {
            payload.name = visitorName;
            payload.email = visitorEmail;
        }

        const csrfToken =
            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        try {
            const response = await fetch(messageUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/event-stream',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const reader = response.body?.getReader();
            if (!reader) {
                throw new Error('No response stream available');
            }

            const decoder = new TextDecoder();
            let accumulated = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) {
                    break;
                }

                for (const line of decoder.decode(value, { stream: true }).split('\n')) {
                    if (!line.startsWith('data: ')) {
                        continue;
                    }
                    const jsonStr = line.slice(6).trim();
                    if (!jsonStr || jsonStr === '[DONE]') {
                        continue;
                    }
                    const event = JSON.parse(jsonStr) as {
                        type: string;
                        delta?: { text?: string };
                        message?: string;
                    };
                    if (event.type === 'content_block_delta' && event.delta?.text) {
                        accumulated += event.delta.text;
                        setStreamingContent(accumulated);
                    } else if (event.type === 'error') {
                        throw new Error(event.message ?? 'Unknown error');
                    }
                }
            }

            if (accumulated) {
                setMessages((prev) => [...prev, { role: 'assistant', content: accumulated }]);
            }
            setShowIdentityForm(false);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Unable to send message right now.');
        } finally {
            setIsStreaming(false);
            setStreamingContent('');
        }
    };

    const handleReset = () => {
        router.post(resetUrl, {});
    };

    const handleSwitch = (handle: string) => {
        router.post(switchUrl, { conversation: handle });
    };

    return (
        <>
            <Head title={bot.name} />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-8">
                <section className="border border-slate-300 bg-white p-6">
                    <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div className="flex flex-col gap-2">
                            <p className="text-sm uppercase tracking-[0.2em] text-slate-500">
                                AI Chat Bot
                            </p>
                            <h1 className="font-heading text-4xl text-slate-900">{bot.name}</h1>
                            {bot.description && (
                                <p className="max-w-3xl text-base text-slate-700">
                                    {bot.description}
                                </p>
                            )}
                        </div>
                        <button
                            type="button"
                            onClick={handleReset}
                            className="border border-slate-400 px-4 py-2 text-sm uppercase tracking-[0.12em] text-slate-700 transition hover:border-slate-900 hover:text-slate-900"
                        >
                            New Chat
                        </button>
                    </div>
                </section>

                <section className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_320px]">
                    <div className="border border-slate-300 bg-white">
                        <div className="border-b border-slate-300 px-6 py-4">
                            <h2 className="font-heading text-2xl text-slate-900">Conversation</h2>
                        </div>

                        <div
                            ref={messagesRef}
                            className="flex max-h-[60vh] flex-col gap-4 overflow-y-auto px-6 py-5"
                        >
                            {messages.length === 0 && !isStreaming ? (
                                <div className="border border-dashed border-slate-300 px-4 py-8 text-center text-slate-500">
                                    Send the first message to start the conversation.
                                </div>
                            ) : (
                                messages.map((message, index) => (
                                    <MessageBubble key={index} message={message} />
                                ))
                            )}
                        </div>

                        {isStreaming && (
                            <div className="border-t border-slate-300 bg-slate-50 px-6 py-4">
                                <p className="mb-2 text-xs uppercase tracking-[0.16em] text-slate-500">
                                    assistant
                                </p>
                                {streamingContent ? (
                                    <MarkdownContent content={streamingContent} isUser={false} />
                                ) : (
                                    <span className="text-sm text-slate-400">…</span>
                                )}
                            </div>
                        )}

                        <form
                            className="border-t border-slate-300 px-6 py-5"
                            onSubmit={(e) => {
                                e.preventDefault();
                                void handleSubmit();
                            }}
                        >
                            {showIdentityForm && (
                                <div className="mb-4 grid gap-4 md:grid-cols-2">
                                    <label className="flex flex-col gap-2 text-sm text-slate-700">
                                        <span>Name</span>
                                        <input
                                            type="text"
                                            value={visitorName}
                                            onChange={(e) => setVisitorName(e.target.value)}
                                            className="border border-slate-300 px-3 py-2 text-slate-900"
                                            required
                                        />
                                    </label>
                                    <label className="flex flex-col gap-2 text-sm text-slate-700">
                                        <span>Email</span>
                                        <input
                                            type="email"
                                            value={visitorEmail}
                                            onChange={(e) => setVisitorEmail(e.target.value)}
                                            className="border border-slate-300 px-3 py-2 text-slate-900"
                                            required
                                        />
                                    </label>
                                </div>
                            )}
                            <div className="flex flex-col gap-3">
                                <label className="flex flex-col gap-2 text-sm text-slate-700">
                                    <span>Your message</span>
                                    <textarea
                                        value={messageText}
                                        onChange={(e) => setMessageText(e.target.value)}
                                        onKeyDown={handleKeyDown}
                                        rows={5}
                                        className="border border-slate-300 px-3 py-3 text-slate-900"
                                        required
                                    />
                                </label>
                                <div className="flex items-center justify-between gap-3">
                                    {error && <p className="text-sm text-red-700">{error}</p>}
                                    <button
                                        type="submit"
                                        disabled={isStreaming}
                                        className="ml-auto border border-slate-900 bg-slate-900 px-5 py-3 text-sm uppercase tracking-[0.16em] text-white transition hover:bg-slate-700 disabled:opacity-50"
                                    >
                                        Send Message
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <aside className="flex flex-col gap-4">
                        <section className="border border-slate-300 bg-white p-5">
                            <div className="mb-3 flex items-center justify-between gap-3">
                                <h2 className="font-heading text-2xl text-slate-900">Your Chats</h2>
                                <span className="text-xs uppercase tracking-[0.16em] text-slate-500">
                                    Private to this browser
                                </span>
                            </div>
                            {history.length > 0 ? (
                                <div className="flex flex-col gap-2">
                                    {history.map((item) => (
                                        <button
                                            key={item.handle}
                                            type="button"
                                            onClick={() => handleSwitch(item.handle)}
                                            className={`flex w-full items-center justify-between gap-3 border px-3 py-3 text-left transition ${item.is_current ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-slate-50 text-slate-900 hover:border-slate-900'}`}
                                        >
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-medium">
                                                    {item.label}
                                                </span>
                                                <span
                                                    className={`block text-xs uppercase tracking-[0.14em] ${item.is_current ? 'text-slate-200' : 'text-slate-500'}`}
                                                >
                                                    {item.updated_at}
                                                </span>
                                            </span>
                                            {item.is_current && (
                                                <span className="text-[11px] uppercase tracking-[0.14em] text-slate-200">
                                                    Current
                                                </span>
                                            )}
                                        </button>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm leading-6 text-slate-700">
                                    No saved chats in this browser yet. Start a message to create a
                                    private thread.
                                </p>
                            )}
                        </section>

                        <section className="border border-slate-300 bg-white p-5">
                            <h2 className="mb-3 font-heading text-2xl text-slate-900">Access</h2>
                            <div className="flex flex-col gap-2 text-sm text-slate-700">
                                <p>{bot.is_public ? 'Public bot' : 'Restricted bot'}</p>
                                <p>
                                    {bot.require_visitor_identity
                                        ? 'Name and email are required before the first guest message.'
                                        : 'No guest identity is required by this bot.'}
                                </p>
                                <p>Only chats created in this browser are listed here.</p>
                            </div>
                        </section>

                        <section className="border border-slate-300 bg-white p-5">
                            <h2 className="mb-3 font-heading text-2xl text-slate-900">
                                Prompt Notes
                            </h2>
                            <p className="text-sm leading-6 text-slate-700">
                                The conversation is saved and can contribute new insights to AI
                                Memory for this bot.
                            </p>
                        </section>
                    </aside>
                </section>
            </div>
        </>
    );
}
