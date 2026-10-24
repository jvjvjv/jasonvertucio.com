<?php

namespace App\Services\Mcp\Tools\CompanyIntel;

use App\Models\RoleIntel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('save-role-intel')]
#[Description('Record what you learned about the role, in Markdown. Send only the sections you have something for; sections you leave out keep what was recorded before.')]
class SaveRoleIntelTool extends CompanyIntelTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->description('What the role is, in a short paragraph.'),
            'responsibilities' => $schema->string()->description('What the job involves day to day.'),
            'requirements' => $schema->string()->description('The skills and experience it really asks for.'),
            'team' => $schema->string()->description('The team, and who the role reports to.'),
            'compensation' => $schema->string()->description('Likely pay and benefits, with sources.'),
            'interview_process' => $schema->string()->description('How the company interviews for roles like this.'),
            'concerns' => $schema->string()->description('Anything worth asking about or watching out for.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($response = $this->guard()) {
            return $response;
        }

        $updates = [];

        foreach (RoleIntel::FINDING_FIELDS as $field) {
            if (($value = $this->text($request->get($field), null)) !== null) {
                $updates[$field] = $value;
            }
        }

        if ($updates === []) {
            return Response::error('Send at least one section of the role intel.');
        }

        $this->roleIntel()->update($updates);

        return Response::structured(['success' => true, 'updated' => array_keys($updates)]);
    }
}
