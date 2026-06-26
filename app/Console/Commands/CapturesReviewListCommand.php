<?php

namespace App\Console\Commands;

use App\Models\Capture;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('captures:review-list {--limit=50} {--reason=}')]
#[Description('List processed CharlieMind captures that still need review')]
class CapturesReviewListCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $reason = $this->option('reason');

        $captures = Capture::query()
            ->where('status', Capture::STATUS_PROCESSED)
            ->where('needs_review', true)
            ->when(is_string($reason) && $reason !== '', fn (Builder $query): Builder => $query->where('review_reason', $reason))
            ->orderBy('processed_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($captures->isEmpty()) {
            $this->line('No captures need review.');

            return self::SUCCESS;
        }

        $this->table(
            ['Capture ID', 'Type', 'Reason', 'Processed Path'],
            $captures->map(fn (Capture $capture): array => [
                $capture->capture_id,
                $capture->type,
                $capture->review_reason ?? '',
                $capture->processed_markdown_path ?? '',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
