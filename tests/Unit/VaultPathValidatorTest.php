<?php

use App\Services\VaultPathValidator;
use Tests\TestCase;

uses(TestCase::class);

test('normalizes safe vault relative paths', function () {
    $validator = new VaultPathValidator;

    expect($validator->normalize('Review//2026-06-26 - note.md'))->toBe('Review/2026-06-26 - note.md')
        ->and($validator->normalize('inbox\\audio\\capture.m4a'))->toBe('inbox/audio/capture.m4a');
});

test('rejects unsafe vault paths', function (?string $path) {
    expect((new VaultPathValidator)->normalize($path))->toBeNull();
})->with([
    'null' => [null],
    'empty' => [''],
    'leading slash' => ['/Review/note.md'],
    'traversal' => ['Review/../secret.md'],
    'single dot segment' => ['Review/./note.md'],
    'null byte' => ["Review/note.md\0"],
    'windows drive slash' => ['C:/Users/charl/note.md'],
    'windows drive backslash' => ['C:\\Users\\charl\\note.md'],
]);
