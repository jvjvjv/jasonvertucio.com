<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TranscribeVoiceRequest extends FormRequest
{
    /**
     * Browser recording types: WebM and Ogg (Chrome, Firefox), MP4/M4A
     * (Safari), and WAV or MPEG from anything else.
     */
    private const array AUDIO_TYPES = [
        'audio/webm',
        'video/webm',
        'audio/ogg',
        'audio/mp4',
        'video/mp4',
        'audio/x-m4a',
        'audio/m4a',
        'audio/wav',
        'audio/x-wav',
        'audio/wave',
        'audio/mpeg',
    ];

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
            'audio' => ['required', 'file', 'mimetypes:'.implode(',', self::AUDIO_TYPES), 'max:'.(int) config('voice.max_upload_kb')],
            'language' => ['nullable', 'string', 'max:16', 'regex:/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})?$/'],
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
            'audio.required' => 'No recording was received.',
            'audio.mimetypes' => 'The recording is not an audio type the speech server accepts.',
            'audio.max' => 'The recording is too long to transcribe.',
            'language.regex' => 'The language must be a language code such as en or en-US.',
        ];
    }

    /**
     * The request's own language, else the configured one, else none, in
     * which case the speech server detects the language.
     */
    public function language(): ?string
    {
        $language = $this->input('language') ?: config('voice.transcription_language');

        return filled($language) ? (string) $language : null;
    }
}
