<?php

namespace App\Services;

use App\Models\Capture;

class ReviewIndexWriter
{
    public function __construct(
        private CharlieMindStorage $storage,
    ) {}

    public function append(Capture $capture, CaptureProcessingResult $result, CaptureReviewRoute $reviewRoute, string $processedPath): void
    {
        if (! $reviewRoute->needsReview) {
            return;
        }

        $indexPath = $this->indexPath();
        $contents = $this->storage->exists($indexPath)
            ? $this->storage->get($indexPath)
            : $this->initialContents();
        $link = $this->wikiLink($processedPath);

        if (str_contains($contents, '[['.$link.']]')) {
            return;
        }

        $reason = $reviewRoute->reviewReason ?? 'needs-review';
        $entry = '- [ ] [['.$link.']] - '.$reason.', '.$result->type.' capture, '.$capture->capture_id.PHP_EOL;

        $this->storage->putVaultFile($indexPath, rtrim($contents).PHP_EOL.$entry);
    }

    private function indexPath(): string
    {
        return trim((string) config('charliemind.processor_review_index', 'Review/_Review Index.md'), '/') ?: 'Review/_Review Index.md';
    }

    private function initialContents(): string
    {
        return implode(PHP_EOL, [
            '# Review Index',
            '',
            'Notes here were processed automatically but need a quick human check.',
            '',
            '## Needs review',
            '',
        ]);
    }

    private function wikiLink(string $processedPath): string
    {
        return str($processedPath)->replaceEnd('.md', '')->toString();
    }
}
