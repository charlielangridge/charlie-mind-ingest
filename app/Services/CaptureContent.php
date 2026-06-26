<?php

namespace App\Services;

class CaptureContent
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $frontMatter
     */
    public function __construct(
        public string $rawMarkdown,
        public string $body,
        public ?string $title,
        public string $type,
        public ?string $mediaPath,
        public ?string $url,
        public array $metadata = [],
        public array $frontMatter = [],
        public ?string $transcript = null,
    ) {}
}
