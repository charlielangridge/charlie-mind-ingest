<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'charliemind-pull.php';

final class CharlieMindPullerFakeClient implements CharlieMindPullerClient
{
    /**
     * @param  array<int, array<string, mixed>>  $exports
     * @param  array<string, string>  $downloads
     */
    public function __construct(
        public array $exports,
        public array $downloads = [],
        public array $marked = [],
    ) {}

    public function pending(int $limit, bool $includeRaw): array
    {
        return array_slice($this->exports, 0, $limit);
    }

    public function download(string $path): string
    {
        if (! array_key_exists($path, $this->downloads)) {
            throw new RuntimeException('missing fake download');
        }

        return $this->downloads[$path];
    }

    public function markComplete(array $captureIds): void
    {
        $this->marked = array_values($captureIds);
    }
}

function charliemindPullerVault(): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'charliemind-puller-'.bin2hex(random_bytes(4));
    mkdir($path, 0775, true);

    return $path;
}

function charliemindPullerOptions(string $vault, bool $dryRun = false, bool $force = false): CharlieMindPullerOptions
{
    return new CharlieMindPullerOptions(
        apiUrl: 'https://capture.example.com',
        apiToken: 'token',
        vaultPath: $vault,
        dryRun: $dryRun,
        force: $force,
    );
}

function charliemindPullerExport(array $files = [['path' => 'inbox/mobile-captures/review/note.md']]): array
{
    return [
        'capture_id' => '2026-06-26-161905',
        'files' => $files,
    ];
}

test('puller path function rejects unsafe paths', function (?string $path) {
    expect(CharlieMindPullerPath::normalize($path))->toBeNull();
})->with([
    'traversal' => ['../secret.md'],
    'leading slash' => ['/Review/note.md'],
    'windows drive' => ['C:\\Users\\charl\\note.md'],
    'null byte' => ["Review/note.md\0"],
]);

test('puller dry run does not write files or mark captures exported', function () {
    $vault = charliemindPullerVault();
    $client = new CharlieMindPullerFakeClient([charliemindPullerExport()], [
        'inbox/mobile-captures/review/note.md' => '# Note',
    ]);

    $result = (new CharlieMindPuller($client, charliemindPullerOptions($vault, dryRun: true)))->pull();

    expect($result['planned'])->toBe(1)
        ->and($result['downloaded'])->toBe(0)
        ->and($client->marked)->toBe([])
        ->and(is_file($vault.DIRECTORY_SEPARATOR.'inbox'.DIRECTORY_SEPARATOR.'mobile-captures'.DIRECTORY_SEPARATOR.'review'.DIRECTORY_SEPARATOR.'note.md'))->toBeFalse();
});

test('puller does not overwrite existing files unless force is enabled', function () {
    $vault = charliemindPullerVault();
    $directory = $vault.DIRECTORY_SEPARATOR.'inbox'.DIRECTORY_SEPARATOR.'mobile-captures'.DIRECTORY_SEPARATOR.'review';
    mkdir($directory, 0775, true);
    file_put_contents($directory.DIRECTORY_SEPARATOR.'note.md', 'existing');

    $client = new CharlieMindPullerFakeClient([charliemindPullerExport()], [
        'inbox/mobile-captures/review/note.md' => 'new',
    ]);

    $result = (new CharlieMindPuller($client, charliemindPullerOptions($vault)))->pull();

    expect($result['skipped_existing'])->toBe(1)
        ->and(file_get_contents($directory.DIRECTORY_SEPARATOR.'note.md'))->toBe('existing')
        ->and($client->marked)->toBe(['2026-06-26-161905']);
});

test('puller overwrites existing files with force enabled', function () {
    $vault = charliemindPullerVault();
    $directory = $vault.DIRECTORY_SEPARATOR.'inbox'.DIRECTORY_SEPARATOR.'mobile-captures'.DIRECTORY_SEPARATOR.'review';
    mkdir($directory, 0775, true);
    file_put_contents($directory.DIRECTORY_SEPARATOR.'note.md', 'existing');

    $client = new CharlieMindPullerFakeClient([charliemindPullerExport()], [
        'inbox/mobile-captures/review/note.md' => 'new',
    ]);

    $result = (new CharlieMindPuller($client, charliemindPullerOptions($vault, force: true)))->pull();

    expect($result['downloaded'])->toBe(1)
        ->and(file_get_contents($directory.DIRECTORY_SEPARATOR.'note.md'))->toBe('new')
        ->and($client->marked)->toBe(['2026-06-26-161905']);
});

test('puller only marks captures exported when every required file succeeds', function () {
    $vault = charliemindPullerVault();
    $client = new CharlieMindPullerFakeClient([
        charliemindPullerExport([
            ['path' => 'inbox/mobile-captures/review/note.md'],
            ['path' => 'inbox/mobile-captures/audio/missing.m4a'],
        ]),
    ], [
        'inbox/mobile-captures/review/note.md' => '# Note',
    ]);

    $result = (new CharlieMindPuller($client, charliemindPullerOptions($vault)))->pull();

    expect($result['failed'])->toBe(1)
        ->and($client->marked)->toBe([]);
});
