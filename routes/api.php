<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\KontakController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Api\KaryawanController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Api\PenggajianController;
use App\Http\Controllers\Api\RekruitmenController;
use App\Http\Controllers\Api\LowonganKerjaController;
use App\Http\Controllers\Auth\DeleteAccountController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Api\ExportPenggajianController;
use App\Http\Controllers\Api\TagihanPerusahaanController;
use App\Http\Controllers\Api\ExportTagihanPerusahaanController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// 📆 Route public lowongan kerja
Route::get('/lowongan-kerja', [LowonganKerjaController::class, 'getLowonganAktif']);
Route::get('/lowongan-kerja/statistik', [LowonganKerjaController::class, 'statistik']);
Route::get('/lowongan-kerja/{id}', [LowonganKerjaController::class, 'detailLowongan']);

// 📆 Pendaftaran rekruitmen (public)
Route::post('/rekruitmen', [RekruitmenController::class, 'store']);
Route::post('/rekruitmen/cek', [RekruitmenController::class, 'checkStatusByToken']);

// 📱 Kontak publik (create)
Route::post('kontak', [KontakController::class, 'store'])->name('api.kontak.store');

// 🛻 Auth routes
Route::post('/register', RegisterController::class);
Route::post('/login', LoginController::class);
Route::post('/logout', LogoutController::class)->middleware('auth:sanctum');
Route::post('/ganti-password', ChangePasswordController::class)->middleware('auth:sanctum');

// 👇 Hapus akun
Route::middleware('auth:sanctum')->delete('/hapus-akun', DeleteAccountController::class);

// 📆 Manajemen rekruitmen (admin)
Route::middleware('auth:sanctum')->prefix('rekruitmen')->group(function () {
    Route::get('/', [RekruitmenController::class, 'index']);
    Route::get('/{id}', [RekruitmenController::class, 'show']);
    Route::put('/{id}', [RekruitmenController::class, 'update']);
    Route::delete('/{id}', [RekruitmenController::class, 'destroy']);
    Route::patch('/{id}/status', [RekruitmenController::class, 'updateStatus']);
});

// 👥 Manajemen karyawan
Route::middleware('auth:sanctum')->prefix('karyawan')->group(function () {
    // Rute statis harus di atas /{id}
    Route::get('/download-excel', [KaryawanController::class, 'downloadExcel']);
    Route::post('/bulk-download-kartu', [KaryawanController::class, 'bulkDownloadKartuPdf']);
    Route::post('/restore-by-nik', [KaryawanController::class, 'restoreByNik']);
    Route::post('/import', [KaryawanController::class, 'import'])->name('karyawan.import');
    Route::get('/import/template', [KaryawanController::class, 'downloadTemplate'])->name('karyawan.import.template');
    Route::post('/import/preview', [KaryawanController::class, 'importPreview'])->name('karyawan.import.preview');
    Route::get('/import/status', [KaryawanController::class, 'importStatus'])->name('karyawan.import.status');
    // CRUD
    Route::get('/', [KaryawanController::class, 'index']);
    Route::post('/', [KaryawanController::class, 'store']);
    Route::get('/{id}', [KaryawanController::class, 'show']);
    Route::put('/{id}', [KaryawanController::class, 'update']);
    Route::delete('/{id}', [KaryawanController::class, 'destroy']);
    // Download kartu per ID
    Route::get('/{id}/download-kartu', [KaryawanController::class, 'downloadKartuPdf']);
    Route::get('/{id}/preview-kartu', [KaryawanController::class, 'previewKartuPdf']);
});

// 📱 Manajemen kontak (admin)
Route::middleware('auth:sanctum')->prefix('kontak')->name('api.kontak.')->group(function () {
    Route::get('/', [KontakController::class, 'index'])->name('index');
    Route::get('/{id}', [KontakController::class, 'show'])->name('show');
    Route::put('/{id}', [KontakController::class, 'update'])->name('update');
    Route::delete('/{id}', [KontakController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/status', [KontakController::class, 'updateStatus'])->name('update-status');
});

// 💰 Manajemen tagihan perusahaan
Route::middleware('auth:sanctum')->prefix('tagihan')->name('api.tagihan.')->group(function () {
    // Rute statis harus di atas /{id}
    Route::get('/export/available-months', [ExportTagihanPerusahaanController::class, 'getAvailableMonths']);
    Route::get('/export/preview', [ExportTagihanPerusahaanController::class, 'previewExport']);
    Route::get('/export/excel', [ExportTagihanPerusahaanController::class, 'exportExcel']);
    Route::get('/summary', [TagihanPerusahaanController::class, 'summary']);
    Route::get('/available-months', [TagihanPerusahaanController::class, 'getAvailableMonths']);
    Route::post('/copy-previous-month', [TagihanPerusahaanController::class, 'copyFromPreviousMonth']);
    Route::post('/import', [TagihanPerusahaanController::class, 'import']);
    Route::post('/bulk-delete', [TagihanPerusahaanController::class, 'bulkDestroy']);
    // CRUD
    Route::get('/', [TagihanPerusahaanController::class, 'index']);
    Route::post('/', [TagihanPerusahaanController::class, 'store']);
    Route::get('/{id}', [TagihanPerusahaanController::class, 'show']);
    Route::put('/{id}', [TagihanPerusahaanController::class, 'update']);
    Route::delete('/{id}', [TagihanPerusahaanController::class, 'destroy']);
    Route::post('/{id}/restore', [TagihanPerusahaanController::class, 'restore']);
});

// 💰 Manajemen penggajian
Route::middleware('auth:sanctum')->prefix('penggajian')->name('api.penggajian.')->group(function () {
    // Rute statis harus di atas /{id}
    Route::get('/available-months', [ExportPenggajianController::class, 'getAvailableMonths']);
    Route::get('/preview', [ExportPenggajianController::class, 'previewExport']);
    Route::get('/excel', [ExportPenggajianController::class, 'exportExcel']);
    Route::get('/test-data', [ExportPenggajianController::class, 'testData']);
    Route::post('/batch', [PenggajianController::class, 'batchStore']);
    Route::get('/summary/statistik', [PenggajianController::class, 'summary']);
    Route::get('/available-months-list', [PenggajianController::class, 'getAvailableMonths']);
    Route::post('/preview-copy', [PenggajianController::class, 'previewCopy']);
    Route::post('/copy-previous-month', [PenggajianController::class, 'copyFromPreviousMonth']);
    // CRUD
    Route::get('/', [PenggajianController::class, 'index']);
    Route::post('/', [PenggajianController::class, 'store']);
    Route::post('/send-whatsapp-bulk', [PenggajianController::class, 'sendWhatsAppBulk']);
    Route::get('/{id}', [PenggajianController::class, 'show']);
    Route::put('/{id}', [PenggajianController::class, 'update']);
    Route::delete('/{id}', [PenggajianController::class, 'destroy']);
    Route::get('/{id}/send-whatsapp', [PenggajianController::class, 'sendWhatsApp']);
    Route::post('/{id}/cetak', [PenggajianController::class, 'cetakSlip']);
});

// 👥 Manajemen lowongan kerja (admin)
Route::middleware('auth:sanctum')->prefix('lowongan')->group(function () {
    Route::get('/', [LowonganKerjaController::class, 'index']);
    Route::post('/', [LowonganKerjaController::class, 'store']);
    Route::get('/{id}', [LowonganKerjaController::class, 'show']);
    Route::put('/{id}', [LowonganKerjaController::class, 'update']);
    Route::delete('/{id}', [LowonganKerjaController::class, 'destroy']);
    Route::get('/{id}/pelamar', [LowonganKerjaController::class, 'pelamar']);
});

// 📊 Manajemen dashboard
Route::middleware('auth:sanctum')->prefix('dashboard')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'getDashboardData']);
    Route::get('/status', [DashboardController::class, 'getStats']);
    Route::get('/summary', [DashboardController::class, 'getSummary']);
    Route::get('/gaji-chart', [DashboardController::class, 'getGajiChart']);
    Route::get('/pelamar-chart', [DashboardController::class, 'getPelamarChart']);
});

// 📱 Manajemen kontak (admin)
Route::middleware('auth:sanctum')->prefix('kontak')->name('api.kontak.')->group(function () {
    Route::get('/', [KontakController::class, 'index'])->name('index');
    Route::get('/{id}', [KontakController::class, 'show'])->name('show');
    Route::put('/{id}', [KontakController::class, 'update'])->name('update');
    Route::delete('/{id}', [KontakController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/status', [KontakController::class, 'updateStatus'])->name('update-status');
});
