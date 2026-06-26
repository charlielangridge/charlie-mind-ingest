<?php

namespace App\Services;

use App\Models\Capture;
use Illuminate\Support\Str;

class ProcessedNotePathGenerator
{
    public function __construct(
        private CharlieMindStorage $storage,
    ) {}

    public function pathFor(Capture $capture, CaptureProcessingResult $result, string $folder): string
    {
        $date = ($capture->captured_at ?? $capture->created_at ?? now())->format('Y-m-d');
        $folder = trim($folder, '/') ?: 'Notes';
        $title = $this->filenameTitle($result->title !== '' ? $result->title : 'captured note');
        $basePath = "{$folder}/{$date} - {$title}.md";

        if (! $this->storage->exists($basePath)) {
            return $basePath;
        }

        for ($suffix = 2; $suffix < 1000; $suffix++) {
            $path = "{$folder}/{$date} - {$title}-{$suffix}.md";

            if (! $this->storage->exists($path)) {
                return $path;
            }
        }

        return "{$folder}/{$date} - {$title}-".Str::random(6).'.md';
    }

    private function filenameTitle(string $title): string
    {
        $safeTitle = Str::of($title)
            ->lower()
            ->ascii()
            ->replaceMatches('/[<>:"\/\\\\|?*\x00-\x1F]/u', ' ')
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->limit(90, '')
            ->toString();

        return $safeTitle !== '' ? $safeTitle : 'captured note';
    }
}
