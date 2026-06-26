<?php

namespace App\Services;

use App\Models\Capture;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CapturePathGenerator
{
    /**
     * @return array<int, string>
     */
    public function inboxDirectories(): array
    {
        return [
            'inbox/captures/quick',
            'inbox/captures/tasks',
            'inbox/captures/ideas',
            'inbox/captures/dev',
            'inbox/captures/scouts',
            'inbox/captures/wine',
            'inbox/captures/links',
            'inbox/captures/general',
            'inbox/voice',
            'inbox/audio',
            'inbox/photos',
            'inbox/media/photos',
            'inbox/media/documents',
            'inbox/processed',
            'inbox/failed',
        ];
    }

    public function markdownPath(string $type, string $captureId): string
    {
        return match ($type) {
            Capture::TYPE_QUICK => "inbox/captures/quick/{$captureId}.md",
            Capture::TYPE_TASK => "inbox/captures/tasks/{$captureId}.md",
            Capture::TYPE_IDEA => "inbox/captures/ideas/{$captureId}.md",
            Capture::TYPE_DEV => "inbox/captures/dev/{$captureId}.md",
            Capture::TYPE_SCOUTS => "inbox/captures/scouts/{$captureId}.md",
            Capture::TYPE_WINE => "inbox/captures/wine/{$captureId}.md",
            Capture::TYPE_LINK => "inbox/captures/links/{$captureId}.md",
            Capture::TYPE_VOICE => "inbox/voice/{$captureId}.md",
            Capture::TYPE_PHOTO => "inbox/photos/{$captureId}.md",
            default => "inbox/captures/general/{$captureId}.md",
        };
    }

    public function mediaPath(string $type, string $captureId, UploadedFile $file): string
    {
        $extension = Str::lower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $directory = $this->mediaDirectory($type, $extension, $file->getMimeType());

        return "{$directory}/{$captureId}.{$extension}";
    }

    private function mediaDirectory(string $type, string $extension, ?string $mimeType): string
    {
        if ($type === Capture::TYPE_VOICE || str_starts_with((string) $mimeType, 'audio/')) {
            return 'inbox/audio';
        }

        if ($type === Capture::TYPE_PHOTO || str_starts_with((string) $mimeType, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'heic', 'webp'], true)) {
            return 'inbox/media/photos';
        }

        return 'inbox/media/documents';
    }
}
