@extends('layout')

@section('title', $bot->name)

@section('main')
<div class="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-8">
    <section class="border border-slate-300 bg-white p-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div class="flex flex-col gap-2">
                <p class="text-sm uppercase tracking-[0.2em] text-slate-500">AI Chat Bot</p>
                <h1 class="font-heading text-4xl text-slate-900">{{ $bot->name }}</h1>
                @if ($bot->description)
                    <p class="max-w-3xl text-base text-slate-700">{{ $bot->description }}</p>
                @endif
            </div>
            <form method="POST" action="{{ $resetUrl }}">
                @csrf
                <button type="submit" class="border border-slate-400 px-4 py-2 text-sm uppercase tracking-[0.12em] text-slate-700 transition hover:border-slate-900 hover:text-slate-900">
                    New Chat
                </button>
            </form>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_320px]">
        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-300 px-6 py-4">
                <h2 class="font-heading text-2xl text-slate-900">Conversation</h2>
            </div>
            <div id="chat-messages" class="flex max-h-[60vh] flex-col gap-4 overflow-y-auto px-6 py-5">
                @forelse ($messages as $message)
                    <article class="border px-4 py-3 {{ $message['role'] === 'user' ? 'border-slate-800 bg-slate-800 text-white' : 'border-slate-300 bg-slate-50 text-slate-900' }}">
                        <p class="mb-2 text-xs uppercase tracking-[0.16em] {{ $message['role'] === 'user' ? 'text-slate-200' : 'text-slate-500' }}">{{ $message['role'] }}</p>
                        <div class="whitespace-pre-wrap text-sm leading-6">{{ $message['content'] }}</div>
                    </article>
                @empty
                    <div class="border border-dashed border-slate-300 px-4 py-8 text-center text-slate-500">
                        Send the first message to start the conversation.
                    </div>
                @endforelse
            </div>
            <div id="streaming-message" class="hidden border-t border-slate-300 bg-slate-50 px-6 py-4">
                <p class="mb-2 text-xs uppercase tracking-[0.16em] text-slate-500">assistant</p>
                <div id="streaming-message-content" class="whitespace-pre-wrap text-sm leading-6 text-slate-900"></div>
            </div>
            <form id="chat-form" class="border-t border-slate-300 px-6 py-5">
                @csrf
                @guest
                    @if ($bot->require_visitor_identity && !$conversation)
                        <div class="mb-4 grid gap-4 md:grid-cols-2">
                            <label class="flex flex-col gap-2 text-sm text-slate-700">
                                <span>Name</span>
                                <input id="visitor-name" name="name" type="text" class="border border-slate-300 px-3 py-2 text-slate-900" required>
                            </label>
                            <label class="flex flex-col gap-2 text-sm text-slate-700">
                                <span>Email</span>
                                <input id="visitor-email" name="email" type="email" class="border border-slate-300 px-3 py-2 text-slate-900" required>
                            </label>
                        </div>
                    @endif
                @endguest

                <div class="flex flex-col gap-3">
                    <label class="flex flex-col gap-2 text-sm text-slate-700">
                        <span>Your message</span>
                        <textarea id="chat-message" name="message" rows="5" class="border border-slate-300 px-3 py-3 text-slate-900" required></textarea>
                    </label>
                    <div class="flex items-center justify-between gap-3">
                        <p id="chat-error" class="text-sm text-red-700"></p>
                        <button id="chat-submit" type="submit" class="border border-slate-900 bg-slate-900 px-5 py-3 text-sm uppercase tracking-[0.16em] text-white transition hover:bg-slate-700">
                            Send Message
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <aside class="flex flex-col gap-4">
            <section class="border border-slate-300 bg-white p-5">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="font-heading text-2xl text-slate-900">Your Chats</h2>
                    <span class="text-xs uppercase tracking-[0.16em] text-slate-500">Private to this browser</span>
                </div>

                @if (count($history) > 0)
                    <div class="flex flex-col gap-2">
                        @foreach ($history as $historyItem)
                            <form method="POST" action="{{ $switchUrl }}">
                                @csrf
                                <input type="hidden" name="conversation" value="{{ $historyItem['handle'] }}">
                                <button type="submit" class="flex w-full items-center justify-between gap-3 border px-3 py-3 text-left transition {{ $historyItem['is_current'] ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-slate-50 text-slate-900 hover:border-slate-900' }}">
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium">{{ $historyItem['label'] }}</span>
                                        <span class="block text-xs uppercase tracking-[0.14em] {{ $historyItem['is_current'] ? 'text-slate-200' : 'text-slate-500' }}">{{ $historyItem['updated_at'] }}</span>
                                    </span>
                                    @if ($historyItem['is_current'])
                                        <span class="text-[11px] uppercase tracking-[0.14em] text-slate-200">Current</span>
                                    @endif
                                </button>
                            </form>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm leading-6 text-slate-700">No saved chats in this browser yet. Start a message to create a private thread.</p>
                @endif
            </section>

            <section class="border border-slate-300 bg-white p-5">
                <h2 class="mb-3 font-heading text-2xl text-slate-900">Access</h2>
                <div class="flex flex-col gap-2 text-sm text-slate-700">
                    <p>{{ $bot->is_public ? 'Public bot' : 'Restricted bot' }}</p>
                    <p>{{ $bot->require_visitor_identity ? 'Name and email are required before the first guest message.' : 'No guest identity is required by this bot.' }}</p>
                    <p>Only chats created in this browser are listed here.</p>
                </div>
            </section>

            <section class="border border-slate-300 bg-white p-5">
                <h2 class="mb-3 font-heading text-2xl text-slate-900">Prompt Notes</h2>
                <p class="text-sm leading-6 text-slate-700">The conversation is saved and can contribute new insights to AI Memory for this bot.</p>
            </section>
        </aside>
    </section>
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const form = document.getElementById('chat-form');
        const textarea = document.getElementById('chat-message');
        const submitButton = document.getElementById('chat-submit');
        const errorNode = document.getElementById('chat-error');
        const messagesNode = document.getElementById('chat-messages');
        const streamingNode = document.getElementById('streaming-message');
        const streamingContentNode = document.getElementById('streaming-message-content');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        const appendMessage = (role, content) => {
            const article = document.createElement('article');
            article.className = role === 'user'
                ? 'border border-slate-800 bg-slate-800 px-4 py-3 text-white'
                : 'border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900';

            const label = document.createElement('p');
            label.className = role === 'user'
                ? 'mb-2 text-xs uppercase tracking-[0.16em] text-slate-200'
                : 'mb-2 text-xs uppercase tracking-[0.16em] text-slate-500';
            label.textContent = role;

            const body = document.createElement('div');
            body.className = 'whitespace-pre-wrap text-sm leading-6';
            body.textContent = content;

            article.appendChild(label);
            article.appendChild(body);
            messagesNode.appendChild(article);
            messagesNode.scrollTop = messagesNode.scrollHeight;
        };

        textarea?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
                event.preventDefault();
                form?.dispatchEvent(new SubmitEvent('submit', { bubbles: true, cancelable: true }));
            }
        });

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            errorNode.textContent = '';

            const message = textarea.value.trim();
            if (!message) {
                return;
            }

            appendMessage('user', message);
            textarea.value = '';
            submitButton.setAttribute('disabled', 'disabled');
            streamingNode.classList.remove('hidden');
            streamingContentNode.textContent = '';

            const payload = {
                message,
                name: document.getElementById('visitor-name')?.value ?? '',
                email: document.getElementById('visitor-email')?.value ?? '',
            };

            try {
                const response = await fetch(@json($messageUrl), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'text/event-stream',
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

                    const chunk = decoder.decode(value, { stream: true });
                    const lines = chunk.split('\n');

                    for (const line of lines) {
                        if (!line.startsWith('data: ')) {
                            continue;
                        }

                        const jsonStr = line.slice(6).trim();
                        if (!jsonStr || jsonStr === '[DONE]') {
                            continue;
                        }

                        const eventPayload = JSON.parse(jsonStr);
                        if (eventPayload.type === 'content_block_delta' && eventPayload.delta?.text) {
                            accumulated += eventPayload.delta.text;
                            streamingContentNode.textContent = accumulated;
                        } else if (eventPayload.type === 'error') {
                            throw new Error(eventPayload.message || 'Unknown error');
                        }
                    }
                }

                if (accumulated) {
                    appendMessage('assistant', accumulated);
                }

                streamingNode.classList.add('hidden');
                streamingContentNode.textContent = '';
            } catch (error) {
                errorNode.textContent = error.message || 'Unable to send message right now.';
                streamingNode.classList.add('hidden');
                streamingContentNode.textContent = '';
            } finally {
                submitButton.removeAttribute('disabled');
            }
        });
    })();
</script>
@endpush
