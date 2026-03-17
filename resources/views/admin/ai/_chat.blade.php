@props(["chatUrl", "conversationId", "messages" => [], "actions" => [], "autoStart" => false])

<div data-chat-root
     x-data="aiChat({
         chatUrl: '{{ $chatUrl }}',
         conversationId: {{ $conversationId }},
         initialMessages: {{ \Illuminate\Support\Js::from($messages) }},
         csrfToken: '{{ csrf_token() }}',
         autoStart: {{ $autoStart ? "true" : "false" }}
     })"
     class="flex h-full min-h-[calc(100vh-18rem)] flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white">

    {{-- Messages area --}}
    <div x-ref="messagesContainer" class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-5 sm:px-6">
        <template x-for="(msg, index) in messages" :key="index">
            <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                <div
                     :class="msg.role === 'user' ?
                         'max-w-[85%] rounded-2xl bg-primary px-4 py-3 text-white lg:max-w-[78%]' :
                         'max-w-[85%] rounded-2xl bg-gray-100 px-4 py-3 text-gray-800 lg:max-w-[78%]'">
                    <div class="mb-1 text-xs font-medium opacity-70" x-text="msg.role === 'user' ? 'You' : 'Assistant'">
                    </div>
                    <div class="prose max-w-none text-sm"
                         :class="msg.role === 'user' ? 'prose-invert' : ''"
                         x-html="renderMarkdown(msg.content)"></div>
                </div>
            </div>
        </template>

        {{-- Streaming indicator --}}
        <div x-show="isStreaming" class="flex justify-start">
            <div class="rounded-lg bg-gray-100 px-4 py-3 text-gray-800">
                <div class="mb-1 text-xs font-medium opacity-70">Assistant</div>
                <div class="prose max-w-none text-sm"
                     x-html="renderMarkdown(streamingContent)"></div>
                <span class="ml-0.5 inline-block h-4 w-2 animate-pulse bg-gray-400"></span>
            </div>
        </div>

        {{-- Thinking indicator --}}
        <div x-show="isThinking && !isStreaming" class="flex justify-start">
            <div class="rounded-lg bg-gray-100 px-4 py-3">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <svg class="h-4 w-4 animate-spin"
                         xmlns="http://www.w3.org/2000/svg"
                         fill="none"
                         viewBox="0 0 24 24">
                        <circle class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"></circle>
                        <path class="opacity-75"
                              fill="currentColor"
                              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Thinking...
                </div>
            </div>
        </div>

        {{-- Error display --}}
        <div x-show="error" class="flex justify-center">
            <div class="max-w-[80%] rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-medium">Error</p>
                <p x-text="error"></p>
                <button class="mt-2 text-xs text-red-500 hover:underline" @click="error = null">Dismiss</button>
            </div>
        </div>
    </div>

    {{-- Action buttons (contextual) --}}
    @php
        $actionMarkup =
            $actions instanceof \Illuminate\Contracts\Support\Renderable ? $actions->render() : (string) $actions;
    @endphp
    @if ($actionMarkup !== "")
        <div x-show="!isThinking && !isStreaming && messages.length > 0"
             class="shrink-0 border-t border-gray-100 px-4 py-3 sm:px-6">
            <div class="flex flex-wrap gap-2">
            {!! $actionMarkup !!}
            </div>
        </div>
    @endif

    {{-- Input area --}}
    <div class="shrink-0 border-t border-gray-200 bg-white px-4 py-4 sm:px-6">
        <form @submit.prevent="sendMessage" class="flex items-end gap-3">
            <div class="flex-1">
                <textarea x-model="userInput"
                          x-ref="chatInput"
                          @keydown="handleComposerKeydown($event)"
                          :disabled="isThinking || isStreaming"
                          placeholder="Type your message..."
                          rows="2"
                          class="min-h-14 w-full max-h-40 resize-y rounded-xl border border-gray-300 px-4 py-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-gray-50 disabled:text-gray-400"></textarea>
            </div>
            <button type="submit"
                    :disabled="isThinking || isStreaming || !userInput.trim()"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-medium text-white transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50">
                <i class="fa-classic fa-paper-plane"></i>
                Send
            </button>
        </form>
        <p class="mt-2 text-xs text-gray-500">Enter inserts a new line. Ctrl+Enter or Cmd+Enter sends.</p>
    </div>
</div>

<script>
    function aiChat(config) {
        return {
            chatUrl: config.chatUrl,
            conversationId: config.conversationId,
            csrfToken: config.csrfToken,
            messages: config.initialMessages || [],
            autoStart: config.autoStart || false,
            hasAutoStarted: false,
            userInput: '',
            isThinking: false,
            isStreaming: false,
            streamingContent: '',
            error: null,

            init() {
                this.$el.__chatState = this;
                this.broadcastMessages();
                this.$nextTick(() => {
                    this.scrollToBottom();
                    this.scrollPageToBottom();
                    if (this.autoStart && !this.hasAutoStarted) {
                        this.hasAutoStarted = true;
                        this.sendInitialConversation();
                    }
                });
            },

            async sendInitialConversation() {
                this.error = null;
                this.isThinking = true;
                this.streamingContent = '';

                try {
                    await this.streamResponse(null);
                } catch (err) {
                    this.error = err.message || 'Failed to start analysis. Please try again.';
                } finally {
                    this.isThinking = false;
                    this.isStreaming = false;
                    this.streamingContent = '';
                    this.$nextTick(() => {
                        this.scrollToBottom();
                        this.scrollPageToBottom();
                    });
                }
            },

            async sendMessage() {
                const message = this.userInput.trim();
                if (!message) return;

                this.userInput = '';
                this.error = null;

                // Add user message to display
                this.messages.push({
                    role: 'user',
                    content: message
                });
                this.broadcastMessages();
                this.$nextTick(() => this.scrollToBottom());

                this.isThinking = true;
                this.streamingContent = '';

                try {
                    await this.streamResponse(message);
                } catch (err) {
                    this.error = err.message || 'Failed to send message. Please try again.';
                } finally {
                    this.isThinking = false;
                    this.isStreaming = false;
                    this.streamingContent = '';
                    this.$nextTick(() => {
                        this.scrollToBottom();
                        this.scrollPageToBottom();
                        this.$refs.chatInput?.focus();
                    });
                }
            },

            async streamResponse(message) {
                const payload = message === null ? {} : {
                    message: message
                };

                const response = await fetch(this.chatUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'text/event-stream',
                    },
                    body: JSON.stringify(payload),
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
                    const {
                        done,
                        value
                    } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, {
                        stream: true
                    });
                    const lines = buffer.split('\n');
                    buffer = lines.pop();

                    for (const line of lines) {
                        if (!line.startsWith('data: ')) {
                            continue;
                        }

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
                            } else if (parsed.type === 'content' && parsed.text) {
                                this.streamingContent += parsed.text;
                                this.$nextTick(() => this.scrollToBottom());
                            }
                        } catch (e) {
                            // Ignore malformed SSE payloads.
                        }
                    }
                }

                if (this.streamingContent) {
                    this.messages.push({
                        role: 'assistant',
                        content: this.streamingContent
                    });
                    this.broadcastMessages();
                }
            },

            scrollToBottom() {
                const container = this.$refs.messagesContainer;
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            },

            scrollPageToBottom() {
                requestAnimationFrame(() => {
                    const chatBounds = this.$el.getBoundingClientRect();
                    const targetTop = window.scrollY + chatBounds.bottom - window.innerHeight + 24;

                    if (targetTop > 0) {
                        window.scrollTo({
                            top: targetTop,
                            behavior: 'auto',
                        });
                    }
                });
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

                return text
                    // Tailored-resume and cover-letter code blocks — boxed with dark border
                    .replace(/```(?:tailored[-\s]resume|cover[-\s]letter)\s*\n([\s\S]*?)```/gi, (match, content) => {
                        // Render inner markdown for the resume/cover-letter content
                        const inner = content
                            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                            .replace(/\*(.+?)\*/g, '<em>$1</em>')
                            .replace(/^### (.+)$/gm, '<h3 class="font-bold text-base mt-3 mb-1">$1</h3>')
                            .replace(/^## (.+)$/gm, '<h2 class="font-bold text-lg mt-3 mb-1">$1</h2>')
                            .replace(/^# (.+)$/gm, '<h1 class="font-bold text-xl mt-3 mb-1">$1</h1>')
                            .replace(/^---$/gm, '<hr class="my-3 border-gray-300">')
                            .replace(/^[-*] (.+)$/gm, '<li class="ml-4 list-disc">$1</li>')
                            .replace(/^\d+\. (.+)$/gm, '<li class="ml-4 list-decimal">$1</li>')
                            .replace(/\n\n/g, '</p><p class="mt-2">')
                            .replace(/\n/g, '<br>');
                        return '<div class="my-3 rounded-lg border-2 border-dark p-4 bg-white text-sm"><p>' + inner + '</p></div>';
                    })
                    // Generic code blocks
                    .replace(/```(\w*)\n([\s\S]*?)```/g,
                        '<pre class="bg-gray-800 text-gray-100 p-3 rounded text-xs overflow-x-auto my-2"><code>$2</code></pre>'
                    )
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
                    // Horizontal rules (--- on its own line)
                    .replace(/^---$/gm, '<hr class="my-3 border-gray-300">')
                    // Unordered lists
                    .replace(/^[-*] (.+)$/gm, '<li class="ml-4 list-disc">$1</li>')
                    // Ordered lists
                    .replace(/^\d+\. (.+)$/gm, '<li class="ml-4 list-decimal">$1</li>')
                    // Double line breaks become paragraph breaks
                    .replace(/\n\n/g, '</p><p class="mt-2">')
                    // Single line breaks
                    .replace(/\n/g, '<br>')
                    // Wrap in paragraph
                    .replace(/^(.*)$/, '<p>$1</p>');
            }
        };
    }
</script>
