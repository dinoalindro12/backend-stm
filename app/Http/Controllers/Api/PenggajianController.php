<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Penggajian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PenggajianController extends Controller
{
    /**
     * Display a listing of penggajian.
     */
    public function index(Request $request)
    {
        try {
            $query = Penggajian::with('karyawan');

            // Filter berdasarkan posisi
            if ($request->has('posisi')) {
                $query->posisi($request->posisi);
            }

            // Filter berdasarkan status penggajian
            if ($request->has('status')) {
                $query->status($request->status);
            }

            // Filter berdasarkan periode
            if ($request->has('periode_awal') && $request->has('periode_akhir')) {
                $query->whereBetween('gajian_bulan', [
                    $request->periode_awal,
                    $request->periode_akhir
                ]);
            }

            // Filter berdasarkan bulan gajian
            if ($request->has('bulan')) {
                $query->whereMonth('gajian_bulan', $request->bulan);
            }

            if ($request->has('tahun')) {
                $query->whereYear('gajian_bulan', $request->tahun);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $penggajian = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Data penggajian berhasil diambil',
                'data' => $penggajian
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data penggajian',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created penggajian.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'no_induk' => 'required|string|max:12|exists:karyawans,nomor_induk',
            'nik' => 'required|string|max:20',
            'nama' => 'required|string|max:100',
            'no_rek_bri' => 'nullable|string|max:20',
            'posisi' => 'required|in:jasa,supir,keamanan,cleaning_service,operator',
            'jumlah_penghasilan_kotor' => 'required|numeric|min:0',
            'bpjs_kesehatan' => 'required|numeric|min:0',
            'bpjs_jht' => 'required|numeric|min:0',
            'bpjs_jp' => 'required|numeric|min:0',
            'uang_thr' => 'nullable|numeric|min:0',
            'jumlah_hari_kerja' => 'required|numeric|min:0',
            'gaji_harian' => 'required|numeric|min:0',
            'jumlah_lembur' => 'required|numeric|min:0',
            'gajian_bulan' => 'required|date',
            'periode_awal' => 'required|date',
            'periode_akhir' => 'required|date|after_or_equal:periode_awal',
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

            // Cek duplikasi penggajian untuk karyawan yang sama di bulan yang sama
            $existingPenggajian = Penggajian::where('no_induk', $request->no_induk)
                ->whereYear('gajian_bulan', date('Y', strtotime($request->gajian_bulan)))
                ->whereMonth('gajian_bulan', date('m', strtotime($request->gajian_bulan)))
                ->first();

            if ($existingPenggajian) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Penggajian gagal ditambahkan',
                    'error' => 'Karyawan dengan nomor induk ' . $request->no_induk . 
                            ' sudah memiliki data penggajian untuk bulan ' . 
                            date('F Y', strtotime($request->gajian_bulan)) . 
                            '. Tidak dapat membuat penggajian ganda pada bulan yang sama.'
                ], 409);
            }

            $penggajian = Penggajian::create($request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data penggajian berhasil ditambahkan',
                'data' => $penggajian
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan data penggajian',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified penggajian.
     */
    public function show($id)
    {
        try {
            $penggajian = Penggajian::with('karyawan')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Detail penggajian berhasil diambil',
                'data' => $penggajian
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data penggajian tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified penggajian.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'no_induk' => 'sometimes|string|max:12|exists:karyawans,nomor_induk',
            'nik' => 'sometimes|string|max:20',
            'nama' => 'sometimes|string|max:100',
            'no_rek_bri' => 'nullable|string|max:20',
            'posisi' => 'sometimes|in:jasa,supir,keamanan,cleaning_service,operator',
            'jumlah_penghasilan_kotor' => 'required|numeric|min:0',
            'bpjs_kesehatan' => 'sometimes|numeric|min:0',
            'bpjs_jht' => 'sometimes|numeric|min:0',
            'bpjs_jp' => 'sometimes|numeric|min:0',
            'uang_thr' => 'nullable|numeric|min:0',
            'jumlah_hari_kerja' => 'sometimes|numeric|min:0',
            'gaji_harian' => 'sometimes|numeric|min:0',
            'jumlah_lembur' => 'sometimes|numeric|min:0',
            'gajian_bulan' => 'sometimes|date',
            'periode_awal' => 'sometimes|date',
            'periode_akhir' => 'sometimes|date|after_or_equal:periode_awal',
            'status_penggajian' => 'sometimes|boolean',
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

            $penggajian = Penggajian::findOrFail($id);

            // Cek duplikasi jika ada perubahan no_induk atau gajian_bulan
            if ($request->has('no_induk') || $request->has('gajian_bulan')) {
                $noInduk = $request->no_induk ?? $penggajian->no_induk;
                $gajianBulan = $request->gajian_bulan ?? $penggajian->gajian_bulan;

                $existingPenggajian = Penggajian::where('no_induk', $noInduk)
                    ->whereYear('gajian_bulan', date('Y', strtotime($gajianBulan)))
                    ->whereMonth('gajian_bulan', date('m', strtotime($gajianBulan)))
                    ->where('id', '!=', $id)
                    ->first();

                if ($existingPenggajian) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Penggajian gagal diupdate',
                        'error' => 'Karyawan dengan nomor induk ' . $noInduk . 
                                ' sudah memiliki data penggajian untuk bulan ' . 
                                date('F Y', strtotime($gajianBulan)) . 
                                '. Tidak dapat membuat penggajian ganda pada bulan yang sama.'
                    ], 409);
                }
            }

            $penggajian->update($request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data penggajian berhasil diupdate',
                'data' => $penggajian
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate data penggajian',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified penggajian (soft delete).
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $penggajian = Penggajian::findOrFail($id);
            $penggajian->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data penggajian berhasil dihapus'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data penggajian',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cetak slip gaji (update tanggal cetak).
     */
    public function cetakSlip($id)
    {
        try {
            DB::beginTransaction();

            $penggajian = Penggajian::findOrFail($id);
            $penggajian->update([
                'tanggal_cetak' => now(),
                'status_penggajian' => true
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Slip gaji berhasil dicetak',
                'data' => $penggajian
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencetak slip gaji',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch create penggajian untuk multiple karyawan.
     */
    public function batchStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'data' => 'required|array',
            'data.*.no_induk' => 'required|string|exists:karyawans,nomor_induk',
            'data.*.nik' => 'required|string',
            'data.*.nama' => 'required|string',
            'data.*.posisi' => 'required|in:jasa,supir,keamanan,cleaning_service,operator',
            'data.*.jumlah_hari_kerja' => 'required|numeric',
            'data.*.gaji_harian' => 'required|numeric',
            'gajian_bulan' => 'required|date',
            'periode_awal' => 'required|date',
            'periode_akhir' => 'required|date',
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

            $results = [];
            $duplicates = [];
            $year = date('Y', strtotime($request->gajian_bulan));
            $month = date('m', strtotime($request->gajian_bulan));

            // Cek duplikasi untuk semua karyawan sekaligus
            $noIndukList = array_column($request->data, 'no_induk');
            $existingPenggajian = Penggajian::whereIn('no_induk', $noIndukList)
                ->whereYear('gajian_bulan', $year)
                ->whereMonth('gajian_bulan', $month)
                ->pluck('no_induk')
                ->toArray();

            foreach ($request->data as $data) {
                // Skip jika sudah ada penggajian di bulan tersebut
                if (in_array($data['no_induk'], $existingPenggajian)) {
                    $duplicates[] = [
                        'no_induk' => $data['no_induk'],
                        'nama' => $data['nama'],
                        'message' => 'Sudah memiliki penggajian untuk bulan ' . date('F Y', strtotime($request->gajian_bulan))
                    ];
                    continue;
                }

                $data['gajian_bulan'] = $request->gajian_bulan;
                $data['periode_awal'] = $request->periode_awal;
                $data['periode_akhir'] = $request->periode_akhir;
                
                $penggajian = Penggajian::create($data);
                $results[] = $penggajian;
            }

            DB::commit();

            $response = [
                'success' => true,
                'message' => 'Batch penggajian berhasil dibuat',
                'data' => [
                    'created' => $results,
                    'created_count' => count($results)
                ]
            ];

            // Tambahkan info duplikasi jika ada
            if (!empty($duplicates)) {
                $response['data']['duplicates'] = $duplicates;
                $response['data']['duplicates_count'] = count($duplicates);
                $response['message'] = 'Batch penggajian selesai dengan ' . count($duplicates) . ' data duplikat yang dilewati';
            }

            return response()->json($response, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat batch penggajian',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get summary/statistik penggajian.
     */
    public function summary(Request $request)
    {
        try {
            $query = Penggajian::query();

            if ($request->has('bulan') && $request->has('tahun')) {
                $query->whereMonth('gajian_bulan', $request->bulan)
                    ->whereYear('gajian_bulan', $request->tahun);
            }

            $summary = [
                'total_karyawan' => $query->count(),
                'total_upah_kotor' => $query->sum('upah_kotor_karyawan'),
                'total_bpjs' => $query->sum('total_bpjs'),
                'total_upah_diterima' => $query->sum('upah_diterima'),
                'total_thr' => $query->sum('uang_thr'),
                'total_lembur' => $query->sum('jumlah_lembur'),
                'by_posisi' => $query->select('posisi', 
                    DB::raw('COUNT(*) as jumlah'),
                    DB::raw('SUM(upah_diterima) as total_upah')
                )->groupBy('posisi')->get(),
                'status_cetak' => [
                    'sudah_cetak' => Penggajian::where('status_penggajian', true)->count(),
                    'belum_cetak' => Penggajian::where('status_penggajian', false)->count(),
                ]
            ];

            return response()->json([
                'success' => true,
                'message' => 'Summary penggajian berhasil diambil',
                'data' => $summary
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil summary penggajian',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}