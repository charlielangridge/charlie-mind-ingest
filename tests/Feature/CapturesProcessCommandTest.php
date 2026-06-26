<?php

use App\Ai\Agents\CaptureProcessingAgent;
use App\Models\Capture;
use App\Services\CaptureProcessingResult;
use App\Services\CaptureReviewRoute;
use App\Services\ReviewIndexWriter;
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
        'charliemind.processor_review_mode' => 'confidence',
        'charliemind.processor_review_confidence_threshold' => 'low',
        'charliemind.processor_medium_review_tag' => true,
        'charliemind.processor_review_folder' => 'Review',
        'charliemind.processor_review_index' => 'Review/_Review Index.md',
        'charliemind.staging_root' => 'inbox/mobile-captures',
        'charliemind.staging_processed_folder' => 'inbox/mobile-captures/processed',
        'charliemind.staging_review_folder' => 'inbox/mobile-captures/review',
        'charliemind.staging_audio_folder' => 'inbox/mobile-captures/audio',
        'charliemind.staging_media_folder' => 'inbox/mobile-captures/media',
        'charliemind.staging_raw_folder' => 'inbox/mobile-captures/raw',
        'charliemind.staging_log_folder' => 'inbox/mobile-captures/logs',
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

function fakeAiCaptureResult(array $overrides = []): void
{
    config(['ai.providers.openai.key' => 'test-key']);

    CaptureProcessingAgent::fake([array_merge([
        'title' => 'DoorScan Queue Length Estimation',
        'summary' => 'Estimate DoorScan queue length from recent scans.',
        'body' => 'Estimate [[DoorScan]] queue length from recent scans.',
        'type' => 'idea',
        'folder' => 'Ideas',
        'tags' => ['mobile-capture', 'idea'],
        'tasks' => [],
        'links' => ['DoorScan'],
        'confidence' => 'high',
    ], $overrides)]);
}

test('processes a pending text capture and preserves the raw file', function () {
    $capture = pendingCapture(markdown: "# Idea\n\nDoorScan queue length estimation from recent scans.");

    $this->artisan('captures:process')
        ->expectsOutput('Processing 1 captures...')
        ->assertSuccessful();

    $capture->refresh();

    expect($capture->status)->toBe(Capture::STATUS_PROCESSED)
        ->and($capture->processed_markdown_path)->toBe('inbox/mobile-captures/review/2026-06-26 - doorscan queue length estimation from recent scans.md')
        ->and($capture->summary)->toContain('DoorScan queue length estimation')
        ->and($capture->suggested_title)->toBe('Doorscan Queue Length Estimation From Recent Scans')
        ->and($capture->suggested_folder)->toBe('Ideas')
        ->and($capture->suggested_path)->toBe('Ideas/2026-06-26 - doorscan queue length estimation from recent scans.md')
        ->and($capture->needs_review)->toBeTrue()
        ->and($capture->review_reason)->toBe('low-confidence');

    Storage::disk('local')->assertExists('charliemind/'.$capture->markdown_path);
    Storage::disk('local')->assertExists('charliemind/inbox/mobile-captures/raw/2026-06-26-161905.md');
    Storage::disk('local')->assertExists('charliemind/'.$capture->processed_markdown_path);

    expect(Storage::disk('local')->get('charliemind/'.$capture->processed_markdown_path))
        ->toContain('processed: true')
        ->toContain('needs_review: true')
        ->toContain('review_reason: low-confidence')
        ->toContain('suggested_folder: "Ideas"')
        ->toContain('suggested_path: "Ideas/2026-06-26 - doorscan queue length estimation from recent scans.md"')
        ->toContain('[[DoorScan]]')
        ->toContain('Original capture: [[inbox/mobile-captures/raw/2026-06-26-161905.md]]');
});

test('high confidence capture is staged with the suggested folder preserved', function () {
    fakeAiCaptureResult(['confidence' => 'high']);
    $capture = pendingCapture(markdown: "# Idea\n\nDoorScan queue length estimation.");

    $this->artisan('captures:process')->assertSuccessful();

    $capture->refresh();
    $markdown = Storage::disk('local')->get('charliemind/'.$capture->processed_markdown_path);

    expect($capture->processed_markdown_path)->toBe('inbox/mobile-captures/processed/2026-06-26 - doorscan queue length estimation.md')
        ->and($capture->suggested_folder)->toBe('Ideas')
        ->and($capture->suggested_path)->toBe('Ideas/2026-06-26 - doorscan queue length estimation.md')
        ->and($capture->needs_review)->toBeFalse()
        ->and($capture->review_reason)->toBeNull()
        ->and($markdown)->toContain('needs_review: false')
        ->toContain('suggested_folder: "Ideas"')
        ->toContain('suggested_path: "Ideas/2026-06-26 - doorscan queue length estimation.md"')
        ->not->toContain('#needs-review');
});

test('medium confidence capture is staged as processed with review tags', function () {
    fakeAiCaptureResult([
        'title' => 'Call Venue About Quote',
        'summary' => 'Call the venue about the quote.',
        'body' => 'Call the venue about the quote.',
        'type' => 'task',
        'folder' => 'Tasks',
        'tags' => ['task'],
        'tasks' => ['Call venue about quote'],
        'confidence' => 'medium',
    ]);

    $capture = pendingCapture([
        'type' => Capture::TYPE_TASK,
        'markdown_path' => 'inbox/captures/tasks/2026-06-26-161905.md',
    ], "# Task\n\nCall venue about quote.");

    $this->artisan('captures:process')
        ->expectsOutput('⚠ 2026-06-26-161905 task → inbox/mobile-captures/processed/2026-06-26 - call venue about quote.md needs review: medium-confidence')
        ->assertSuccessful();

    $capture->refresh();
    $markdown = Storage::disk('local')->get('charliemind/'.$capture->processed_markdown_path);

    expect($capture->processed_markdown_path)->toBe('inbox/mobile-captures/processed/2026-06-26 - call venue about quote.md')
        ->and($capture->suggested_folder)->toBe('Tasks')
        ->and($capture->suggested_path)->toBe('Tasks/2026-06-26 - call venue about quote.md')
        ->and($capture->needs_review)->toBeTrue()
        ->and($capture->review_reason)->toBe('medium-confidence')
        ->and($capture->suggested_tags)->toContain('needs-review')
        ->and($markdown)->toContain('needs_review: true')
        ->toContain('review_reason: medium-confidence')
        ->toContain('suggested_path: "Tasks/2026-06-26 - call venue about quote.md"')
        ->toContain('  - needs-review')
        ->toContain('#needs-review');
});

test('medium confidence remains in processed staging even when threshold is medium', function () {
    config(['charliemind.processor_review_confidence_threshold' => 'medium']);
    fakeAiCaptureResult([
        'title' => 'Maybe A Task',
        'type' => 'task',
        'folder' => 'Tasks',
        'tags' => ['task'],
        'confidence' => 'medium',
    ]);

    $capture = pendingCapture(markdown: "# Task\n\nMaybe a task.");

    $this->artisan('captures:process')->assertSuccessful();

    expect($capture->refresh()->processed_markdown_path)->toBe('inbox/mobile-captures/processed/2026-06-26 - maybe a task.md')
        ->and($capture->suggested_path)->toBe('Tasks/2026-06-26 - maybe a task.md')
        ->and($capture->needs_review)->toBeTrue()
        ->and($capture->review_reason)->toBe('medium-confidence');
});

test('low confidence capture is staged into review folder with review metadata', function () {
    fakeAiCaptureResult([
        'title' => 'Unclear Voice Note',
        'summary' => 'Unclear voice note.',
        'body' => 'Unclear voice note.',
        'type' => 'voice',
        'folder' => 'Voice',
        'tags' => ['voice'],
        'confidence' => 'low',
    ]);

    $capture = pendingCapture([
        'type' => Capture::TYPE_VOICE,
        'markdown_path' => 'inbox/voice/2026-06-26-161905.md',
    ], "# Voice Note\n\nUnclear voice note.");

    $this->artisan('captures:process')
        ->expectsOutput('⚠ 2026-06-26-161905 voice → inbox/mobile-captures/review/2026-06-26 - unclear voice note.md needs review: low-confidence')
        ->assertSuccessful();

    $capture->refresh();
    $markdown = Storage::disk('local')->get('charliemind/'.$capture->processed_markdown_path);

    expect($capture->processed_markdown_path)->toBe('inbox/mobile-captures/review/2026-06-26 - unclear voice note.md')
        ->and($capture->suggested_folder)->toBe('Voice')
        ->and($capture->suggested_path)->toBe('Voice/2026-06-26 - unclear voice note.md')
        ->and($capture->needs_review)->toBeTrue()
        ->and($capture->review_reason)->toBe('low-confidence')
        ->and($markdown)->toContain('needs_review: true')
        ->toContain('review_reason: low-confidence')
        ->toContain('  - needs-review')
        ->toContain('#needs-review');
});

test('processes raw markdown front matter without requiring symfony yaml', function () {
    $capture = pendingCapture(markdown: <<<'MARKDOWN'
---
created: 2026-06-26 17:13
capture_id: 2026-06-26-161905
processed: false
tags:
  - mobile-capture
  - idea
---

# Idea

Front matter should not require an optional parser package.
MARKDOWN);

    $this->artisan('captures:process --dry-run')
        ->expectsOutput('Dry run: processing 1 captures...')
        ->assertSuccessful();

    expect($capture->refresh()->status)->toBe(Capture::STATUS_PENDING);
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

    Storage::disk('local')->assertMissing('charliemind/inbox/mobile-captures/review/2026-06-26 - dry run body.md');
    Storage::disk('local')->assertMissing('charliemind/inbox/mobile-captures/logs/processing-log.md');
    Storage::disk('local')->assertMissing('charliemind/inbox/mobile-captures/review/_Review Index.md');
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

    expect($capture->processed_markdown_path)->toStartWith('inbox/mobile-captures/review/')
        ->and(Storage::disk('local')->get('charliemind/'.$capture->processed_markdown_path))->toContain('[[Laravel]]');
});

test('generated filenames are safe and unique', function () {
    Storage::disk('local')->put('charliemind/inbox/mobile-captures/review/2026-06-26 - duplicate title.md', 'existing');

    $capture = pendingCapture(markdown: "# Idea\n\nDuplicate title.");

    $this->artisan('captures:process')->assertSuccessful();

    expect($capture->refresh()->processed_markdown_path)->toBe('inbox/mobile-captures/review/2026-06-26 - duplicate title-2.md');
});

test('processing log is appended', function () {
    pendingCapture(markdown: "# Idea\n\nLogged capture.");
    Storage::disk('local')->put('charliemind/inbox/mobile-captures/logs/processing-log.md', '# Existing Log'.PHP_EOL);

    $this->artisan('captures:process')->assertSuccessful();

    expect(Storage::disk('local')->get('charliemind/inbox/mobile-captures/logs/processing-log.md'))
        ->toContain('# Existing Log')
        ->toContain('## 2026-06-26 17:10')
        ->toContain('Processed: 1')
        ->toContain('Needs review: 1')
        ->toContain('- ⚠ 2026-06-26-161905 → inbox/mobile-captures/review/2026-06-26 - logged capture.md low-confidence');
});

test('review index is created and receives medium and low confidence entries', function () {
    config(['ai.providers.openai.key' => 'test-key']);

    CaptureProcessingAgent::fake([
        [
            'title' => 'Unclear Voice Note',
            'summary' => 'Unclear.',
            'body' => 'Unclear.',
            'type' => 'voice',
            'folder' => 'Voice',
            'tags' => ['voice'],
            'tasks' => [],
            'links' => [],
            'confidence' => 'low',
        ],
        [
            'title' => 'Call Venue About Quote',
            'summary' => 'Call venue.',
            'body' => 'Call venue.',
            'type' => 'task',
            'folder' => 'Tasks',
            'tags' => ['task'],
            'tasks' => ['Call venue'],
            'links' => [],
            'confidence' => 'medium',
        ],
    ]);

    pendingCapture([
        'type' => Capture::TYPE_VOICE,
        'markdown_path' => 'inbox/voice/2026-06-26-161905.md',
    ], "# Voice\n\nUnclear.");

    pendingCapture([
        'capture_id' => '2026-06-26-161906',
        'type' => Capture::TYPE_TASK,
        'markdown_path' => 'inbox/captures/tasks/2026-06-26-161906.md',
    ], "# Task\n\nCall venue.");

    $this->artisan('captures:process --limit=2')->assertSuccessful();

    $index = Storage::disk('local')->get('charliemind/inbox/mobile-captures/review/_Review Index.md');

    expect($index)
        ->toContain('# Review Index')
        ->toContain('- [ ] [[inbox/mobile-captures/review/2026-06-26 - unclear voice note]] - low-confidence, voice capture, 2026-06-26-161905')
        ->toContain('- [ ] [[inbox/mobile-captures/processed/2026-06-26 - call venue about quote]] - medium-confidence, task capture, 2026-06-26-161906');
});

test('review index does not duplicate entries', function () {
    $capture = pendingCapture();
    $result = new CaptureProcessingResult(
        title: 'Unclear Voice Note',
        summary: 'Unclear.',
        body: 'Unclear.',
        type: 'voice',
        folder: 'Voice',
        tags: ['voice'],
        confidence: 'low',
    );
    $route = new CaptureReviewRoute(
        folder: 'Voice',
        needsReview: true,
        reviewReason: 'low-confidence',
        tags: ['mobile-capture', 'voice', 'needs-review'],
    );

    $writer = app(ReviewIndexWriter::class);
    $writer->append($capture, $result, $route, 'inbox/mobile-captures/review/2026-06-26 - unclear voice note.md');
    $writer->append($capture, $result, $route, 'inbox/mobile-captures/review/2026-06-26 - unclear voice note.md');

    $index = Storage::disk('local')->get('charliemind/inbox/mobile-captures/review/_Review Index.md');

    expect(substr_count($index, '[[inbox/mobile-captures/review/2026-06-26 - unclear voice note]]'))->toBe(1);
});

test('review mode off still stages low confidence captures without review metadata', function () {
    config(['charliemind.processor_review_mode' => 'off']);
    fakeAiCaptureResult([
        'title' => 'Uncertain Idea',
        'confidence' => 'low',
    ]);

    $capture = pendingCapture(markdown: "# Idea\n\nUncertain idea.");

    $this->artisan('captures:process')->assertSuccessful();

    $capture->refresh();
    $markdown = Storage::disk('local')->get('charliemind/'.$capture->processed_markdown_path);

    expect($capture->processed_markdown_path)->toBe('inbox/mobile-captures/review/2026-06-26 - uncertain idea.md')
        ->and($capture->suggested_path)->toBe('Ideas/2026-06-26 - uncertain idea.md')
        ->and($capture->needs_review)->toBeFalse()
        ->and($capture->review_reason)->toBeNull()
        ->and($markdown)->toContain('needs_review: false')
        ->not->toContain('#needs-review');
});

test('review list command lists captures that need review', function () {
    Capture::query()->create([
        'capture_id' => '2026-06-26-161905',
        'type' => Capture::TYPE_VOICE,
        'status' => Capture::STATUS_PROCESSED,
        'markdown_path' => 'inbox/voice/2026-06-26-161905.md',
        'processed_markdown_path' => 'inbox/mobile-captures/review/2026-06-26 - unclear voice note.md',
        'needs_review' => true,
        'review_reason' => 'low-confidence',
        'processed_at' => '2026-06-26 17:10:00',
    ]);

    Capture::query()->create([
        'capture_id' => '2026-06-26-161906',
        'type' => Capture::TYPE_IDEA,
        'status' => Capture::STATUS_PROCESSED,
        'markdown_path' => 'inbox/captures/ideas/2026-06-26-161906.md',
        'processed_markdown_path' => 'Ideas/2026-06-26 - clear idea.md',
        'needs_review' => false,
        'processed_at' => '2026-06-26 17:11:00',
    ]);

    $this->artisan('captures:review-list')
        ->expectsTable(
            ['Capture ID', 'Type', 'Reason', 'Processed Path'],
            [[
                '2026-06-26-161905',
                Capture::TYPE_VOICE,
                'low-confidence',
                'inbox/mobile-captures/review/2026-06-26 - unclear voice note.md',
            ]],
        )
        ->assertSuccessful();
});

test('review list command reports empty state', function () {
    $this->artisan('captures:review-list')
        ->expectsOutput('No captures need review.')
        ->assertSuccessful();
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
        ->and($capture->processed_markdown_path)->toBe('inbox/mobile-captures/processed/2026-06-26 - call dylan about martin audio pricing.md')
        ->and($capture->suggested_path)->toBe('Tasks/2026-06-26 - call dylan about martin audio pricing.md')
        ->and($capture->media_path)->toBe('inbox/mobile-captures/audio/2026-06-26-161905.m4a')
        ->and(Storage::disk('local')->get('charliemind/'.$capture->processed_markdown_path))
        ->toContain('## Transcript')
        ->toContain('Audio: [[inbox/mobile-captures/audio/2026-06-26-161905.m4a]]');

    Storage::disk('local')->assertExists('charliemind/inbox/audio/2026-06-26-161905.m4a');
    Storage::disk('local')->assertExists('charliemind/inbox/mobile-captures/audio/2026-06-26-161905.m4a');

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
