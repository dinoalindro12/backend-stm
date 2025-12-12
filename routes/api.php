<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\KontakController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Api\KaryawanController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Api\RekruitmenController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
// route public rekruitmen
Route::post('/rekrutmen', [RekruitmenController::class, 'store']);
Route::post('/check-token', [RekruitmenController::class, 'checkByToken']);
// auth routes
Route::post('/register', RegisterController::class);
Route::post('/login', LoginController::class);
Route::post('/logout', LogoutController::class)->middleware('auth:sanctum');
// manajemen rekruitmen
Route::middleware('auth:sanctum')->prefix('rekruitmen')->group(function () {
    // bagian ini diproteksi menggunakan sanctum
    Route::get('/rekrutan', [RekruitmenController::class, 'index']);
    Route::get('/{id}', [RekruitmenController::class, 'show']);
    Route::post('/{id}', [RekruitmenController::class, 'update']); // Using POST for file uploads
    Route::delete('/{id}', [RekruitmenController::class, 'destroy']);
    Route::post('/{id}/status', [RekruitmenController::class, 'updateStatus']);
});
// manajemen karyawan
Route::middleware('auth:sanctum')->group(function () {
    // bagian ini diproteksi menggunakan sanctum
    Route::get('karyawan', [KaryawanController::class, 'index']);
    Route::post('karyawan', [KaryawanController::class, 'store']);
    Route::get('karyawan/{id}', [KaryawanController::class, 'show']);
    Route::put('karyawan/{id}', [KaryawanController::class, 'update']);
    Route::delete('karyawan/{id}', [KaryawanController::class, 'destroy']);
});
// manajemen kontak
Route::middleware('auth:sanctum')->prefix('kontak')->name('api.kontak.')->group(function () {
    Route::get('/', [KontakController::class, 'index'])->name('index');
    Route::get('/{id}', [KontakController::class, 'show'])->name('show');
    Route::put('/{id}', [KontakController::class, 'update'])->name('update');
    Route::delete('/{id}', [KontakController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/status', [KontakController::class, 'updateStatus'])->name('update-status');
});
Route::post('kontak', [KontakController::class, 'store'])->name('api.kontak.store');