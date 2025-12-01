<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ExportController;

Route::get('/', function () {
    return redirect('/modules.php');
});

// Admin routes (Laravel migration proof-of-concept)
Route::prefix('admin')->middleware('web')->group(function () {
    Route::get('/export', [ExportController::class, 'index'])->name('admin.export');
    Route::post('/export', [ExportController::class, 'export'])->name('admin.export.process');
});

// Future module routes will be added here as migration progresses
