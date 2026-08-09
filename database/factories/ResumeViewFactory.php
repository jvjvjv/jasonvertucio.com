<?php

namespace Database\Factories;

use App\Models\ResumeShareCode;
use App\Models\ResumeView;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResumeViewFactory extends Factory
{
    protected $model = ResumeView::class;

    public function definition(): array
    {
        return [
            'share_code_id' => ResumeShareCode::factory(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }
}
