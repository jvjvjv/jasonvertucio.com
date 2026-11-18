<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnsubscribeCommentReplyEmailsRequest extends FormRequest
{
    public const SCOPE_COMMENT = 'comment';

    public const SCOPE_ADDRESS = 'address';

    /**
     * The signed URL is the authorization, checked by the route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * A mail client's one-click POST sends no scope, and means this comment.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'scope' => ['nullable', 'string', Rule::in([self::SCOPE_COMMENT, self::SCOPE_ADDRESS])],
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
            'scope.in' => 'Choose whether to stop emails for this comment or for this address.',
        ];
    }

    /**
     * Determine whether the whole address is being opted out.
     */
    public function optsOutAddress(): bool
    {
        return $this->input('scope') === self::SCOPE_ADDRESS;
    }
}
