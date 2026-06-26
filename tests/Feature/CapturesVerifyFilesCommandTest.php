<?php

use App\Models\Capture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'charliemind.disk' => 'local',
        'charliemind.root' => 'charliemind',
    ]);

    Storage::fake('local');
});

test('verify files command succeeds when files exist', function () {
    $capture = Capture::query()->create([
        'capture_id' => '2026-06-26-161905',
        'type' => 'voice',
        'status' => 'pending',
        'markdown_path' => 'inbox/voice/2026-06-26-161905.md',
        'media_path' => 'inbox/audio/2026-06-26-161905.m4a',
    ]);

    Storage::disk('local')->put('charliemind/'.$capture->markdown_path, '# Voice Note');
    Storage::disk('local')->put('charliemind/'.$capture->media_path, 'audio');

    $this->artisan('captures:verify-files --status=pending')
        ->expectsOutput('Using disk: local')
        ->expectsOutput('Using root: charliemind')
        ->expectsOutput('✓ 2026-06-26-161905 markdown exists: charliemind/inbox/voice/2026-06-26-161905.md')
        ->expectsOutput('✓ 2026-06-26-161905 media exists: charliemind/inbox/audio/2026-06-26-161905.m4a')
        ->assertSuccessful();
});

test('verify files command fails when files are missing', function () {
    Capture::query()->create([
        'capture_id' => '2026-06-26-154634',
        'type' => 'link',
        'status' => 'pending',
        'markdown_path' => 'inbox/captures/links/2026-06-26-154634.md',
    ]);

    $this->artisan('captures:verify-files --status=pending --limit=1')
        ->expectsOutput('✗ 2026-06-26-154634 markdown missing: charliemind/inbox/captures/links/2026-06-26-154634.md')
        ->assertFailed();
});
