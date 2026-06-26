<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CharlieMindStorage
{
    public function disk(): FilesystemAdapter
    {
        return Storage::disk($this->diskName());
    }

    public function diskName(): string
    {
        return (string) config('charliemind.disk');
    }

    public function root(): string
    {
        return trim((string) config('charliemind.root', ''), '/');
    }

    public function objectPath(string $vaultRelativePath): string
    {
        $vaultRelativePath = trim($vaultRelativePath, '/');
        $root = $this->root();

        if ($root === '') {
            return $this->normalizePath($vaultRelativePath);
        }

        return $this->normalizePath($root.'/'.$vaultRelativePath);
    }

    public function putVaultFile(string $vaultRelativePath, string $contents): bool
    {
        return $this->disk()->put($this->objectPath($vaultRelativePath), $contents);
    }

    public function putUploadedFile(string $vaultRelativePath, UploadedFile $file): string|false
    {
        $objectPath = $this->objectPath($vaultRelativePath);
        $directory = dirname($objectPath);

        return $this->disk()->putFileAs(
            $directory === '.' ? '' : $directory,
            $file,
            basename($objectPath)
        );
    }

    public function exists(string $vaultRelativePath): bool
    {
        return $this->disk()->exists($this->objectPath($vaultRelativePath));
    }

    public function get(string $vaultRelativePath): string
    {
        return $this->disk()->get($this->objectPath($vaultRelativePath));
    }

    public function delete(string $vaultRelativePath): bool
    {
        return $this->disk()->delete($this->objectPath($vaultRelativePath));
    }

    public function url(string $vaultRelativePath): ?string
    {
        try {
            return $this->disk()->url($this->objectPath($vaultRelativePath));
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizePath(string $path): string
    {
        return preg_replace('#/+#', '/', $path) ?? $path;
    }
}
