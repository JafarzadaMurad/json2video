<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTranscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'src' => 'required|url|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'src.required' => 'The source URL is required. Provide a URL to an audio or video file.',
            'src.url' => 'The source must be a valid URL.',
        ];
    }
}
