<?php

namespace App\Console\Commands;

use App\Services\CapturePathGenerator;
use Illuminate\Console\Command;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Signature('captures:health')]
#[Description('Check CharlieMind capture API configuration, storage, and database access')]
class CapturesHealthCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CapturePathGenerator $pathGenerator): int
    {
        $healthy = true;
        $token = config('charliemind.capture_api_token');

        if (is_string($token) && $token !== '') {
            $this->info('Capture API token is configured.');
        } else {
            $this->error('Capture API token is missing.');
            $healthy = false;
        }

        $disk = Storage::disk('charliemind');

        foreach ($pathGenerator->inboxDirectories() as $directory) {
            if (! $disk->exists($directory)) {
                $disk->makeDirectory($directory);
            }
        }

        $this->info('CharlieMind inbox folders exist.');

        try {
            DB::connection()->getPdo();
            $this->info('Database connection is available.');
        } catch (\Throwable $throwable) {
            $this->error('Database connection failed: '.$throwable->getMessage());
            $healthy = false;
        }

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
