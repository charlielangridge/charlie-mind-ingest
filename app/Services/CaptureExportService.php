<?php

namespace App\Services;

use App\Models\Capture;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CaptureExportService
{
    public function __construct(
        private CharlieMindStorage $storage,
        private VaultPathValidator $pathValidator,
        private CaptureStagingPaths $stagingPaths,
    ) {}

    /**
     * @return Collection<int, array{capture_id: string, type: string, status: string, needs_review: bool, review_reason: string|null, suggested_folder: string|null, suggested_path: string|null, processed_markdown_path: string, files: array<int, array{role: string, path: string, mime: string, size: int|null}>}>
     */
    public function pending(int $limit = 50, bool $includeRaw = false): Collection
    {
        return $this->pendingQuery()
            ->orderBy('processed_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (Capture $capture): array => $this->manifestEntry($capture, $includeRaw));
    }

    public function filePathIsExportable(string $path): bool
    {
        $path = $this->pathValidator->normalize($path);

        if ($path === null) {
            return false;
        }

        if (dirname($path) === $this->stagingPaths->rawFolder()) {
            $captureId = pathinfo($path, PATHINFO_FILENAME);

            if ($path !== $this->stagingPaths->rawFolder().'/'.$captureId.'.md') {
                return false;
            }

            return Capture::query()
                ->where('status', Capture::STATUS_PROCESSED)
                ->whereNotNull('processed_at')
                ->whereNotNull('markdown_path')
                ->where('capture_id', $captureId)
                ->exists();
        }

        return Capture::query()
            ->where('status', Capture::STATUS_PROCESSED)
            ->whereNotNull('processed_at')
            ->where(function (Builder $query) use ($path): void {
                $query
                    ->where('processed_markdown_path', $path)
                    ->orWhere('media_path', $path);
            })
            ->exists();
    }

    public function normalizePath(?string $path): ?string
    {
        return $this->pathValidator->normalize($path);
    }

    /**
     * @param  array<int, string>  $captureIds
     * @return array{exported: array<int, string>, unknown: array<int, string>}
     */
    public function markComplete(array $captureIds): array
    {
        $requestedIds = collect($captureIds)
            ->filter(fn (mixed $captureId): bool => is_string($captureId) && $captureId !== '')
            ->unique()
            ->values();

        $captures = Capture::query()
            ->whereIn('capture_id', $requestedIds)
            ->get();

        $knownIds = $captures->pluck('capture_id');
        $unknownIds = $requestedIds->diff($knownIds)->values();

        foreach ($captures as $capture) {
            $capture->forceFill([
                'exported_at' => now(),
                'export_status' => Capture::EXPORT_STATUS_EXPORTED,
                'export_error' => null,
                'last_export_attempt_at' => now(),
                'export_attempts' => ((int) $capture->export_attempts) + 1,
            ])->save();
        }

        return [
            'exported' => $knownIds->values()->all(),
            'unknown' => $unknownIds->values()->all(),
        ];
    }

    private function pendingQuery(): Builder
    {
        return Capture::query()
            ->where('status', Capture::STATUS_PROCESSED)
            ->whereNotNull('processed_at')
            ->whereNotNull('processed_markdown_path')
            ->whereNull('exported_at')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('export_status')
                    ->orWhereNotIn('export_status', [
                        Capture::EXPORT_STATUS_EXPORTED,
                        Capture::EXPORT_STATUS_SKIPPED,
                    ]);
            });
    }

    /**
     * @return array{capture_id: string, type: string, status: string, needs_review: bool, review_reason: string|null, suggested_folder: string|null, suggested_path: string|null, processed_markdown_path: string, files: array<int, array{role: string, path: string, mime: string, size: int|null}>}
     */
    private function manifestEntry(Capture $capture, bool $includeRaw): array
    {
        $files = [];

        if (is_string($capture->processed_markdown_path)) {
            $files[] = $this->fileEntry('processed_note', $capture->processed_markdown_path, 'text/markdown');
        }

        if (is_string($capture->media_path)) {
            $files[] = $this->fileEntry('media', $capture->media_path, $capture->media_mime ?? 'application/octet-stream');
        }

        if ($includeRaw && is_string($capture->markdown_path)) {
            $files[] = $this->fileEntry('raw_capture', $this->stagingPaths->rawPath($capture), 'text/markdown');
        }

        return [
            'capture_id' => $capture->capture_id,
            'type' => $capture->type,
            'status' => $capture->status,
            'needs_review' => (bool) $capture->needs_review,
            'review_reason' => $capture->review_reason,
            'suggested_folder' => $capture->suggested_folder,
            'suggested_path' => $capture->suggested_path,
            'processed_markdown_path' => $capture->processed_markdown_path,
            'files' => array_values(array_filter($files)),
        ];
    }

    /**
     * @return array{role: string, path: string, mime: string, size: int|null}|null
     */
    private function fileEntry(string $role, string $path, string $fallbackMime): ?array
    {
        $path = $this->pathValidator->normalize($path);

        if ($path === null || ! $this->storage->exists($path)) {
            return null;
        }

        return [
            'role' => $role,
            'path' => $path,
            'mime' => $this->storage->mimeType($path) ?? $fallbackMime,
            'size' => $this->storage->size($path),
        ];
    }
}
