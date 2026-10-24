<?php

namespace Database\Factories;

use App\Models\CoverLetter;
use App\Models\DocumentDownload;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentDownload>
 */
class DocumentDownloadFactory extends Factory
{
    protected $model = DocumentDownload::class;

    /**
     * Defaults to a main-resume download. Use `forTargetedResume()` or
     * `forCoverLetter()` to log a download of one of the other document
     * kinds instead — only one of the three should ever be set.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resume_id' => ResumeVersion::factory(),
            'targeted_resume_id' => null,
            'cover_letter_id' => null,
            'type' => fake()->randomElement(['docx', 'pdf']),
            'served_cached_document' => fake()->boolean(),
            'ip_address' => fake()->ipv4(),
        ];
    }

    public function forTargetedResume(?TargetedResume $targetedResume = null): self
    {
        return $this->state(fn (array $attributes): array => [
            'resume_id' => null,
            'targeted_resume_id' => $targetedResume?->id ?? TargetedResume::factory(),
        ]);
    }

    public function forCoverLetter(?CoverLetter $coverLetter = null): self
    {
        return $this->state(fn (array $attributes): array => [
            'resume_id' => null,
            'cover_letter_id' => $coverLetter?->id ?? CoverLetter::create([
                'company_name' => fake()->company(),
                'position' => fake()->jobTitle(),
                'date' => now()->toDateString(),
                'greeting' => 'Dear Hiring Manager,',
                'message_body' => fake()->paragraph(),
            ])->id,
        ]);
    }

    public function docx(): self
    {
        return $this->state(fn (array $attributes): array => ['type' => 'docx']);
    }

    public function pdf(): self
    {
        return $this->state(fn (array $attributes): array => ['type' => 'pdf']);
    }

    public function cached(): self
    {
        return $this->state(fn (array $attributes): array => ['served_cached_document' => true]);
    }

    public function freshlyGenerated(): self
    {
        return $this->state(fn (array $attributes): array => ['served_cached_document' => false]);
    }
}
