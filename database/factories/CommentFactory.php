<?php

namespace Database\Factories;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'message' => $this->faker->paragraphs(2, true),
            'user_id' => null,
            'post_id' => null,
            'parent_id' => null,
            'fb_user_id' => null,
            'fb_comment_id' => null,
            'fb_parent_comment_id' => null,
        ];
    }
}
