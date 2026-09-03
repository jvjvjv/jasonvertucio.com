<?php

namespace App\Http\Requests;

use App\Models\Comment;
use Canvas\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCommentRequest extends FormRequest
{
    /**
     * The post being commented on, resolved once during validation.
     */
    protected ?Post $targetPost = null;

    /**
     * The comment being replied to, resolved once during validation.
     */
    protected ?Comment $parentComment = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => [$this->user() ? 'nullable' : 'required', 'string', 'max:128'],
            'email' => [$this->user() ? 'nullable' : 'required', 'email', 'max:128'],
            'message' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
            // The honeypot must never fail validation: a rejected submission
            // would tell a bot the field exists. The controller silently
            // discards it instead, via looksAutomated().
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get the custom error messages for the defined rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please tell us your name.',
            'email.required' => 'Please give us an email address.',
            'email.email' => 'That does not look like an email address.',
            'message.required' => 'A comment needs something to say.',
            'message.max' => 'Comments are limited to 5,000 characters.',
        ];
    }

    /**
     * Reject replies that cannot legally hang where they are aimed.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $post = $this->targetPost();

            if (! $post || $post->published_at === null || $post->published_at->isFuture()) {
                $validator->errors()->add('post', 'Comments are closed on this post.');

                return;
            }

            $parent = $this->parentComment();

            if ($parent === null) {
                return;
            }

            if ($parent->post_id !== $post->id) {
                $validator->errors()->add('parent_id', 'That comment belongs to a different post.');

                return;
            }

            if (! $parent->acceptsReplies()) {
                $validator->errors()->add('parent_id', 'This thread has reached its maximum depth.');
            }
        });
    }

    /**
     * Resolve the post this comment targets.
     */
    public function targetPost(): ?Post
    {
        return $this->targetPost ??= Post::query()->where('slug', $this->route('slug'))->first();
    }

    /**
     * Resolve the comment this one replies to, if any.
     */
    public function parentComment(): ?Comment
    {
        if ($this->input('parent_id') === null) {
            return null;
        }

        return $this->parentComment ??= Comment::query()->find($this->input('parent_id'));
    }

    /**
     * Determine whether the honeypot field was filled in.
     */
    public function looksAutomated(): bool
    {
        return filled($this->input('website'));
    }
}
