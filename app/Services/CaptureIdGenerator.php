<?php

namespace App\Services;

use App\Models\Capture;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CaptureIdGenerator
{
    public function generate(?Carbon $capturedAt = null): string
    {
        $baseCaptureId = ($capturedAt ?? now())->format('Y-m-d-His');
        $captureId = $baseCaptureId;

        while (Capture::query()->where('capture_id', $captureId)->exists()) {
            $captureId = $baseCaptureId.'-'.Str::lower(Str::random(4));
        }

        return $captureId;
    }
}
