<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\User;
use Canvas\Models\Post;
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
            'user_id' => User::factory(),
            'post_id' => $this->getOrCreatePost(),
            'parent_id' => null,
            'fb_user_id' => null,
            'fb_comment_id' => null,
            'fb_parent_comment_id' => null,
        ];
    }

    public function anonymous(): self
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }

    public function asReply(?Comment $parent = null): self
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent?->id,
        ]);
    }

    public function forPost(Post $post): self
    {
        return $this->state(fn (array $attributes) => [
            'post_id' => $post->id,
        ]);
    }

    private function getOrCreatePost()
    {
        // Try to get an existing post, otherwise create a minimal one
        $post = Post::query()->inRandomOrder()->first();

        if (!$post) {
            // Create a minimal post if none exists
            return Post::create([
                'title' => $this->faker->sentence(),
                'slug' => $this->faker->slug(),
                'summary' => $this->faker->paragraph(),
                'body' => $this->faker->paragraphs(5, true),
                'published_at' => now(),
                'user_id' => User::factory()->create()->id,
            ])->id;
        }

        return $post->id;
    }
}
