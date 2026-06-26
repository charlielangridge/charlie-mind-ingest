<?php

namespace App\Services;

use App\Models\Capture;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

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

        $yaml = substr($normalized, 4, $endPosition - 4);
        $body = substr($normalized, $endPosition + 5);

        try {
            $frontMatter = Yaml::parse($yaml);
        } catch (ParseException) {
            $frontMatter = [];
        }

        return [is_array($frontMatter) ? $frontMatter : [], $body];
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
