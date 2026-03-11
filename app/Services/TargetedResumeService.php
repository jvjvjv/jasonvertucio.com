<?php

namespace App\Services;

use App\Contracts\ResumeDataServiceContract;
use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use App\Models\AiInteractionLog;
use App\Models\AiSystem;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use Generator;

class TargetedResumeService
{
    public function __construct(
        private AiClientFactory $clientFactory,
        private ResumeDataServiceContract $resumeDataService,
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
            'status' => 'active',
            'context' => [
                'job_title' => $jobTitle,
                'job_description' => $jobDescription,
                'resume_version_id' => $resumeVersion->id,
                'step' => 'analysis',
            ],
        ]);

        // Store the system prompt as the first message
        $systemPrompt = $this->buildSystemPrompt($resumeVersion);
        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'system',
            'content' => $systemPrompt,
        ]);

        // Store the initial user message (job description)
        $userContent = $this->buildInitialUserMessage($jobDescription, $jobTitle);
        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userContent,
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
                'status' => 'success',
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
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);

            yield "data: " . json_encode(['type' => 'error', 'message' => $e->getMessage()]) . "\n\n";
        }
    }

    /**
     * Save a finalized targeted resume from conversation context.
     */
    public function saveTailoredResume(AiConversation $conversation, string $tailoredHtml, ?int $fitScore = null): TargetedResume
    {
        $context = $conversation->context ?? [];

        $targetedResume = TargetedResume::create([
            'resume_version_id' => $context['resume_version_id'],
            'ai_conversation_id' => $conversation->id,
            'company_name' => $context['company_name'] ?? 'Unknown Company',
            'position' => $context['job_title'] ?? 'Unknown Position',
            'job_description' => $context['job_description'] ?? '',
            'tailored_data' => ['html' => $tailoredHtml],
            'fit_score' => $fitScore ?? $context['fit_score'] ?? null,
            'fit_summary' => $context['fit_summary'] ?? null,
            'status' => 'finalized',
        ]);

        $conversation->update(['status' => 'completed']);

        return $targetedResume;
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

## Your Role

You will be given a job posting. Follow this multi-step process:

### Step 1: Company Analysis
Describe the company briefly — high-level and important notes only. Identify the key requirements and qualifications from the job description.

### Step 2: Eligibility Assessment
Using the candidate's resume data above, assess their eligibility for this role. Identify:
- Strong matches between the candidate's experience and the job requirements
- Gaps or areas where the candidate may fall short
- Transferable skills that could bridge any gaps

### Step 3: Information Gathering
Ask if there is any additional experience, skills, or accomplishments NOT in the resume that could strengthen the application. Wait for the candidate's response.

### Step 4: Fit Assessment
Provide a fit score (1-100) and a brief summary of fit for the role. Ask if the candidate wants to proceed with tailoring their resume.

### Step 5: Resume Tailoring (only if candidate agrees to proceed)
Generate a tailored version of the resume optimized for this job posting. The tailored resume must be returned as HTML wrapped in a code block with the language tag `tailored-resume`.

Use this structure:

```html
<h1>Summary</h1>
<p>Professional summary tailored to the role...</p>

<h1>Skills</h1>
<h2>Category Name</h2>
<ul>
  <li>Skill 1</li>
  <li>Skill 2</li>
</ul>

<h1>Experience</h1>
<h2>Job Title</h2>
<h3>Company Name — Location — Start Date – End Date</h3>
<ul>
  <li>Achievement or responsibility</li>
</ul>

<h1>Education</h1>
<h2>Degree</h2>
<h3>Institution — Start Date – End Date</h3>
<p>Description if applicable</p>

<h1>Projects</h1>
<h2>Project Name</h2>
<p>Description</p>
<ul>
  <li>Detail or highlight</li>
</ul>
```

When tailoring:
- Adjust the professional summary to highlight relevance to this specific role
- Reorder and emphasize skills that match the job requirements
- Refine experience bullet points to use keywords from the job description
- Keep all factual information accurate — only change presentation and emphasis
- Use semantic HTML only (h1, h2, h3, p, ul, li) — no classes, styles, or divs

### Step 6: Application Assistance
After providing the tailored resume, offer to help with any application questions the candidate may encounter during the application process.

## Important Guidelines
- Be concise and actionable in your responses
- Always wait for the candidate's input before moving to the next step
- When providing the tailored resume, wrap it in a code block with the language tag `tailored-resume`
- Do NOT fabricate experience or qualifications
PROMPT;
    }

    /**
     * Build the initial user message from job description.
     */
    private function buildInitialUserMessage(string $jobDescription, ?string $jobTitle): string
    {
        $message = '';
        if ($jobTitle) {
            $message .= "Job Title: {$jobTitle}\n\n";
        }
        $message .= "Job Description:\n\n{$jobDescription}";

        return $message;
    }
}
