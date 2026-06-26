<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCaptureRequest;
use App\Models\Capture;
use App\Services\CaptureIdGenerator;
use App\Services\CaptureMarkdownRenderer;
use App\Services\CapturePathGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CaptureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(Capture::STATUSES)],
            'type' => ['nullable', 'string', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $captures = Capture::query()
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['type'] ?? null, fn ($query, string $type) => $query->where('type', Capture::normalizeType($type)))
            ->latest()
            ->limit($validated['limit'] ?? 50)
            ->get()
            ->map(fn (Capture $capture): array => $this->capturePayload($capture));

        return response()->json([
            'captures' => $captures,
        ]);
    }

    public function store(
        StoreCaptureRequest $request,
        CaptureIdGenerator $captureIdGenerator,
        CapturePathGenerator $pathGenerator,
        CaptureMarkdownRenderer $markdownRenderer,
    ): JsonResponse {
        $validated = $request->validated();
        $type = Capture::normalizeType($validated['type'] ?? null);
        $capturedAt = isset($validated['captured_at'])
            ? Carbon::parse($validated['captured_at'])
            : now();
        $captureId = $captureIdGenerator->generate($capturedAt);
        $metadata = $validated['metadata'] ?? [];
        $file = $request->file('file');
        $mediaPath = null;
        $mediaMime = null;
        $mediaOriginalName = null;

        if ($file !== null) {
            $mediaPath = $pathGenerator->mediaPath($type, $captureId, $file);
            Storage::disk('charliemind')->put($mediaPath, $file->getContent());
            $mediaMime = $file->getMimeType();
            $mediaOriginalName = $file->getClientOriginalName();
        }

        if ($file === null && in_array($type, [Capture::TYPE_VOICE, Capture::TYPE_PHOTO, Capture::TYPE_DOCUMENT], true)) {
            $metadata['media_missing'] = true;
        }

        $capture = Capture::query()->create([
            'capture_id' => $captureId,
            'type' => $type,
            'title' => $validated['title'] ?? null,
            'body' => $validated['body'] ?? null,
            'url' => $validated['url'] ?? null,
            'source' => $validated['source'] ?? 'iphone',
            'status' => Capture::STATUS_PENDING,
            'markdown_path' => $pathGenerator->markdownPath($type, $captureId),
            'media_path' => $mediaPath,
            'media_mime' => $mediaMime,
            'media_original_name' => $mediaOriginalName,
            'metadata' => $metadata,
            'captured_at' => $capturedAt,
        ]);

        Storage::disk('charliemind')->put($capture->markdown_path, $markdownRenderer->render($capture));

        return response()->json([
            'success' => true,
            'capture' => $this->capturePayload($capture),
        ], 201);
    }

    public function show(string $capture): JsonResponse
    {
        $captureModel = Capture::query()
            ->where('capture_id', $capture)
            ->firstOrFail();

        return response()->json([
            'capture' => $this->capturePayload($captureModel),
        ]);
    }

    /**
     * @return array{id: int|null, capture_id: string, type: string, status: string, markdown_path: string, media_path: string|null}
     */
    private function capturePayload(Capture $capture): array
    {
        return [
            'id' => $capture->id,
            'capture_id' => $capture->capture_id,
            'type' => $capture->type,
            'status' => $capture->status,
            'markdown_path' => $capture->markdown_path,
            'media_path' => $capture->media_path,
        ];
    }
}
