<?php

namespace App\Http\Requests;

use App\Support\SpeakableText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SynthesizeSpeechRequest extends FormRequest
{
    private ?string $speakableText = null;

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
     * The raw Markdown is bounded loosely here; the real limit applies to the
     * text left once markup is removed, in withValidator().
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:'.((int) config('voice.max_speech_chars') * 4)],
            'voice' => ['nullable', 'string', 'max:100'],
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
            'text.required' => 'There is nothing to read aloud.',
            'text.max' => 'This text is too long to read aloud in one request.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('text')) {
                return;
            }

            $text = $this->speakableText();
            $max = (int) config('voice.max_speech_chars');

            if ($text === '') {
                $validator->errors()->add('text', 'There is nothing to read aloud.');
            } elseif (mb_strlen($text) > $max) {
                $validator->errors()->add('text', "This text is too long to read aloud in one request (over {$max} characters).");
            }
        });
    }

    /**
     * The submitted Markdown reduced to the words to be spoken.
     */
    public function speakableText(): string
    {
        return $this->speakableText ??= SpeakableText::fromMarkdown((string) $this->input('text'));
    }
}
