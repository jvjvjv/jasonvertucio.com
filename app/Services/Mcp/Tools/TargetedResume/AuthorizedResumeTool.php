<?php

namespace App\Services\Mcp\Tools\TargetedResume;

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

    private function resolveAuthorizedUser(): ?User
    {
        if ($this->context->userId === null) {
            return null;
        }

        $user = User::find($this->context->userId);

        return $user !== null && $user->can('save-resume') ? $user : null;
    }
}
