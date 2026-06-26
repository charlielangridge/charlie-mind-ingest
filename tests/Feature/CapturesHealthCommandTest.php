<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('health command succeeds', function () {
    config([
        'charliemind.capture_api_token' => 'test-token',
        'charliemind.disk' => 'local',
        'charliemind.root' => 'charliemind',
    ]);

    Storage::fake('local');

    $this->artisan('captures:health')
        ->expectsOutput('Configured disk: local')
        ->expectsOutput('Configured storage root: charliemind')
        ->expectsOutput('Capture API token is configured.')
        ->expectsOutput('Configured storage disk can be resolved.')
        ->expectsOutputToContain('Storage write/read/delete test succeeded: charliemind/.health-check/')
        ->expectsOutput('Database connection is available.')
        ->assertSuccessful();

    expect(Storage::disk('local')->allFiles('charliemind/.health-check'))->toBeEmpty();
});
