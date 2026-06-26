<?php

namespace App\Services;

use App\Ai\Agents\CaptureProcessingAgent;
use App\Models\Capture;
use Laravel\Ai\Enums\Lab;
use Throwable;

class CaptureAiProcessor
{
    public function process(Capture $capture, CaptureContent $content): CaptureProcessingResult
    {
        if ($this->hasOpenAiKey()) {
            return $this->processWithAi($capture, $content);
        }

        return $this->fallback($capture, $content);
    }

    private function processWithAi(Capture $capture, CaptureContent $content): CaptureProcessingResult
    {
        try {
            $response = (new CaptureProcessingAgent)->prompt(
                $this->prompt($capture, $content),
                provider: Lab::OpenAI,
                model: (string) config('charliemind.openai_text_model'),
                timeout: 60,
            );

            return CaptureProcessingResult::fromArray($response->toArray());
        } catch (Throwable) {
            return $this->fallback($capture, $content);
        }
    }

    private function hasOpenAiKey(): bool
    {
        $key = config('ai.providers.openai.key');

        return is_string($key) && trim($key) !== '';
    }

    private function prompt(Capture $capture, CaptureContent $content): string
    {
        return json_encode([
            'capture_id' => $capture->capture_id,
            'type' => $capture->type,
            'title' => $content->title,
            'url' => $content->url,
            'body' => $content->body,
            'transcript' => $content->transcript,
            'media_path' => $content->mediaPath,
            'metadata' => $content->metadata,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function fallback(Capture $capture, CaptureContent $content): CaptureProcessingResult
    {
        $body = trim($content->transcript ?? $content->body);
        $title = $this->isGenericTitle($content->title)
            ? $this->titleFromText($body, $capture->type)
            : (string) $content->title;
        $type = $this->processedType($capture->type);
        $folder = $this->folderForType($type);
        $tasks = $type === 'task' ? $this->tasksFromBody($body) : [];

        return new CaptureProcessingResult(
            title: $title,
            summary: $body !== '' ? str($body)->limit(180, '')->toString() : $title,
            body: $this->linkKnownEntities($body),
            type: $type,
            folder: $folder,
            tags: array_values(array_filter(['mobile-capture', $type])),
            tasks: $tasks,
            links: $this->knownEntityLinks($body),
            confidence: 'low',
        );
    }

    private function titleFromText(string $text, string $type): string
    {
        $firstSentence = str($text)->replaceMatches('/\s+/u', ' ')->before('.')->trim()->toString();

        if ($firstSentence !== '') {
            return str($firstSentence)->words(8, '')->headline()->toString();
        }

        return match ($type) {
            Capture::TYPE_TASK => 'Captured Task',
            Capture::TYPE_IDEA => 'Captured Idea',
            Capture::TYPE_VOICE => 'Voice Note',
            default => 'Captured Note',
        };
    }

    private function isGenericTitle(?string $title): bool
    {
        if ($title === null || trim($title) === '') {
            return true;
        }

        return in_array(strtolower(trim($title)), [
            'captured note',
            'quick note',
            'task',
            'idea',
            'development note',
            'scout note',
            'wine note',
            'link',
            'voice note',
            'general note',
        ], true);
    }

    private function processedType(string $type): string
    {
        return match ($type) {
            Capture::TYPE_TASK => 'task',
            Capture::TYPE_IDEA => 'idea',
            Capture::TYPE_DEV => 'development',
            Capture::TYPE_SCOUTS => 'scouts',
            Capture::TYPE_WINE => 'wine',
            Capture::TYPE_LINK => 'link',
            Capture::TYPE_VOICE => 'voice',
            default => 'note',
        };
    }

    private function folderForType(string $type): string
    {
        return match ($type) {
            'task' => 'Tasks',
            'idea' => 'Ideas',
            'development' => 'Development',
            'scouts' => 'Scouts',
            'wine' => 'Wine',
            'link' => 'Links',
            'person' => 'People',
            'company' => 'Companies',
            'project' => 'Projects',
            'voice' => 'Voice',
            default => 'Notes',
        };
    }

    /**
     * @return array<int, string>
     */
    private function tasksFromBody(string $body): array
    {
        $task = trim(preg_replace('/^\s*[-*]?\s*(\[[ xX]\])?\s*/', '', $body) ?? $body);

        return $task === '' ? ['Captured task'] : [$task];
    }

    private function linkKnownEntities(string $body): string
    {
        foreach ($this->knownEntities() as $entity) {
            $body = preg_replace('/(?<!\[\[)\b'.preg_quote($entity, '/').'\b(?!\]\])/u', '[['.$entity.']]', $body) ?? $body;
        }

        return $body;
    }

    /**
     * @return array<int, string>
     */
    private function knownEntityLinks(string $body): array
    {
        return array_values(array_filter(
            $this->knownEntities(),
            fn (string $entity): bool => str_contains(strtolower($body), strtolower($entity)),
        ));
    }

    /**
     * @return array<int, string>
     */
    private function knownEntities(): array
    {
        return [
            'Penguin Media Solutions',
            'Penguin Media Hire',
            'Ganda Media',
            'DoorScan',
            'CharlieMind',
            'Scouts',
            'Wine',
            'Dylan',
            'Martin Audio',
            'Brighton Marquees',
            'Laravel',
            'Obsidian',
            'Codex',
        ];
    }
}
