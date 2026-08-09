<?php

namespace Database\Factories;

use App\Models\ResumeVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResumeVersion>
 */
class ResumeVersionFactory extends Factory
{
    /**
     * Feeds the MAJOR segment so generated versions never collide.
     *
     * `resume_versions.version` is uniquely indexed, and randomising the
     * segments only yields a few hundred combinations — a full suite run
     * exhausts that and fails intermittently on a duplicate key.
     */
    protected static int $versionSequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static::$versionSequence++;

        return [
            // Must match /^\d{4}\.\d+\.\d+$/ (DatabaseResumeVersionService).
            'version' => sprintf('%d.%d.%d', fake()->year(), static::$versionSequence, 0),
            'is_current' => false,
            'docx_path' => null,
            'pdf_path' => null,
        ];
    }
}
