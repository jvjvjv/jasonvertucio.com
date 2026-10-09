<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use App\Models\Application;
use App\Models\User;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Base class for targeted-resume tools, gating every action on the Keystone
 * `save-resume` permission of the user the tool runs for.
 *
 * In the local chat loop the user is derived from the conversation; for any
 * future external MCP exposure it comes from the authenticated caller. The
 * local loop enforces authorization through {@see guard()} inside handle(),
 * while {@see shouldRegister()} hides the tool from unauthorized external
 * callers (it is not consulted by the local loop).
 */
abstract class AuthorizedResumeTool extends Tool
{
    public function __construct(
        protected ToolContext $context,
    ) {}

    public function shouldRegister(): bool
    {
        return $this->resolveAuthorizedUser() !== null;
    }

    protected function guard(): ?Response
    {
        if ($this->resolveAuthorizedUser() === null) {
            return Response::error('This action requires resume-management access.');
        }

        return null;
    }

    /**
     * The tracked job the tool's conversation belongs to. Null outside a
     * targeted-resume session, and for an external caller with no
     * conversation at all.
     */
    protected function application(): ?Application
    {
        $conversation = $this->context->conversation;

        return $conversation !== null ? Application::forConversation($conversation) : null;
    }

    /**
     * The status these tools have always reported for a saved resume. A
     * resume that has not been applied with reads `finalized`, which is no
     * longer an application status but is the word the model was given.
     */
    protected function reportedStatus(Application $application): string
    {
        return $application->status->isPipeline() ? $application->status->value : 'finalized';
    }

    private function resolveAuthorizedUser(): ?User
    {
        if ($this->context->userId === null) {
            return null;
        }

        $user = User::find($this->context->userId);

        return $user !== null && $user->can('save-resume') ? $user : null;
    }
}
