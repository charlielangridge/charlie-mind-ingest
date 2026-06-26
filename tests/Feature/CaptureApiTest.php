<?php

use App\Models\Capture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'charliemind.capture_api_token' => 'test-token',
        'charliemind.disk' => 'local',
        'charliemind.root' => 'charliemind',
    ]);

    Storage::fake('local');
});

function captureAuthHeaders(): array
{
    return [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer test-token',
    ];
}

test('unauthenticated requests fail', function () {
    $this->postJson('/api/captures', [
        'type' => 'idea',
        'body' => 'A captured idea.',
    ])->assertUnauthorized()
        ->assertExactJson([
            'message' => 'Unauthenticated.',
        ]);
});

test('authenticated text capture succeeds', function () {
    $response = $this->postJson('/api/captures', [
        'type' => 'idea',
        'body' => 'Idea for DoorScan: estimate queue length from recent scans.',
        'source' => 'iphone',
    ], captureAuthHeaders());

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('capture.type', 'idea')
        ->assertJsonPath('capture.status', 'pending');

    $capture = Capture::query()->firstOrFail();

    expect($capture->markdown_path)->toStartWith('inbox/captures/ideas/');

    expect($capture->markdown_path)->not->toStartWith('charliemind/');

    Storage::disk('local')->assertExists('charliemind/'.$capture->markdown_path);
    expect(Storage::disk('local')->get('charliemind/'.$capture->markdown_path))
        ->toContain('capture_id: '.$capture->capture_id)
        ->toContain('# Idea')
        ->toContain('Idea for DoorScan');
});

test('cherri json request captures text payloads at the captures endpoint', function () {
    $response = $this->postJson('/api/captures', [
        'type' => 'quick',
        'title' => 'Shortcut note',
        'body' => 'Captured through Cherri jsonRequest().',
        'url' => 'https://example.com/cherri',
        'source' => 'iphone-shortcut',
        'captured_at' => '2026-06-26 15:30:00',
        'metadata' => [
            'shortcut_action' => 'jsonRequest',
        ],
    ], captureAuthHeaders());

    $response
        ->assertCreated()
        ->assertJsonPath('capture.type', 'quick');

    $capture = Capture::query()->firstOrFail();

    expect($capture->title)->toBe('Shortcut note')
        ->and($capture->body)->toBe('Captured through Cherri jsonRequest().')
        ->and($capture->url)->toBe('https://example.com/cherri')
        ->and($capture->source)->toBe('iphone-shortcut')
        ->and($capture->captured_at->format('Y-m-d H:i:s'))->toBe('2026-06-26 15:30:00')
        ->and($capture->metadata)->toBe([
            'shortcut_action' => 'jsonRequest',
        ]);
});

test('task capture creates a markdown task', function () {
    $response = $this->postJson('/api/captures', [
        'type' => 'task',
        'body' => 'Call Dylan about Martin Audio pricing.',
    ], captureAuthHeaders());

    $capture = Capture::query()->findOrFail($response->json('capture.id'));
    $markdown = Storage::disk('local')->get('charliemind/'.$capture->markdown_path);

    expect($markdown)->toContain('- [ ] Call Dylan about Martin Audio pricing.');
});

test('link capture includes the URL', function () {
    $response = $this->postJson('/api/captures', [
        'type' => 'link',
        'title' => 'Interesting Laravel package',
        'url' => 'https://example.com',
        'body' => 'Worth reviewing later.',
    ], captureAuthHeaders());

    $capture = Capture::query()->findOrFail($response->json('capture.id'));
    $markdown = Storage::disk('local')->get('charliemind/'.$capture->markdown_path);

    expect($markdown)
        ->toContain('[Interesting Laravel package](https://example.com)')
        ->toContain('Worth reviewing later.');
});

test('voice capture with file stores audio and creates markdown embed', function () {
    $file = UploadedFile::fake()->create('recording.m4a', 200, 'audio/mp4');

    $response = $this->post('/api/captures', [
        'type' => 'voice',
        'source' => 'iphone',
        'file' => $file,
    ], captureAuthHeaders());

    $response->assertCreated();

    $capture = Capture::query()->findOrFail($response->json('capture.id'));

    expect($capture->media_path)->toBe('inbox/audio/'.$capture->capture_id.'.m4a');
    Storage::disk('local')->assertExists('charliemind/'.$capture->media_path);
    expect(Storage::disk('local')->get('charliemind/'.$capture->markdown_path))
        ->toContain('![[inbox/audio/'.$capture->capture_id.'.m4a]]')
        ->not->toContain('![[charliemind/inbox/audio/'.$capture->capture_id.'.m4a]]')
        ->toContain('#mobile-capture #voice #audio');
});

test('cherri file request captures multipart payloads and file at the captures endpoint', function () {
    $file = UploadedFile::fake()->create('meeting-audio.m4a', 200, 'audio/mp4');

    $response = $this->post('/api/captures', [
        'type' => 'voice',
        'title' => 'Meeting audio',
        'body' => 'Discussed capture ingestion.',
        'source' => 'iphone-shortcut',
        'captured_at' => '2026-06-26 16:45:00',
        'metadata' => json_encode([
            'shortcut_action' => 'fileRequest',
            'duration_seconds' => 32,
        ], JSON_THROW_ON_ERROR),
        'file' => $file,
    ], captureAuthHeaders());

    $response
        ->assertCreated()
        ->assertJsonPath('capture.type', 'voice');

    $capture = Capture::query()->firstOrFail();

    expect($capture->title)->toBe('Meeting audio')
        ->and($capture->body)->toBe('Discussed capture ingestion.')
        ->and($capture->source)->toBe('iphone-shortcut')
        ->and($capture->captured_at->format('Y-m-d H:i:s'))->toBe('2026-06-26 16:45:00')
        ->and($capture->metadata)->toBe([
            'shortcut_action' => 'fileRequest',
            'duration_seconds' => 32,
        ])
        ->and($capture->media_original_name)->toBe('meeting-audio.m4a')
        ->and($capture->media_path)->toBe('inbox/audio/'.$capture->capture_id.'.m4a');

    Storage::disk('local')->assertExists('charliemind/'.$capture->media_path);
    Storage::disk('local')->assertExists('charliemind/'.$capture->markdown_path);
});

test('photo capture with file stores image and creates markdown embed', function () {
    $file = UploadedFile::fake()->image('site-photo.jpg');

    $response = $this->post('/api/captures', [
        'type' => 'photo',
        'body' => 'Rack wiring photo from site visit',
        'file' => $file,
    ], captureAuthHeaders());

    $response->assertCreated();

    $capture = Capture::query()->findOrFail($response->json('capture.id'));

    expect($capture->media_path)->toBe('inbox/media/photos/'.$capture->capture_id.'.jpg');
    Storage::disk('local')->assertExists('charliemind/'.$capture->media_path);
    expect(Storage::disk('local')->get('charliemind/'.$capture->markdown_path))
        ->toContain('![[inbox/media/photos/'.$capture->capture_id.'.jpg]]')
        ->toContain('Rack wiring photo from site visit');
});

test('unknown type falls back to general', function () {
    $response = $this->postJson('/api/captures', [
        'type' => 'something-new',
        'body' => 'Fallback body.',
    ], captureAuthHeaders());

    $response
        ->assertCreated()
        ->assertJsonPath('capture.type', 'general');

    expect($response->json('capture.markdown_path'))->toStartWith('inbox/captures/general/');
});

test('same second capture ids do not collide', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-26 14:12:33'));

    $first = $this->postJson('/api/captures', [
        'type' => 'idea',
        'body' => 'First idea.',
    ], captureAuthHeaders());

    $second = $this->postJson('/api/captures', [
        'type' => 'idea',
        'body' => 'Second idea.',
    ], captureAuthHeaders());

    Carbon::setTestNow();

    expect($first->json('capture.capture_id'))->toBe('2026-06-26-141233');
    expect($second->json('capture.capture_id'))
        ->not->toBe($first->json('capture.capture_id'))
        ->toStartWith('2026-06-26-141233-');
});

test('index endpoint filters by status and type', function () {
    Capture::query()->create([
        'capture_id' => '2026-06-26-141233',
        'type' => 'voice',
        'status' => 'pending',
        'markdown_path' => 'inbox/voice/2026-06-26-141233.md',
    ]);

    Capture::query()->create([
        'capture_id' => '2026-06-26-141234',
        'type' => 'idea',
        'status' => 'processed',
        'markdown_path' => 'inbox/captures/ideas/2026-06-26-141234.md',
    ]);

    Capture::query()->create([
        'capture_id' => '2026-06-26-141235',
        'type' => 'voice',
        'status' => 'processed',
        'markdown_path' => 'inbox/voice/2026-06-26-141235.md',
    ]);

    $this->getJson('/api/captures?status=processed&type=voice&limit=1', captureAuthHeaders())
        ->assertSuccessful()
        ->assertJsonCount(1, 'captures')
        ->assertJsonPath('captures.0.capture_id', '2026-06-26-141235');
});

test('show endpoint looks up captures by capture id', function () {
    Capture::query()->create([
        'capture_id' => '2026-06-26-141233',
        'type' => 'idea',
        'status' => 'pending',
        'markdown_path' => 'inbox/captures/ideas/2026-06-26-141233.md',
    ]);

    $this->getJson('/api/captures/2026-06-26-141233', captureAuthHeaders())
        ->assertSuccessful()
        ->assertJsonPath('capture.capture_id', '2026-06-26-141233');
});
