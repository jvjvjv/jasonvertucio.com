<?php

namespace App\Services\Mcp\Tools\ChatBot\ResumeEdit;

use App\Models\User;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Base class for AI-persona resume-edit tools, gating every action on the
 * Keystone `edit-resume` permission of the user the conversation runs for.
 *
 * This is the second of two independent gates: which tools an AiSystem is
 * allowed to use at all (`AiSystem::allowed_tools`) is enforced upstream by
 * `Jvjvjv\CodeTalker\Services\Mcp\ChatBotToolRegistry` filtering discovered
 * tools by name before this class is ever invoked. This class enforces the
 * second gate — the authenticated user's permission — at call time via
 * {@see guard()}, mirroring `App\Services\Mcp\Tools\TargetedResume\AuthorizedResumeTool`.
 */
abstract class AuthorizedResumeEditTool extends Tool
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
            return Response::error('This action requires resume-editing access.');
        }

        return null;
    }

    protected function resolveAuthorizedUser(): ?User
    {
        if ($this->context->userId === null) {
            return null;
        }

        $user = User::find($this->context->userId);

        return $user !== null && $user->can('edit-resume') ? $user : null;
    }
}
