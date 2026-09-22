<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\PublicRecentBlogPostsTool;
use App\Mcp\Tools\PublicResumeDataTool;
use App\Mcp\Tools\PublicSiteInfoTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Jason Vertucio')]
#[Version('1.0.0')]
#[Instructions(
    "Read-only access to Jason Vertucio's professional data: his resume (experience, skills, "
    .'education, projects), his recent blog posts, and his site profile. No credentials are '
    .'required. Calling without a bearer token returns the public projection, which omits salary '
    .'history and direct contact details; presenting a personal access token returns everything '
    .'that token\'s owner is permitted to see.'
)]
class PublicServer extends Server
{
    /**
     * The tools reachable over this endpoint.
     *
     * This array is the audit surface: everything listed here is public,
     * read-only, and callable by anyone. Tools that write — resume editing,
     * candidate approval, targeted resumes — are deliberately absent and must
     * never be added.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        PublicResumeDataTool::class,
        PublicRecentBlogPostsTool::class,
        PublicSiteInfoTool::class,
    ];

    /** @var array<int, class-string> */
    protected array $resources = [];

    /** @var array<int, class-string> */
    protected array $prompts = [];
}
