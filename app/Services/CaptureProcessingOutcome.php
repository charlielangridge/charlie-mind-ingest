<?php

namespace App\Services;

use App\Models\Capture;

class CaptureProcessingOutcome
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public Capture $capture,
        public string $status,
        public ?string $processedPath = null,
        public ?string $message = null,
    ) {}

    public function failed(): bool
    {
        return $this->status === Capture::STATUS_FAILED;
    }
}
