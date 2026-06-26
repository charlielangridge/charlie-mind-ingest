<?php

namespace App\Services;

use App\Models\Capture;

class ProcessedNoteRenderer
{
    public function render(Capture $capture, CaptureContent $content, CaptureProcessingResult $result, CaptureReviewRoute $reviewRoute): string
    {
        return implode(PHP_EOL.PHP_EOL, array_filter([
            $this->frontMatter($capture, $result, $reviewRoute),
            '# '.$result->title,
            $this->reviewTag($reviewRoute),
            $this->summary($result),
            $this->actions($result),
            $this->notes($result),
            $this->transcript($content),
            $this->source($capture),
        ])).PHP_EOL;
    }

    private function frontMatter(Capture $capture, CaptureProcessingResult $result, CaptureReviewRoute $reviewRoute): string
    {
        $created = ($capture->captured_at ?? $capture->created_at ?? now())->format('Y-m-d H:i');
        $tags = array_values(array_unique(array_filter($reviewRoute->tags)));

        $lines = [
            '---',
            'created: '.$created,
            'processed_at: '.now()->format('Y-m-d H:i'),
            'capture_id: '.$capture->capture_id,
            'source: '.$this->yamlString($capture->source ?? 'iphone-shortcut'),
            'original_type: '.$capture->type,
            'processed: true',
            'confidence: '.$result->confidence,
            'needs_review: '.($reviewRoute->needsReview ? 'true' : 'false'),
        ];

        if ($reviewRoute->reviewReason !== null) {
            $lines[] = 'review_reason: '.$reviewRoute->reviewReason;
        }

        $lines[] = 'tags:';

        foreach ($tags as $tag) {
            $lines[] = '  - '.$this->tag($tag);
        }

        $lines[] = '---';

        return implode(PHP_EOL, $lines);
    }

    private function reviewTag(CaptureReviewRoute $reviewRoute): string
    {
        return $reviewRoute->needsReview ? '#needs-review' : '';
    }

    private function summary(CaptureProcessingResult $result): string
    {
        if ($result->summary === '') {
            return '';
        }

        return '## Summary'.PHP_EOL.PHP_EOL.$result->summary;
    }

    private function actions(CaptureProcessingResult $result): string
    {
        if ($result->tasks === []) {
            return '';
        }

        $tasks = array_map(
            fn (string $task): string => '- [ ] '.$task,
            $result->tasks,
        );

        return '## Actions'.PHP_EOL.PHP_EOL.implode(PHP_EOL, $tasks);
    }

    private function notes(CaptureProcessingResult $result): string
    {
        if ($result->body === '') {
            return '';
        }

        return '## Notes'.PHP_EOL.PHP_EOL.$result->body;
    }

    private function transcript(CaptureContent $content): string
    {
        if ($content->transcript === null || trim($content->transcript) === '') {
            return '';
        }

        return '## Transcript'.PHP_EOL.PHP_EOL.trim($content->transcript);
    }

    private function source(Capture $capture): string
    {
        $lines = [
            'Original capture: [['.$capture->markdown_path.']]',
        ];

        if ($capture->media_path !== null) {
            $lines[] = 'Audio: [['.$capture->media_path.']]';
        }

        return '## Source'.PHP_EOL.PHP_EOL.implode(PHP_EOL, $lines);
    }

    private function yamlString(string $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function tag(string $tag): string
    {
        return str($tag)->lower()->replaceMatches('/[^a-z0-9\-]/', '-')->trim('-')->toString();
    }
}
