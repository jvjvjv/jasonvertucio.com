<?php

namespace Database\Factories;

use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TargetedResume>
 */
class TargetedResumeFactory extends Factory
{
    /**
     * A real document: tailored content in the shape `saveTailoredResume()`
     * stores. The job it was tailored for belongs to an Application — use
     * `Application::factory()->withTargetedResume()` when a test needs one.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->jobTitle();
        $markdown = "# Summary\n".fake()->sentence()."\n\n# Skills\n## Languages\nPHP, TypeScript";

        return [
            'resume_version_id' => ResumeVersion::factory(),
            'title' => $title,
            'tailored_data' => [
                'title' => $title,
                'content' => $markdown,
                'format' => 'markdown',
                'markdown' => $markdown,
            ],
        ];
    }
}
