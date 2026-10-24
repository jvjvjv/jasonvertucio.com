<?php

namespace App\Services\Mcp\Tools\CompanyIntel;

use App\Models\RoleIntel;
use App\Models\User;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Base class for the intel session's tools. Each acts on the Role intel its
 * conversation gathers, so outside an intel session — the tools are also
 * discoverable by chat bots — every one refuses.
 *
 * The user is checked with hasPermissionTo() rather than can(): the first
 * turn usually runs in the queue worker, where Keystone registers no gates.
 */
abstract class CompanyIntelTool extends Tool
{
    private ?RoleIntel $roleIntel = null;

    public function __construct(
        protected ToolContext $context,
    ) {}

    public function shouldRegister(): bool
    {
        return $this->authorizedUser() !== null;
    }

    protected function guard(): ?Response
    {
        if ($this->roleIntel() === null) {
            return Response::error('This tool only works in a Company Intel session.');
        }

        if ($this->authorizedUser() === null) {
            return Response::error('This action requires resume-management access.');
        }

        return null;
    }

    /**
     * The Role intel this tool's conversation gathers, or null outside an
     * intel session.
     */
    protected function roleIntel(): ?RoleIntel
    {
        if ($this->roleIntel === null && $this->context->conversation !== null) {
            $this->roleIntel = RoleIntel::forConversation($this->context->conversation);
        }

        return $this->roleIntel;
    }

    /**
     * Trim a value to a column's length, treating blank as absent.
     */
    protected function text(mixed $value, ?int $limit = 255): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return $limit !== null ? mb_substr($value, 0, $limit) : $value;
    }

    private function authorizedUser(): ?User
    {
        if ($this->context->userId === null) {
            return null;
        }

        $user = User::find($this->context->userId);

        return $user !== null && $user->hasPermissionTo('edit-resume') ? $user : null;
    }
}
