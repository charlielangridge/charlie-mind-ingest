<?php

namespace App\Services;

class CaptureReviewRoute
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public string $folder,
        public bool $needsReview,
        public ?string $reviewReason,
        public array $tags,
    ) {}
}
