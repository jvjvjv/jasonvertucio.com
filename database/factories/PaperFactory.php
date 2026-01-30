<?php

namespace Database\Factories;

use App\Models\Paper;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaperFactory extends Factory
{
    protected $model = Paper::class;

    public function definition(): array
    {
        $edition = $this->faker->numerify('###');

        return [
            'edition_id' => Str::uuid(),
            'edition' => $edition,
            'published_at' => $this->faker->dateTime(),
        ];
    }
}
