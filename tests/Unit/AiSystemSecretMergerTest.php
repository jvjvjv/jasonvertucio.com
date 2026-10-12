<?php

namespace Tests\Unit;

use App\Services\AiSystemSecretMerger;
use PHPUnit\Framework\TestCase;

class AiSystemSecretMergerTest extends TestCase
{
    private AiSystemSecretMerger $merger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merger = new AiSystemSecretMerger;
    }

    public function test_an_empty_key_keeps_the_stored_key(): void
    {
        $this->assertSame('stored', $this->merger->apiKey('stored', '', false));
        $this->assertSame('stored', $this->merger->apiKey('stored', null, false));
    }

    public function test_a_supplied_key_replaces_and_clear_removes(): void
    {
        $this->assertSame('new', $this->merger->apiKey('stored', 'new', false));
        $this->assertSame('', $this->merger->apiKey('stored', null, true));
        $this->assertSame('', $this->merger->apiKey('stored', 'new', true));
    }

    public function test_empty_credentials_keep_the_stored_credentials(): void
    {
        $stored = ['token' => 'secret'];

        $this->assertSame($stored, $this->merger->credentials($stored, '', false));
        $this->assertSame($stored, $this->merger->credentials($stored, null, false));
        $this->assertSame($stored, $this->merger->credentials($stored, '{}', false));
    }

    public function test_supplied_credentials_replace_and_clear_removes(): void
    {
        $stored = ['token' => 'secret'];

        $this->assertSame(['token' => 'new'], $this->merger->credentials($stored, '{"token":"new"}', false));
        $this->assertNull($this->merger->credentials($stored, null, true));
    }

    public function test_a_policy_without_domains_is_dropped(): void
    {
        $this->assertNull($this->merger->webToolPolicy(['allowed_domains' => ['a.test']], null));
        $this->assertNull($this->merger->webToolPolicy(['allowed_domains' => ['a.test']], []));
    }
}
