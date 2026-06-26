<?php

use App\Http\Controllers\Api\CaptureController;
use Illuminate\Support\Facades\Route;

Route::middleware('capture.token')->group(function (): void {
    Route::get('captures', [CaptureController::class, 'index'])->name('captures.index');
    Route::post('captures', [CaptureController::class, 'store'])->name('captures.store');
    Route::get('captures/{capture}', [CaptureController::class, 'show'])->name('captures.show');
});
