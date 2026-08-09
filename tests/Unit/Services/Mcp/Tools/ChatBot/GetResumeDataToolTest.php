<?php

namespace Tests\Unit\Services\Mcp\Tools\ChatBot;

use App\Contracts\ResumeDataServiceContract;
use App\Models\User;
use App\Services\Mcp\Tools\ChatBot\GetResumeDataTool;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Jvjvjv\CodeTalker\Services\Mcp\ToolResultConverter;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Mockery;
use Tests\TestCase;

class GetResumeDataToolTest extends TestCase
{
    use DatabaseTransactions;

    private function service(): ResumeDataServiceContract
    {
        $mock = Mockery::mock(ResumeDataServiceContract::class);
        $mock->shouldReceive('getAllEditableData')->andReturn([
            'experience' => [[
                'company' => 'Acme',
                'salaryStart' => ['amount' => 120000.0, 'period' => 'per_year'],
                'salaryEnd' => ['amount' => 150000.0, 'period' => 'per_year'],
            ]],
        ]);

        return $mock;
    }

    /**
     * @return array<string, mixed>
     */
    private function handle(ToolContext $context): array
    {
        $tool = new GetResumeDataTool($context, $this->service());

        return ToolResultConverter::toArray($tool->handle(new Request([])));
    }

    public function test_it_redacts_salary_for_anonymous_callers(): void
    {
        $result = $this->handle(ToolContext::forUser(null));

        $this->assertNull($result['experience'][0]['salaryStart']);
        $this->assertNull($result['experience'][0]['salaryEnd']);
    }

    public function test_it_redacts_salary_for_users_without_save_resume_permission(): void
    {
        $user = User::factory()->create();

        $result = $this->handle(ToolContext::forUser($user->id));

        $this->assertNull($result['experience'][0]['salaryStart']);
        $this->assertNull($result['experience'][0]['salaryEnd']);
    }

    public function test_it_exposes_salary_for_users_with_save_resume_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('save-resume');

        $result = $this->handle(ToolContext::forUser($user->id));

        $this->assertSame(['amount' => 120000.0, 'period' => 'per_year'], $result['experience'][0]['salaryStart']);
        $this->assertSame(['amount' => 150000.0, 'period' => 'per_year'], $result['experience'][0]['salaryEnd']);
    }
}
