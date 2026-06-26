<?php

namespace App\Services;

class VaultPathValidator
{
    public function normalize(?string $path): ?string
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

        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = trim($path);
        $path = trim($path, '/');

        if ($path === '') {
            return null;
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $path;
    }

    public function isSafe(?string $path): bool
    {
        return $this->normalize($path) !== null;
    }
}
