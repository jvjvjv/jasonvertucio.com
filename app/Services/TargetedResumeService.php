<?php

namespace App\Services;

use App\Concerns\ExecutesAiTools;
use App\Contracts\ResumeDataServiceContract;
use App\Enums\AiConversationStatus;
use App\Enums\AiInteractionStatus;
use App\Enums\TargetedResumeStatus;
use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use App\Models\AiInteractionLog;
use App\Models\AiSystem;
use App\Models\CoverLetter;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Services\Mcp\TargetedResumeToolRegistry;
use Generator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class TargetedResumeService
{
    use ExecutesAiTools;
    public function __construct(
        private AiClientFactory $clientFactory,
        private ResumeDataServiceContract $resumeDataService,
        private \App\Services\TargetedResumeDocumentService $documentService,
        private CoverLetterDocumentService $coverLetterDocumentService,
        private AiMemoryService $memoryService,
        private ConversationUsageService $conversationUsageService,
    ) {
    }

    /**
     * Start a new targeted resume conversation.
     */
    public function startConversation(
        AiSystem $system,
        string $jobDescription,
        ResumeVersion $resumeVersion,
        ?string $jobTitle = null,
        ?string $companyName = null,
        ?string $jobLocation = null,
        ?string $jobUrlId = null,
    ): AiConversation {
        $conversation = AiConversation::create([
            'user_id' => auth()->id(),
            'ai_system_id' => $system->id,
            'feature' => 'targeted-resume',
            'title' => $jobTitle ? "Targeted Resume: {$jobTitle}" : 'Targeted Resume',
            'status' => AiConversationStatus::Active,
            'context' => [
                'job_title' => $jobTitle,
                'job_description' => $jobDescription,
                'resume_version_id' => $resumeVersion->id,
                'job_url_id' => $jobUrlId,
                'step' => 'analysis',
                'auto_start_pending' => true,
            ],
        ]);

        // Store the system prompt as the first message
        $systemPrompt = $this->buildSystemPrompt();
        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'system',
            'content' => $systemPrompt,
        ]);

        // Store the initial user message that should be sent immediately on first load.
        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $this->buildInitialAnalysisMessage($jobDescription, $jobTitle, $jobLocation, $companyName),
        ]);

        return $conversation;
    }

    /**
     * Continue a conversation by streaming a response.
     *
     * @return Generator<int, string> Yields SSE-formatted data lines
     */
    public function continueConversation(AiConversation $conversation, ?string $userMessage = null): Generator
    {
        $conversation->load('aiSystem');

        if ($userMessage === null) {
            $context = $conversation->context ?? [];
            if (($context['auto_start_pending'] ?? false) === true) {
                $context['auto_start_pending'] = false;
                $conversation->update(['context' => $context]);
            }
        }

        if ($userMessage !== null) {
            AiConversationMessage::create([
                'ai_conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => $userMessage,
            ]);
        }

        // Build messages array for the API (exclude system messages from the messages array)
        $allMessages = $conversation->messages()->orderBy('created_at')->get();
        $systemPrompt = null;
        $apiMessages = [];

        foreach ($allMessages as $msg) {
            if ($msg->role === 'system') {
                $systemPrompt = $msg->content;
            } else {
                $apiMessages[] = [
                    'role' => $msg->role,
                    'content' => $msg->content,
                ];
            }
        }

        $client = $this->clientFactory->forSystem($conversation->aiSystem);

        if ($systemPrompt) {
            $client->withSystem($systemPrompt);
        }

        $client->withMaxTokens($conversation->aiSystem->max_tokens);

        $toolRegistry = new TargetedResumeToolRegistry(
            $conversation,
            $this->resumeDataService,
            $this->memoryService,
            $this,
        );

        $startTime = microtime(true);
        $loopResult = null;

        try {
            yield from $this->runToolLoop($client, $apiMessages, $toolRegistry, maxIterations: 10, result: $loopResult);

            yield "data: [DONE]\n\n";

            $fullResponse = $loopResult['text'] ?? '';
            $inputTokens = $loopResult['inputTokens'] ?? null;
            $outputTokens = $loopResult['outputTokens'] ?? null;

            $pricingSnapshot = $this->conversationUsageService->pricingSnapshotForSystem(
                $conversation->aiSystem,
                $conversation->aiSystem->model,
            );

            if ($fullResponse !== '') {
                AiConversationMessage::create([
                    'ai_conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $fullResponse,
                    'metadata' => [
                        'input_tokens' => $inputTokens,
                        'output_tokens' => $outputTokens,
                        'model' => $conversation->aiSystem->model,
                    ],
                ]);

                $this->syncConversationMetadataFromAssistantResponse($conversation, $fullResponse);
            }

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            AiInteractionLog::create([
                'ai_system_id' => $conversation->aiSystem->id,
                'ai_conversation_id' => $conversation->id,
                'user_id' => auth()->id(),
                'feature' => 'targeted-resume',
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'model' => $conversation->aiSystem->model,
                'input_token_price_snapshot' => $pricingSnapshot['input_token_price_snapshot'],
                'output_token_price_snapshot' => $pricingSnapshot['output_token_price_snapshot'],
                'duration_ms' => $durationMs,
                'status' => AiInteractionStatus::Success,
            ]);

            $this->conversationUsageService->syncConversation($conversation->fresh());
        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            AiInteractionLog::create([
                'ai_system_id' => $conversation->aiSystem->id,
                'ai_conversation_id' => $conversation->id,
                'user_id' => auth()->id(),
                'feature' => 'targeted-resume',
                'model' => $conversation->aiSystem->model,
                'duration_ms' => $durationMs,
                'status' => AiInteractionStatus::Error,
                'error_message' => $e->getMessage(),
            ]);

            yield "data: " . json_encode(['type' => 'error', 'message' => $e->getMessage()]) . "\n\n";
        }
    }

    /**
     * Save a finalized targeted resume from conversation context.
     */
    public function saveTailoredResume(AiConversation $conversation, string $tailoredContent, ?int $fitScore = null): TargetedResume
    {
        $context = $conversation->context ?? [];
        $parsedResume = $this->parseTailoredResumeContent($tailoredContent);
        $existingTargetedResume = $conversation->targetedResume;

        $targetedResume = TargetedResume::updateOrCreate(
            ['ai_conversation_id' => $conversation->id],
            [
                'resume_version_id' => $context['resume_version_id'],
                'job_url_id' => $context['job_url_id'] ?? null,
                'company_name' => $context['company_name'] ?? 'Unknown Company',
                'position' => $context['job_title'] ?? 'Unknown Position',
                'title' => $parsedResume['title'] ?? ($context['job_title'] ?? null),
                'job_description' => $context['job_description'] ?? '',
                'tailored_data' => [
                    'title' => $parsedResume['title'],
                    'content' => $parsedResume['markdown'],
                    'format' => 'markdown',
                    'markdown' => $parsedResume['markdown'],
                ],
                'fit_score' => $fitScore ?? $context['fit_score'] ?? null,
                'fit_summary' => $context['fit_summary'] ?? null,
                'status' => ($existingTargetedResume?->status !== null && $existingTargetedResume->status !== TargetedResumeStatus::Draft)
                    ? $existingTargetedResume->status
                    : TargetedResumeStatus::Finalized,
            ]
        );

        $docxResult = $this->documentService->generateDocx($targetedResume);

        if (!$docxResult['success']) {
            throw new \RuntimeException($docxResult['error'] ?? 'Failed to generate the targeted resume DOCX.');
        }

        $pdfResult = $this->documentService->generatePdf($targetedResume);

        if (!$pdfResult['success']) {
            throw new \RuntimeException($pdfResult['error'] ?? 'Failed to generate the targeted resume PDF.');
        }

        $conversation->update(['status' => AiConversationStatus::Completed]);


        return $targetedResume->fresh();
    }

    /**
     * @return array{title: ?string, markdown: string}
     */
    protected function parseTailoredResumeContent(string $tailoredContent): array {
        $normalizedContent = str_replace("\r\n", "\n", trim($tailoredContent));
        $title = null;

        if (preg_match('/\ATitle:\s*(.+)\n+/i', $normalizedContent, $matches) === 1) {
            $title = $this->normalizeTailoredResumeTitle($matches[1]);
            $normalizedContent = preg_replace('/\ATitle:\s*.+\n+/i', '', $normalizedContent, 1) ?? $normalizedContent;
            $normalizedContent = ltrim($normalizedContent);
        }

        if ($title === null) {
            $title = $this->extractTitleFromSummary($normalizedContent);
        }

        return [
            'title' => $title,
            'markdown' => trim($normalizedContent),
        ];
    }

    protected function extractTitleFromSummary(string $tailoredContent): ?string {
        $parts = preg_split('/^#\s+Summary\s*$/mi', $tailoredContent, 2);

        if (!is_array($parts) || count($parts) < 2) {
            return null;
        }

        $summarySection = ltrim($parts[1]);
        $nextSection = preg_split('/^#\s+/m', $summarySection, 2);
        $summaryLines = preg_split('/\n+/', trim($nextSection[0] ?? '')) ?: [];

        foreach ($summaryLines as $summaryLine) {
            $summary = trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(['**', '*', '_', '`'], '', $summaryLine))) ?? '');

            if ($summary === '' || str_starts_with($summary, '#') || str_starts_with($summary, '-')) {
                continue;
            }

            $delimiters = [
                ' with ',
                ' specializing in ',
                ' specialised in ',
                ' focused on ',
                ' experienced in ',
                ' bringing ',
                ' known for ',
                ',',
                '.',
            ];

            foreach ($delimiters as $delimiter) {
                $position = stripos($summary, $delimiter);

                if ($position !== false) {
                    return $this->normalizeTailoredResumeTitle(substr($summary, 0, $position));
                }
            }

            return $this->normalizeTailoredResumeTitle($summary);
        }

        return null;
    }

    protected function normalizeTailoredResumeTitle(?string $title): ?string {
        $normalizedTitle = trim((string) $title, " \t\n\r\0\x0B-:,");

        return $normalizedTitle !== '' ? $normalizedTitle : null;
    }

    /**
     * Save a cover letter generated from the conversation.
     */
    public function saveCoverLetter(AiConversation $conversation, string $coverLetterContent): CoverLetter
    {
        $targetedResume = $conversation->targetedResume;

        if (!$targetedResume) {
            throw new \RuntimeException('Please finalize the targeted resume before creating a cover letter.');
        }

        $context = $conversation->context ?? [];
        $resumeVersion = ResumeVersion::find($context['resume_version_id'] ?? null);
        $personalInfo = $resumeVersion?->personalInfo;

        $parsed = $this->parseCoverLetterContent($coverLetterContent);

        $coverLetter = CoverLetter::updateOrCreate(
            ['targeted_resume_id' => $targetedResume->id],
            [
                'resume_version_id' => $targetedResume->resume_version_id,
                'company_name' => $context['company_name'] ?? $targetedResume->company_name ?? 'Unknown Company',
                'position' => $context['job_title'] ?? $targetedResume->position ?? 'Unknown Position',
                'date' => now(),
                'greeting' => $parsed['greeting'],
                'message_body' => $parsed['message_body'],
                'closing' => $parsed['closing'],
                'signature' => $personalInfo?->name,
            ]
        );

        $docxResult = $this->coverLetterDocumentService->generateDocx($coverLetter);
        if (!$docxResult['success']) {
            throw new \RuntimeException($docxResult['error'] ?? 'Failed to generate the cover letter DOCX.');
        }

        $pdfResult = $this->coverLetterDocumentService->generatePdf($coverLetter);
        if (!$pdfResult['success']) {
            throw new \RuntimeException($pdfResult['error'] ?? 'Failed to generate the cover letter PDF.');
        }

        return $coverLetter->fresh();
    }

    /**
     * Parse cover letter content into greeting, body, and closing.
     * Detects if AI returned raw OOXML (wrapped in ```cover-letter code block) or plain text.
     *
     * @return array{greeting: string, message_body: string, closing: string}
     */
    private function parseCoverLetterContent(string $content): array
    {
        // Check if AI returned raw OOXML wrapped in a markdown code block
        $codeBlockMatch = null;
        if (preg_match('/```cover[-\s]?letter\s*\n([\s\S]*?)```/i', $content, $codeBlockMatch)) {
            // Extract the content inside the code block - this is raw OOXML text
            $rawContent = trim($codeBlockMatch[1]);

            // Try to extract greeting from beginning of OOXML (it's plain text)
            $greeting = 'Dear Hiring Manager,';
            $closing = 'Sincerely,';

            if (preg_match('/^(<w:p[^>]*><w:r[^>]*><w:t[^>]*>)?([^<]+)<\/w:t>(<\/w:r><\/w:p>)?/s', $rawContent, $greetingMatch)) {
                $extractedGreeting = trim($greetingMatch[2]);
                if (preg_match('/^(Dear\b|To Whom|Hello|Hi\b|Greetings)/i', $extractedGreeting)) {
                    $greeting = $extractedGreeting;
                }
            }

            // Try to extract closing from end of OOXML
            if (preg_match('/(<w:p[^>]*><w:r[^>]*><w:t[^>]*>)?([^<]+)<\/w:t>(<\/w:r><\/w:p>)?\s*$/', $rawContent, $closingMatch)) {
                $extractedClosing = trim($closingMatch[2]);
                if (preg_match('/^(Sincerely|Best regards|Kind regards|Regards|Warm regards|Respectfully|Thank you|With appreciation)/i', $extractedClosing)) {
                    $closing = $extractedClosing;
                }
            }

            // Extract the message body (OOXML paragraphs between greeting and closing)
            $messageBody = trim($rawContent);

            return [
                'greeting' => $greeting,
                'message_body' => $messageBody,
                'closing' => $closing,
            ];
        }

        // Fallback: treat as plain text cover letter
        $lines = preg_split('/\r?\n/', trim($content));

        $greeting = 'Dear Hiring Manager,';
        $closing = 'Sincerely,';

        // Check first line for a greeting
        if (!empty($lines) && preg_match('/^(Dear\b|To Whom|Hello|Hi\b|Greetings)/i', $lines[0])) {
            $greeting = trim(array_shift($lines));
        }

        // Check last non-empty line for a closing
        $lastIndex = count($lines) - 1;
        while ($lastIndex >= 0 && trim($lines[$lastIndex]) === '') {
            $lastIndex--;
        }

        if ($lastIndex >= 0 && preg_match('/^(Sincerely|Best regards|Kind regards|Regards|Warm regards|Respectfully|Thank you|With appreciation)/i', trim($lines[$lastIndex]))) {
            $closing = trim($lines[$lastIndex]);
            array_splice($lines, $lastIndex);
        }

        $messageBody = trim(implode("\n", $lines));

        return [
            'greeting' => $greeting,
            'message_body' => $messageBody,
            'closing' => $closing,
        ];
    }

    /**
     * Build the system prompt. Resume data and memories are fetched on-demand via tools.
     */
    public function buildSystemPrompt(): string
    {
        return <<<PROMPT
# Targeted Resume & Cover Letter

You are an expert career advisor, resume tailoring specialist, and cover letter ghostwriter for Jason Vertucio. You help candidates optimize their resumes for specific job postings and produce cover letters that sound like Jay wrote them himself — not like an AI filled in a template.

## Tools Available

Use these tools to load data and take actions at the appropriate steps:

- `get_resume_data` — Call this first to load the candidate's full resume data (experience, skills, salary history, education, projects).
- `get_job_description` — Call this to access the full job posting text and any known title or company name.
- `get_resume_memories` — Call this to load learned preferences and insights from previous sessions.
- `update_fit_assessment` — Call this after Step 4 to persist the fit score, fit summary, company name, and job title. Do NOT write "Fit Score: N" in your text response; use this tool instead so the data is saved.
- `save_tailored_resume` — Call this when the user approves the tailored resume. It generates DOCX and PDF automatically.
- `save_cover_letter` — Call this when the user approves the cover letter. It generates DOCX and PDF automatically.
- `update_status` - Call this when the user reports a status change in their job application (e.g. "I applied", "I have an interview on June 12th", "I got rejected"). Accepts status (applied, interviewing, interviewed, offered, accepted, hired, rejected), an optional date, and optional notes. For `interviewing`, the date should be the scheduled interview date.

## Your Role

You will be given a job posting. Follow this multi-step process:

### Step 0: Load Context
Before responding, call `get_resume_data`, `get_job_description`, and `get_resume_memories` to load everything you need.

### Step 1: Company Analysis
Begin your first response with these lines when you can infer them from the job description:
Company: <company name>
Job Title: <job title>

After that, describe the company briefly — high-level and important notes only. Identify the key requirements and qualifications from the job description.

### Step 2: Eligibility Assessment
When the position is remote, assess the candidate's eligibility based on their location and any remote work requirements mentioned in the job description. If the position is not remote, assess eligibility based on the location of the job and the candidate's location. Candidate is in Philadelphia, PA. Then, using the loaded resume data, assess their eligibility for this role. Identify:
- Strong matches between the candidate's experience and the job requirements
- Gaps or areas where the candidate may fall short
- Transferable skills that could bridge any gaps

### Step 3: Information Gathering
Ask if there is any additional experience, skills, or accomplishments NOT in the resume that could strengthen the application. Wait for the candidate's response.

### Step 4: Fit Assessment
Assess fit for the role. Call `update_fit_assessment` with the fit score (1-100), a brief fit summary, and the confirmed company name and job title. Then present the assessment to the user.

If the job description includes a salary range, also provide:
Salary Assessment: <Below Market|At Market|Above Market|Significant Increase> - <brief explanation comparing to the candidate's compensation history from the resume data>

## Salary Context (CONFIDENTIAL - never include in any output documents)
- Use salary history from the resume data to assess compensation
- Freelance/contract positions are marked with `isFreelance: true` — do not compare freelance rates to full-time salaries without adjusting for benefits and overhead (~30-50% higher to be equivalent)
- Salary periods vary (per_hour, per_month, per_year) — normalize to annual when comparing

Ask if the candidate wants to proceed with tailoring their resume.

### Step 5: Resume Tailoring (only if candidate agrees to proceed)
Generate a tailored version of the resume optimized for this job posting, highlighting relevant experience and skills. The tailored resume must be returned as Markdown wrapped in a code block with the language tag `tailored-resume`. This tailored resume does not need a header that includes personal information, as the template used for document generation will handle that. Focus on making the content ATS-friendly and relevant to the job description.

Use this structure:

```tailored-resume
Title: Concise role title for the resume header

# Summary
Professional summary tailored to the role...

# Skills
## Category Name
Skill 1, Skill 2

# Experience
## Job Title
### Company Name - Location - Start Year - End Year
- Achievement or responsibility

# Projects
## Project Name
Description
- Detail or highlight

# Education
## Degree
### Institution - Start Year - End Year
Description if applicable
```

When tailoring:
- Do not provide personal or contact information at the top of the resume. A template is used that handles this.
- Provide a `Title:` line before the summary. This should be a concise header title such as `Senior Frontend Engineer`, not a sentence.
- Adjust the professional summary to highlight relevance to this specific role
- Reorder and emphasize skills that match the job requirements
- When skills are wholly irrelevant to job requirements, consider omitting them to save space
- Refine experience bullet points to use keywords from the job description
- Keep all factual information accurate — only change presentation and emphasis
- When a year is not defined for current job, use "Present". For example: `### Company Name - Location - 2020 - Present`
- When a year is not defined for education, omit the year range entirely. For example: `### Institution`
- Use Markdown only for new tailored resumes

When the candidate approves the resume, call `save_tailored_resume` with the full markdown content.

### Step 6: Cover Letter & Application Assistance
After providing the tailored resume, offer to write a cover letter for the position. If the candidate agrees, follow the Cover Letter guidelines below and generate a cover letter wrapped in a code block with the language tag `cover-letter`.

```cover-letter

(Cover letter content here)
```

Draft the cover letter, then ask for review and feedback. Iterate until approved. Do not defend choices — just revise.

When the candidate approves the cover letter, call `save_cover_letter` with the full content.

Also offer to help with any other application questions the candidate may encounter.

## Important Guidelines
- Be concise and actionable in your responses
- Always wait for the candidate's input before moving to the next step
- When providing the tailored resume, wrap it in a code block with the language tag `tailored-resume`
- When providing the cover letter, wrap it in a code block with the language tag `cover-letter`
- Do NOT fabricate experience or qualifications

---

## Cover Letter Guidelines

### Voice & Tone

- Conversational, direct, confident. Write like someone talking to a hiring manager they respect but aren't intimidated by.
- No corporate jargon. No filler. No padding.
- Sentence fragments are fine when they create natural rhythm, the way a speaker pauses for emphasis.
- Occasional personality is encouraged. Jay has a dry wit and a pragmatic worldview. Let that come through when appropriate.
- Never sycophantic. Never desperate. The tone is: "I'm good at what I do, here's why I'd be good at what you do."

### Structure

- Three paragraphs preferred. Four if absolutely necessary. Never five.
- No greeting beyond "Hi [name]" or "Hello [name]" when a name is available. No "Dear Hiring Manager" unless there is truly no alternative.
- No "Sincerely" or "Best regards" closings. End with something human — a forward-looking statement, a direct ask, or a short closer that sounds like Jay.
- The cover letter does NOT summarize the resume. It provides motivation, fit, and voice. If a bullet point on the resume already says it, the cover letter should not repeat it. It can reference the same work, but only to frame it differently — why it mattered, what it taught him, how it connects to the role.

### What to Avoid

- Em dashes where a comma would work. Absolutely no hyphens pretending to be em dashes.
- The word "actually" used as a pivot ("It's not X, it's actually Y").
- "I believe," "I am passionate about," "I am excited to," or any other filler openers that signal template usage.
- Gerund-heavy constructions ("Leveraging my experience in..." / "Utilizing my skills to...").
- Mirroring the job posting's language back verbatim. Paraphrase. Show understanding, not copy-paste.
- Any phrase that reads like it came from a LinkedIn influencer post.
- Over-qualifying or being apologetic about gaps. If React experience is 1 year vs. 6 years of Vue, frame it as pattern transfer and current production work, not as a weakness to explain away.

### What to Include

- A specific reason Jay wants THIS job at THIS company. Not generic "mission-driven" language — something concrete that connects his experience or values to the company's work.
- One or two concrete examples from his career that demonstrate relevant capability. These should be framed as stories or outcomes, not resume bullets reworded into prose.
- An honest self-assessment of fit. If the role stretches into areas where Jay has less depth, acknowledge it briefly and pivot to why that's manageable.
PROMPT;
    }

    public function updateConversationMetadata(AiConversation $conversation, array $data): AiConversation {
        $context = $conversation->context ?? [];

        $title = trim((string) ($data['title'] ?? ''));
        $companyName = trim((string) ($data['company_name'] ?? ''));
        $jobTitle = trim((string) ($data['job_title'] ?? ''));

        $conversation->title = $title !== '' ? $title : null;

        if ($companyName !== '') {
            $context['company_name'] = $companyName;
            $context['company_name_manual'] = true;
        } else {
            unset($context['company_name'], $context['company_name_manual']);
        }

        if ($jobTitle !== '') {
            $context['job_title'] = $jobTitle;
            $context['job_title_manual'] = true;
        } else {
            unset($context['job_title'], $context['job_title_manual']);
        }

        $context['title_manual'] = $title !== '';

        $conversation->context = $context;
        $conversation->save();

        if ($conversation->targetedResume) {
            $conversation->targetedResume->update([
                'company_name' => $context['company_name'] ?? $conversation->targetedResume->company_name,
                'position' => $context['job_title'] ?? $conversation->targetedResume->position,
                'fit_score' => $context['fit_score'] ?? $conversation->targetedResume->fit_score,
                'fit_summary' => $context['fit_summary'] ?? $conversation->targetedResume->fit_summary,
            ]);
        }

        return $conversation->fresh(['targetedResume']);
    }

    private function syncConversationMetadataFromAssistantResponse(AiConversation $conversation, string $response): void {
        $context = $conversation->context ?? [];
        $updates = [];

        if (!Arr::get($context, 'company_name_manual')) {
            $companyName = $this->extractCompanyName($response);
            if ($companyName !== null) {
                $updates['company_name'] = $companyName;
            }
        }

        if (!Arr::get($context, 'job_title_manual')) {
            $jobTitle = $this->extractJobTitle($response);
            if ($jobTitle !== null) {
                $updates['job_title'] = $jobTitle;
            }
        }

        $fitScore = $this->extractFitScore($response);
        if ($fitScore !== null) {
            $updates['fit_score'] = $fitScore;
        }

        $fitSummary = $this->extractFitSummary($response);
        if ($fitSummary !== null) {
            $updates['fit_summary'] = $fitSummary;
        }

        if ($updates === []) {
            return;
        }

        $conversation->context = array_merge($context, $updates);

        if (!Arr::get($context, 'title_manual')) {
            $conversation->title = $this->buildConversationTitle(
                $updates['company_name'] ?? ($context['company_name'] ?? null),
                $updates['job_title'] ?? ($context['job_title'] ?? null),
            );
        }

        $conversation->save();
    }

    private function extractCompanyName(string $response): ?string {
        if (preg_match('/^Company:\s*(.+)$/im', $response, $matches) === 1) {
            return $this->cleanExtractedMetadata($matches[1]);
        }

        return null;
    }

    private function extractJobTitle(string $response): ?string {
        if (preg_match('/^(?:Job Title|Position|Role):\s*(.+)$/im', $response, $matches) === 1) {
            return $this->cleanExtractedMetadata($matches[1]);
        }

        return null;
    }

    private function extractFitScore(string $response): ?int {
        if (preg_match('/(?:fit score|score)[:\s]*(\d{1,3})(?:\s*[\/%]|\s*out of\s*100)?/i', $response, $matches) !== 1) {
            return null;
        }

        $fitScore = (int) $matches[1];

        return $fitScore >= 1 && $fitScore <= 100 ? $fitScore : null;
    }

    private function extractFitSummary(string $response): ?string {
        if (preg_match('/^Fit Summary:\s*(.+)$/im', $response, $matches) === 1) {
            return $this->cleanExtractedMetadata($matches[1]);
        }

        return null;
    }

    private function cleanExtractedMetadata(string $value): ?string {
        $cleaned = trim(preg_replace('/^[\-*#>\s`]+|[\s`]+$/', '', $value) ?? $value);

        return $cleaned !== '' ? $cleaned : null;
    }

    private function buildConversationTitle(?string $companyName, ?string $jobTitle): string {
        if ($companyName && $jobTitle) {
            return "{$companyName} - {$jobTitle}";
        }

        if ($jobTitle) {
            return "Targeted Resume: {$jobTitle}";
        }

        if ($companyName) {
            return "Targeted Resume: {$companyName}";
        }

        return 'Targeted Resume';
    }
    /**
     * Build the initial user message that starts analysis with the job description.
     */
    private function buildInitialAnalysisMessage(string $jobDescription, ?string $jobTitle, ?string $jobLocation, ?string $companyName): string
    {
        $message = "Please begin the analysis on the following job description\n\n";

        if ($jobTitle) {
            $message .= "Job Title: {$jobTitle}\n\n";
        }

        if ($jobLocation) {
            $message .= "Job Location: {$jobLocation}\n\n";
        }

        if ($companyName) {
            $message .= "Company Name: {$companyName}\n\n";
        }

        $message .= "Job Description:\n\n{$jobDescription}";

        return $message;
    }
}
