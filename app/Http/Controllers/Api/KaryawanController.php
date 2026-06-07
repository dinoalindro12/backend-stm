<?php

namespace App\Http\Controllers\Api;


use App\Models\Karyawan;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Exports\KaryawanExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\KaryawanResource;
use Illuminate\Support\Facades\Validator;
use App\Imports\KaryawanImport;



class KaryawanController extends Controller
{
    /**
     * Menampilkan semua karyawan dengan filtering
     */
    public function index(Request $request)
    {
        try {
            $query = Karyawan::with(['admin', 'updatedBy']);

            // Filter berdasarkan status aktif
            if ($request->has('status_aktif')) {
                $query->where('status_aktif', $request->status_aktif);
            }

            // Filter berdasarkan posisi
            if ($request->filled('posisi')) {
                $query->where('posisi', $request->posisi);
            }

            // Search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nomor_induk', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('no_wa', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortField = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortField, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 10);
            $karyawan = $query->paginate($perPage);

            return KaryawanResource::collection($karyawan)->additional([
                'success' => true,
                'message' => 'List Data Karyawan'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Menyimpan data karyawan baru
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $validator = Validator::make($request->all(), [
                'nomor_induk'   => 'required|unique:karyawans,nomor_induk|regex:/^[0-9]+$/|max:',
                'nik'           => 'required|unique:karyawans,nik|string|regex:/^[0-9]+$/|max:16',
                'no_rek_bri'    => 'nullable|unique:karyawans,no_rek_bri|string|regex:/^[0-9]+$/|max:16',
                'nama_lengkap'  => 'required|string|max:100',
                'posisi'        => 'required|string|in:jasa,supir,keamanan,cleaning_service,operator',
                'email'         => 'nullable|email|unique:karyawans,email|max:100',
                'alamat'        => 'required|string',
                'no_wa'         => 'required|string|regex:/^[0-9]+$/|max:15',
                'image'         => 'nullable|image|mimes:jpeg,png,jpg,svg,webp|max:5120', // 5MB
                'tanggal_masuk' => 'required|date',
                'tanggal_keluar'=> 'nullable|date|after_or_equal:tanggal_masuk',
                'status_aktif'  => 'required|boolean',
            ], [
                'nomor_induk.unique' => 'Maaf, nomor induk sudah terdaftar',
                'nik.unique' => 'Maaf, NIK sudah terdaftar',
                'no_rek_bri.unique' => 'Maaf, nomor Rekening BRI sudah terdaftar',
                'email.unique' => 'Maaf, email sudah terdaftar',
                'image.max' => 'Maaf, ukuran gambar maksimal 5MB',
                'no_wa.regex' => "Maaf, format nomor WhatsApp yang anda masukan tidak valid",
                'image.mimes' => 'Maaf, format gambar harus jpeg, png, jpg, svg, atau webp',
                'no_wa.unique' => 'Maaf, nomor  WhatsApp yang anda masukan sudah terdaftar',
                'no_rek_bri.max' => 'Maaf, nomor rekening BRI maksimal 16 digit',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Catat admin yang menambahkan
            $data['admin_id'] = $request->user()->id;
            $data['updated_by'] = $request->user()->id;
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = Str::random(40) . '.' . $image->getClientOriginalExtension();
                $imagePath = 'karyawans/' . $imageName;
                
                // Simpan ke storage
                Storage::disk('public')->put($imagePath, file_get_contents($image));
                $data['image'] = $imagePath;
            }
            
            // Set default nilai jika kosong
            $data['tanggal_keluar'] = $data['tanggal_keluar'] ?? null;
            $data['email'] = $data['email'] ?? null;
            $data['no_wa'] = $data['no_wa'] ?? null;

            // Simpan data
            $karyawan = Karyawan::create($data);

            DB::commit();

            return (new KaryawanResource($karyawan->load(['admin', 'updatedBy'])))->additional([
                'success' => true,
                'message' => 'Data karyawan berhasil disimpan'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Hapus gambar jika ada error setelah upload
            if (isset($imagePath) && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Menampilkan detail 1 karyawan
     */
    public function show($id)
    {
        try {
            $karyawan = Karyawan::with(['admin', 'updatedBy'])->find($id);

            if (!$karyawan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan'
                ], 404);
            }

            return (new KaryawanResource($karyawan->load(['admin', 'updatedBy'])))->additional([
                'success' => true,
                'message' => 'Detail data karyawan'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update data karyawan
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        
        try {
            $karyawan = Karyawan::find($id);
            
            if (!$karyawan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'nik'           => 'sometimes|required|unique:karyawans,nik,' . $karyawan->id . '|string|regex:/^[0-9]+$/|max:16',
                'no_rek_bri'    => 'nullable|unique:karyawans,no_rek_bri,' . $karyawan->id . '|string|regex:/^[0-9]+$/|max:15',
                'nama_lengkap'  => 'sometimes|required|string|max:100',
                'posisi'        => 'sometimes|required|string|in:jasa,supir,keamanan,cleaning_service,operator',
                'email'         => 'nullable|email|unique:karyawans,email,' . $karyawan->id . '|max:100',
                'alamat'        => 'sometimes|required|string',
                'no_wa'         => 'sometimes|string|regex:/^[0-9]+$/|max:15',
                'image'         => 'nullable|image|mimes:jpeg,png,jp,svg,webp|max:5120',
                'tanggal_masuk' => 'sometimes|required|date',
                'tanggal_keluar'=> 'nullable|date|after_or_equal:tanggal_masuk',
                'status_aktif'  => 'sometimes|required|boolean',
            ], [
                'nik.unique' => 'NIK sudah terdaftar',
                'no_rek_bri.unique' => 'No Rekening BRI sudah terdaftar',
                'no_rek_bri.max' => 'Maaf, nomor rekening BRI maksimal 16 digit',
                'no_wa.unique' => 'Nomor WhatsApp sudah terdaftar',
                'no_wa.max' => 'Maaf, nomor WhatsApp maksimal 15 digit',
                'no_wa.regex' => "Maaf, format nomor WhatsApp yang anda masukan tidak valid",
                'email.unique' => 'Email sudah terdaftar',
                'image.max' => 'Ukuran gambar maksimal 5MB',
                'image.mimes' => 'Format gambar harus jpeg, png, jpg, svg, atau webp',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Catat admin yang melakukan perubahan
            $data['updated_by'] = $request->user()->id;

            // Tangani upload foto baru
            if ($request->hasFile('image')) {
                // Hapus foto lama — gunakan getRawOriginal agar tidak kena accessor URL
                $oldImage = $karyawan->getRawOriginal('image');
                if ($oldImage && Storage::disk('public')->exists($oldImage)) {
                    Storage::disk('public')->delete($oldImage);
                }
                
                $image = $request->file('image');
                $imageName = Str::random(40) . '.' . $image->getClientOriginalExtension();
                $imagePath = 'karyawans/' . $imageName;
                
                Storage::disk('public')->put($imagePath, file_get_contents($image));
                $data['image'] = $imagePath;
            }
            
            // Hapus foto jika request memiliki remove_image
            if ($request->has('remove_image') && $request->remove_image == true) {
                $oldImage = $karyawan->getRawOriginal('image');
                if ($oldImage && Storage::disk('public')->exists($oldImage)) {
                    Storage::disk('public')->delete($oldImage);
                }
                $data['image'] = null;
            }
            
            // Update data
            $karyawan->update($data);

            DB::commit();

            return (new KaryawanResource($karyawan->load(['admin', 'updatedBy'])))->additional([
                'success' => true,
                'message' => 'Data karyawan berhasil diupdate'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate data',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Menghapus karyawan (soft delete)
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        
        try {
            $karyawan = Karyawan::find($id);
            
            if (!$karyawan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan'
                ], 404);
            }

            // Soft delete — foto TIDAK dihapus agar bisa dipulihkan saat restore
            $karyawan->delete();
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data karyawan berhasil dihapus'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Bulk delete multiple karyawan
     */
    public function bulkDestroy(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:karyawans,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $karyawans = Karyawan::whereIn('id', $request->ids)->get();

            // Soft delete — foto TIDAK dihapus agar bisa dipulihkan saat restore

            // Soft delete
            $deleted = Karyawan::whereIn('id', $request->ids)->delete();
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deleted} data karyawan"
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Restore soft deleted karyawan
     */
    public function restore($id)
    {
        DB::beginTransaction();
        
        try {
            $karyawan = Karyawan::withTrashed()->find($id);
            
            if (!$karyawan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan'
                ], 404);
            }
            
            if (!$karyawan->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak dalam status terhapus'
                ], 400);
            }
            
            $karyawan->restore();

            DB::commit();

            return (new KaryawanResource($karyawan->load(['admin', 'updatedBy'])))->additional([
                'success' => true,
                'message' => 'Data karyawan berhasil dipulihkan'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulihkan data',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get statistics summary
     */
    public function getSummary()
    {
        try {
            $summary = [
                'total' => Karyawan::count(),
                'active' => Karyawan::where('status_aktif', true)->count(),
                'inactive' => Karyawan::where('status_aktif', false)->count(),
                'by_position' => Karyawan::select('posisi', DB::raw('COUNT(*) as total'))
                    ->groupBy('posisi')
                    ->get()
                    ->pluck('total', 'posisi')
                    ->toArray(),
                'new_this_month' => Karyawan::whereMonth('tanggal_masuk', now()->month)
                    ->whereYear('tanggal_masuk', now()->year)
                    ->count(),
                'new_this_year' => Karyawan::whereYear('tanggal_masuk', now()->year)->count(),
            ];
            
            return response()->json([
                'success' => true,
                'message' => 'Statistik karyawan',
                'data' => $summary
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil statistik',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Hard delete karyawan — hapus permanen beserta foto
     * Hanya dipanggil setelah soft delete
     */
    public function forceDelete($id)
    {
        DB::beginTransaction();

        try {
            $karyawan = Karyawan::withTrashed()->find($id);

            if (!$karyawan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan'
                ], 404);
            }

            if (!$karyawan->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hapus data terlebih dahulu sebelum menghapus permanen'
                ], 400);
            }

            // Hapus foto dari storage
            if ($karyawan->getRawOriginal('image') &&
                Storage::disk('public')->exists($karyawan->getRawOriginal('image'))) {
                Storage::disk('public')->delete($karyawan->getRawOriginal('image'));
            }

            $karyawan->forceDelete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data karyawan berhasil dihapus permanen'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data permanen',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Upload image only
     */
    public function uploadImage(Request $request, $id)
    {
        DB::beginTransaction();
        
        try {
            $karyawan = Karyawan::find($id);
            
            if (!$karyawan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'image' => 'required|image|mimes:jpeg,png,jp,svg,webp|max:5120',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gambar gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Hapus foto lama jika ada — gunakan getRawOriginal agar tidak kena accessor URL
            $oldImage = $karyawan->getRawOriginal('image');
            if ($oldImage && Storage::disk('public')->exists($oldImage)) {
                Storage::disk('public')->delete($oldImage);
            }

            // Upload foto baru
            $image = $request->file('image');
            $imageName = Str::random(40) . '.' . $image->getClientOriginalExtension();
            $imagePath = 'karyawans/' . $imageName;

            Storage::disk('public')->put($imagePath, file_get_contents($image));

            // Update database — catat admin yang mengupload
            $karyawan->update([
                'image' => $imagePath,
                'updated_by' => $request->user()->id,
            ]);

            DB::commit();

            return (new KaryawanResource($karyawan->load(['admin', 'updatedBy'])))->additional([
                'success' => true,
                'message' => 'Foto berhasil diupload',
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal upload foto',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Export karyawan data (placeholder for Excel export)
     */
    public function export(Request $request)
    {
        try {
            $query = Karyawan::with(['admin', 'updatedBy']);

            if ($request->has('status_aktif')) {
                $query->where('status_aktif', $request->status_aktif);
            }

            if ($request->filled('posisi')) {
                $query->where('posisi', $request->posisi);
            }

            $karyawan = $query->orderBy('nama_lengkap')->get();

            return KaryawanResource::collection($karyawan)->additional([
                'success' => true,
                'message' => 'Data siap untuk diexport',
                'total'   => $karyawan->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyiapkan data export',
                'error'   => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }
    /**
     * Download Excel - Export karyawan data
     */
    public function downloadExcel(Request $request)
    {
        try {
            $filters = [
                'status_aktif' => $request->status_aktif,
                'posisi' => $request->posisi,
                'search' => $request->search,
            ];

            $fileName = 'Data_Karyawan_' . date('Y-m-d_His') . '.xlsx';

            return Excel::download(new KaryawanExport($filters), $fileName);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengunduh data Excel',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Download PDF - Kartu karyawan
     */
    public function downloadKartuPdf($id)
{
    try {
        $karyawan = Karyawan::find($id);

        if (!$karyawan) {
            return response()->json([
                'success' => false,
                'message' => 'Data karyawan tidak ditemukan'
            ], 404);
        }

        $pdf = Pdf::loadView('pdf.kartu-karyawan', compact('karyawan'));
        
        // Paper size yang pas untuk kartu (A6 landscape = 148mm x 105mm)
        $pdf->setPaper('a6', 'landscape');
        
        // Atau gunakan custom size yang lebih kecil
        // $pdf->setPaper([0, 0, 340, 240]); // dalam points
        
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
            'dpi' => 150,
            'defaultPaperSize' => 'a6',
            'isPhpEnabled' => true
        ]);

        $fileName = 'Kartu_' . str_replace(' ', '_', $karyawan->nama_lengkap) . '_' . date('Ymd') . '.pdf';

        return $pdf->download($fileName);

    } catch (\Exception $e) {
        Log::error('Error download kartu PDF: ' . $e->getMessage());
        Log::error($e->getTraceAsString());
        
        return response()->json([
            'success' => false,
            'message' => 'Gagal mengunduh kartu karyawan',
            'error' => env('APP_DEBUG') ? $e->getMessage() : null
        ], 500);
    }
}

    /**
     * Bulk download kartu PDF untuk multiple karyawan
     */
    public function bulkDownloadKartuPdf(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:karyawans,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $karyawans = Karyawan::whereIn('id', $request->ids)->get();

            $pdf = Pdf::loadView('pdf.kartu-karyawan-bulk', compact('karyawans'))
                ->setPaper([0, 0, 242.65, 153.07], 'landscape');

            $fileName = 'Kartu_Karyawan_Bulk_' . date('Ymd_His') . '.pdf';

            return $pdf->download($fileName);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengunduh kartu karyawan',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Preview kartu karyawan di browser
     */
    public function previewKartuPdf($id)
    {
        try {
            $karyawan = Karyawan::find($id);

            if (!$karyawan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan'
                ], 404);
            }

            $pdf = Pdf::loadView('pdf.kartu-karyawan', compact('karyawan'))
                ->setPaper([0, 0, 242.65, 153.07], 'landscape');

            return $pdf->stream();

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menampilkan preview kartu',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }
    /**
     * Import data karyawan dari file Excel
     */
    public function import(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // max 10MB
                'update_duplicate' => 'nullable|boolean', // Update jika data sudah ada
                'delete_existing' => 'nullable|boolean', // Hapus data lama sebelum import
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Opsi: Hapus data lama jika diminta — forceDelete agar tidak konflik unique constraint
            if ($request->boolean('delete_existing')) {
                $karyawans = Karyawan::withTrashed()->get();
                foreach ($karyawans as $karyawan) {
                    $rawImage = $karyawan->getRawOriginal('image');
                    if ($rawImage && Storage::disk('public')->exists($rawImage)) {
                        Storage::disk('public')->delete($rawImage);
                    }
                }
                Karyawan::withTrashed()->forceDelete();
            }

            // Proses import
            $import = new KaryawanImport();
            Excel::import($import, $request->file('file'));

            $successCount = $import->getSuccessCount();
            $totalRows = $import->getTotalRows();
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil mengimport {$successCount} dari {$totalRows} data karyawan",
                'data' => [
                    'imported' => $successCount,
                    'total_rows' => $totalRows,
                    'imported_ids' => $import->getImportedIds()
                ]
            ]);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            DB::rollBack();
            
            $failures = $e->failures();
            $errors = [];
            
            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimport data, terdapat kesalahan pada file Excel',
                'errors' => $errors
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error import karyawan: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimport data',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Terjadi kesalahan saat import'
            ], 500);
        }
    }

    /**
     * Download template Excel untuk import
     */
    public function downloadTemplate()
    {
        try {
            $headers = [
                'nomor_induk',
                'nik',
                'no_rek_bri',
                'nama_lengkap',
                'posisi',
                'email',
                'alamat',
                'no_wa',
                'tanggal_masuk',
                'tanggal_keluar',
                'status_aktif'
            ];

            $exampleData = [
                [
                    'nomor_induk' => 'KRY-001',
                    'nik' => '3273010101900001',
                    'no_rek_bri' => '012345678901',
                    'nama_lengkap' => 'John Doe',
                    'posisi' => 'operator',
                    'email' => 'john.doe@example.com',
                    'alamat' => 'Jl. Contoh No. 123, Jakarta',
                    'no_wa' => '081234567890',
                    'tanggal_masuk' => '2024-01-15',
                    'tanggal_keluar' => '',
                    'status_aktif' => 'aktif'
                ],
                [
                    'nomor_induk' => 'KRY-002',
                    'nik' => '3273010101900002',
                    'no_rek_bri' => '012345678902',
                    'nama_lengkap' => 'Jane Smith',
                    'posisi' => 'supir',
                    'email' => 'jane.smith@example.com',
                    'alamat' => 'Jl. Contoh No. 456, Jakarta',
                    'no_wa' => '081234567891',
                    'tanggal_masuk' => '2024-02-01',
                    'tanggal_keluar' => '',
                    'status_aktif' => 'aktif'
                ],
                [
                    'nomor_induk' => 'KRY-003',
                    'nik' => '3273010101900003',
                    'no_rek_bri' => '012345678903',
                    'nama_lengkap' => 'Bob Johnson',
                    'posisi' => 'keamanan',
                    'email' => 'bob.johnson@example.com',
                    'alamat' => 'Jl. Contoh No. 789, Jakarta',
                    'no_wa' => '081234567892',
                    'tanggal_masuk' => '2023-12-10',
                    'tanggal_keluar' => '2024-03-31',
                    'status_aktif' => 'tidak aktif'
                ]
            ];

            return Excel::download(new class($headers, $exampleData) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithTitle, \Maatwebsite\Excel\Concerns\WithStyles {
                protected $headers;
                protected $data;

                public function __construct($headers, $data)
                {
                    $this->headers = $headers;
                    $this->data = $data;
                }

                public function array(): array
                {
                    return $this->data;
                }

                public function headings(): array
                {
                    return $this->headers;
                }

                public function title(): string
                {
                    return 'Template Import Karyawan';
                }

                public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
                {
                    // Style untuk header
                    $sheet->getStyle('A1:K1')->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['argb' => 'FFFFFFFF'],
                        ],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FF4CAF50'],
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                'color' => ['argb' => 'FF000000'],
                            ],
                        ],
                    ]);

                    // Auto-size columns
                    foreach (range('A', 'K') as $column) {
                        $sheet->getColumnDimension($column)->setAutoSize(true);
                    }

                    // Validasi data untuk kolom posisi
                    $validation = $sheet->getCell('E2')->getDataValidation();
                    $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
                    $validation->setAllowBlank(false);
                    $validation->setShowInputMessage(true);
                    $validation->setShowErrorMessage(true);
                    $validation->setShowDropDown(true);
                    $validation->setFormula1('"jasa,supir,keamanan,cleaning_service,operator"');
                    $sheet->setDataValidation("E2:E1048576", $validation);

                    // Validasi untuk status_aktif
                    $validation = $sheet->getCell('K2')->getDataValidation();
                    $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
                    $validation->setAllowBlank(false);
                    $validation->setShowInputMessage(true);
                    $validation->setShowErrorMessage(true);
                    $validation->setShowDropDown(true);
                    $validation->setFormula1('"aktif,tidak aktif"');
                    $sheet->setDataValidation("K2:K1048576", $validation);

                    return $sheet;
                }
            }, 'Template_Import_Karyawan.xlsx');

        } catch (\Exception $e) {
            Log::error('Error download template: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengunduh template',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Import dengan preview data sebelum commit
     */
    public function importPreview(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Baca file tanpa menyimpan ke database
            $rows = Excel::toArray(new KaryawanImport, $request->file('file'));
            
            $data = [];
            $headers = [];
            $previewData = [];

            if (!empty($rows) && !empty($rows[0])) {
                $headers = array_keys($rows[0][0]);
                $previewData = array_slice($rows[0], 0, 10); // Ambil 10 baris pertama untuk preview
                
                // Validasi awal menggunakan aturan dasar
                $basicRules = [
                    'nik'           => 'required|string|max:20',
                    'nama_lengkap'  => 'required|string|max:100',
                    'posisi'        => 'required|in:jasa,supir,keamanan,cleaning_service,operator',
                    'tanggal_masuk' => 'required|date',
                    'status_aktif'  => 'required|in:aktif,tidak aktif',
                ];

                $errors = [];
                foreach ($rows[0] as $index => $row) {
                    $rowValidator = Validator::make($row, $basicRules);
                    if ($rowValidator->fails()) {
                        $errors[] = [
                            'row' => $index + 2,
                            'errors' => $rowValidator->errors()->toArray()
                        ];
                    }
                }

                $data = [
                    'headers' => $headers,
                    'preview' => $previewData,
                    'total_rows' => count($rows[0]),
                    'validation_errors' => $errors,
                    'has_errors' => !empty($errors)
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Preview data berhasil',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error preview import: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal preview data',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Cek status import
     */
    public function importStatus()
    {
        try {
            // Logika untuk mengecek status import terakhir
            // Bisa disimpan di cache atau database
            
            return response()->json([
                'success' => true,
                'message' => 'Status import',
                'data' => [
                    'last_import' => cache('last_import_time'),
                    'total_imported_today' => Karyawan::whereDate('created_at', today())->count(),
                    'total_records' => Karyawan::count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil status import',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
        
    }
    /**
     * Batch insert karyawan dari array JSON.
     * Posisi di-normalize ke lowercase. NIK boleh sampai 20 digit untuk data lama.
     */
    public function batchStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'data'                    => 'required|array|min:1',
            'data.*.nomor_induk'      => 'required|string|max:20|distinct',
            'data.*.nik'              => 'required|string|regex:/^[0-9]+$/|max:20|distinct',
            'data.*.nama_lengkap'     => 'required|string|max:100',
            'data.*.posisi'           => 'required|string',
            'data.*.no_rek_bri'       => 'nullable|string|regex:/^[0-9]+$/|max:20|distinct',
            'data.*.email'            => 'nullable|email|max:100|distinct',
            'data.*.alamat'           => 'required|string',
            'data.*.no_wa'            => 'required|string|regex:/^[0-9]+$/|max:15|distinct',
            'data.*.tanggal_masuk'    => 'required|date',
            'data.*.tanggal_keluar'   => 'nullable|date',
            'data.*.status_aktif'     => 'required|boolean',
        ], [
            'data.*.nomor_induk.required'  => 'Baris :index: nomor_induk wajib diisi',
            'data.*.nomor_induk.distinct'  => 'Baris :index: nomor_induk duplikat dalam request',
            'data.*.nik.required'          => 'Baris :index: NIK wajib diisi',
            'data.*.nik.distinct'          => 'Baris :index: NIK duplikat dalam request',
            'data.*.nik.regex'             => 'Baris :index: NIK hanya boleh angka',
            'data.*.nama_lengkap.required' => 'Baris :index: nama_lengkap wajib diisi',
            'data.*.posisi.required'       => 'Baris :index: posisi wajib diisi',
            'data.*.no_wa.required'        => 'Baris :index: no_wa wajib diisi',
            'data.*.no_wa.regex'           => 'Baris :index: no_wa hanya boleh angka',
            'data.*.no_wa.distinct'        => 'Baris :index: no_wa duplikat dalam request',
            'data.*.email.distinct'        => 'Baris :index: email duplikat dalam request',
            'data.*.no_rek_bri.distinct'   => 'Baris :index: no_rek_bri duplikat dalam request',
            'data.*.tanggal_masuk.required'=> 'Baris :index: tanggal_masuk wajib diisi',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors()
            ], 422);
        }

        // Validasi posisi valid
        $validPosisi = ['jasa', 'supir', 'keamanan', 'cleaning_service', 'operator'];
        $posisiErrors = [];
        foreach ($request->data as $index => $item) {
            $posisi = strtolower($item['posisi'] ?? '');
            if (!in_array($posisi, $validPosisi)) {
                $posisiErrors["data.{$index}.posisi"] = ["Posisi '{$item['posisi']}' tidak valid. Pilihan: " . implode(', ', $validPosisi)];
            }
        }
        if (!empty($posisiErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi posisi gagal',
                'errors'  => $posisiErrors
            ], 422);
        }

        // Cek duplikasi dengan database
        $nomorIndukList = array_column($request->data, 'nomor_induk');
        $nikList        = array_column($request->data, 'nik');
        $emailList      = array_filter(array_column($request->data, 'email'));
        $noWaList       = array_column($request->data, 'no_wa');
        $noRekList      = array_filter(array_column($request->data, 'no_rek_bri'));

        $dupErrors = [];

        $existNomorInduk = Karyawan::withTrashed()->whereIn('nomor_induk', $nomorIndukList)->pluck('nomor_induk')->toArray();
        if (!empty($existNomorInduk)) {
            $dupErrors['nomor_induk'] = ['Nomor induk sudah terdaftar: ' . implode(', ', $existNomorInduk)];
        }

        $existNik = Karyawan::withTrashed()->whereIn('nik', $nikList)->pluck('nik')->toArray();
        if (!empty($existNik)) {
            $dupErrors['nik'] = ['NIK sudah terdaftar: ' . implode(', ', $existNik)];
        }

        if (!empty($emailList)) {
            $existEmail = Karyawan::withTrashed()->whereIn('email', $emailList)->pluck('email')->toArray();
            if (!empty($existEmail)) {
                $dupErrors['email'] = ['Email sudah terdaftar: ' . implode(', ', $existEmail)];
            }
        }

        $existNoWa = Karyawan::withTrashed()->whereIn('no_wa', $noWaList)->pluck('no_wa')->toArray();
        if (!empty($existNoWa)) {
            $dupErrors['no_wa'] = ['No. WhatsApp sudah terdaftar: ' . implode(', ', $existNoWa)];
        }

        if (!empty($noRekList)) {
            $existNoRek = Karyawan::withTrashed()->whereIn('no_rek_bri', $noRekList)->pluck('no_rek_bri')->toArray();
            if (!empty($existNoRek)) {
                $dupErrors['no_rek_bri'] = ['No. Rekening BRI sudah terdaftar: ' . implode(', ', $existNoRek)];
            }
        }

        if (!empty($dupErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Terdapat data duplikat dengan database',
                'errors'  => $dupErrors
            ], 422);
        }

        DB::beginTransaction();

        try {
            $adminId = $request->user()->id;
            $created = [];

            foreach ($request->data as $item) {
                $karyawan = Karyawan::create([
                    'nomor_induk'    => $item['nomor_induk'],
                    'nik'            => $item['nik'],
                    'nama_lengkap'   => $item['nama_lengkap'],
                    'posisi'         => strtolower($item['posisi']),
                    'no_rek_bri'     => $item['no_rek_bri'] ?? null,
                    'email'          => $item['email'] ?? null,
                    'alamat'         => $item['alamat'],
                    'no_wa'          => $item['no_wa'],
                    'tanggal_masuk'  => $item['tanggal_masuk'],
                    'tanggal_keluar' => $item['tanggal_keluar'] ?? null,
                    'status_aktif'   => $item['status_aktif'],
                    'admin_id'       => $adminId,
                    'updated_by'     => $adminId,
                ]);

                $created[] = $karyawan;
            }

            DB::commit();

            return KaryawanResource::collection(
                    Karyawan::with(['admin', 'updatedBy'])
                        ->whereIn('id', collect($created)->pluck('id'))
                        ->get()
                )
                ->additional([
                    'success' => true,
                    'message' => count($created) . ' data karyawan berhasil disimpan',
                ])
                ->response()
                ->setStatusCode(201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data karyawan',
                'error'   => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function restoreByNik(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'nik' => 'required|string|regex:/^[0-9]+$/|max:16',
            ], [
                'nik.required' => 'NIK wajib diisi',
                'nik.regex' => 'NIK hanya boleh angka',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $karyawan = Karyawan::withTrashed()
                ->where('nik', $request->nik)
                ->first();

            if (!$karyawan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan dengan NIK tersebut tidak ditemukan'
                ], 404);
            }

            if (!$karyawan->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan masih aktif'
                ], 400);
            }

            $karyawan->restore();

            $karyawan->update([
                'status_aktif' => true,
                'tanggal_keluar' => null,
                'updated_by' => $request->user()->id,
            ]);

            DB::commit();

            return (new KaryawanResource($karyawan->load(['admin', 'updatedBy'])))->additional([
                'success' => true,
                'message' => 'Data karyawan berhasil dipulihkan berdasarkan NIK'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal memulihkan data',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }
}