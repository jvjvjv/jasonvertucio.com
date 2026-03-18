<?php

namespace App\Services;

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
use Generator;
use Illuminate\Support\Arr;

class TargetedResumeService
{
    public function __construct(
        private AiClientFactory $clientFactory,
        private ResumeDataServiceContract $resumeDataService,
        private \App\Services\TargetedResumeDocumentService $documentService,
        private CoverLetterDocumentService $coverLetterDocumentService,
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
                'step' => 'analysis',
                'auto_start_pending' => true,
            ],
        ]);

        // Store the system prompt as the first message
        $systemPrompt = $this->buildSystemPrompt($resumeVersion);
        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'system',
            'content' => $systemPrompt,
        ]);

        // Store the initial user messages that should be sent immediately on first load.
        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Please begin the analysis on the following job description',
        ]);

        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $this->buildInitialJobDescriptionMessage($jobDescription, $jobTitle),
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

        $startTime = microtime(true);
        $fullResponse = '';
        $inputTokens = null;
        $outputTokens = null;

        try {
            $stream = $client->stream($apiMessages);

            foreach ($stream as $event) {
                if (isset($event['type'])) {
                    if ($event['type'] === 'content_block_delta' && isset($event['delta']['text'])) {
                        $fullResponse .= $event['delta']['text'];
                        yield "data: " . json_encode($event) . "\n\n";
                    } elseif ($event['type'] === 'message_start' && isset($event['message']['usage'])) {
                        $inputTokens = $event['message']['usage']['input_tokens'] ?? null;
                    } elseif ($event['type'] === 'message_delta' && isset($event['usage'])) {
                        $outputTokens = $event['usage']['output_tokens'] ?? null;
                    } elseif ($event['type'] === 'message_stop') {
                        yield "data: " . json_encode($event) . "\n\n";
                    }
                }
            }

            yield "data: [DONE]\n\n";

            // Save the assistant response
            if ($fullResponse) {
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

            // Log the interaction
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            AiInteractionLog::create([
                'ai_system_id' => $conversation->aiSystem->id,
                'ai_conversation_id' => $conversation->id,
                'user_id' => auth()->id(),
                'feature' => 'targeted-resume',
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'model' => $conversation->aiSystem->model,
                'duration_ms' => $durationMs,
                'status' => AiInteractionStatus::Success,
            ]);
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

        $targetedResume = TargetedResume::updateOrCreate(
            ['ai_conversation_id' => $conversation->id],
            [
                'resume_version_id' => $context['resume_version_id'],
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
                'status' => TargetedResumeStatus::Finalized,
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
     *
     * @return array{greeting: string, message_body: string, closing: string}
     */
    private function parseCoverLetterContent(string $content): array
    {
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
     * Build the system prompt with full resume data.
     */
    public function buildSystemPrompt(ResumeVersion $resumeVersion): string
    {
        $resumeData = $this->resumeDataService->getAllEditableData();

        $resumeJson = json_encode($resumeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
You are an expert career advisor and resume tailoring specialist. You help candidates optimize their resumes for specific job postings.

## Candidate's Current Resume Data

```json
{$resumeJson}
```

## Salary Context (CONFIDENTIAL - do not include in any output documents)

The resume data above may include salary history for some positions. Use this to:
- Understand the candidate's compensation trajectory
- When a job posting includes a salary range, assess whether it represents a lateral move, increase, or decrease relative to the candidate's most recent comparable full-time salary
- Rate the offered salary range as: "Below Market" / "At Market" / "Above Market" / "Significant Increase" relative to the candidate's history
- Freelance/contract positions are marked with `isFreelance: true` — do not directly compare freelance rates to full-time salaries without adjusting for benefits, taxes, and overhead (a common rule of thumb is that freelance rates need to be ~30-50% higher to be equivalent)
- Salary periods vary (per_hour, per_month, per_year) — normalize to annual when comparing
- NEVER include salary information in the tailored resume or cover letter output

## Your Role

You will be given a job posting. Follow this multi-step process:

### Step 0: Initial Analysis
Find the company name and position from the job description. If not explicitly stated, make your best guess based on the content. Indicate these in your response as "Company Name: XYZ" and "Position: ABC". This will help with tailoring the resume later.

### Step 1: Company Analysis
Begin your first response with these lines when you can infer them from the job description:
Company: <company name>
Job Title: <job title>

After that, describe the company briefly — high-level and important notes only. Identify the key requirements and qualifications from the job description.

### Step 2: Eligibility Assessment
When the position is remote, assess the candidate's eligibility based on their location and any remote work requirements mentioned in the job description. If the position is not remote, assess eligibility based on the location of the job and the candidate's location. Candidate is in Philadelphia, PA. Then, using the candidate's resume data above, assess their eligibility for this role. Identify:
- Strong matches between the candidate's experience and the job requirements
- Gaps or areas where the candidate may fall short
- Transferable skills that could bridge any gaps

### Step 3: Information Gathering
Ask if there is any additional experience, skills, or accomplishments NOT in the resume that could strengthen the application. Wait for the candidate's response.

### Step 4: Fit Assessment
Provide a fit score (1-100) and a brief summary of fit for the role. Format the score line as `Fit Score: <number>`.

If the job description includes a salary range, also provide:
Salary Assessment: <Below Market|At Market|Above Market|Significant Increase> - <brief explanation comparing to candidate's compensation history>

Ask if the candidate wants to proceed with tailoring their resume.

### Step 5: Resume Tailoring (only if candidate agrees to proceed)
Generate a tailored version of the resume optimized for this job posting, highlighting relevant experience and skills. The tailored resume must be returned as Markdown wrapped in a code block with the language tag `tailored-resume`.

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
### Company Name - Location - Start Date - End Date
- Achievement or responsibility

# Education
## Degree
### Institution - Start Date - End Date
Description if applicable

# Projects
## Project Name
Description
- Detail or highlight
```

When tailoring:
- Provide a `Title:` line before the summary. This should be a concise header title such as `Senior Frontend Engineer`, not a sentence.
- Adjust the professional summary to highlight relevance to this specific role
- Reorder and emphasize skills that match the job requirements
- When skills are wholly irrelevant to job requirements, consider omitting them to save space
- Refine experience bullet points to use keywords from the job description
- Keep all factual information accurate — only change presentation and emphasis
- Use Markdown only for new tailored resumes

### Step 6: Cover Letter & Application Assistance
After providing the tailored resume, offer to write a cover letter for the position. If the candidate agrees, generate a cover letter wrapped in a code block with the language tag `cover-letter`.

```cover-letter

(Cover letter content here)
```

The cover letter content should follow this structure:
- Start with a greeting line (e.g., "Dear Hiring Manager,")
- Body paragraphs explaining fit for the role
- A closing line (e.g., "Sincerely,")

Do NOT include the candidate's name, address, date, or signature in the cover letter — those are added automatically from their profile data.

When providing the cover letter, wrap it in a code block with the language tag `cover-letter`.

Also offer to help with any other application questions the candidate may encounter.

## Important Guidelines
- Be concise and actionable in your responses
- Always wait for the candidate's input before moving to the next step
- When providing the tailored resume, wrap it in a code block with the language tag `tailored-resume`
- Do NOT fabricate experience or qualifications
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
     * Build the initial user message from job description.
     */
    private function buildInitialJobDescriptionMessage(string $jobDescription, ?string $jobTitle): string
    {
        $message = '';
        if ($jobTitle) {
            $message .= "Job Title: {$jobTitle}\n\n";
        }
        $message .= "Job Description:\n\n{$jobDescription}";

        return $message;
    }
}
