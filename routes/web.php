<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PreviewController;

Route::middleware('admin.token')->group(function () {
    Route::get('/preview/{website}', [PreviewController::class, 'index']);
    Route::get('/preview/folder/{folder}', [PreviewController::class, 'folder']);
    Route::get('/preview/folder/{folder}/{slug}', [PreviewController::class, 'folder'])->where('slug', '.*');
    Route::get('/preview/{website}/{path}', [PreviewController::class, 'page'])->where('path', '.*');
});

Route::get('/{path?}', function () {
    return view('app');
})->where('path', '.*');