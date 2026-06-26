<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CaptureExportService;
use App\Services\CharlieMindStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class ExportController extends Controller
{
    public function pending(Request $request, CaptureExportService $exports): array
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'include_raw' => [
                'nullable',
                function (string $attribute, mixed $value, callable $fail): void {
                    if (! in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false', 'on', 'off', 'yes', 'no'], true)) {
                        $fail('The '.$attribute.' field must be true or false.');
                    }
                },
            ],
        ]);

        return [
            'exports' => $exports->pending(
                limit: $validated['limit'] ?? 50,
                includeRaw: $request->boolean('include_raw'),
            )->values(),
        ];
    }

    public function file(Request $request, CaptureExportService $exports, CharlieMindStorage $storage): Response
    {
        $validated = $request->validate([
            'path' => ['required', 'string'],
        ]);

        $path = $exports->normalizePath($validated['path']);

        if ($path === null) {
            throw ValidationException::withMessages([
                'path' => ['The path is not a safe vault-relative path.'],
            ]);
        }

        if (! $exports->filePathIsExportable($path) || ! $storage->exists($path)) {
            abort(404);
        }

        $headers = [
            'Content-Type' => $storage->mimeType($path) ?? 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.basename($path).'"',
        ];

        $size = $storage->size($path);

        if ($size !== null) {
            $headers['Content-Length'] = (string) $size;
        }

        return response($storage->get($path), 200, $headers);
    }

    public function markComplete(Request $request, CaptureExportService $exports): array
    {
        $validated = $request->validate([
            'capture_ids' => ['required', 'array', 'min:1'],
            'capture_ids.*' => ['required', 'string'],
        ]);

        $result = $exports->markComplete($validated['capture_ids']);

        if ($result['exported'] === []) {
            throw ValidationException::withMessages([
                'capture_ids' => ['No known capture IDs were provided.'],
            ]);
        }

        return [
            'success' => true,
            'exported' => $result['exported'],
            'unknown' => $result['unknown'],
        ];
    }
}
