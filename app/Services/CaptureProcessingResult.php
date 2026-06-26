<?php

namespace App\Services;

class CaptureProcessingResult
{
    /**
     * @param  array<int, string>  $tags
     * @param  array<int, string>  $tasks
     * @param  array<int, string>  $links
     */
    public function __construct(
        public string $title,
        public string $summary,
        public string $body,
        public string $type,
        public string $folder,
        public array $tags = [],
        public array $tasks = [],
        public array $links = [],
        public string $confidence = 'low',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: self::stringValue($data['title'] ?? 'Captured note'),
            summary: self::stringValue($data['summary'] ?? ''),
            body: self::stringValue($data['body'] ?? ''),
            type: self::typeValue($data['type'] ?? 'note'),
            folder: self::folderValue($data['folder'] ?? 'Notes'),
            tags: self::stringList($data['tags'] ?? []),
            tasks: self::stringList($data['tasks'] ?? []),
            links: self::stringList($data['links'] ?? []),
            confidence: self::confidenceValue($data['confidence'] ?? 'low'),
        );
    }

    private static function stringValue(mixed $value): string
    {
        return trim((string) $value);
    }

    private static function typeValue(mixed $value): string
    {
        $type = str((string) $value)->lower()->trim()->toString();

        return in_array($type, ['task', 'idea', 'development', 'scouts', 'wine', 'link', 'person', 'company', 'project', 'note', 'voice'], true)
            ? $type
            : 'note';
    }

    private static function folderValue(mixed $value): string
    {
        $folder = str((string) $value)->trim('/ ')->studly()->toString();

        return in_array($folder, ['Tasks', 'Ideas', 'Development', 'Scouts', 'Wine', 'Links', 'People', 'Companies', 'Projects', 'Notes', 'Voice'], true)
            ? $folder
            : 'Notes';
    }

    /**
     * @return array<int, string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): string => trim((string) $item),
            $value,
        )));
    }

    private static function confidenceValue(mixed $value): string
    {
        $confidence = str((string) $value)->lower()->trim()->toString();

        return in_array($confidence, ['high', 'medium', 'low'], true) ? $confidence : 'low';
    }
}
