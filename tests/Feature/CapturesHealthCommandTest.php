<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('health command succeeds', function () {
    config(['charliemind.capture_api_token' => 'test-token']);

    Storage::fake('charliemind');

    $this->artisan('captures:health')
        ->expectsOutput('Capture API token is configured.')
        ->expectsOutput('CharlieMind inbox folders exist.')
        ->expectsOutput('Database connection is available.')
        ->assertSuccessful();

    expect(Storage::disk('charliemind')->directoryExists('inbox/captures/ideas'))->toBeTrue();
    expect(Storage::disk('charliemind')->directoryExists('inbox/audio'))->toBeTrue();
    expect(Storage::disk('charliemind')->directoryExists('inbox/media/photos'))->toBeTrue();
});
