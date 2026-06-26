<?php

use App\Http\Controllers\Api\CaptureController;
use App\Http\Controllers\Api\ExportController;
use Illuminate\Support\Facades\Route;

Route::middleware('capture.token')->group(function (): void {
    Route::get('captures', [CaptureController::class, 'index'])->name('captures.index');
    Route::post('captures', [CaptureController::class, 'store'])->name('captures.store');
    Route::get('captures/{capture}', [CaptureController::class, 'show'])->name('captures.show');

    Route::get('exports/pending', [ExportController::class, 'pending'])->name('exports.pending');
    Route::get('exports/file', [ExportController::class, 'file'])->name('exports.file');
    Route::post('exports/mark-complete', [ExportController::class, 'markComplete'])->name('exports.mark-complete');
});
