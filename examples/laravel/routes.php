<?php

// Add to routes/web.php

use App\Http\Controllers\WasabiFileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('wasabi')->name('wasabi.')->group(function () {
    Route::get('/', [WasabiFileController::class, 'index'])->name('index');          // Blade / Twig page
    Route::get('/files', [WasabiFileController::class, 'list'])->name('list');       // JSON for Vue / React
    Route::post('/files', [WasabiFileController::class, 'store'])->name('store');    // classic form upload
    Route::post('/presign', [WasabiFileController::class, 'presign'])->name('presign'); // direct upload, step 1
    Route::post('/confirm', [WasabiFileController::class, 'confirm'])->name('confirm'); // direct upload, step 2
    Route::get('/download/{key}', [WasabiFileController::class, 'download'])->where('key', '.*')->name('download');
    Route::delete('/files/{key}', [WasabiFileController::class, 'destroy'])->where('key', '.*')->name('destroy');
});
