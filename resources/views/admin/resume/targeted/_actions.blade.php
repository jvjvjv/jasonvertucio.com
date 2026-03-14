<div x-data="resumeActions({ conversationId: {{ $conversation->id }}, csrfToken: '{{ csrf_token() }}', resumeFinalized: {{ $resumeFinalized ? 'true' : 'false' }}, coverLetterFinalized: {{ $coverLetterFinalized ? 'true' : 'false' }} })"
    @targeted-resume-chat-messages-updated.window="syncMessages($event.detail)">
    <button @click="finalizeResume"
        :disabled="isFinalizing || !canFinalize || resumeFinalized"
        class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        :title="resumeFinalized ? 'Resume already finalized' : (canFinalize ? 'Extract and save the tailored resume from the conversation' : 'Finalize is available after the assistant returns a tailored resume block')">
        <template x-if="isFinalizing">
            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </template>
        <template x-if="!isFinalizing">
            <i class="fa-classic fa-check"></i>
        </template>
        <span x-text="isFinalizing ? 'Saving...' : (resumeFinalized ? 'Resume Finalized' : 'Finalize Resume')"></span>
    </button>

    <button @click="finalizeCoverLetter"
        :disabled="isFinalizingCoverLetter || !canFinalizeCoverLetter || coverLetterFinalized"
        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        :title="coverLetterFinalized ? 'Cover letter already finalized' : (canFinalizeCoverLetter ? 'Extract and save the cover letter from the conversation' : 'Finalize is available after the assistant returns a cover letter block')">
        <template x-if="isFinalizingCoverLetter">
            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </template>
        <template x-if="!isFinalizingCoverLetter">
            <i class="fa-classic fa-envelope"></i>
        </template>
        <span x-text="isFinalizingCoverLetter ? 'Saving...' : (coverLetterFinalized ? 'Cover Letter Finalized' : 'Finalize Cover Letter')"></span>
    </button>

    <div x-show="finalizeError" class="text-sm text-red-600 mt-1" x-text="finalizeError"></div>
    <div x-show="finalizeCoverLetterError" class="text-sm text-red-600 mt-1" x-text="finalizeCoverLetterError"></div>
</div>

<script>
function resumeActions(config) {
    return {
        conversationId: config.conversationId,
        resumeFinalized: config.resumeFinalized || false,
        coverLetterFinalized: config.coverLetterFinalized || false,
        isFinalizing: false,
        isFinalizingCoverLetter: false,
        finalizeError: null,
        finalizeCoverLetterError: null,
        messages: [],

        init() {
            const chatState = this.$root.closest('[data-chat-root]')?.__chatState;
            this.messages = Array.isArray(chatState?.messages) ? [...chatState.messages] : [];
        },

        get canFinalize() {
            return this.messages.some((msg) => {
                return msg.role === 'assistant' && /```tailored(?:-|\s+)resume/i.test(msg.content || '');
            });
        },

        get canFinalizeCoverLetter() {
            return this.messages.some((msg) => {
                return msg.role === 'assistant' && /```cover[-\s]letter/i.test(msg.content || '');
            });
        },

        syncMessages(detail) {
            if (detail?.conversationId !== this.conversationId) {
                return;
            }

            this.messages = Array.isArray(detail.messages) ? [...detail.messages] : [];
        },

        async finalizeResume() {
            this.isFinalizing = true;
            this.finalizeError = null;

            if (!this.canFinalize) {
                this.finalizeError = 'No tailored resume found in the conversation. Please complete the tailoring process first.';
                this.isFinalizing = false;
                return;
            }

            // Find the last assistant message that contains tailored-resume data
            const messages = this.messages;
            let tailoredContent = null;
            let fitScore = null;

            // Search messages in reverse for the tailored resume block
            for (let i = messages.length - 1; i >= 0; i--) {
                const msg = messages[i];
                if (msg.role !== 'assistant') continue;

                // Look for tailored resume code block
                const contentMatch = msg.content.match(/```tailored(?:-|\s+)resume\s*\n([\s\S]*?)```/i);
                if (contentMatch) {
                    tailoredContent = contentMatch[1].trim();
                }

                // Look for fit score
                const scoreMatch = msg.content.match(/(?:fit score|score)[:\s]*(\d{1,3})(?:\s*[\/%]|\s*out of\s*100)?/i);
                if (scoreMatch && !fitScore) {
                    fitScore = parseInt(scoreMatch[1]);
                    if (fitScore > 100) fitScore = null;
                }

                if (tailoredContent) break;
            }

            if (!tailoredContent) {
                this.finalizeError = 'No tailored resume found in the conversation. Please complete the tailoring process first.';
                this.isFinalizing = false;
                return;
            }

            try {
                const response = await fetch(`/admin/resume/targeted-builder/${config.conversationId}/finalize`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        tailored_content: tailoredContent,
                        fit_score: fitScore,
                    }),
                });

                const data = await response.json();

                if (!response.ok) {
                    this.finalizeError = data.message || 'Failed to save targeted resume.';
                    return;
                }

                // Reload the page to show the finalized state
                window.location.reload();
            } catch (err) {
                this.finalizeError = 'Network error. Please try again.';
            } finally {
                this.isFinalizing = false;
            }
        },

        async finalizeCoverLetter() {
            this.isFinalizingCoverLetter = true;
            this.finalizeCoverLetterError = null;

            if (!this.canFinalizeCoverLetter) {
                this.finalizeCoverLetterError = 'No cover letter found in the conversation. Ask the assistant to write one first.';
                this.isFinalizingCoverLetter = false;
                return;
            }

            // Find the last assistant message that contains a cover-letter block
            let coverLetterContent = null;

            for (let i = this.messages.length - 1; i >= 0; i--) {
                const msg = this.messages[i];
                if (msg.role !== 'assistant') continue;

                const contentMatch = msg.content.match(/```cover[-\s]letter\s*\n([\s\S]*?)```/i);
                if (contentMatch) {
                    coverLetterContent = contentMatch[1].trim();
                    break;
                }
            }

            if (!coverLetterContent) {
                this.finalizeCoverLetterError = 'No cover letter found in the conversation. Ask the assistant to write one first.';
                this.isFinalizingCoverLetter = false;
                return;
            }

            try {
                const response = await fetch(`/admin/resume/targeted-builder/${config.conversationId}/finalize-cover-letter`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        cover_letter_content: coverLetterContent,
                    }),
                });

                const data = await response.json();

                if (!response.ok) {
                    this.finalizeCoverLetterError = data.message || 'Failed to save cover letter.';
                    return;
                }

                window.location.reload();
            } catch (err) {
                this.finalizeCoverLetterError = 'Network error. Please try again.';
            } finally {
                this.isFinalizingCoverLetter = false;
            }
        }
    };
}
</script>
