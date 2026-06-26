<?php

namespace App\Services;

use App\Models\Capture;

class CaptureContentExtractor
{
    public function __construct(
        private CharlieMindStorage $storage,
    ) {}

    public function extract(Capture $capture): CaptureContent
    {
        $rawMarkdown = $this->storage->get($capture->markdown_path);
        [$frontMatter, $body] = $this->splitMarkdown($rawMarkdown);

        return new CaptureContent(
            rawMarkdown: $rawMarkdown,
            body: $this->cleanBody($body),
            title: $capture->title ?: $this->titleFromBody($body),
            type: $capture->type,
            mediaPath: $capture->media_path,
            url: $capture->url,
            metadata: is_array($capture->metadata) ? $capture->metadata : [],
            frontMatter: $frontMatter,
            transcript: $capture->transcript,
        );
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function splitMarkdown(string $markdown): array
    {
        if (! str_starts_with($markdown, "---\n") && ! str_starts_with($markdown, "---\r\n")) {
            return [[], $markdown];
        }

        $normalized = str_replace("\r\n", "\n", $markdown);
        $endPosition = strpos($normalized, "\n---\n", 4);

        if ($endPosition === false) {
            return [[], $markdown];
        }

        $frontMatterText = substr($normalized, 4, $endPosition - 4);
        $body = substr($normalized, $endPosition + 5);

        return [$this->parseFrontMatter($frontMatterText), $body];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFrontMatter(string $frontMatter): array
    {
        $parsed = [];
        $currentListKey = null;

        foreach (explode("\n", $frontMatter) as $line) {
            if (trim($line) === '') {
                continue;
            }

            if ($currentListKey !== null && preg_match('/^\s+-\s*(.+)$/', $line, $matches) === 1) {
                $parsed[$currentListKey][] = $this->parseFrontMatterValue($matches[1]);

                continue;
            }

            $currentListKey = null;

            if (preg_match('/^([A-Za-z0-9_\-]+):\s*(.*)$/', $line, $matches) !== 1) {
                continue;
            }

            $key = $matches[1];
            $value = trim($matches[2]);

            if ($value === '') {
                $parsed[$key] = [];
                $currentListKey = $key;

                continue;
            }

            $parsed[$key] = $this->parseFrontMatterValue($value);
        }

        return $parsed;
    }

    private function parseFrontMatterValue(string $value): mixed
    {
        $value = trim($value);

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        if ($value === 'null') {
            return null;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $decoded = json_decode($value, true);

            return is_string($decoded) ? $decoded : trim($value, '"\'');
        }

        return $value;
    }

    private function cleanBody(string $body): string
    {
        return str($body)
            ->replaceMatches('/^# .+?(\R{2,}|\R)/u', '')
            ->replaceMatches('/#mobile-capture[^\r\n]*/u', '')
            ->trim()
            ->toString();
    }

    private function titleFromBody(string $body): ?string
    {
        if (preg_match('/^#\s+(.+)$/m', $body, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }
}
