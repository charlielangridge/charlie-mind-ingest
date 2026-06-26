<?php

namespace App\Console\Commands;

use App\Models\Capture;
use App\Services\CaptureProcessingOutcome;
use App\Services\CaptureProcessor;
use App\Services\CaptureStagingPaths;
use App\Services\CharlieMindStorage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

#[Signature('captures:process {--limit=20} {--type=} {--id=} {--dry-run} {--retry-failed}')]
#[Description('Process pending CharlieMind captures into cleaned Obsidian notes')]
class CapturesProcessCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CaptureProcessor $processor, CharlieMindStorage $storage, CaptureStagingPaths $stagingPaths): int
    {
        if (! $this->enabled()) {
            $this->line('Capture processor is disabled.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run') || $this->configBool('charliemind.processor_dry_run');
        $captures = $this->captures();

        if ($captures->isEmpty()) {
            $this->line('No captures to process.');

            return self::SUCCESS;
        }

        $this->line(($dryRun ? 'Dry run: p' : 'P').'rocessing '.$captures->count().' captures...');
        $this->newLine();

        $outcomes = [];

        foreach ($captures as $capture) {
            $outcome = $processor->process($capture, $dryRun);
            $outcomes[] = $outcome;
            $this->writeOutcome($outcome, $dryRun);
        }

        $processed = collect($outcomes)->where('status', Capture::STATUS_PROCESSED)->count();
        $failed = collect($outcomes)->filter->failed()->count();
        $skipped = collect($outcomes)->where('status', Capture::STATUS_SKIPPED)->count();
        $needsReview = collect($outcomes)->where('needsReview', true)->count();

        if (! $dryRun) {
            $this->appendProcessingLog($storage, $stagingPaths, $outcomes, $processed, $failed, $skipped, $needsReview);
        }

        $this->newLine();
        $this->line("Done. Processed: {$processed}. Failed: {$failed}. Skipped: {$skipped}. Needs review: {$needsReview}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function enabled(): bool
    {
        return $this->configBool('charliemind.processor_enabled');
    }

    private function configBool(string $key): bool
    {
        return filter_var(config($key), FILTER_VALIDATE_BOOL);
    }

    /**
     * @return Collection<int, Capture>
     */
    private function captures()
    {
        $limit = $this->limit();
        $id = $this->option('id');
        $type = $this->option('type');
        $statuses = [Capture::STATUS_PENDING];

        if ((bool) $this->option('retry-failed')) {
            $statuses[] = Capture::STATUS_FAILED;
        }

        return Capture::query()
            ->when(is_string($id) && $id !== '', function (Builder $query) use ($id): void {
                $query->where(function (Builder $query) use ($id): void {
                    $query->where('capture_id', $id);

                    if (ctype_digit($id)) {
                        $query->orWhere('id', (int) $id);
                    }
                });
            }, fn (Builder $query): Builder => $query->whereIn('status', $statuses))
            ->when(is_string($type) && $type !== '', fn (Builder $query): Builder => $query->where('type', Capture::normalizeType($type)))
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    private function limit(): int
    {
        $requestedLimit = max(1, (int) $this->option('limit'));
        $configuredLimit = max(1, (int) config('charliemind.processor_max_per_run', 20));

        return min($requestedLimit, $configuredLimit);
    }

    private function writeOutcome(CaptureProcessingOutcome $outcome, bool $dryRun): void
    {
        $capture = $outcome->capture;

        if ($outcome->failed()) {
            $this->error("✗ {$capture->capture_id} {$capture->type} failed: {$outcome->message}");

            return;
        }

        $prefix = $dryRun ? '-' : ($outcome->needsReview ? '⚠' : '✓');
        $label = $dryRun ? 'would write '.$outcome->processedPath : $outcome->processedPath;
        $review = $outcome->needsReview ? ' needs review: '.$outcome->reviewReason : '';

        $this->line("{$prefix} {$capture->capture_id} {$capture->type} → {$label}{$review}");
    }

    /**
     * @param  array<int, CaptureProcessingOutcome>  $outcomes
     */
    private function appendProcessingLog(CharlieMindStorage $storage, CaptureStagingPaths $stagingPaths, array $outcomes, int $processed, int $failed, int $skipped, int $needsReview): void
    {
        $lines = [
            '',
            '## '.now()->format('Y-m-d H:i'),
            '',
            'Processed: '.$processed,
            'Failed: '.$failed,
            'Skipped: '.$skipped,
            'Needs review: '.$needsReview,
            '',
        ];

        foreach ($outcomes as $outcome) {
            $capture = $outcome->capture;

            if ($outcome->failed()) {
                $lines[] = "- ✗ {$capture->capture_id} failed: {$outcome->message}";
            } elseif ($outcome->needsReview) {
                $lines[] = "- ⚠ {$capture->capture_id} → {$outcome->processedPath} {$outcome->reviewReason}";
            } else {
                $lines[] = "- ✓ {$capture->capture_id} → {$outcome->processedPath}";
            }
        }

        $lines[] = '';

        $storage->appendVaultFile($stagingPaths->logPath(), implode(PHP_EOL, $lines));
    }
}
