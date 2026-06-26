<?php

namespace App\Services;

use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Transcription;
use RuntimeException;

class CaptureTranscriber
{
    public function __construct(
        private CharlieMindStorage $storage,
    ) {}

    public function transcribe(string $vaultRelativeMediaPath): string
    {
        if (! $this->hasOpenAiKey()) {
            throw new RuntimeException('OpenAI API key required for voice transcription');
        }

        $temporaryPath = $this->temporaryAudioPath($vaultRelativeMediaPath);

        try {
            $transcript = Transcription::fromPath($temporaryPath)
                ->timeout(120)
                ->generate(Lab::OpenAI, (string) config('charliemind.openai_transcription_model'));

            return trim((string) $transcript);
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    private function hasOpenAiKey(): bool
    {
        $key = config('ai.providers.openai.key');

        return is_string($key) && trim($key) !== '';
    }

    private function temporaryAudioPath(string $vaultRelativeMediaPath): string
    {
        $extension = pathinfo($vaultRelativeMediaPath, PATHINFO_EXTENSION) ?: 'bin';
        $temporaryPath = tempnam(sys_get_temp_dir(), 'charliemind-audio-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create temporary audio file');
        }

        $audioPath = $temporaryPath.'.'.$extension;
        rename($temporaryPath, $audioPath);
        file_put_contents($audioPath, $this->storage->get($vaultRelativeMediaPath));

        return $audioPath;
    }
}
