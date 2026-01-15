<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\KontakController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Api\KaryawanController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Api\PenggajianController;
use App\Http\Controllers\Api\RekruitmenController;
use App\Http\Controllers\Auth\DeleteAccountController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Api\ExportPenggajianController;
use App\Http\Controllers\Api\TagihanPerusahaanController;
use App\Http\Controllers\Api\ExportTagihanPerusahaanController;

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
Route::post('/ganti-password', ChangePasswordController::class)->middleware('auth:sanctum');  
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
Route::middleware('auth:sanctum')->prefix('tagihan')->name('api.tagihan.')->group(function () {
Route::get('export/test', [ExportTagihanPerusahaanController::class, 'testData']);
    Route::get('export/available-periodes', [ExportTagihanPerusahaanController::class, 'getAvailablePeriodes']);
    Route::get('export/preview', [ExportTagihanPerusahaanController::class, 'previewExport']);
    Route::get('export/excel', [ExportTagihanPerusahaanController::class, 'exportExcel']);
    Route::get('/', [TagihanPerusahaanController::class, 'index']);
    Route::post('/', [TagihanPerusahaanController::class, 'store']);
    Route::post('/import', [TagihanPerusahaanController::class, 'import']);
    Route::post('/bulk-delete', [TagihanPerusahaanController::class, 'bulkDestroy']);
    Route::get('/summary', [TagihanPerusahaanController::class, 'summary']);
    Route::post('/export', [TagihanPerusahaanController::class, 'export']);
    Route::get('/{id}', [TagihanPerusahaanController::class, 'show']);
    Route::put('/{id}', [TagihanPerusahaanController::class, 'update']);
    Route::delete('/{id}', [TagihanPerusahaanController::class, 'destroy']);
    Route::post('/{id}/restore', [TagihanPerusahaanController::class, 'restore']);
});
Route::middleware('auth:sanctum')->prefix('penggajian')->name('api.penggajian.')->group(function () {
    // Route::get('/', [PenggajianController::class, 'index'])->name('index');
    // Route::post('/', [PenggajianController::class, 'store'])->name('store');
    // Route::get('/{id}', [PenggajianController::class, 'show'])->name('show');
    // Route::put('/{id}', [PenggajianController::class, 'update'])->name('update');
    // Route::delete('/{id}', [PenggajianController::class, 'destroy'])->name('destroy');
    // Route::post('/filter', [PenggajianController::class, 'filterByPeriode'])->name('filter-periode');
    // Route::get('/penggajian/get-by-periode', [PenggajianController::class, 'getByPeriode']);
    
    // route export penggajian
    Route::get('/available-months', [ExportPenggajianController::class, 'getAvailableMonths']);
    Route::get('/preview', [ExportPenggajianController::class, 'previewExport']);
    Route::get('/excel', [ExportPenggajianController::class, 'exportExcel']);
    Route::get('/', [PenggajianController::class, 'index']);
    Route::post('/', [PenggajianController::class, 'store']);
    Route::get('/{id}', [PenggajianController::class, 'show']);
    Route::put('/{id}', [PenggajianController::class, 'update']);
    Route::delete('/{id}', [PenggajianController::class, 'destroy']);
    
    // Additional Routes
    Route::post('/batch', [PenggajianController::class, 'batchStore']);
    Route::post('/{id}/cetak', [PenggajianController::class, 'cetakSlip']);
    Route::get('/summary/statistik', [PenggajianController::class, 'summary']);
    Route::get('/test-data', [ExportPenggajianController::class, 'testData']); // Route testing
});

Route::middleware('auth:sanctum')->group(function () {
    // Hapus akun langsung (dengan konfirmasi password)
    Route::delete('/hapus-akun', DeleteAccountController::class);

    // routes/api.php

    
});