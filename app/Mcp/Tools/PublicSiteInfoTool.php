<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\CachesPublicToolResults;
use App\Services\Mcp\Tools\ChatBot\GetSiteInfoTool;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * The site profile as served over the public MCP endpoint, with its result
 * cached. Attributes are re-declared because PHP does not inherit them.
 */
#[Name('get-site-info')]
#[Description('Load Jason Vertucio\'s public site profile — the site title, selected projects and personal interests, exactly as published on the homepage.')]
class PublicSiteInfoTool extends GetSiteInfoTool
{
    use CachesPublicToolResults;

    /**
     * @return array<string, mixed>|null
     */
    protected function siteInfoPayload(): ?array
    {
        return $this->remember('site-info', [], fn (): ?array => parent::siteInfoPayload());
    }
}
