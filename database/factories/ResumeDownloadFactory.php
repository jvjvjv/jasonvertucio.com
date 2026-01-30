<?php

namespace Database\Factories;

use App\Models\ResumeDownload;
use App\Models\ResumeShareCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResumeDownloadFactory extends Factory
{
    protected $model = ResumeDownload::class;

    public function definition(): array
    {
        return [
            'version' => $this->faker->numerify('####.#.#'),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'share_code_id' => null,
            'user_id' => null,
        ];
    }

    public function withShareCode(): self
    {
        return $this->state(fn (array $attributes) => [
            'share_code_id' => ResumeShareCode::factory(),
        ]);
    }

    public function withUser(): self
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory(),
        ]);
    }
}
