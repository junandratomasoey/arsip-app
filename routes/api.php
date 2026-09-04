<?php

use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\WorkController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
 * REST API v1 - baca-saja, untuk integrasi sistem lain (Bab IV.14
 * dokumen perancangan). Autentikasi lewat token Sanctum pribadi yang
 * dibuat dari halaman Profile; hak akses per endpoint tetap mengikuti
 * permission Spatie pemilik token, sama seperti saat login lewat web.
 */
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('v1')->name('api.v1.')->group(function () {
    Route::middleware('can:work.view')->group(function () {
        Route::get('works', [WorkController::class, 'index'])->name('works.index');
        Route::get('works/{work}', [WorkController::class, 'show'])->name('works.show');
        Route::get('works/{work}/documents', [WorkController::class, 'documents'])->name('works.documents');
    });

    Route::middleware('can:document.view')->group(function () {
        Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    });

    Route::middleware('can:document.download')->group(function () {
        Route::get('documents/{document}/files/{version}/download', [DocumentController::class, 'downloadFile'])
            ->name('documents.files.download');
    });
});
