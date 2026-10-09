<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommentRequest extends FormRequest
{
    /**
     * A page can hold dozens of edit forms, and the new-comment form reads the
     * default bag's `message` error. A named bag keeps an edit failure on the
     * comment it was aimed at.
     *
     * @var string
     */
    protected $errorBag = 'commentEdit';

    /**
     * Determine if the user is authorized to make this request.
     *
     * A comment reached through the wrong post is answered 404 here, ahead of
     * validation, so a malformed edit cannot turn that into a redirect.
     */
    public function authorize(): bool
    {
        $comment = $this->route('comment');

        abort_unless($comment->isOnPost($this->route('slug')), 404);

        return $this->user()->can('update', $comment);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:5000'],
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
            'message.required' => 'A comment needs something to say.',
            'message.max' => 'Comments are limited to 5,000 characters.',
        ];
    }

    /**
     * Send a rejected edit back to the comment it was aimed at.
     */
    protected function getRedirectUrl(): string
    {
        return route('post', $this->route('slug')).'#comment-'.$this->route('comment')->id;
    }
}
