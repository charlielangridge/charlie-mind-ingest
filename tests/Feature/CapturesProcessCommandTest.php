<?php

use App\Ai\Agents\CaptureProcessingAgent;
use App\Models\Capture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Transcription;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'ai.providers.openai.key' => null,
        'charliemind.disk' => 'local',
        'charliemind.root' => 'charliemind',
        'charliemind.processor_enabled' => true,
        'charliemind.processor_dry_run' => false,
        'charliemind.processor_max_per_run' => 20,
    ]);

    Storage::fake('local');
    Carbon::setTestNow(Carbon::parse('2026-06-26 17:10:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function pendingCapture(array $attributes = [], string $markdown = "# Captured Note\n\nCaptured body."): Capture
{
    $capture = Capture::query()->create(array_merge([
        'capture_id' => '2026-06-26-161905',
        'type' => 'idea',
        'status' => Capture::STATUS_PENDING,
        'markdown_path' => 'inbox/captures/ideas/2026-06-26-161905.md',
        'captured_at' => '2026-06-26 16:19:05',
    ], $attributes));

    Storage::disk('local')->put('charliemind/'.$capture->markdown_path, $markdown);

    if ($capture->media_path !== null) {
        Storage::disk('local')->put('charliemind/'.$capture->media_path, 'audio');
    }

    return $capture;
}

test('processes a pending text capture and preserves the raw file', function () {
    $capture = pendingCapture(markdown: "# Idea\n\nDoorScan queue length estimation from recent scans.");

    $this->artisan('captures:process')
        ->expectsOutput('Processing 1 captures...')
        ->assertSuccessful();

    $capture->refresh();

    expect($capture->status)->toBe(Capture::STATUS_PROCESSED)
        ->and($capture->processed_markdown_path)->toBe('Ideas/2026-06-26 - doorscan queue length estimation from recent scans.md')
        ->and($capture->summary)->toContain('DoorScan queue length estimation')
        ->and($capture->suggested_title)->toBe('Doorscan Queue Length Estimation From Recent Scans');

    Storage::disk('local')->assertExists('charliemind/'.$capture->markdown_path);
    Storage::disk('local')->assertExists('charliemind/'.$capture->processed_markdown_path);

    expect(Storage::disk('local')->get('charliemind/'.$capture->processed_markdown_path))
        ->toContain('processed: true')
        ->toContain('[[DoorScan]]')
        ->toContain('Original capture: [['.$capture->markdown_path.']]');
});

test('task captures create markdown checklist items', function () {
    $capture = pendingCapture([
        'capture_id' => '2026-06-26-161906',
        'type' => Capture::TYPE_TASK,
        'markdown_path' => 'inbox/captures/tasks/2026-06-26-161906.md',
    ], "# Task\n\nCall Dylan about Martin Audio pricing.");

    $this->artisan('captures:process --type=task')->assertSuccessful();

    $capture->refresh();
    $markdown = Storage::disk('local')->get('charliemind/'.$capture->processed_markdown_path);

    expect($markdown)
        ->toContain('- [ ] Call Dylan about Martin Audio pricing.')
        ->toContain('[[Dylan]]')
        ->toContain('[[Martin Audio]]');
});

test('voice capture without an openai key fails clearly and stores the error', function () {
    $capture = pendingCapture([
        'type' => Capture::TYPE_VOICE,
        'markdown_path' => 'inbox/voice/2026-06-26-161905.md',
        'media_path' => 'inbox/audio/2026-06-26-161905.m4a',
    ], "# Voice Note\n\n![[inbox/audio/2026-06-26-161905.m4a]]");

    $this->artisan('captures:process --type=voice')
        ->expectsOutput('✗ 2026-06-26-161905 voice failed: OpenAI API key required for voice transcription')
        ->assertFailed();

    $capture->refresh();

    expect($capture->status)->toBe(Capture::STATUS_FAILED)
        ->and($capture->processing_error)->toBe('OpenAI API key required for voice transcription');
});

test('dry run does not write files or update status', function () {
    $capture = pendingCapture(markdown: "# Idea\n\nDry run body.");

    $this->artisan('captures:process --dry-run')
        ->expectsOutput('Dry run: processing 1 captures...')
        ->assertSuccessful();

    $capture->refresh();

    expect($capture->status)->toBe(Capture::STATUS_PENDING)
        ->and($capture->processed_markdown_path)->toBeNull();

    Storage::disk('local')->assertMissing('charliemind/Ideas/2026-06-26 - dry run body.md');
    Storage::disk('local')->assertMissing('charliemind/inbox/processing-log.md');
});

test('id option processes only the selected capture', function () {
    $first = pendingCapture(markdown: "# Idea\n\nSelected capture.");
    $second = pendingCapture([
        'capture_id' => '2026-06-26-161906',
        'markdown_path' => 'inbox/captures/ideas/2026-06-26-161906.md',
    ], "# Idea\n\nIgnored capture.");

    $this->artisan('captures:process --id='.$first->capture_id)->assertSuccessful();

    expect($first->refresh()->status)->toBe(Capture::STATUS_PROCESSED)
        ->and($second->refresh()->status)->toBe(Capture::STATUS_PENDING);
});

test('type option filters captures', function () {
    $idea = pendingCapture(markdown: "# Idea\n\nFiltered idea.");
    $task = pendingCapture([
        'capture_id' => '2026-06-26-161906',
        'type' => Capture::TYPE_TASK,
        'markdown_path' => 'inbox/captures/tasks/2026-06-26-161906.md',
    ], "# Task\n\nFiltered task.");

    $this->artisan('captures:process --type=task')->assertSuccessful();

    expect($idea->refresh()->status)->toBe(Capture::STATUS_PENDING)
        ->and($task->refresh()->status)->toBe(Capture::STATUS_PROCESSED);
});

test('fallback processor works without openai for text captures', function () {
    $capture = pendingCapture([
        'type' => Capture::TYPE_LINK,
        'url' => 'https://example.com',
        'markdown_path' => 'inbox/captures/links/2026-06-26-161905.md',
    ], "# Link\n\nInteresting Laravel package.");

    $this->artisan('captures:process')->assertSuccessful();

    $capture->refresh();

    expect($capture->processed_markdown_path)->toStartWith('Links/')
        ->and(Storage::disk('local')->get('charliemind/'.$capture->processed_markdown_path))->toContain('[[Laravel]]');
});

test('generated filenames are safe and unique', function () {
    Storage::disk('local')->put('charliemind/Ideas/2026-06-26 - duplicate title.md', 'existing');

    $capture = pendingCapture(markdown: "# Idea\n\nDuplicate title.");

    $this->artisan('captures:process')->assertSuccessful();

    expect($capture->refresh()->processed_markdown_path)->toBe('Ideas/2026-06-26 - duplicate title-2.md');
});

test('processing log is appended', function () {
    pendingCapture(markdown: "# Idea\n\nLogged capture.");
    Storage::disk('local')->put('charliemind/inbox/processing-log.md', '# Existing Log'.PHP_EOL);

    $this->artisan('captures:process')->assertSuccessful();

    expect(Storage::disk('local')->get('charliemind/inbox/processing-log.md'))
        ->toContain('# Existing Log')
        ->toContain('## 2026-06-26 17:10')
        ->toContain('Processed: 1')
        ->toContain('- ✓ 2026-06-26-161905 → Ideas/2026-06-26 - logged capture.md');
});

test('ai agent and transcription paths are fakeable', function () {
    config(['ai.providers.openai.key' => 'test-key']);

    CaptureProcessingAgent::fake([[
        'title' => 'Call Dylan About Martin Audio Pricing',
        'summary' => 'Need to call Dylan about pricing.',
        'body' => 'Discuss [[Martin Audio]] pricing with [[Dylan]].',
        'type' => 'task',
        'folder' => 'Tasks',
        'tags' => ['mobile-capture', 'voice'],
        'tasks' => ['Call Dylan'],
        'links' => ['Dylan', 'Martin Audio'],
        'confidence' => 'medium',
    ]]);

    Transcription::fake(['Call Dylan about Martin Audio pricing.']);

    $capture = pendingCapture([
        'type' => Capture::TYPE_VOICE,
        'markdown_path' => 'inbox/voice/2026-06-26-161905.md',
        'media_path' => 'inbox/audio/2026-06-26-161905.m4a',
    ], "# Voice Note\n\n![[inbox/audio/2026-06-26-161905.m4a]]");

    $this->artisan('captures:process --type=voice')->assertSuccessful();

    $capture->refresh();

    expect($capture->transcript)->toBe('Call Dylan about Martin Audio pricing.')
        ->and($capture->processed_markdown_path)->toBe('Tasks/2026-06-26 - call dylan about martin audio pricing.md')
        ->and(Storage::disk('local')->get('charliemind/'.$capture->processed_markdown_path))->toContain('## Transcript');

    CaptureProcessingAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Call Dylan about Martin Audio pricing.'));
    Transcription::assertGenerated(fn (): bool => true);
});

test('disabled processor skips cleanly', function () {
    config(['charliemind.processor_enabled' => false]);
    $capture = pendingCapture();

    $this->artisan('captures:process')
        ->expectsOutput('Capture processor is disabled.')
        ->assertSuccessful();

    expect($capture->refresh()->status)->toBe(Capture::STATUS_PENDING);
});
