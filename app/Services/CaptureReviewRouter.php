<?php

namespace App\Services;

use App\Models\Capture;

class CaptureReviewRouter
{
    public function route(Capture $capture, CaptureProcessingResult $result): CaptureReviewRoute
    {
        $mode = str((string) config('charliemind.processor_review_mode', 'confidence'))->lower()->trim()->toString();
        $confidence = $this->confidence($result->confidence);
        $folder = trim($result->folder, '/') ?: 'Notes';
        $needsReview = false;
        $reviewReason = null;

        if ($mode === 'confidence') {
            $needsReview = $this->rank($confidence) <= $this->rank($this->threshold());

            if ($needsReview) {
                $reviewReason = $confidence.'-confidence';
            }

            if ($confidence === 'medium' && $this->mediumReviewTag()) {
                $needsReview = true;
                $reviewReason = 'medium-confidence';
            }

            if ($needsReview && $this->rank($confidence) <= $this->rank($this->threshold())) {
                $folder = $this->reviewFolder();
            }
        }

        return new CaptureReviewRoute(
            folder: $folder,
            needsReview: $needsReview,
            reviewReason: $reviewReason,
            tags: $this->tags($capture, $result, $needsReview),
        );
    }

    private function threshold(): string
    {
        return $this->confidence((string) config('charliemind.processor_review_confidence_threshold', 'low'));
    }

    private function confidence(string $confidence): string
    {
        $confidence = str($confidence)->lower()->trim()->toString();

        return in_array($confidence, ['low', 'medium', 'high'], true) ? $confidence : 'low';
    }

    private function rank(string $confidence): int
    {
        return match ($confidence) {
            'high' => 3,
            'medium' => 2,
            default => 1,
        };
    }

    private function mediumReviewTag(): bool
    {
        return filter_var(config('charliemind.processor_medium_review_tag', true), FILTER_VALIDATE_BOOL);
    }

    private function reviewFolder(): string
    {
        return trim((string) config('charliemind.processor_review_folder', 'Review'), '/') ?: 'Review';
    }

    /**
     * @return array<int, string>
     */
    private function tags(Capture $capture, CaptureProcessingResult $result, bool $needsReview): array
    {
        $tags = array_merge(['mobile-capture', $capture->type], $result->tags);

        if ($needsReview) {
            $tags[] = 'needs-review';
        }

        return array_values(array_unique(array_filter($tags)));
    }
}
