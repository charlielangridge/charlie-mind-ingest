<?php

use App\Models\Capture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'charliemind.capture_api_token' => 'test-token',
        'charliemind.disk' => 'local',
        'charliemind.root' => 'charliemind',
        'charliemind.staging_processed_folder' => 'inbox/mobile-captures/processed',
        'charliemind.staging_review_folder' => 'inbox/mobile-captures/review',
        'charliemind.staging_audio_folder' => 'inbox/mobile-captures/audio',
        'charliemind.staging_media_folder' => 'inbox/mobile-captures/media',
        'charliemind.staging_raw_folder' => 'inbox/mobile-captures/raw',
    ]);

    Storage::fake('local');
    Carbon::setTestNow(Carbon::parse('2026-06-26 18:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function exportAuthHeaders(): array
{
    return [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer test-token',
    ];
}

function exportCapture(array $attributes = []): Capture
{
    $capture = Capture::query()->create(array_merge([
        'capture_id' => '2026-06-26-161905',
        'type' => Capture::TYPE_VOICE,
        'status' => Capture::STATUS_PROCESSED,
        'markdown_path' => 'inbox/voice/2026-06-26-161905.md',
        'processed_markdown_path' => 'inbox/mobile-captures/review/2026-06-26 - unclear voice note.md',
        'media_path' => 'inbox/mobile-captures/audio/2026-06-26-161905.m4a',
        'media_mime' => 'audio/mp4',
        'processed_at' => '2026-06-26 17:10:00',
        'needs_review' => true,
        'review_reason' => 'low-confidence',
        'suggested_folder' => 'Voice',
        'suggested_path' => 'Voice/2026-06-26 - unclear voice note.md',
    ], $attributes));

    Storage::disk('local')->put('charliemind/'.$capture->markdown_path, '# Raw Voice');
    Storage::disk('local')->put('charliemind/inbox/mobile-captures/raw/'.$capture->capture_id.'.md', '# Raw Voice');

    if ($capture->processed_markdown_path !== null) {
        Storage::disk('local')->put('charliemind/'.$capture->processed_markdown_path, '# Processed Voice');
    }

    if ($capture->media_path !== null) {
        Storage::disk('local')->put('charliemind/'.$capture->media_path, "\x00\x01audio");
    }

    return $capture;
}

test('pending export endpoint returns processed captures only', function () {
    exportCapture();
    exportCapture([
        'capture_id' => '2026-06-26-161906',
        'status' => Capture::STATUS_PENDING,
        'processed_markdown_path' => null,
        'processed_at' => null,
        'media_path' => null,
    ]);

    $this->getJson('/api/exports/pending', exportAuthHeaders())
        ->assertSuccessful()
        ->assertJsonCount(1, 'exports')
        ->assertJsonPath('exports.0.capture_id', '2026-06-26-161905')
        ->assertJsonPath('exports.0.needs_review', true)
        ->assertJsonPath('exports.0.review_reason', 'low-confidence');
});

test('pending export endpoint excludes already exported captures', function () {
    exportCapture([
        'export_status' => Capture::EXPORT_STATUS_EXPORTED,
        'exported_at' => now(),
    ]);

    $this->getJson('/api/exports/pending', exportAuthHeaders())
        ->assertSuccessful()
        ->assertJsonCount(0, 'exports');
});

test('export manifest includes processed markdown and media files', function () {
    exportCapture();

    $this->getJson('/api/exports/pending', exportAuthHeaders())
        ->assertSuccessful()
        ->assertJsonPath('exports.0.processed_markdown_path', 'inbox/mobile-captures/review/2026-06-26 - unclear voice note.md')
        ->assertJsonPath('exports.0.suggested_folder', 'Voice')
        ->assertJsonPath('exports.0.suggested_path', 'Voice/2026-06-26 - unclear voice note.md')
        ->assertJsonPath('exports.0.files.0.role', 'processed_note')
        ->assertJsonPath('exports.0.files.0.path', 'inbox/mobile-captures/review/2026-06-26 - unclear voice note.md')
        ->assertJsonPath('exports.0.files.1.role', 'media')
        ->assertJsonPath('exports.0.files.1.path', 'inbox/mobile-captures/audio/2026-06-26-161905.m4a');
});

test('export manifest includes raw markdown when requested', function () {
    exportCapture();

    $response = $this->getJson('/api/exports/pending?include_raw=true', exportAuthHeaders())
        ->assertSuccessful();

    expect(collect($response->json('exports.0.files'))->pluck('role')->all())
        ->toContain('raw_capture');

    expect(collect($response->json('exports.0.files'))->firstWhere('role', 'raw_capture')['path'])
        ->toBe('inbox/mobile-captures/raw/2026-06-26-161905.md');
});

test('export file endpoint downloads markdown', function () {
    exportCapture();

    $this->get('/api/exports/file?path='.urlencode('inbox/mobile-captures/review/2026-06-26 - unclear voice note.md'), exportAuthHeaders())
        ->assertSuccessful()
        ->assertHeader('content-type', 'text/markdown; charset=UTF-8')
        ->assertContent('# Processed Voice');
});

test('export file endpoint downloads binary media', function () {
    exportCapture();

    $response = $this->get('/api/exports/file?path='.urlencode('inbox/mobile-captures/audio/2026-06-26-161905.m4a'), exportAuthHeaders())
        ->assertSuccessful();

    expect($response->getContent())->toBe("\x00\x01audio");
});

test('export file endpoint downloads staged raw markdown', function () {
    exportCapture();

    $this->get('/api/exports/file?path='.urlencode('inbox/mobile-captures/raw/2026-06-26-161905.md'), exportAuthHeaders())
        ->assertSuccessful()
        ->assertHeader('content-type', 'text/markdown; charset=UTF-8')
        ->assertContent('# Raw Voice');
});

test('export file endpoint rejects unsafe paths', function (string $path) {
    exportCapture();

    $this->getJson('/api/exports/file?path='.urlencode($path), exportAuthHeaders())
        ->assertUnprocessable();
})->with([
    'traversal' => ['../secret.md'],
    'leading slash' => ['/Review/secret.md'],
    'windows drive' => ['C:\\Users\\charl\\secret.md'],
]);

test('mark complete sets export fields and reports unknown ids', function () {
    $capture = exportCapture();

    $this->postJson('/api/exports/mark-complete', [
        'capture_ids' => [$capture->capture_id, 'unknown-id'],
    ], exportAuthHeaders())
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('exported.0', $capture->capture_id)
        ->assertJsonPath('unknown.0', 'unknown-id');

    $capture->refresh();

    expect($capture->export_status)->toBe(Capture::EXPORT_STATUS_EXPORTED)
        ->and($capture->exported_at)->not->toBeNull()
        ->and($capture->export_error)->toBeNull()
        ->and($capture->last_export_attempt_at)->not->toBeNull()
        ->and($capture->export_attempts)->toBe(1);
});

test('mark complete fails when no capture ids are known', function () {
    $this->postJson('/api/exports/mark-complete', [
        'capture_ids' => ['unknown-id'],
    ], exportAuthHeaders())->assertUnprocessable();
});
