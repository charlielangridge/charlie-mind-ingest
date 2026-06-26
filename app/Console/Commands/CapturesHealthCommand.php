<?php

namespace App\Console\Commands;

use App\Services\CharlieMindStorage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('captures:health')]
#[Description('Check CharlieMind capture API configuration, storage, and database access')]
class CapturesHealthCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CharlieMindStorage $storage): int
    {
        $healthy = true;
        $token = config('charliemind.capture_api_token');

        $this->line('Configured disk: '.$storage->diskName());
        $this->line('Configured storage root: '.$storage->root());

        if (is_string($token) && $token !== '') {
            $this->info('Capture API token is configured.');
        } else {
            $this->error('Capture API token is missing.');
            $healthy = false;
        }

        try {
            $storage->disk();
            $this->info('Configured storage disk can be resolved.');
        } catch (Throwable $throwable) {
            $this->error('Configured storage disk cannot be resolved: '.$throwable->getMessage());

            return self::FAILURE;
        }

        $healthCheckPath = '.health-check/'.now()->format('YmdHis').'.txt';
        $healthCheckContents = 'ok';

        try {
            $written = $storage->putVaultFile($healthCheckPath, $healthCheckContents);
            $readMatches = $storage->get($healthCheckPath) === $healthCheckContents;
            $deleted = $storage->delete($healthCheckPath);

            if ($written && $readMatches && $deleted && ! $storage->exists($healthCheckPath)) {
                $this->info('Storage write/read/delete test succeeded: '.$storage->objectPath($healthCheckPath));
            } else {
                $this->error('Storage write/read/delete test failed: '.$storage->objectPath($healthCheckPath));
                $healthy = false;
            }
        } catch (Throwable $throwable) {
            $this->error('Storage write/read/delete test failed: '.$throwable->getMessage());
            $healthy = false;
        }

        try {
            DB::connection()->getPdo();
            $this->info('Database connection is available.');
        } catch (Throwable $throwable) {
            $this->error('Database connection failed: '.$throwable->getMessage());
            $healthy = false;
        }

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
