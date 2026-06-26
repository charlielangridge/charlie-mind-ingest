<?php

namespace App\Http\Requests;

use App\Models\Capture;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCaptureRequest extends FormRequest
{
    public const ALLOWED_FILE_EXTENSIONS = [
        'm4a',
        'mp3',
        'wav',
        'aac',
        'caf',
        'jpg',
        'jpeg',
        'png',
        'heic',
        'webp',
        'pdf',
        'txt',
        'md',
        'doc',
        'docx',
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'max:50'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'url' => ['nullable', 'url', 'max:2048'],
            'source' => ['nullable', 'string', 'max:255'],
            'captured_at' => ['nullable', 'date'],
            'file' => ['nullable', 'file', 'max:51200', 'extensions:'.implode(',', self::ALLOWED_FILE_EXTENSIONS)],
            'metadata' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $metadata = $this->input('metadata');

        if (is_string($metadata)) {
            $decodedMetadata = json_decode($metadata, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedMetadata)) {
                $metadata = $decodedMetadata;
            }
        }

        $this->merge([
            'type' => Capture::normalizeType($this->input('type')),
            'source' => $this->input('source', 'iphone'),
            'metadata' => $metadata,
        ]);
    }
}
