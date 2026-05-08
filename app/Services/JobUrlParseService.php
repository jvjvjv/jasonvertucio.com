<?php

namespace App\Services;

use App\Models\AiSystem;
use App\Models\JobUrl;
use App\Models\JobUrlParser;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

use Log;

class JobUrlParseService {
    private const MAX_HTML_LENGTH = 100000;

    public function __construct(
        private AiClientFactory $clientFactory,
    ) {
    }

    /**
     * Parse a job URL and extract job information.
     *
        * @return array{job_title: string, company_name: string, job_location: string, job_description: string, reasoning: string, parser_id: int, used_existing_parser: bool}
     */
    public function parseUrl(string $url, AiSystem $aiSystem): array {
        $domain = $this->extractDomain($url);
        $html = $this->fetchHtml($url);

        $activeParser = JobUrlParser::findActiveForDomain($domain);

        if ($activeParser !== null) {
            $extracted = $this->extractWithSelectors($html, $activeParser);

            if ($extracted !== null) {
                $this->storeJobUrl($url, $activeParser, $extracted);

                return [
                    ...$extracted,
                    'parser_id' => $activeParser->id,
                    'used_existing_parser' => true,
                ];
            }
        }

        return $this->extractWithAi($html, $url, $domain, $aiSystem);
    }

    /**
     * Extract job data using CSS selectors from an active parser.
     *
        * @return array{job_title: string, company_name: string, job_location: string, job_description: string, reasoning: string}|null
     */
    private function extractWithSelectors(string $html, JobUrlParser $parser): ?array {
        $crawler = new Crawler($html);

        try {
            $jobTitle = $parser->job_title_selector
                ? $crawler->filter($parser->job_title_selector)->first()->text('')
                : '';

            $companyName = $parser->company_name_selector
                ? $crawler->filter($parser->company_name_selector)->first()->text('')
                : '';

            $jobDescription = $parser->job_description_selector
                ? $crawler->filter($parser->job_description_selector)->first()->text('')
                : '';

            $jobLocation = $parser->job_location_selector
                ? $crawler->filter($parser->job_location_selector)->first()->text('')
                : '';
        } catch (\Exception) {
            return null;
        }

        if (empty(trim($jobDescription))) {
            return null;
        }

        return [
            'job_title' => trim($jobTitle),
            'company_name' => trim($companyName),
            'job_location' => trim($jobLocation),
            'job_description' => trim($jobDescription),
            'reasoning' => trim((string) ($parser->ai_reasoning ?? '')),
        ];
    }

    /**
     * Use AI to extract job data from HTML.
     *
        * @return array{job_title: string, company_name: string, job_location: string, job_description: string, reasoning: string, parser_id: int, used_existing_parser: bool}
     */
    public function extractWithAi(string $html, string $url, string $domain, AiSystem $aiSystem, ?string $feedback = null): array {
        $cleanedHtml = $this->cleanHtml($html);

        $client = $this->clientFactory->forSystem($aiSystem);

        $systemPrompt = $this->buildSystemPrompt();

        $userContent = "URL: {$url}\n\nHTML content:\n{$cleanedHtml}";

        if ($feedback !== null) {
            $userContent .= "\n\n---\nIMPORTANT: This is a re-parse. The previous extraction was inaccurate.\nFeedback on what was wrong: {$feedback}\nPlease try again, taking this feedback into account.";
        }

        $messages = [
            ['role' => 'user', 'content' => $userContent],
        ];

        $response = $client
            ->withSystem($systemPrompt)
            ->withMaxTokens(4096)
            ->message($messages);

        $parsed = $this->parseAiResponse($response);

        $parser = JobUrlParser::create([
            'domain' => $domain,
            'company_name_selector' => $parsed['company_name_selector'] ?? null,
            'job_title_selector' => $parsed['job_title_selector'] ?? null,
            'job_location_selector' => $parsed['job_location_selector'] ?? null,
            'job_description_selector' => $parsed['job_description_selector'] ?? null,
            'html' => $html,
            'ai_reasoning' => $parsed['reasoning'] ?? null,
            'status' => 'inactive',
        ]);

        $extracted = [
            'job_title' => $parsed['job_title'] ?? '',
            'company_name' => $parsed['company_name'] ?? '',
            'job_location' => $parsed['job_location'] ?? '',
            'job_description' => $parsed['job_description'] ?? '',
            'reasoning' => $parsed['reasoning'] ?? '',
        ];

        $this->storeJobUrl($url, $parser, $extracted);

        return [
            ...$extracted,
            'parser_id' => $parser->id,
            'used_existing_parser' => false,
        ];
    }

    /**
     * Mark a parser as active and deactivate all other parsers for the same domain.
     */
    public function confirmParser(JobUrlParser $parser): void {
        JobUrlParser::query()
            ->where('domain', $parser->domain)
            ->where('id', '!=', $parser->id)
            ->update(['status' => 'inactive']);

        $parser->update(['status' => 'active']);
    }

    /**
     * Keep a parser as inactive.
     */
    public function rejectParser(JobUrlParser $parser, ?string $feedback = null): void {
        $parser->update(['status' => 'inactive']);
    }

    /**
     * Re-parse using stored HTML with user feedback.
     *
        * @return array{job_title: string, company_name: string, job_location: string, job_description: string, reasoning: string, parser_id: int, used_existing_parser: bool}
     */
    public function reparseWithFeedback(JobUrlParser $parser, string $feedback, AiSystem $aiSystem): array {
        $html = $parser->html;

        if (empty($html)) {
            throw new \RuntimeException('No stored HTML available for re-parsing.');
        }

        $domain = $parser->domain;
        $url = "https://{$domain}";

        return $this->extractWithAi($html, $url, $domain, $aiSystem, $feedback);
    }

    /**
     * Store a parsed job URL record linked to its parser.
     *
     * @param array{job_title: string, company_name: string, job_location: string, job_description: string, reasoning: string} $extracted
     */
    private function storeJobUrl(string $url, JobUrlParser $parser, array $extracted): void {
        JobUrl::create([
            'job_url_parser_id' => $parser->id,
            'url' => $url,
            'contents' => json_encode($extracted, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]);
    }

    /**
     * Fetch HTML content from a URL.
     */
    private function fetchHtml(string $url): string {
        $response = Http::timeout(15)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ])
            ->get($url);

        $response->throw();

        $contentType = $response->header('Content-Type') ?? '';

        if (!str_contains($contentType, 'text/html') && !str_contains($contentType, 'application/xhtml')) {
            throw new \RuntimeException('The URL did not return an HTML page.');
        }

        $body = $response->body();

        if (strlen(trim(strip_tags($body))) < 50) {
            throw new \RuntimeException('This page may require JavaScript to render. Please paste the job description manually.');
        }

        return $body;
    }

    /**
     * Extract the domain from a URL, stripping "www." prefix.
     */
    public function extractDomain(string $url): string {
        $host = parse_url($url, PHP_URL_HOST) ?? '';

        return preg_replace('/^www\./', '', strtolower($host));
    }

    /**
     * Clean HTML by removing non-content elements and truncating.
     */
    public function cleanHtml(string $html): string {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<svg\b[^>]*>.*?<\/svg>/is', '', $html);
        $html = preg_replace('/<noscript\b[^>]*>.*?<\/noscript>/is', '', $html);
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        $html = preg_replace('/\s+/', ' ', $html);
        $html = trim($html);

        if (strlen($html) > self::MAX_HTML_LENGTH) {
            $html = substr($html, 0, self::MAX_HTML_LENGTH);
        }

        return $html;
    }

    /**
     * Build the system prompt for AI extraction.
     */
    private function buildSystemPrompt(): string {
        return <<<'PROMPT'
You are a job posting parser. You will receive the HTML content of a job posting webpage.

Extract the following information:
1. job_title - The title of the job position
2. company_name - The name of the hiring company
3. job_location - The location of the job if available, as well as if it is onsite, hybrid, or remote
4. job_description - The full job description including responsibilities, requirements, qualifications, and any other relevant details. Include all text content relevant for a job seeker to understand the role. Format as you see fit as clean plain text with line breaks between sections or as Markdown.

Additionally, provide CSS selectors that could be used to extract each field from similar pages on this same website:
5. job_title_selector - CSS selector for the job title element
6. job_location_selector - CSS selector for the job location element
7. company_name_selector - CSS selector for the company name element
8. job_description_selector - CSS selector for the job description container element

Respond with valid JSON only, no markdown formatting or code fences:
{
  "job_title": "...",
  "company_name": "...",
  "job_description": "...",
  "job_location": "...",
  "job_title_selector": "...",
  "job_location_selector": "...",
  "company_name_selector": "...",
  "job_description_selector": "...",
  "reasoning": "Brief explanation of how you identified each field and chose the selectors"
}
PROMPT;
    }

    /**
     * Parse the AI response to extract JSON data.
     *
     * @return array<string, string>
     */
    private function parseAiResponse(array $response): array {
        $text = '';

        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }

        $parsed = json_decode($text, true);

        Log::debug('AI response text: ' . $text);

        if ($parsed !== null) {
            return $parsed;
        }

        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $matches)) {
            $parsed = json_decode($matches[1], true);

            if ($parsed !== null) {
                return $parsed;
            }
        }

        throw new \RuntimeException('AI could not parse the page content. Please paste the job description manually.');
    }
}
