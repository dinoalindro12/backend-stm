<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagihanPerusahaanResource;
use App\Models\TagihanPerusahaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TagihanPerusahaanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = TagihanPerusahaan::with('karyawan');
            
            // Filter berdasarkan periode
            if ($request->has('periode_awal') && $request->has('periode_akhir')) {
                $query->periode($request->periode_awal, $request->periode_akhir);
            }
            
            // Filter berdasarkan bulan
            if ($request->has('bulan')) {
                $query->bulanTagihan($request->bulan);
            }
            
            // Filter berdasarkan tahun
            if ($request->has('tahun')) {
                $query->tahunTagihan($request->tahun);
            }
            
            // Filter berdasarkan bulan dan tahun
            if ($request->has('bulan') && $request->has('tahun')) {
                $query->bulanTahunTagihan($request->bulan, $request->tahun);
            }
            
            // Filter berdasarkan posisi
            if ($request->has('posisi')) {
                $query->posisi($request->posisi);
            }
            
            // Filter berdasarkan no_induk
            if ($request->has('no_induk')) {
                $query->karyawan($request->no_induk);
            }
            
            // Filter berdasarkan NIK
            if ($request->has('nik')) {
                $query->nik($request->nik);
            }
            
            // Sorting
            if ($request->has('sort_by')) {
                $sortOrder = $request->get('sort_order', 'asc');
                $query->orderBy($request->sort_by, $sortOrder);
            } else {
                $query->orderBy('periode_awal', 'desc')->orderBy('created_at', 'desc');
            }
            
            // Pagination
            $perPage = $request->get('per_page', 15);
            $tagihan = $query->paginate($perPage);
            
            return TagihanPerusahaanResource::collection($tagihan);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data tagihan perusahaan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'no_induk' => 'required|string|max:12|exists:karyawans,nomor_induk',
        'nik' => 'required|string|max:20',
        'nama' => 'required|string|max:100',
        'no_rek_bri' => 'nullable|string|max:20',
        'posisi' => 'required|in:jasa,supir,keamanan,cleaning_service,operator',
        'jumlah_hari_kerja' => 'required|numeric|min:0',
        'gaji_harian' => 'required|numeric|min:0',
        'lembur' => 'nullable|numeric|min:0',
        'thr' => 'nullable|numeric|min:0',
        'bpjs_kesehatan' => 'nullable|numeric|min:0',
        'jkk' => 'nullable|numeric|min:0',
        'jkm' => 'nullable|numeric|min:0',
        'jht' => 'nullable|numeric|min:0',
        'jp' => 'nullable|numeric|min:0',
        'seragam_cs_dan_keamanan' => 'nullable|numeric|min:0',
        'fee_manajemen' => 'nullable|numeric|min:0',
        'periode_awal' => 'required|date',
        'periode_akhir' => 'required|date|after_or_equal:periode_awal',
        'tanggal_cetak' => 'nullable|date',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        DB::beginTransaction();

        // Cek duplikasi tagihan untuk karyawan yang sama di periode yang sama
        $existingTagihan = TagihanPerusahaan::where('no_induk', $request->no_induk)
            ->whereYear('periode_awal', date('Y', strtotime($request->periode_awal)))
            ->whereMonth('periode_awal', date('m', strtotime($request->periode_awal)))
            ->first();

        if ($existingTagihan) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Tagihan gagal ditambahkan',
                'error' => 'Karyawan dengan nomor induk ' . $request->no_induk . 
                        ' sudah memiliki data tagihan untuk bulan ' . 
                        date('F Y', strtotime($request->periode_awal)) . 
                        '. Tidak dapat membuat tagihan ganda pada bulan yang sama.'
            ], 409);
        }

        $tagihan = TagihanPerusahaan::create($request->all());

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Data tagihan perusahaan berhasil ditambahkan',
            'data' => new TagihanPerusahaanResource($tagihan)
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Gagal menambahkan data tagihan perusahaan',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $tagihan = TagihanPerusahaan::with('karyawan')->findOrFail($id);
            
            return new TagihanPerusahaanResource($tagihan);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tagihan perusahaan tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data tagihan perusahaan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
{
    $validator = Validator::make($request->all(), [
        'no_induk' => 'sometimes|string|max:12|exists:karyawans,nomor_induk',
        'nik' => 'sometimes|string|max:20',
        'nama' => 'sometimes|string|max:100',
        'no_rek_bri' => 'nullable|string|max:20',
        'posisi' => 'sometimes|in:jasa,supir,keamanan,cleaning_service,operator',
        'jumlah_hari_kerja' => 'sometimes|numeric|min:0',
        'gaji_harian' => 'sometimes|numeric|min:0',
        'lembur' => 'nullable|numeric|min:0',
        'thr' => 'nullable|numeric|min:0',
        'bpjs_kesehatan' => 'nullable|numeric|min:0',
        'jkk' => 'nullable|numeric|min:0',
        'jkm' => 'nullable|numeric|min:0',
        'jht' => 'nullable|numeric|min:0',
        'jp' => 'nullable|numeric|min:0',
        'seragam_cs_dan_keamanan' => 'nullable|numeric|min:0',
        'fee_manajemen' => 'nullable|numeric|min:0',
        'periode_awal' => 'sometimes|date',
        'periode_akhir' => 'sometimes|date|after_or_equal:periode_awal',
        'tanggal_cetak' => 'nullable|date',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        DB::beginTransaction();

        $tagihan = TagihanPerusahaan::findOrFail($id);

        // Cek duplikasi jika ada perubahan no_induk atau periode_awal
        if ($request->has('no_induk') || $request->has('periode_awal')) {
            $noInduk = $request->no_induk ?? $tagihan->no_induk;
            $periodeAwal = $request->periode_awal ?? $tagihan->periode_awal;

            $existingTagihan = TagihanPerusahaan::where('no_induk', $noInduk)
                ->whereYear('periode_awal', date('Y', strtotime($periodeAwal)))
                ->whereMonth('periode_awal', date('m', strtotime($periodeAwal)))
                ->where('id', '!=', $id)
                ->first();

            if ($existingTagihan) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Tagihan perusahaan gagal diupdate',
                    'error' => 'Karyawan dengan nomor induk ' . $noInduk . 
                            ' sudah memiliki data tagihan untuk bulan ' . 
                            date('F Y', strtotime($periodeAwal)) . 
                            '. Tidak dapat membuat tagihan ganda pada bulan yang sama.'
                ], 409);
            }
        }

        $tagihan->update($request->all());

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Data tagihan perusahaan berhasil diupdate',
            'data' => new TagihanPerusahaanResource($tagihan)
        ], 200);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Tagihan perusahaan tidak ditemukan'
        ], 404);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Gagal mengupdate data tagihan perusahaan',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            DB::beginTransaction();
            
            $tagihan = TagihanPerusahaan::findOrFail($id);
            $tagihan->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Tagihan perusahaan berhasil dihapus'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tagihan perusahaan tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus tagihan perusahaan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore soft deleted tagihan perusahaan.
     */
    public function restore(string $id)
    {
        try {
            DB::beginTransaction();
            
            $tagihan = TagihanPerusahaan::withTrashed()->findOrFail($id);
            $tagihan->restore();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Tagihan perusahaan berhasil dipulihkan',
                'data' => new TagihanPerusahaanResource($tagihan)
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tagihan perusahaan tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulihkan tagihan perusahaan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get summary statistics for tagihan perusahaan.
     */
    public function summary(Request $request)
    {
        try {
            $query = TagihanPerusahaan::query();
            
            // Apply filters
            if ($request->has('periode_awal') && $request->has('periode_akhir')) {
                $query->periode($request->periode_awal, $request->periode_akhir);
            }
            
            if ($request->has('bulan') && $request->has('tahun')) {
                $query->bulanTahunTagihan($request->bulan, $request->tahun);
            }
            
            if ($request->has('posisi')) {
                $query->posisi($request->posisi);
            }
            
            $summary = $query->select(
                DB::raw('COUNT(*) as total_karyawan'),
                DB::raw('SUM(jumlah_hari_kerja) as total_hari_kerja'),
                DB::raw('SUM(upa_pekerja) as total_upa_pekerja'),
                DB::raw('SUM(jumlah_iuran_bpjs) as total_iuran_bpjs'),
                DB::raw('SUM(total_diterima) as total_tagihan')
            )->first();
            
            // Get position distribution
            $posisiDistribution = $query->clone()
                ->select('posisi', DB::raw('COUNT(*) as jumlah'))
                ->groupBy('posisi')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'posisi_distribution' => $posisiDistribution,
                    'periode' => [
                        'awal' => $request->periode_awal ?? null,
                        'akhir' => $request->periode_akhir ?? null
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil summary tagihan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import multiple tagihan perusahaan.
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'data' => 'required|array',
            'data.*.no_induk' => 'required|string|max:50',
            'data.*.nik' => 'required|string|max:20',
            'data.*.nama' => 'required|string|max:255',
            'data.*.posisi' => 'required|string|max:100',
            'data.*.jumlah_hari_kerja' => 'required|integer|min:0',
            'data.*.gaji_harian' => 'required|numeric|min:0',
            'data.*.periode_awal' => 'required|date',
            'data.*.periode_akhir' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();
            
            $imported = [];
            $errors = [];
            
            foreach ($request->data as $index => $item) {
                try {
                    $tagihan = TagihanPerusahaan::create($item);
                    $imported[] = $tagihan;
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'data' => $item,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Import tagihan perusahaan selesai',
                'data' => [
                    'total_imported' => count($imported),
                    'total_failed' => count($errors),
                    'imported' => TagihanPerusahaanResource::collection(collect($imported)),
                    'errors' => $errors
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal import tagihan perusahaan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete tagihan perusahaan.
     */
    public function bulkDestroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'required|exists:tagihan_perusahaan,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();
            
            $deletedCount = TagihanPerusahaan::whereIn('id', $request->ids)->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Tagihan perusahaan berhasil dihapus secara massal',
                'data' => [
                    'deleted_count' => $deletedCount
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus tagihan perusahaan secara massal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export tagihan perusahaan by periode.
     */
    public function export(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'periode_awal' => 'required|date',
            'periode_akhir' => 'required|date',
            'format' => 'sometimes|in:pdf,excel'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tagihan = TagihanPerusahaan::with('karyawan')
                ->periode($request->periode_awal, $request->periode_akhir)
                ->orderBy('nama')
                ->get();
            
            if ($tagihan->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data tagihan untuk periode tersebut'
                ], 404);
            }
            
            // Return data for now - bisa di-extend untuk generate PDF/Excel
            return response()->json([
                'success' => true,
                'message' => 'Data tagihan berhasil diambil',
                'data' => [
                    'periode' => [
                        'awal' => $request->periode_awal,
                        'akhir' => $request->periode_akhir
                    ],
                    'total_records' => $tagihan->count(),
                    'total_tagihan' => $tagihan->sum('total_diterima'),
                    'tagihan' => TagihanPerusahaanResource::collection($tagihan)
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor data tagihan',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}