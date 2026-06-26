<?php

namespace App\Services;

use App\Models\Capture;

class CaptureStagingPaths
{
    public function processedFolder(): string
    {
        return $this->folder('staging_processed_folder', 'inbox/mobile-captures/processed');
    }

    public function reviewFolder(): string
    {
        return $this->folder('staging_review_folder', 'inbox/mobile-captures/review');
    }

    public function audioFolder(): string
    {
        return $this->folder('staging_audio_folder', 'inbox/mobile-captures/audio');
    }

    public function mediaFolder(): string
    {
        return $this->folder('staging_media_folder', 'inbox/mobile-captures/media');
    }

    public function rawFolder(): string
    {
        return $this->folder('staging_raw_folder', 'inbox/mobile-captures/raw');
    }

    public function logPath(): string
    {
        return $this->folder('staging_log_folder', 'inbox/mobile-captures/logs').'/processing-log.md';
    }

    public function reviewIndexPath(): string
    {
        return $this->reviewFolder().'/_Review Index.md';
    }

    public function rawPath(Capture $capture): string
    {
        return $this->rawFolder().'/'.$capture->capture_id.'.md';
    }

    public function mediaPath(Capture $capture): ?string
    {
        if ($capture->media_path === null) {
            return null;
        }

        $extension = pathinfo($capture->media_path, PATHINFO_EXTENSION) ?: 'bin';
        $folder = $this->isAudio($capture) ? $this->audioFolder() : $this->mediaFolder();

        return $folder.'/'.$capture->capture_id.'.'.$extension;
    }

    public function stagedProcessedFolder(string $confidence): string
    {
        return $this->confidence($confidence) === 'low'
            ? $this->reviewFolder()
            : $this->processedFolder();
    }

    private function folder(string $key, string $fallback): string
    {
        return trim((string) config('charliemind.'.$key, $fallback), '/') ?: $fallback;
    }

    private function isAudio(Capture $capture): bool
    {
        return $capture->type === Capture::TYPE_VOICE
            || str_starts_with((string) $capture->media_mime, 'audio/');
    }

    private function confidence(string $confidence): string
    {
        $confidence = str($confidence)->lower()->trim()->toString();

        return in_array($confidence, ['high', 'medium', 'low'], true) ? $confidence : 'low';
    }
}
