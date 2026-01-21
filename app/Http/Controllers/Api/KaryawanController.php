<?php

namespace App\Http\Controllers\Api;

use App\Models\Karyawan;
use App\Http\Controllers\Controller;
use App\Http\Resources\KaryawanResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KaryawanController extends Controller
{
    /**
     * Menampilkan semua karyawan dengan filtering
     */
    public function index(Request $request)
    {
        try {
            $query = Karyawan::query();
            
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
                $query->where(function($q) use ($search) {
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
                'nomor_induk'   => 'required|unique:karyawans,nomor_induk|string|max:12',
                'nik'           => 'required|unique:karyawans,nik|string|max:20',
                'no_rek_bri'    => 'required|unique:karyawans,no_rek_bri|string|max:30',
                'nama_lengkap'  => 'required|string|max:100',
                'posisi'        => 'required|string|in:jasa,supir,keamanan,cleaning_service,operator',
                'email'         => 'nullable|email|unique:karyawans,email|max:100',
                'alamat'        => 'required|string',
                'no_wa'         => 'nullable|string|max:15',
                'image'         => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120', // 5MB
                'tanggal_masuk' => 'required|date',
                'tanggal_keluar'=> 'nullable|date|after_or_equal:tanggal_masuk',
                'status_aktif'  => 'required|boolean',
            ], [
                'nomor_induk.unique' => 'Nomor induk sudah terdaftar',
                'nik.unique' => 'NIK sudah terdaftar',
                'no_rek_bri.unique' => 'No Rekening BRI sudah terdaftar',
                'email.unique' => 'Email sudah terdaftar',
                'image.max' => 'Ukuran gambar maksimal 5MB',
                'image.mimes' => 'Format gambar harus jpeg, png, jpg, gif, svg, atau webp',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            
            // Upload foto jika ada
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

            return (new KaryawanResource($karyawan))->additional([
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
            $karyawan = Karyawan::find($id);
            
            if (!$karyawan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan'
                ], 404);
            }
            
            return (new KaryawanResource($karyawan));
            
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
                'nomor_induk'   => 'sometimes|required|unique:karyawans,nomor_induk,' . $karyawan->id . '|string|max:12',
                'nik'           => 'sometimes|required|unique:karyawans,nik,' . $karyawan->id . '|string|max:20',
                'no_rek_bri'    => 'sometimes|required|unique:karyawans,no_rek_bri,' . $karyawan->id . '|string|max:30',
                'nama_lengkap'  => 'sometimes|required|string|max:100',
                'posisi'        => 'sometimes|required|string|in:jasa,supir,keamanan,cleaning_service,operator',
                'email'         => 'nullable|email|unique:karyawans,email,' . $karyawan->id . '|max:100',
                'alamat'        => 'sometimes|required|string',
                'no_wa'         => 'nullable|string|max:15',
                'image'         => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
                'tanggal_masuk' => 'sometimes|required|date',
                'tanggal_keluar'=> 'nullable|date|after_or_equal:tanggal_masuk',
                'status_aktif'  => 'sometimes|required|boolean',
            ], [
                'nomor_induk.unique' => 'Nomor induk sudah terdaftar',
                'nik.unique' => 'NIK sudah terdaftar',
                'no_rek_bri.unique' => 'No Rekening BRI sudah terdaftar',
                'email.unique' => 'Email sudah terdaftar',
                'image.max' => 'Ukuran gambar maksimal 5MB',
                'image.mimes' => 'Format gambar harus jpeg, png, jpg, gif, svg, atau webp',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            
            // Tangani upload foto baru
            if ($request->hasFile('image')) {
                // Hapus foto lama jika ada
                if ($karyawan->image && Storage::disk('public')->exists($karyawan->image)) {
                    Storage::disk('public')->delete($karyawan->image);
                }
                
                $image = $request->file('image');
                $imageName = Str::random(40) . '.' . $image->getClientOriginalExtension();
                $imagePath = 'karyawans/' . $imageName;
                
                Storage::disk('public')->put($imagePath, file_get_contents($image));
                $data['image'] = $imagePath;
            }
            
            // Hapus foto jika request memiliki remove_image
            if ($request->has('remove_image') && $request->remove_image == true) {
                if ($karyawan->image && Storage::disk('public')->exists($karyawan->image)) {
                    Storage::disk('public')->delete($karyawan->image);
                }
                $data['image'] = null;
            }
            
            // Update data
            $karyawan->update($data);
            
            DB::commit();

            return (new KaryawanResource($karyawan))->additional([
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
            // Hapus foto jika ada
            if ($karyawan->image && Storage::disk('public')->exists($karyawan->image)) {
                Storage::disk('public')->delete($karyawan->image);
            }
            
            // Soft delete
            $karyawan->delete($id);
            
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
            
            // Hapus foto-foto
            foreach ($karyawans as $karyawan) {
                if ($karyawan->image && Storage::disk('public')->exists($karyawan->image)) {
                    Storage::disk('public')->delete($karyawan->image);
                }
            }
            
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

            return response()->json([
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
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gambar gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Hapus foto lama jika ada
            if ($karyawan->image && Storage::disk('public')->exists($karyawan->image)) {
                Storage::disk('public')->delete($karyawan->image);
            }
            
            // Upload foto baru
            $image = $request->file('image');
            $imageName = Str::random(40) . '.' . $image->getClientOriginalExtension();
            $imagePath = 'karyawans/' . $imageName;
            
            Storage::disk('public')->put($imagePath, file_get_contents($image));
            
            // Update database
            $karyawan->update(['image' => $imagePath]);
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Foto berhasil diupload',
                'data' => [
                    'image_url' => asset('storage/' . $imagePath)
                ]
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
            $query = Karyawan::query();
            
            if ($request->has('status_aktif')) {
                $query->where('status_aktif', $request->status_aktif);
            }
            
            if ($request->filled('posisi')) {
                $query->where('posisi', $request->posisi);
            }
            
            $karyawan = $query->orderBy('nama_lengkap')->get();
            
            // Format data untuk export
            $exportData = $karyawan->map(function($item) {
                return [
                    'nomor_induk' => $item->nomor_induk,
                    'nik' => $item->nik,
                    'nama_lengkap' => $item->nama_lengkap,
                    'posisi' => $item->posisi,
                    'email' => $item->email,
                    'no_wa' => $item->no_wa,
                    'alamat' => $item->alamat,
                    'tanggal_masuk' => $item->tanggal_masuk->format('d/m/Y'),
                    'tanggal_keluar' => $item->tanggal_keluar ? $item->tanggal_keluar->format('d/m/Y') : '-',
                    'status_aktif' => $item->status_aktif ? 'Aktif' : 'Tidak Aktif',
                    'created_at' => $item->created_at->format('d/m/Y H:i'),
                ];
            });
            
            return response()->json([
                'success' => true,
                'message' => 'Data siap untuk diexport',
                'data' => $exportData,
                'total' => $exportData->count()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyiapkan data export',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }
}