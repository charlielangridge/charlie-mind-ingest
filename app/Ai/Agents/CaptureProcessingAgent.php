<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class CaptureProcessingAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are processing raw mobile captures for CharlieMind, an Obsidian vault.

Your job is to transform rough iPhone captures into clean, useful, linked Markdown notes.

Preserve the user's meaning.
Do not invent facts.
Extract actions only when clearly present.
Use Obsidian links only when obvious.
Add tags sparingly.
If uncertain, choose general Notes or Voice and set confidence to low.
Return only valid JSON matching the configured schema.

Known entities:
- Penguin Media Solutions
- Penguin Media Hire
- Ganda Media
- DoorScan
- CharlieMind
- Scouts
- Wine
- Dylan
- Martin Audio
- Brighton Marquees
- Laravel
- Obsidian
- Codex
PROMPT;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'summary' => $schema->string()->required(),
            'body' => $schema->string()->required(),
            'type' => $schema->string()->enum(['task', 'idea', 'development', 'scouts', 'wine', 'link', 'person', 'company', 'project', 'note', 'voice'])->required(),
            'folder' => $schema->string()->enum(['Tasks', 'Ideas', 'Development', 'Scouts', 'Wine', 'Links', 'People', 'Companies', 'Projects', 'Notes', 'Voice'])->required(),
            'tags' => $schema->array()->items($schema->string())->required(),
            'tasks' => $schema->array()->items($schema->string())->required(),
            'links' => $schema->array()->items($schema->string())->required(),
            'confidence' => $schema->string()->enum(['high', 'medium', 'low'])->required(),
        ];
    }
}
