<?php

declare(strict_types=1);

interface CharlieMindPullerClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function pending(int $limit, bool $includeRaw): array;

    public function download(string $path): string;

    /**
     * @param  array<int, string>  $captureIds
     */
    public function markComplete(array $captureIds): void;
}

final class CharlieMindPullerPath
{
    public static function normalize(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_contains($path, "\0")) {
            return null;
        }

        $path = str_replace('\\', '/', $path);

        if (preg_match('/^[A-Za-z]:/', $path) === 1) {
            return null;
        }

        if (str_starts_with($path, '/')) {
            return null;
        }

        $path = preg_replace('#/+#', '/', $path) ?: $path;
        $path = trim($path);
        $path = trim($path, '/');

        if ($path === '') {
            return null;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $path;
    }

    public static function localPath(string $vaultPath, string $relativePath): string
    {
        return rtrim($vaultPath, '\\/').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }
}

final class CharlieMindPullerOptions
{
    public function __construct(
        public string $apiUrl,
        public string $apiToken,
        public string $vaultPath,
        public int $limit = 50,
        public bool $dryRun = false,
        public bool $force = false,
        public bool $includeRaw = false,
    ) {}

    /**
     * @param  array<int, string>  $argv
     */
    public static function fromArgv(array $argv, string $baseDir): self
    {
        $env = self::loadEnv($baseDir);
        $limit = 50;
        $dryRun = false;
        $force = false;
        $includeRaw = false;

        foreach (array_slice($argv, 1) as $argument) {
            if ($argument === '--dry-run') {
                $dryRun = true;
            } elseif ($argument === '--force') {
                $force = true;
            } elseif ($argument === '--include-raw') {
                $includeRaw = true;
            } elseif (str_starts_with($argument, '--limit=')) {
                $limit = max(1, min(100, (int) substr($argument, 8)));
            }
        }

        return new self(
            apiUrl: rtrim(self::envValue('CHARLIEMIND_API_URL', $env), '/'),
            apiToken: self::envValue('CHARLIEMIND_API_TOKEN', $env),
            vaultPath: self::envValue('CHARLIEMIND_LOCAL_VAULT_PATH', $env),
            limit: $limit,
            dryRun: $dryRun,
            force: $force,
            includeRaw: $includeRaw,
        );
    }

    /**
     * @return array<string, string>
     */
    private static function loadEnv(string $baseDir): array
    {
        $values = [];

        foreach ([$baseDir.DIRECTORY_SEPARATOR.'.env', $baseDir.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'.env'] as $file) {
            if (! is_file($file)) {
                continue;
            }

            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);

                if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                    continue;
                }

                [$key, $value] = explode('=', $line, 2);
                $values[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
            }
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $env
     */
    private static function envValue(string $key, array $env): string
    {
        $value = getenv($key);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $env[$key] ?? '';
    }
}

final class CharlieMindPullerApiClient implements CharlieMindPullerClient
{
    public function __construct(
        private string $apiUrl,
        private string $apiToken,
    ) {}

    public function pending(int $limit, bool $includeRaw): array
    {
        $query = http_build_query([
            'limit' => $limit,
            'include_raw' => $includeRaw ? 'true' : 'false',
        ]);

        $response = $this->request('GET', '/api/exports/pending?'.$query);
        $decoded = json_decode($response, true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded['exports'] ?? null) ? $decoded['exports'] : [];
    }

    public function download(string $path): string
    {
        return $this->request('GET', '/api/exports/file?'.http_build_query(['path' => $path]));
    }

    public function markComplete(array $captureIds): void
    {
        $this->request('POST', '/api/exports/mark-complete', json_encode([
            'capture_ids' => array_values($captureIds),
        ], JSON_THROW_ON_ERROR));
    }

    private function request(string $method, string $path, ?string $body = null): string
    {
        $headers = [
            'Authorization: Bearer '.$this->apiToken,
            'Accept: application/json',
        ];

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body ?? '',
                'ignore_errors' => true,
                'timeout' => 60,
            ],
        ]);

        $response = file_get_contents($this->apiUrl.$path, false, $context);

        if ($response === false) {
            throw new RuntimeException('Request failed: '.$method.' '.$path);
        }

        $status = self::statusCode($http_response_header ?? []);

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Request failed with HTTP '.$status.': '.$method.' '.$path);
        }

        return $response;
    }

    /**
     * @param  array<int, string>  $headers
     */
    private static function statusCode(array $headers): int
    {
        if ($headers !== [] && preg_match('/\s(\d{3})\s/', $headers[0], $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }
}

final class CharlieMindPuller
{
    public function __construct(
        private CharlieMindPullerClient $client,
        private CharlieMindPullerOptions $options,
    ) {}

    /**
     * @return array{found: int, downloaded: int, planned: int, skipped_existing: int, failed: int, captures_exported: array<int, string>, lines: array<int, string>}
     */
    public function pull(): array
    {
        $exports = $this->client->pending($this->options->limit, $this->options->includeRaw);
        $downloaded = 0;
        $planned = 0;
        $skippedExisting = 0;
        $failed = 0;
        $lines = [];
        $completeCaptureIds = [];

        foreach ($exports as $export) {
            $captureId = is_string($export['capture_id'] ?? null) ? $export['capture_id'] : null;
            $files = is_array($export['files'] ?? null) ? $export['files'] : [];
            $captureComplete = $captureId !== null && $files !== [];

            foreach ($files as $file) {
                $path = CharlieMindPullerPath::normalize($file['path'] ?? null);

                if ($path === null) {
                    $failed++;
                    $captureComplete = false;
                    $lines[] = 'FAIL unsafe path';

                    continue;
                }

                $localPath = CharlieMindPullerPath::localPath($this->options->vaultPath, $path);

                try {
                    if (is_file($localPath) && ! $this->options->force) {
                        $skippedExisting++;
                        $lines[] = 'SKIP '.$path;

                        continue;
                    }

                    if ($this->options->dryRun) {
                        $planned++;
                        $lines[] = 'DRY '.$path;

                        continue;
                    }

                    $directory = dirname($localPath);

                    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                        throw new RuntimeException('Could not create directory: '.$directory);
                    }

                    file_put_contents($localPath, $this->client->download($path), LOCK_EX);
                    $downloaded++;
                    $lines[] = 'OK '.$path;
                } catch (Throwable $throwable) {
                    $failed++;
                    $captureComplete = false;
                    $lines[] = 'FAIL '.$path.' '.$throwable->getMessage();
                }
            }

            if ($captureComplete && ! $this->options->dryRun) {
                $completeCaptureIds[] = $captureId;
            }
        }

        $completeCaptureIds = array_values(array_unique($completeCaptureIds));

        if ($completeCaptureIds !== []) {
            $this->client->markComplete($completeCaptureIds);
        }

        return [
            'found' => count($exports),
            'downloaded' => $downloaded,
            'planned' => $planned,
            'skipped_existing' => $skippedExisting,
            'failed' => $failed,
            'captures_exported' => $completeCaptureIds,
            'lines' => $lines,
        ];
    }
}

function charliemind_puller_main(array $argv): int
{
    $baseDir = dirname(__DIR__);
    $options = CharlieMindPullerOptions::fromArgv($argv, $baseDir);

    echo "CharlieMind Pull\n\n";
    echo 'API: '.$options->apiUrl.PHP_EOL;
    echo 'Vault: '.$options->vaultPath.PHP_EOL.PHP_EOL;

    if ($options->apiUrl === '' || $options->apiToken === '' || $options->vaultPath === '') {
        fwrite(STDERR, 'Missing CHARLIEMIND_API_URL, CHARLIEMIND_API_TOKEN, or CHARLIEMIND_LOCAL_VAULT_PATH.'.PHP_EOL);

        return 1;
    }

    $puller = new CharlieMindPuller(
        new CharlieMindPullerApiClient($options->apiUrl, $options->apiToken),
        $options,
    );

    try {
        $result = $puller->pull();
    } catch (Throwable $throwable) {
        fwrite(STDERR, $throwable->getMessage().PHP_EOL);

        return 1;
    }

    echo 'Found '.$result['found'].' pending exports.'.PHP_EOL.PHP_EOL;

    foreach ($result['lines'] as $line) {
        echo $line.PHP_EOL;
    }

    if ($result['captures_exported'] !== []) {
        echo PHP_EOL.'Marked exported:'.PHP_EOL;

        foreach ($result['captures_exported'] as $captureId) {
            echo '- '.$captureId.PHP_EOL;
        }
    }

    echo PHP_EOL.'Done.'.PHP_EOL;
    echo 'Downloaded: '.$result['downloaded'].' files'.PHP_EOL;
    echo 'Dry run: '.$result['planned'].' files'.PHP_EOL;
    echo 'Skipped existing: '.$result['skipped_existing'].PHP_EOL;
    echo 'Failed: '.$result['failed'].PHP_EOL;
    echo 'Captures exported: '.count($result['captures_exported']).PHP_EOL;

    return $result['failed'] === 0 ? 0 : 1;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(charliemind_puller_main($argv));
}
