<?php

namespace Tests\Feature\CompanyIntel;

use App\Models\User;
use App\Services\CompanyIntel\CompanyIntelService;
use App\Services\CompanyIntel\CompanyIntelSystems;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Models\AiSystemFeatureDefault;
use Tests\TestCase;

class CompanyIntelSystemsTest extends TestCase
{
    use DatabaseTransactions;

    private CompanyIntelSystems $systems;

    protected function setUp(): void
    {
        parent::setUp();

        AiSystemFeatureDefault::query()->delete();
        AiSystem::query()->where('system_prompt_id', CompanyIntelService::PROMPT_ID_COMPANY_INTEL)->update(['system_prompt_id' => null]);
        $this->systems = app(CompanyIntelSystems::class);
    }

    private function administrator(): User
    {
        Permission::firstOrCreate(['name' => 'manage-ai-tools']);
        $user = User::factory()->create();
        $user->givePermissionTo('manage-ai-tools');

        return $user;
    }

    /**
     * @param  array<int, string>  $featureDefaults
     * @return array<string, mixed>
     */
    private function updatePayload(AiSystem $system, array $featureDefaults): array
    {
        return [
            'name' => $system->name,
            'base_url' => 'http://localhost:1234/v1',
            'api_version' => '',
            'max_tokens' => 4096,
            'context_length' => null,
            'temperature' => 0.5,
            'system_prompt' => 'Test.',
            'config' => '',
            'credentials' => '',
            'auth_type' => 'none',
            'endpoint_type' => 'openai-compatible',
            'stream_protocol' => 'chunked-json',
            'system_prompt_mode' => 'messages',
            'supports_tools' => true,
            'allowed_tools' => [],
            'supports_json_mode' => false,
            'is_local_endpoint' => true,
            'pricing_profile' => '',
            'is_active' => true,
            'feature_defaults' => $featureDefaults,
        ];
    }

    private function localSystem(array $attributes = []): AiSystem
    {
        return AiSystem::factory()->create([
            'provider' => 'openai-compatible',
            'base_url' => 'http://localhost:1234/v1',
            ...$attributes,
        ]);
    }

    public function test_saving_the_company_intel_default_leaves_one_default(): void
    {
        $previous = $this->localSystem();
        AiSystemFeatureDefault::create(['ai_system_id' => $previous->id, 'feature' => 'company-intel']);
        $system = $this->localSystem();

        $this->actingAs($this->administrator())
            ->put(route('admin.ai.systems.update', $system), $this->updatePayload($system, ['company-intel']))
            ->assertSessionHasNoErrors();

        $this->assertSame([$system->id], AiSystemFeatureDefault::where('feature', 'company-intel')->pluck('ai_system_id')->all());
        $this->assertTrue($this->systems->default()->is($system));
    }

    public function test_an_unknown_feature_default_fails_validation(): void
    {
        $system = $this->localSystem();

        $this->actingAs($this->administrator())
            ->put(route('admin.ai.systems.update', $system), $this->updatePayload($system, ['company-gossip']))
            ->assertSessionHasErrors('feature_defaults.0');
    }

    public function test_eligible_systems_are_active_and_use_the_company_intel_prompt(): void
    {
        $eligible = $this->localSystem(['system_prompt_id' => CompanyIntelService::PROMPT_ID_COMPANY_INTEL]);
        $this->localSystem(['system_prompt_id' => CompanyIntelService::PROMPT_ID_COMPANY_INTEL, 'is_active' => false]);
        $this->localSystem(['system_prompt_id' => 1]);
        $this->localSystem(['system_prompt_id' => null]);

        $this->assertSame([$eligible->id], $this->systems->eligible()->pluck('id')->all());
    }

    public function test_the_default_is_eligible_whatever_its_prompt(): void
    {
        $default = $this->localSystem(['system_prompt_id' => 1]);
        AiSystemFeatureDefault::create(['ai_system_id' => $default->id, 'feature' => 'company-intel']);

        $this->assertTrue($this->systems->isEligible($default));
        $this->assertTrue($this->systems->resolve(null)->is($default));
    }

    public function test_an_inactive_default_counts_as_none(): void
    {
        $default = $this->localSystem(['is_active' => false]);
        AiSystemFeatureDefault::create(['ai_system_id' => $default->id, 'feature' => 'company-intel']);

        $this->assertNull($this->systems->default());
    }

    public function test_resolving_an_ineligible_system_gives_nothing(): void
    {
        $other = $this->localSystem(['system_prompt_id' => 1]);

        $this->assertNull($this->systems->resolve($other->id));
        $this->assertNull($this->systems->resolve(null));
    }
}
