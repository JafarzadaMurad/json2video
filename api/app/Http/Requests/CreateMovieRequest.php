<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateMovieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware
    }

    public function rules(): array
    {
        return [
            'resolution' => 'sometimes|string|in:sd,hd,full-hd,4k,custom',
            'width' => 'required_if:resolution,custom|sometimes|integer|min:100|max:7680',
            'height' => 'required_if:resolution,custom|sometimes|integer|min:100|max:4320',
            'quality' => 'sometimes|string|in:low,medium,high',
            'fps' => 'sometimes|integer|in:24,25,30,60',
            'webhook_url' => 'sometimes|nullable|url',

            // Scenes
            'scenes' => 'required|array|min:1',
            'scenes.*.comment' => 'sometimes|string|max:255',
            'scenes.*.duration' => 'required|numeric|min:0.1|max:600',
            'scenes.*.background' => 'sometimes|string|max:20',

            // Transitions
            'scenes.*.transition' => 'sometimes|array',
            'scenes.*.transition.type' => 'sometimes|string|in:fade,slide-left,slide-right,slide-up,slide-down,zoom-in,zoom-out,wipe,dissolve',
            'scenes.*.transition.duration' => 'sometimes|numeric|min:0.1|max:3',

            // Elements
            'scenes.*.elements' => 'required|array|min:1',
            'scenes.*.elements.*.type' => 'required|string|in:image,video,text,audio,subtitles',

            // Image/Video/Audio/Subtitles elements
            'scenes.*.elements.*.src' => 'required_if:scenes.*.elements.*.type,image,video,audio|nullable|string',
            'scenes.*.elements.*.width' => 'sometimes|integer|min:1|max:7680',
            'scenes.*.elements.*.height' => 'sometimes|integer|min:1|max:4320',
            'scenes.*.elements.*.x' => 'sometimes|integer',
            'scenes.*.elements.*.y' => 'sometimes|integer',

            // Text elements (subtitles can use text OR src)
            'scenes.*.elements.*.text' => 'required_if:scenes.*.elements.*.type,text|nullable|string',
            'scenes.*.elements.*.font-size' => 'sometimes|integer|min:1|max:500',
            'scenes.*.elements.*.color' => 'sometimes|string|max:20',
            'scenes.*.elements.*.background-color' => 'sometimes|string|max:20',
            'scenes.*.elements.*.font-family' => 'sometimes|string|max:100',
            'scenes.*.elements.*.text-align' => 'sometimes|string|in:left,center,right',
            'scenes.*.elements.*.max-width' => 'sometimes|integer|min:1',

            // Timing & Animation
            'scenes.*.elements.*.start' => 'sometimes|numeric|min:0',
            'scenes.*.elements.*.duration' => 'sometimes|numeric|min:0.1',
            'scenes.*.elements.*.opacity' => 'sometimes|numeric|min:0|max:1',

            // Animation
            'scenes.*.elements.*.animation' => 'sometimes|array',
            'scenes.*.elements.*.animation.type' => 'sometimes|string|in:fade-in,fade-out,slide-in-left,slide-in-right,slide-in-top,slide-in-bottom,zoom-in,zoom-out,bounce',
            'scenes.*.elements.*.animation.duration' => 'sometimes|numeric|min:0.1|max:5',
            'scenes.*.elements.*.animation.easing' => 'sometimes|string|in:linear,ease-in,ease-out,ease-in-out',

            // Audio
            'scenes.*.elements.*.volume' => 'sometimes|numeric|min:0|max:1',
        ];
    }

    public function messages(): array
    {
        return [
            'scenes.required' => 'At least one scene is required',
            'scenes.*.elements.required' => 'Each scene must have at least one element',
            'scenes.*.elements.*.type.in' => 'Element type must be one of: image, video, text, audio, subtitles',
            'resolution.in' => 'Resolution must be one of: sd, hd, full-hd, 4k, custom',
            'width.required_if' => 'Width is required when resolution is "custom"',
            'height.required_if' => 'Height is required when resolution is "custom"',
        ];
    }
}
