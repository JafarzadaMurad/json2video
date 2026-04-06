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
            'src' => 'required_without:file|nullable|url|max:1000',
            'file' => 'required_without:src|nullable|file|mimes:mp3,wav,m4a,aac,ogg,flac,mp4,webm,mov,avi,mkv|max:512000',
            'language' => 'sometimes|nullable|string|max:5',
        ];
    }

    public function messages(): array
    {
        return [
            'src.required_without' => 'Provide either a source URL (src) or upload a file (file).',
            'file.required_without' => 'Provide either a source URL (src) or upload a file (file).',
            'src.url' => 'The source must be a valid URL.',
            'file.mimes' => 'File must be: mp3, wav, m4a, aac, ogg, flac, mp4, webm, mov, avi, mkv.',
            'file.max' => 'File size must not exceed 500 MB.',
        ];
    }
}
