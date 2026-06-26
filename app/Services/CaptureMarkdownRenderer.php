<?php

namespace App\Services;

use App\Models\Capture;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CaptureMarkdownRenderer
{
    public function render(Capture $capture): string
    {
        $frontMatter = $this->frontMatter($capture);
        $body = $this->body($capture);

        return $frontMatter.PHP_EOL.PHP_EOL.$body.PHP_EOL;
    }

    /**
     * @return array<string, string>
     */
    public function defaultTitles(): array
    {
        return [
            Capture::TYPE_QUICK => 'Quick Note',
            Capture::TYPE_TASK => 'Task',
            Capture::TYPE_IDEA => 'Idea',
            Capture::TYPE_DEV => 'Development Note',
            Capture::TYPE_SCOUTS => 'Scout Note',
            Capture::TYPE_WINE => 'Wine Note',
            Capture::TYPE_LINK => 'Link',
            Capture::TYPE_VOICE => 'Voice Note',
            Capture::TYPE_PHOTO => 'Photo Capture',
            Capture::TYPE_DOCUMENT => 'Document Capture',
            Capture::TYPE_GENERAL => 'General Note',
        ];
    }

    private function frontMatter(Capture $capture): string
    {
        $capturedAt = $capture->captured_at ?? $capture->created_at ?? now();
        $lines = [
            '---',
            'created: '.$this->formatDate($capture->created_at ?? now()),
            'captured_at: '.$this->formatDate($capturedAt),
            'capture_id: '.$capture->capture_id,
            'source: '.$this->yamlString($capture->source ?? 'iphone'),
            'type: '.$capture->type,
            'processed: false',
            'status: '.$capture->status,
        ];

        if ($capture->media_path !== null) {
            $lines[] = 'media_path: '.$this->yamlString($capture->media_path);
        }

        if (($capture->metadata['media_missing'] ?? false) === true) {
            $lines[] = 'media_missing: true';
        }

        $lines[] = '---';

        return implode(PHP_EOL, $lines);
    }

    private function body(Capture $capture): string
    {
        $title = $this->title($capture);
        $body = trim((string) $capture->body);

        return match ($capture->type) {
            Capture::TYPE_TASK => $this->taskBody($title, $body),
            Capture::TYPE_LINK => $this->linkBody($title, $capture->url, $body),
            Capture::TYPE_VOICE => $this->mediaBody($title, $capture->media_path, $body, '#mobile-capture #voice #audio', '_No audio file was attached._'),
            Capture::TYPE_PHOTO => $this->mediaBody($title, $capture->media_path, $body, '#mobile-capture #photo', '_No photo file was attached._'),
            Capture::TYPE_DOCUMENT => $this->mediaBody($title, $capture->media_path, $body, '#mobile-capture #document', '_No document file was attached._'),
            default => $this->textBody($title, $body, $this->tags($capture->type)),
        };
    }

    private function title(Capture $capture): string
    {
        $title = trim((string) $capture->title);

        if ($title === '') {
            return $this->defaultTitles()[$capture->type] ?? $this->defaultTitles()[Capture::TYPE_GENERAL];
        }

        return Str::of($title)
            ->replace(["\r", "\n"], ' ')
            ->replaceMatches('/^#+\s*/', '')
            ->squish()
            ->toString();
    }

    private function textBody(string $title, string $body, string $tags): string
    {
        return "# {$title}".PHP_EOL.PHP_EOL.$body.PHP_EOL.PHP_EOL.$tags;
    }

    private function taskBody(string $title, string $body): string
    {
        $task = $body !== '' ? $body : 'Captured task';

        return "# {$title}".PHP_EOL.PHP_EOL."- [ ] {$task}".PHP_EOL.PHP_EOL.'#mobile-capture #task';
    }

    private function linkBody(string $title, ?string $url, string $body): string
    {
        $linkText = $title !== 'Link' ? $title : ($url ?? 'Link');
        $link = $url !== null ? "[{$linkText}]({$url})" : $linkText;

        return "# {$title}".PHP_EOL.PHP_EOL.$link.PHP_EOL.PHP_EOL.$body.PHP_EOL.PHP_EOL.'#mobile-capture #link';
    }

    private function mediaBody(string $title, ?string $mediaPath, string $body, string $tags, string $missingText): string
    {
        $media = $mediaPath !== null ? "![[{$mediaPath}]]" : $missingText;
        $sections = ["# {$title}", $media];

        if ($body !== '') {
            $sections[] = $body;
        }

        $sections[] = $tags;

        return implode(PHP_EOL.PHP_EOL, $sections);
    }

    private function tags(string $type): string
    {
        return match ($type) {
            Capture::TYPE_IDEA => '#mobile-capture #idea',
            Capture::TYPE_DEV => '#mobile-capture #dev',
            Capture::TYPE_SCOUTS => '#mobile-capture #scouts',
            Capture::TYPE_WINE => '#mobile-capture #wine',
            default => '#mobile-capture',
        };
    }

    private function formatDate(Carbon $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    private function yamlString(string $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
