<?php

namespace App\Console\Commands;

use App\Models\Capture;
use App\Services\CharlieMindStorage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('captures:verify-files {--status=} {--limit=50}')]
#[Description('Verify capture markdown and media files exist on the configured storage disk')]
class CapturesVerifyFilesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CharlieMindStorage $storage): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $status = $this->option('status');
        $missingFiles = 0;

        $this->line('Using disk: '.$storage->diskName());
        $this->line('Using root: '.$storage->root());
        $this->newLine();

        $captures = Capture::query()
            ->when(is_string($status) && $status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        foreach ($captures as $capture) {
            $missingFiles += $this->checkPath($storage, $capture->capture_id, 'markdown', $capture->markdown_path);

            if ($capture->processed_markdown_path !== null) {
                $missingFiles += $this->checkPath($storage, $capture->capture_id, 'processed markdown', $capture->processed_markdown_path);
            }

            if ($capture->media_path !== null) {
                $missingFiles += $this->checkPath($storage, $capture->capture_id, 'media', $capture->media_path);
            }
        }

        return $missingFiles === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function checkPath(CharlieMindStorage $storage, string $captureId, string $label, string $vaultRelativePath): int
    {
        $objectPath = $storage->objectPath($vaultRelativePath);

        if ($storage->exists($vaultRelativePath)) {
            $this->info("✓ {$captureId} {$label} exists: {$objectPath}");

            return 0;
        }

        $this->error("✗ {$captureId} {$label} missing: {$objectPath}");

        return 1;
    }
}
