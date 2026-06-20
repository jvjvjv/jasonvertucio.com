<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * This app's own MCP tools were renamed from snake_case to laravel/mcp's
     * kebab-case convention when migrating onto CodeTalker v0.5.0. Tool names
     * are persisted in ai_systems.allowed_tools and matched by exact string,
     * so existing rows must be remapped or the tools would silently stop being
     * enabled. The package's own migration covers the three built-in tools;
     * this one covers the host-app tools.
     *
     * @var array<string, string>
     */
    private const RENAMES = [
        'get_recent_blog_posts' => 'get-recent-blog-posts',
        'get_site_info' => 'get-site-info',
        'get_targeted_resume_context' => 'get-targeted-resume-context',
        'get_job_description' => 'get-job-description',
        'get_resume_data' => 'get-resume-data',
        'get_resume_memories' => 'get-resume-memories',
        'save_cover_letter' => 'save-cover-letter',
        'save_tailored_resume' => 'save-tailored-resume',
        'update_fit_assessment' => 'update-fit-assessment',
        'update_status' => 'update-status',
    ];

    public function up(): void
    {
        $this->remap(self::RENAMES);
    }

    public function down(): void
    {
        $this->remap(array_flip(self::RENAMES));
    }

    /**
     * Rewrite only the known tool names inside each row's allowed_tools array,
     * leaving any other tool names untouched. Idempotent: applying it twice is
     * a no-op because only exact source names are rewritten.
     *
     * @param array<string, string> $map
     */
    private function remap(array $map): void
    {
        DB::table('ai_systems')
            ->whereNotNull('allowed_tools')
            ->orderBy('id')
            ->each(function (object $system) use ($map): void {
                $tools = json_decode((string) $system->allowed_tools, true);

                if (!is_array($tools)) {
                    return;
                }

                $remapped = array_values(array_unique(array_map(
                    static fn ($name): string => $map[$name] ?? (string) $name,
                    $tools,
                )));

                if ($remapped === $tools) {
                    return;
                }

                DB::table('ai_systems')
                    ->where('id', $system->id)
                    ->update(['allowed_tools' => json_encode($remapped)]);
            });
    }
};
