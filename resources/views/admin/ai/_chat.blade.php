@props(['chatUrl', 'conversationId', 'messages' => [], 'actions' => []])

<div data-chat-root x-data="aiChat({
    chatUrl: '{{ $chatUrl }}',
    conversationId: {{ $conversationId }},
    initialMessages: {{ \Illuminate\Support\Js::from($messages) }},
    csrfToken: '{{ csrf_token() }}'
})" class="flex flex-col h-[600px] border border-gray-200 rounded-lg overflow-hidden bg-white">

    {{-- Messages area --}}
    <div x-ref="messagesContainer" class="flex-1 overflow-y-auto p-4 space-y-4">
        <template x-for="(msg, index) in messages" :key="index">
            <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                <div :class="msg.role === 'user'
                    ? 'max-w-[80%] bg-primary text-white rounded-lg px-4 py-3'
                    : 'max-w-[80%] bg-gray-100 text-gray-800 rounded-lg px-4 py-3'">
                    <div class="text-xs font-medium mb-1 opacity-70" x-text="msg.role === 'user' ? 'You' : 'Assistant'"></div>
                    <div class="text-sm whitespace-pre-wrap prose prose-sm max-w-none"
                        :class="msg.role === 'user' ? 'prose-invert' : ''"
                        x-html="renderMarkdown(msg.content)"></div>
                </div>
            </div>
        </template>

        {{-- Streaming indicator --}}
        <div x-show="isStreaming" class="flex justify-start">
            <div class="bg-gray-100 text-gray-800 rounded-lg px-4 py-3">
                <div class="text-xs font-medium mb-1 opacity-70">Assistant</div>
                <div class="text-sm whitespace-pre-wrap prose prose-sm max-w-none" x-html="renderMarkdown(streamingContent)"></div>
                <span class="inline-block w-2 h-4 bg-gray-400 animate-pulse ml-0.5"></span>
            </div>
        </div>

        {{-- Thinking indicator --}}
        <div x-show="isThinking && !isStreaming" class="flex justify-start">
            <div class="bg-gray-100 rounded-lg px-4 py-3">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Thinking...
                </div>
            </div>
        </div>

        {{-- Error display --}}
        <div x-show="error" class="flex justify-center">
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm max-w-[80%]">
                <p class="font-medium">Error</p>
                <p x-text="error"></p>
                <button @click="error = null" class="mt-2 text-xs text-red-500 hover:underline">Dismiss</button>
            </div>
        </div>
    </div>

    {{-- Action buttons (contextual) --}}
    @php
        $actionMarkup = $actions instanceof \Illuminate\Contracts\Support\Renderable
            ? $actions->render()
            : (string) $actions;
    @endphp
    @if($actionMarkup !== '')
    <div x-show="!isThinking && !isStreaming && messages.length > 0" class="px-4 py-2 border-t border-gray-100 flex gap-2 flex-wrap">
        {!! $actionMarkup !!}
    </div>
    @endif

    {{-- Input area --}}
    <div class="border-t border-gray-200 p-4">
        <form @submit.prevent="sendMessage" class="flex gap-3 items-end">
            <div class="flex-1">
                <textarea x-model="userInput" x-ref="chatInput"
                    @keydown="handleComposerKeydown($event)"
                :disabled="isThinking || isStreaming"
                    placeholder="Type your message..."
                    rows="3"
                    class="w-full resize-y rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-gray-50 disabled:text-gray-400"></textarea>
                <p class="mt-2 text-xs text-gray-500">Enter inserts a new line. Ctrl+Enter or Cmd+Enter sends.</p>
            </div>
            <button type="submit"
                :disabled="isThinking || isStreaming || !userInput.trim()"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fa-classic fa-paper-plane"></i>
                Send
            </button>
        </form>
    </div>
</div>

<script>
function aiChat(config) {
    return {
        chatUrl: config.chatUrl,
        conversationId: config.conversationId,
        csrfToken: config.csrfToken,
        messages: config.initialMessages || [],
        userInput: '',
        isThinking: false,
        isStreaming: false,
        streamingContent: '',
        error: null,

        init() {
            this.$el.__chatState = this;
            this.broadcastMessages();
            this.$nextTick(() => this.scrollToBottom());
        },

        async sendMessage() {
            const message = this.userInput.trim();
            if (!message) return;

            this.userInput = '';
            this.error = null;

            // Add user message to display
            this.messages.push({ role: 'user', content: message });
            this.broadcastMessages();
            this.$nextTick(() => this.scrollToBottom());

            this.isThinking = true;
            this.streamingContent = '';

            try {
                const response = await fetch(this.chatUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'text/event-stream',
                    },
                    body: JSON.stringify({ message: message }),
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || `Request failed with status ${response.status}`);
                }

                this.isThinking = false;
                this.isStreaming = true;

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });

                    // Parse SSE events from the buffer
                    const lines = buffer.split('\n');
                    buffer = lines.pop(); // Keep incomplete line in buffer

                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            const data = line.substring(6);

                            if (data === '[DONE]') {
                                continue;
                            }

                            try {
                                const parsed = JSON.parse(data);

                                if (parsed.type === 'content_block_delta' && parsed.delta?.text) {
                                    this.streamingContent += parsed.delta.text;
                                    this.$nextTick(() => this.scrollToBottom());
                                } else if (parsed.type === 'error') {
                                    this.error = parsed.message || 'An error occurred';
                                } else if (parsed.type === 'message_stop') {
                                    // Stream complete
                                } else if (parsed.type === 'content' && parsed.text) {
                                    // Simple content event from our controller
                                    this.streamingContent += parsed.text;
                                    this.$nextTick(() => this.scrollToBottom());
                                }
                            } catch (e) {
                                // Non-JSON data line, ignore
                            }
                        }
                    }
                }

                // Move streamed content to messages
                if (this.streamingContent) {
                    this.messages.push({ role: 'assistant', content: this.streamingContent });
                    this.broadcastMessages();
                }
            } catch (err) {
                this.error = err.message || 'Failed to send message. Please try again.';
            } finally {
                this.isThinking = false;
                this.isStreaming = false;
                this.streamingContent = '';
                this.$nextTick(() => {
                    this.scrollToBottom();
                    this.$refs.chatInput?.focus();
                });
            }
        },

        scrollToBottom() {
            const container = this.$refs.messagesContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        broadcastMessages() {
            window.dispatchEvent(new CustomEvent('targeted-resume-chat-messages-updated', {
                detail: {
                    conversationId: this.conversationId,
                    messages: this.messages,
                },
            }));
        },

        handleComposerKeydown(event) {
            if (event.key !== 'Enter') {
                return;
            }

            if (event.metaKey || event.ctrlKey) {
                event.preventDefault();
                this.sendMessage();
            }
        },

        renderMarkdown(text) {
            if (!text) return '';
            // Basic markdown rendering: bold, italic, code, links, lists
            return text
                // Code blocks
                .replace(/```(\w*)\n([\s\S]*?)```/g, '<pre class="bg-gray-800 text-gray-100 p-3 rounded text-xs overflow-x-auto my-2"><code>$2</code></pre>')
                // Inline code
                .replace(/`([^`]+)`/g, '<code class="bg-gray-200 px-1 rounded text-xs">$1</code>')
                // Bold
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                // Italic
                .replace(/\*(.+?)\*/g, '<em>$1</em>')
                // Headers
                .replace(/^### (.+)$/gm, '<h3 class="font-bold text-base mt-3 mb-1">$1</h3>')
                .replace(/^## (.+)$/gm, '<h2 class="font-bold text-lg mt-3 mb-1">$1</h2>')
                .replace(/^# (.+)$/gm, '<h1 class="font-bold text-xl mt-3 mb-1">$1</h1>')
                // Unordered lists
                .replace(/^[-*] (.+)$/gm, '<li class="ml-4 list-disc">$1</li>')
                // Ordered lists
                .replace(/^\d+\. (.+)$/gm, '<li class="ml-4 list-decimal">$1</li>')
                // Line breaks
                .replace(/\n/g, '<br>');
        }
    };
}
</script>
