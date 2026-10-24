<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\CachesPublicToolResults;
use App\Services\Mcp\PublicMcpCache;
use App\Services\Mcp\Tools\ChatBot\GetResumeDataTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * The resume tool as served over the public MCP endpoint.
 *
 * Identical to {@see GetResumeDataTool} except that an anonymous caller does
 * not receive direct contact details. That is a property of this endpoint, not
 * of the tool: the site's public chat bots deliberately keep disclosing those
 * fields, so the rule cannot live in the shared class.
 *
 * This class lives outside `app/Services/Mcp/Tools`, which AppServiceProvider
 * registers with CodeTalker's discovery. Inside that tree a second class
 * answering to `get-resume-data` would collide with the base tool in the chat
 * registry, with the winner decided by directory sort order.
 */
#[Name('get-resume-data')]
#[Description(
    "Load Jason Vertucio's resume — experience, skills, education and projects. "
    .'Callers without a bearer token receive the public projection: salary history and '
    .'direct contact details (email, phone) are omitted, while the LinkedIn profile and '
    .'site URL remain. Present a personal access token to receive everything that token\'s '
    .'owner is permitted to see.'
)]
class PublicResumeDataTool extends GetResumeDataTool
{
    use CachesPublicToolResults;

    /**
     * Fields an anonymous caller of this endpoint does not receive.
     *
     * @var array<int, string>
     */
    private const REDACTED_PERSONAL_FIELDS = ['email', 'phone'];

    /**
     * @return array<string, mixed>
     */
    protected function resumeDataFor(Request $request): array
    {
        $resumeData = $this->remember(
            PublicMcpCache::GROUP_RESUME,
            ['revision_number' => $request->get('revision_number')],
            fn (): array => $this->redacted(parent::resumeDataFor($request)),
        );

        return $resumeData ?? [];
    }

    /**
     * @param  array<string, mixed>  $resumeData
     * @return array<string, mixed>
     */
    private function redacted(array $resumeData): array
    {
        if ($this->context->userId !== null) {
            return $resumeData;
        }

        if (! isset($resumeData['personal']) || ! \is_array($resumeData['personal'])) {
            return $resumeData;
        }

        foreach (self::REDACTED_PERSONAL_FIELDS as $field) {
            unset($resumeData['personal'][$field]);
        }

        return $resumeData;
    }
}
