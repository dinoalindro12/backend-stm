<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagihanPerusahaanResource;
use App\Models\Karyawan;
use App\Models\TagihanPerusahaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TagihanPerusahaanController extends Controller
{
    /**
     * bagian ini berfungsi untuk menampilkan daftar tagihan perusahaan.
     */
    public function index(Request $request)
    {
        try {
            $query = TagihanPerusahaan::with(['karyawan', 'admin', 'updatedBy']);
            
            //  FILTER BERDASARKAN BULAN (tagihan_bulan)
            if ($request->has('bulan') && $request->has('tahun')) {
                $query->bulanTahunTagihan($request->bulan, $request->tahun);
            } elseif ($request->has('bulan')) {
                $query->bulanTagihan($request->bulan);
            } elseif ($request->has('tahun')) {
                $query->tahunTagihan($request->tahun);
            }

            // Filter berdasarkan tagihan_bulan sebagai date (misal: 2026-06-01)
            if ($request->filled('tagihan_bulan')) {
                $bulan = Carbon::parse($request->tagihan_bulan);
                $query->whereYear('tagihan_bulan', $bulan->year)
                    ->whereMonth('tagihan_bulan', $bulan->month);
            }
            
            //  FILTER BERDASARKAN RANGE TANGGAL (jika diperlukan)
            if ($request->has('tanggal_awal') && $request->has('tanggal_akhir')) {
                $query->periode($request->tanggal_awal, $request->tanggal_akhir);
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
            
            // Filter status cetak
            if ($request->has('status_cetak')) {
                if ($request->status_cetak === 'sudah') {
                    $query->sudahCetak();
                } elseif ($request->status_cetak === 'belum') {
                    $query->belumCetak();
                }
            }
            
            // Sorting
            if ($request->has('sort_by')) {
                $sortOrder = $request->get('sort_order', 'asc');
                $query->orderBy($request->sort_by, $sortOrder);
            } else {
                $query->orderBy('tagihan_bulan', 'desc')->orderBy('created_at', 'desc');
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
     * Bagian ini berfungsi untuk menambahkan tagihan perusahaan baru.
     */
    public function store(Request $request)
    {
        // Hitung jumlah hari dalam bulan tagihan (default bulan ini jika tidak diisi)
        $maxHariKerja = $request->filled('tagihan_bulan')
            ? Carbon::parse($request->tagihan_bulan)->daysInMonth
            : Carbon::now()->daysInMonth;
        // setelah dapat max hari kerja, kita buat validasi dengan rule max:{$maxHariKerja} dan menjalankan validasi seperti biasa
        $validator = Validator::make($request->all(), [
            'karyawan_id' => 'required|exists:karyawans,id',
            'jumlah_penghasilan_kotor' => 'required|numeric|min:0',
            'jumlah_hari_kerja' => "required|numeric|min:0|max:{$maxHariKerja}",
            'gaji_harian' => 'required|numeric|min:0',
            'jlh_lembur' => 'nullable|numeric|min:0',
            'thr' => 'nullable|numeric|min:0',
            'seragam_cs_dan_keamanan' => 'nullable|numeric|min:0',
            'fee_manajemen' => 'nullable|numeric|min:0',
            'tagihan_bulan' => 'nullable|date',
        ], [
            // Pesan error khusus untuk validasi jumlah_hari_kerja agar lebih informatif
            'jumlah_hari_kerja.max' => "Jumlah hari kerja tidak boleh melebihi {$maxHariKerja} hari (jumlah hari dalam bulan tersebut).",
            // 'tagihan_bulan.date' => "Format tanggal tagihan bulan tidak valid.",
            // 'karyawan_id.exists' => "Karyawan dengan ID yang diberikan tidak ditemukan.",
            // 'jumlah_penghasilan_kotor.numeric' => "Jumlah penghasilan kotor harus berupa angka.",
            // 'jumlah_penghasilan_kotor.min' => "Jumlah penghasilan kotor tidak boleh negatif.",
            // 'gaji_harian.numeric' => "Gaji harian harus berupa angka.",
            // 'gaji_harian.min' => "Gaji harian tidak boleh negatif.",
            // 'jlh_lembur.numeric' => "Jumlah lembur harus berupa angka.",
            // 'jlh_lembur.min' => "Jumlah lembur tidak boleh negatif.",
            // 'thr.numeric' => "Uang THR harus berupa angka.",
            // 'thr.min' => "Uang THR tidak boleh negatif.",
            // 'seragam_cs_dan_keamanan.numeric' => "Biaya seragam CS dan keamanan harus berupa angka.",
            // 'seragam_cs_dan_keamanan.min' => "Biaya seragam CS dan keamanan tidak boleh negatif.",
            // 'fee_manajemen.numeric' => "Fee manajemen harus berupa angka.",
            // 'fee_manajemen.min' => "Fee manajemen tidak boleh negatif.",
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

            // ✅ Cek apakah karyawan sudah dihapus (soft delete) dengan withTrashed
            $karyawan = Karyawan::withTrashed()->find($request->karyawan_id);
            if ($karyawan && $karyawan->trashed()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Tagihan gagal dibuat',
                    'error' => 'Data tagihan tidak dapat dibuat karena karyawan "' . $karyawan->nama_lengkap . '" ('. $karyawan->nomor_induk .') sudah dihapus dari sistem.'
                ], 422);
            }

            // ✅ Cek duplikasi berdasarkan tagihan_bulan
            $tagihanBulan = Carbon::parse($request->tagihan_bulan)->startOfMonth();
            
            $existingTagihan = TagihanPerusahaan::where('karyawan_id', $request->karyawan_id)
                ->whereYear('tagihan_bulan', $tagihanBulan->year)
                ->whereMonth('tagihan_bulan', $tagihanBulan->month)
                ->first();

            if ($existingTagihan) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Tagihan gagal ditambahkan',
                    'error' => 'Karyawan sudah memiliki data tagihan untuk bulan ' . 
                            $this->getBulanIndonesia($tagihanBulan->format('n')) . ' ' . $tagihanBulan->format('Y') .
                            '. Tidak dapat membuat tagihan ganda pada bulan yang sama.'
                ], 409);
            }

            // ✅ HAPUS field yang dihitung otomatis oleh method hitungTagihan. 
            $data = $request->except([
                'bpjs_kesehatan', 'jkk', 'jkm', 'jht', 'jp',
                'upah_diterima_pekerja', 'upah_total',
            ]);

            // Inject admin_id dan updated_by
            $data['admin_id'] = $request->user()->id;
            $data['updated_by'] = $request->user()->id;

            // Hitung komponen tagihan di controller
            $kalkulasi = $this->hitungTagihan(
                jumlahPenghasilanKotor: $request->jumlah_penghasilan_kotor,
                jumlahHariKerja:        $request->jumlah_hari_kerja,
                gajiHarian:             $request->gaji_harian,
                jlhLembur:              $request->jlh_lembur ?? 0,
                thr:                    $request->thr ?? 0,
                seragam:                $request->seragam_cs_dan_keamanan ?? 0,
                feeManajemen:           $request->fee_manajemen ?? 0,
            );

            $tagihan = TagihanPerusahaan::create(array_merge($data, $kalkulasi));

            DB::commit();

            return (new TagihanPerusahaanResource($tagihan->load(['karyawan', 'admin', 'updatedBy'])))
                ->additional([
                    'success' => true,
                    'message' => 'Data tagihan perusahaan berhasil ditambahkan'
                ])
                ->response()
                ->setStatusCode(201);

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
            $tagihan = TagihanPerusahaan::with(['karyawan', 'admin', 'updatedBy'])->findOrFail($id);
            
            return (new TagihanPerusahaanResource($tagihan))
                ->additional([
                    'success' => true,
                    'message' => 'Data tagihan perusahaan berhasil diambil'
                ]);
            
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
        // Hitung jumlah hari dalam bulan tagihan
        // Jika tagihan_bulan tidak dikirim, ambil dari data existing
        $maxHariKerja = 31; // fallback default
        if ($request->filled('tagihan_bulan')) {
            $maxHariKerja = Carbon::parse($request->tagihan_bulan)->daysInMonth;
        } elseif ($request->isMethod('patch') || $request->isMethod('put')) {
            $existingTagihan = TagihanPerusahaan::find($id);
            if ($existingTagihan && $existingTagihan->tagihan_bulan) {
                $maxHariKerja = Carbon::parse($existingTagihan->tagihan_bulan)->daysInMonth;
            }
        }
        // setelah dapat max hari kerja, kita buat validasi dengan rule max:{$maxHariKerja} dan menjalankan validasi seperti biasa
        $validator = Validator::make($request->all(), [
            'karyawan_id' => 'sometimes|exists:karyawans,id',
            'jumlah_penghasilan_kotor' => 'sometimes|numeric|min:0',
            'jumlah_hari_kerja' => "sometimes|numeric|min:0|max:{$maxHariKerja}",
            'gaji_harian' => 'sometimes|numeric|min:0',
            'jlh_lembur' => 'nullable|numeric|min:0',
            'thr' => 'nullable|numeric|min:0',
            'seragam_cs_dan_keamanan' => 'nullable|numeric|min:0',
            'fee_manajemen' => 'nullable|numeric|min:0',
            'tagihan_bulan' => 'sometimes|date',
        ], [
            'jumlah_hari_kerja.max' => "Jumlah hari kerja tidak boleh melebihi {$maxHariKerja} hari (jumlah hari dalam bulan tersebut).",
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

            // ✅ Cek duplikasi berdasarkan tagihan_bulan
            if ($request->has('karyawan_id') || $request->has('tagihan_bulan')) {
                $karyawanId = $request->karyawan_id ?? $tagihan->karyawan_id;
                $tagihanBulan = $request->has('tagihan_bulan')
                    ? Carbon::parse($request->tagihan_bulan)->startOfMonth()
                    : Carbon::parse($tagihan->tagihan_bulan)->startOfMonth();
                    // Jika tagihan_bulan tidak diubah, tetap gunakan bulan dari data existing untuk cek duplikasi agar tidak salah deteksi duplikasi saat update data yang sama tanpa mengubah bulan.
                $existingTagihan = TagihanPerusahaan::where('karyawan_id', $karyawanId)
                    ->whereYear('tagihan_bulan', $tagihanBulan->year)
                    ->whereMonth('tagihan_bulan', $tagihanBulan->month)
                    // Jika data existing yang sedang diupdate memiliki bulan yang sama dengan bulan baru, maka kita harus mengecualikan data tersebut dari pengecekan duplikasi agar tidak salah deteksi duplikasi saat update data yang sama tanpa mengubah bulan.
                    ->where('id', '!=', $id)
                    ->first();
                    
                if ($existingTagihan) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Tagihan perusahaan gagal diupdate',
                        'error' => 'Karyawan sudah memiliki data tagihan untuk bulan ' .
                                $this->getBulanIndonesia($tagihanBulan->format('n')) . ' ' . $tagihanBulan->format('Y') .
                                '. Tidak dapat membuat tagihan ganda pada bulan yang sama.'
                    ], 409);
                }
            }

            // ✅ HAPUS field yang dihitung otomatis
            $data = $request->except([
                'bpjs_kesehatan', 'jkk', 'jkm', 'jht', 'jp',
                'upah_diterima_pekerja', 'upah_total',
            ]);

            // Inject updated_by
            $data['updated_by'] = $request->user()->id;

            // Hitung ulang kalkulasi dengan nilai terbaru (merge request dengan data existing)
            $kalkulasi = $this->hitungTagihan(
                jumlahPenghasilanKotor: $data['jumlah_penghasilan_kotor'] ?? $tagihan->jumlah_penghasilan_kotor,
                jumlahHariKerja:        $data['jumlah_hari_kerja']        ?? $tagihan->jumlah_hari_kerja,
                gajiHarian:             $data['gaji_harian']              ?? $tagihan->gaji_harian,
                jlhLembur:              $data['jlh_lembur']               ?? $tagihan->jlh_lembur ?? 0,
                thr:                    $data['thr']                      ?? $tagihan->thr ?? 0,
                seragam:                $data['seragam_cs_dan_keamanan']  ?? $tagihan->seragam_cs_dan_keamanan ?? 0,
                feeManajemen:           $data['fee_manajemen']            ?? $tagihan->fee_manajemen ?? 0,
            );

            $tagihan->update(array_merge($data, $kalkulasi));

            DB::commit();

            return (new TagihanPerusahaanResource($tagihan->load(['karyawan', 'admin', 'updatedBy'])))
                ->additional([
                    'success' => true,
                    'message' => 'Data tagihan perusahaan berhasil diupdate'
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
            
            return (new TagihanPerusahaanResource($tagihan->load(['karyawan', 'admin', 'updatedBy'])))
                ->additional([
                    'success' => true,
                    'message' => 'Tagihan perusahaan berhasil dipulihkan'
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
            
            // ✅ Filter berdasarkan tagihan_bulan
            if ($request->has('bulan') && $request->has('tahun')) {
                $query->bulanTahunTagihan($request->bulan, $request->tahun);
            } elseif ($request->has('bulan')) {
                $query->bulanTagihan($request->bulan);
            } elseif ($request->has('tahun')) {
                $query->tahunTagihan($request->tahun);
            }
            
            if ($request->has('posisi')) {
                $query->posisi($request->posisi);
            }
            
            // ✅ Summary dengan field yang benar
            $summary = $query->select(
                DB::raw('COUNT(*) as total_karyawan'),
                DB::raw('SUM(jumlah_hari_kerja) as total_hari_kerja'),
                DB::raw('SUM(jumlah_penghasilan_kotor) as total_penghasilan_kotor'),
                DB::raw('SUM(upah_diterima_pekerja) as total_upa_pekerja'),
                DB::raw('SUM(bpjs_kesehatan) as total_bpjs_kesehatan'),
                DB::raw('SUM(jkk) as total_jkk'),
                DB::raw('SUM(jkm) as total_jkm'),
                DB::raw('SUM(jht) as total_jht'),
                DB::raw('SUM(jp) as total_jp'),
                DB::raw('SUM(upah_diterima_pekerja) as total_upah_diterima'),
                DB::raw('SUM(upah_total) as total_tagihan')
            )->first();
            
            // Hitung total BPJS
            $totalBPJS = ($summary->total_bpjs_kesehatan ?? 0) + 
                        ($summary->total_jkk ?? 0) + 
                        ($summary->total_jkm ?? 0) + 
                        ($summary->total_jht ?? 0) + 
                        ($summary->total_jp ?? 0);
            
            // Get position distribution
            $posisiDistribution = TagihanPerusahaan::query()
                ->when($request->has('bulan') && $request->has('tahun'), fn($q) => $q->bulanTahunTagihan($request->bulan, $request->tahun))
                ->when($request->has('bulan') && !$request->has('tahun'), fn($q) => $q->bulanTagihan($request->bulan))
                ->when(!$request->has('bulan') && $request->has('tahun'), fn($q) => $q->tahunTagihan($request->tahun))
                ->when($request->has('posisi'), fn($q) => $q->whereHas('karyawan', fn($k) => $k->where('posisi', $request->posisi)))
                ->with('karyawan')
                ->get()
                ->groupBy(fn($item) => optional($item->karyawan)->posisi)
                ->map(function ($items, $posisi) {
                    return [
                        'posisi' => $posisi,
                        'posisi_label' => $this->getPosisiLabel($posisi),
                        'jumlah' => $items->count(),
                        'total' => $items->sum('upah_total'),
                        'total_formatted' => 'Rp ' . number_format($items->sum('upah_total'), 0, ',', '.'),
                    ];
                })
                ->values();
            
            // Format periode
            $periodeText = null;
            if ($request->has('bulan') && $request->has('tahun')) {
                $periodeText = $this->getBulanIndonesia($request->bulan) . ' ' . $request->tahun;
            } elseif ($request->has('tahun')) {
                $periodeText = 'Tahun ' . $request->tahun;
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_karyawan' => $summary->total_karyawan,
                        'total_hari_kerja' => $summary->total_hari_kerja,
                        'total_penghasilan_kotor' => $summary->total_penghasilan_kotor,
                        'total_penghasilan_kotor_formatted' => 'Rp ' . number_format($summary->total_penghasilan_kotor ?? 0, 0, ',', '.'),
                        'total_upa_pekerja' => $summary->total_upa_pekerja,
                        'total_upa_pekerja_formatted' => 'Rp ' . number_format($summary->total_upa_pekerja ?? 0, 0, ',', '.'),
                        'total_bpjs' => $totalBPJS,
                        'total_bpjs_formatted' => 'Rp ' . number_format($totalBPJS, 0, ',', '.'),
                        'total_upah_diterima' => $summary->total_upah_diterima,
                        'total_upah_diterima_formatted' => 'Rp ' . number_format($summary->total_upah_diterima ?? 0, 0, ',', '.'),
                        'total_tagihan' => $summary->total_tagihan,
                        'total_tagihan_formatted' => 'Rp ' . number_format($summary->total_tagihan ?? 0, 0, ',', '.'),
                    ],
                    'posisi_distribution' => $posisiDistribution,
                    'periode' => $periodeText
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
     * Bulk store — untuk menambahkan beberapa tagihan sekaligus.
     */
    public function bulkStore(Request $request)
    {
        // bagian ini merupakan validasi awal untuk memastika data yang dikirim sesuai dengan format yang diharapkan 
        $validator = Validator::make($request->all(), [
            'data'                                    => 'required|array|min:1',
            'data.*.karyawan_id'                      => 'required|exists:karyawans,id',
            'data.*.jumlah_penghasilan_kotor'         => 'required|numeric|min:0',
            'data.*.jumlah_hari_kerja'                => 'required|numeric|min:0',
            'data.*.gaji_harian'                      => 'required|numeric|min:0',
            'data.*.jlh_lembur'                       => 'nullable|numeric|min:0',
            'data.*.thr'                              => 'nullable|numeric|min:0',
            'data.*.seragam_cs_dan_keamanan'          => 'nullable|numeric|min:0',
            'data.*.fee_manajemen'                    => 'nullable|numeric|min:0',
            'data.*.tagihan_bulan'                    => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $adminId    = $request->user()->id;
            $created    = [];
            $duplicates = [];
            $skippedDeleted = [];

            foreach ($request->data as $index => $item) {
                $tagihanBulan = isset($item['tagihan_bulan']) && $item['tagihan_bulan']
                    ? Carbon::parse($item['tagihan_bulan'])->startOfMonth()
                    : Carbon::now()->startOfMonth();

                // Validasi max hari kerja per bulan
                $maxHariKerja = $tagihanBulan->daysInMonth;
                if ($item['jumlah_hari_kerja'] > $maxHariKerja) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Validasi gagal pada data ke-" . ($index + 1),
                        'errors'  => [
                            "data.{$index}.jumlah_hari_kerja" => [
                                "Jumlah hari kerja tidak boleh melebihi {$maxHariKerja} hari."
                            ]
                        ]
                    ], 422);
                }

                // Cek karyawan soft deleted
                $karyawan = Karyawan::withTrashed()->find($item['karyawan_id']);
                if ($karyawan && $karyawan->trashed()) {
                    $skippedDeleted[] = [
                        'index'       => $index,
                        'karyawan_id' => $item['karyawan_id'],
                        'message'     => 'Karyawan "' . $karyawan->nama_lengkap . '" (' . $karyawan->nomor_induk . ') sudah dihapus dari sistem.',
                    ];
                    continue;
                }

                // Cek duplikasi
                $existing = TagihanPerusahaan::where('karyawan_id', $item['karyawan_id'])
                    ->whereYear('tagihan_bulan', $tagihanBulan->year)
                    ->whereMonth('tagihan_bulan', $tagihanBulan->month)
                    ->exists();

                if ($existing) {
                    $duplicates[] = [
                        'index'       => $index,
                        'karyawan_id' => $item['karyawan_id'],
                        'message'     => 'Sudah memiliki tagihan untuk ' .
                            $this->getBulanIndonesia($tagihanBulan->format('n')) . ' ' . $tagihanBulan->format('Y'),
                    ];
                    continue;
                }

                $kalkulasi = $this->hitungTagihan(
                    jumlahPenghasilanKotor: $item['jumlah_penghasilan_kotor'],
                    jumlahHariKerja:        $item['jumlah_hari_kerja'],
                    gajiHarian:             $item['gaji_harian'],
                    jlhLembur:              $item['jlh_lembur'] ?? 0,
                    thr:                    $item['thr'] ?? 0,
                    seragam:                $item['seragam_cs_dan_keamanan'] ?? 0,
                    feeManajemen:           $item['fee_manajemen'] ?? 0,
                );

                $tagihan = TagihanPerusahaan::create(array_merge([
                    'karyawan_id'              => $item['karyawan_id'],
                    'jumlah_penghasilan_kotor' => $item['jumlah_penghasilan_kotor'],
                    'jumlah_hari_kerja'        => $item['jumlah_hari_kerja'],
                    'gaji_harian'              => $item['gaji_harian'],
                    'jlh_lembur'               => $item['jlh_lembur'] ?? 0,
                    'thr'                      => $item['thr'] ?? 0,
                    'seragam_cs_dan_keamanan'  => $item['seragam_cs_dan_keamanan'] ?? 0,
                    'fee_manajemen'            => $item['fee_manajemen'] ?? 0,
                    'tagihan_bulan'            => $tagihanBulan->format('Y-m-d'),
                    'admin_id'                 => $adminId,
                    'updated_by'               => $adminId,
                ], $kalkulasi));

                $created[] = $tagihan->load(['karyawan', 'admin', 'updatedBy']);
            }

            DB::commit();

            // Semua gagal
            if (empty($created)) {
                return response()->json([
                    'success'         => false,
                    'message'         => 'Semua data gagal dibuat',
                    'duplicates'      => $duplicates,
                    'skipped_deleted' => $skippedDeleted,
                ], 409);
            }

            $additional = [
                'success'       => true,
                'message'       => count($created) . ' data tagihan berhasil ditambahkan',
                'created_count' => count($created),
                'skipped_count' => count($duplicates) + count($skippedDeleted),
            ];

            if (!empty($duplicates)) {
                $additional['duplicates'] = $duplicates;
            }
            if (!empty($skippedDeleted)) {
                $additional['skipped_deleted'] = $skippedDeleted;
            }

            return TagihanPerusahaanResource::collection(collect($created))
                ->additional($additional)
                ->response()
                ->setStatusCode(201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan data tagihan',
                'error'   => $e->getMessage()
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
            'data.*.karyawan_id' => 'required|exists:karyawans,id',
            'data.*.jumlah_penghasilan_kotor' => 'required|numeric|min:0',
            'data.*.jumlah_hari_kerja' => 'required|numeric|min:0|max:31',
            'data.*.gaji_harian' => 'required|numeric|min:0',
            'data.*.tagihan_bulan' => 'required|date',
            'data.*.jlh_lembur' => 'nullable|numeric|min:0',
            'data.*.thr' => 'nullable|numeric|min:0',
            'data.*.seragam_cs_dan_keamanan' => 'nullable|numeric|min:0',
            'data.*.fee_manajemen' => 'nullable|numeric|min:0',
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
                    // ✅ Cek apakah karyawan sudah dihapus (soft delete)
                    $karyawan = Karyawan::withTrashed()->find($item['karyawan_id']);
                    if ($karyawan && $karyawan->trashed()) {
                        throw new \Exception('Data tagihan tidak dapat dibuat karena karyawan "' . $karyawan->nama_lengkap . '" (' . $karyawan->nomor_induk . ') sudah dihapus dari sistem.');
                    }

                    // ✅ Cek duplikasi berdasarkan tagihan_bulan
                    $tagihanBulan = Carbon::parse($item['tagihan_bulan'])->startOfMonth();
                    
                    $existing = TagihanPerusahaan::where('karyawan_id', $item['karyawan_id'])
                        ->whereYear('tagihan_bulan', $tagihanBulan->year)
                        ->whereMonth('tagihan_bulan', $tagihanBulan->month)
                        ->exists();
                    
                    if ($existing) {
                        throw new \Exception('Data sudah ada untuk bulan ' . 
                            $this->getBulanIndonesia($tagihanBulan->format('n')) . ' ' . $tagihanBulan->format('Y'));
                    }
                    
                    // ✅ Hapus field yang dihitung otomatis, lalu hitung di controller
                    $item = array_diff_key($item, array_flip([
                        'bpjs_kesehatan', 'jkk', 'jkm', 'jht', 'jp',
                        'upah_diterima_pekerja', 'upah_total',
                    ]));

                    // Inject admin_id dan updated_by
                    $item['admin_id'] = $request->user()->id;
                    $item['updated_by'] = $request->user()->id;

                    $kalkulasi = $this->hitungTagihan(
                        jumlahPenghasilanKotor: $item['jumlah_penghasilan_kotor'],
                        jumlahHariKerja:        $item['jumlah_hari_kerja'],
                        gajiHarian:             $item['gaji_harian'],
                        jlhLembur:              $item['jlh_lembur'] ?? 0,
                        thr:                    $item['thr'] ?? 0,
                        seragam:                $item['seragam_cs_dan_keamanan'] ?? 0,
                        feeManajemen:           $item['fee_manajemen'] ?? 0,
                    );

                    $tagihan = TagihanPerusahaan::create(array_merge($item, $kalkulasi));
                    $imported[] = $tagihan;
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index + 1,
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
     * Get available months for filtering.
     */
    public function getAvailableMonths()
    {
        try {
            $months = TagihanPerusahaan::select(
                    'tagihan_bulan',
                    DB::raw('COUNT(*) as jumlah_karyawan'),
                    DB::raw('SUM(upah_total) as total_tagihan')
                )
                ->groupBy('tagihan_bulan')
                ->orderBy('tagihan_bulan', 'desc')
                ->get()
                ->map(function ($item) {
                    $bulan = Carbon::parse($item->tagihan_bulan);
                    return [
                        'value' => $bulan->format('Y-m'),
                        'bulan' => $bulan->format('n'),
                        'tahun' => $bulan->format('Y'),
                        'text' => $this->getBulanIndonesia($bulan->format('n')) . ' ' . $bulan->format('Y'),
                        'label' => $this->getBulanIndonesia($bulan->format('n')) . ' ' . $bulan->format('Y'),
                        'jumlah_karyawan' => $item->jumlah_karyawan,
                        'total_tagihan' => $item->total_tagihan,
                        'total_tagihan_formatted' => 'Rp ' . number_format($item->total_tagihan, 0, ',', '.'),
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Daftar bulan tagihan perusahaan berhasil diambil',
                'data' => $months
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar bulan tagihan perusahaan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Metthod ini berfungsi untuk mennyalin data tagihan bulan lalu.
     */
    public function copyFromPreviousMonth(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bulan_referensi' => 'required|date',
            'bulan_tujuan' => 'required|date|after:bulan_referensi',
            'karyawan_id' => 'nullable|array',
            'karyawan_id.*' => 'exists:karyawans,id',
            'reset_lembur_thr' => 'nullable|boolean',
            'reset_seragam_fee' => 'nullable|boolean',
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

            // Parse bulan
            $bulanReferensi = Carbon::parse($request->bulan_referensi)->startOfMonth();
            $bulanTujuan = Carbon::parse($request->bulan_tujuan)->startOfMonth();
            // Validasi bulan tujuan harus setelah bulan referensi
            if ($bulanTujuan->lte($bulanReferensi)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bulan tujuan harus setelah bulan referensi'
                ], 422);
            }

            // Query data referensi
            $query = TagihanPerusahaan::whereYear('tagihan_bulan', $bulanReferensi->year)
                ->whereMonth('tagihan_bulan', $bulanReferensi->month);

            if ($request->has('karyawan_id') && !empty($request->karyawan_id)) {
                $query->whereIn('karyawan_id', $request->karyawan_id);
            }

            $referensiData = $query->with('karyawan')->get();

            if ($referensiData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data tagihan pada bulan ' . 
                        $this->getBulanIndonesia($bulanReferensi->format('n')) . ' ' . $bulanReferensi->format('Y')
                ], 404);
            }

            // Cek data yang sudah ada di bulan tujuan
            $karyawanIdList = $referensiData->pluck('karyawan_id')->toArray();
            $existingData = TagihanPerusahaan::whereIn('karyawan_id', $karyawanIdList)
                ->whereYear('tagihan_bulan', $bulanTujuan->year)
                ->whereMonth('tagihan_bulan', $bulanTujuan->month)
                ->pluck('karyawan_id')
                ->toArray();

            $created = [];
            $skipped = [];
            $resetLemburThr = $request->get('reset_lembur_thr', true);
            $resetSeragamFee = $request->get('reset_seragam_fee', true);

            foreach ($referensiData as $referensi) {
                // Skip jika sudah ada di bulan tujuan
                if (in_array($referensi->karyawan_id, $existingData)) {
                    $skipped[] = [
                        'karyawan_id' => $referensi->karyawan_id,
                        'nama' => optional($referensi->karyawan)->nama_lengkap,
                        'message' => 'Sudah memiliki tagihan untuk bulan ' .
                            $this->getBulanIndonesia($bulanTujuan->format('n')) . ' ' . $bulanTujuan->format('Y')
                    ];
                    continue;
                }

                // ✅ Cek apakah karyawan sudah dihapus (soft delete)
                $karyawan = Karyawan::withTrashed()->find($referensi->karyawan_id);
                if ($karyawan && $karyawan->trashed()) {
                    $skipped[] = [
                        'karyawan_id' => $referensi->karyawan_id,
                        'nama' => $karyawan->nama_lengkap,
                        'message' => 'Data tagihan tidak dapat dibuat karena karyawan "' . $karyawan->nama_lengkap . '" (' . $karyawan->nomor_induk . ') sudah dihapus dari sistem.'
                    ];
                    continue;
                }

                // Siapkan data baru
                $dataBaru = [
                    'karyawan_id'              => $referensi->karyawan_id,
                    'jumlah_penghasilan_kotor' => $referensi->jumlah_penghasilan_kotor,
                    'jumlah_hari_kerja'        => $referensi->jumlah_hari_kerja,
                    'gaji_harian'              => $referensi->gaji_harian,
                    'tagihan_bulan'            => $bulanTujuan->format('Y-m-d'),
                    'admin_id'                 => $request->user()->id,
                    'updated_by'               => $request->user()->id,
                ];

                // Handle jlh_lembur dan THR
                if ($resetLemburThr) {
                    $dataBaru['jlh_lembur'] = 0;
                    $dataBaru['thr'] = 0;
                } else {
                    $dataBaru['jlh_lembur'] = $referensi->jlh_lembur ?? 0;
                    $dataBaru['thr'] = $referensi->thr ?? 0;
                }

                // Seragam & fee manajemen — selalu dari referensi
                $dataBaru['seragam_cs_dan_keamanan'] = $referensi->seragam_cs_dan_keamanan ?? 0;
                $dataBaru['fee_manajemen']           = $referensi->fee_manajemen ?? 0;

                // BPJS langsung dari referensi — tidak dihitung ulang
                $upahDiterima = ($dataBaru['gaji_harian'] * $dataBaru['jumlah_hari_kerja']) + $dataBaru['jlh_lembur'] + $dataBaru['thr'];
                $upahTotal    = $upahDiterima
                    + ($referensi->bpjs_kesehatan ?? 0)
                    + ($referensi->jkk ?? 0)
                    + ($referensi->jkm ?? 0)
                    + ($referensi->jht ?? 0)
                    + ($referensi->jp ?? 0)
                    + $dataBaru['seragam_cs_dan_keamanan']
                    + $dataBaru['fee_manajemen'];

                $tagihanBaru = TagihanPerusahaan::create(array_merge($dataBaru, [
                    'bpjs_kesehatan'          => $referensi->bpjs_kesehatan ?? 0,
                    'jkk'                     => $referensi->jkk ?? 0,
                    'jkm'                     => $referensi->jkm ?? 0,
                    'jht'                     => $referensi->jht ?? 0,
                    'jp'                      => $referensi->jp ?? 0,
                    'upah_diterima_pekerja'   => $upahDiterima,
                    'upah_total'              => $upahTotal,
                ]));
                $created[] = $tagihanBaru->load(['karyawan', 'admin', 'updatedBy']);
            }

            DB::commit();

            // Semua data sudah ada, tidak ada yang berhasil dibuat
            if (empty($created)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak berhasil dibuat, record tagihan ' .
                        $this->getBulanIndonesia($bulanTujuan->format('n')) . ' ' . $bulanTujuan->format('Y') . ' sudah ada',
                    'data'    => [
                        'created_count' => 0,
                        'skipped_count' => count($skipped),
                        'skipped'       => $skipped,
                    ]
                ], 409);
            }

            $message = count($created) . ' data tagihan berhasil disalin';
            if (!empty($skipped)) {
                $message .= ', ' . count($skipped) . ' data dilewati karena sudah ada';
            }

            return TagihanPerusahaanResource::collection(collect($created))
                ->additional([
                    'success' => true,
                    'message' => $message,
                ])
                ->response()
                ->setStatusCode(201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyalin data tagihan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview copy data
     */
    public function previewCopy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bulan_referensi' => 'required|date_format:Y-m',
            'bulan_tujuan' => 'required|date_format:Y-m|after:bulan_referensi',
            'karyawan_id' => 'nullable|array',
            'karyawan_id.*' => 'exists:karyawans,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $bulanReferensi = Carbon::parse($request->bulan_referensi . '-01')->startOfMonth();
            $bulanTujuan = Carbon::parse($request->bulan_tujuan . '-01')->startOfMonth();

            // Query data referensi
            $query = TagihanPerusahaan::whereYear('tagihan_bulan', $bulanReferensi->year)
                ->whereMonth('tagihan_bulan', $bulanReferensi->month);

            if ($request->has('karyawan_id') && !empty($request->karyawan_id)) {
                $query->whereIn('karyawan_id', $request->karyawan_id);
            }

            $referensiData = $query->with('karyawan')->get();

            if ($referensiData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data tagihan pada bulan ' . 
                        $this->getBulanIndonesia($bulanReferensi->format('n')) . ' ' . $bulanReferensi->format('Y')
                ], 404);
            }

            // Cek data yang sudah ada di bulan tujuan
            $karyawanIdList = $referensiData->pluck('karyawan_id')->toArray();
            $existingData = TagihanPerusahaan::whereIn('karyawan_id', $karyawanIdList)
                ->whereYear('tagihan_bulan', $bulanTujuan->year)
                ->whereMonth('tagihan_bulan', $bulanTujuan->month)
                ->pluck('karyawan_id')
                ->toArray();

            $willBeCreated = [];
            $willBeSkipped = [];

            foreach ($referensiData as $referensi) {
                $data = [
                    'karyawan_id' => $referensi->karyawan_id,
                    'nama' => optional($referensi->karyawan)->nama,
                    'posisi' => optional($referensi->karyawan)->posisi,
                    'posisi_label' => $this->getPosisiLabel(optional($referensi->karyawan)->posisi),
                    'jumlah_penghasilan_kotor' => $referensi->jumlah_penghasilan_kotor,
                    'jumlah_penghasilan_kotor_formatted' => 'Rp ' . number_format($referensi->jumlah_penghasilan_kotor, 0, ',', '.'),
                    'gaji_harian' => $referensi->gaji_harian,
                    'gaji_harian_formatted' => 'Rp ' . number_format($referensi->gaji_harian, 0, ',', '.'),
                    'jumlah_hari_kerja' => $referensi->jumlah_hari_kerja,
                    'jlh_lembur' => $referensi->jlh_lembur ?? 0,
                    'jlh_lembur_formatted' => 'Rp ' . number_format($referensi->jlh_lembur ?? 0, 0, ',', '.'),
                    'thr' => $referensi->thr ?? 0,
                    'thr_formatted' => 'Rp ' . number_format($referensi->thr ?? 0, 0, ',', '.'),
                    'seragam_cs_dan_keamanan' => $referensi->seragam_cs_dan_keamanan ?? 0,
                    'seragam_formatted' => 'Rp ' . number_format($referensi->seragam_cs_dan_keamanan ?? 0, 0, ',', '.'),
                    'fee_manajemen' => $referensi->fee_manajemen ?? 0,
                    'fee_formatted' => 'Rp ' . number_format($referensi->fee_manajemen ?? 0, 0, ',', '.'),
                    'upah_diterima_pekerja' => $referensi->upah_diterima_pekerja ?? 0,
                    'upah_diterima_formatted' => 'Rp ' . number_format($referensi->upah_diterima_pekerja ?? 0, 0, ',', '.'),
                    'upah_total' => $referensi->upah_total ?? 0,
                    'upah_total_formatted' => 'Rp ' . number_format($referensi->upah_total ?? 0, 0, ',', '.'),
                ];

                if (in_array($referensi->karyawan_id, $existingData)) {
                    $willBeSkipped[] = $data;
                } else {
                    $willBeCreated[] = $data;
                }
            }

            // Hitung summary
            $totalPenghasilanKotor = $referensiData->sum('jumlah_penghasilan_kotor');
            $totalUpahDiterima = $referensiData->sum('upah_diterima_pekerja');
            $totalTagihan = $referensiData->sum('upah_total');
            
            $totalBPJS = $referensiData->sum(function($item) {
                return ($item->bpjs_kesehatan ?? 0) + 
                       ($item->jkk ?? 0) + 
                       ($item->jkm ?? 0) + 
                       ($item->jht ?? 0) + 
                       ($item->jp ?? 0) ;
            });

            return response()->json([
                'success' => true,
                'message' => 'Preview copy data tagihan',
                'data' => [
                    'bulan_referensi' => [
                        'value' => $bulanReferensi->format('Y-m'),
                        'text' => $this->getBulanIndonesia($bulanReferensi->format('n')) . ' ' . $bulanReferensi->format('Y'),
                    ],
                    'bulan_tujuan' => [
                        'value' => $bulanTujuan->format('Y-m'),
                        'text' => $this->getBulanIndonesia($bulanTujuan->format('n')) . ' ' . $bulanTujuan->format('Y'),
                    ],
                    'summary_referensi' => [
                        'total_karyawan' => $referensiData->count(),
                        'total_penghasilan_kotor' => $totalPenghasilanKotor,
                        'total_penghasilan_kotor_formatted' => 'Rp ' . number_format($totalPenghasilanKotor, 0, ',', '.'),
                        'total_bpjs' => $totalBPJS,
                        'total_bpjs_formatted' => 'Rp ' . number_format($totalBPJS, 0, ',', '.'),
                        'total_upah_diterima' => $totalUpahDiterima,
                        'total_upah_diterima_formatted' => 'Rp ' . number_format($totalUpahDiterima, 0, ',', '.'),
                        'total_tagihan' => $totalTagihan,
                        'total_tagihan_formatted' => 'Rp ' . number_format($totalTagihan, 0, ',', '.'),
                    ],
                    'will_be_created' => $willBeCreated,
                    'will_be_created_count' => count($willBeCreated),
                    'will_be_skipped' => $willBeSkipped,
                    'will_be_skipped_count' => count($willBeSkipped),
                    'total_referensi' => $referensiData->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat preview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hitung komponen tagihan perusahaan.
     *
     * Aturan:
    /**
     * Hitung komponen tagihan perusahaan.
     *
     * Aturan:
     * - BPJS Kesehatan (perusahaan) = 4%    penghasilan kotor  (0 jika hari kerja < 7)
     * - JHT (perusahaan)            = 3.7%  penghasilan kotor  (0 jika hari kerja < 7)
     * - JP  (perusahaan)            = 2%    penghasilan kotor  (0 jika hari kerja < 7)
     * - JKK                         = 0.24% penghasilan kotor  (0 jika hari kerja < 7)
     * - JKM                         = 0.3%  penghasilan kotor  (0 jika hari kerja < 7)
     * - Seragam & Fee               = 0 jika hari kerja < 7
     * - Upah Diterima Pekerja       = (gaji_harian × hari_kerja) + lembur + thr
     * - Total Tagihan               = upah_diterima_pekerja + semua iuran & fee perusahaan
     *
     * @return array kolom-kolom hasil kalkulasi siap di-merge ke data create/update
     */
    private function hitungTagihan(
    float $jumlahPenghasilanKotor,
    float $jumlahHariKerja,
    float $gajiHarian,
    float $jlhLembur,
    float $thr,
    float $seragam,
    float $feeManajemen,
    ): array {
        if ($jumlahHariKerja < 7) {
            $upahDiterimaPekerja = $gajiHarian * $jumlahHariKerja;

            return [
                'bpjs_kesehatan'          => 0,
                'jkk'                     => 0,
                'jkm'                     => 0,
                'jht'                     => 0,
                'jp'                      => 0,
                'seragam_cs_dan_keamanan' => 0,
                'fee_manajemen'           => 0,
                'upah_diterima_pekerja'   => $upahDiterimaPekerja,
                'upah_total'              => $upahDiterimaPekerja, // Total tagihan hanya upah diterima pekerja
            ];
        }

        $bpjsKesehatan = round($jumlahPenghasilanKotor * 0.04);
        $jht           = round($jumlahPenghasilanKotor * 0.037);
        $jp            = round($jumlahPenghasilanKotor * 0.02);
        $jkk           = round($jumlahPenghasilanKotor * 0.0024);
        $jkm           = round($jumlahPenghasilanKotor * 0.003);

        $totalIuranPerusahaan = $bpjsKesehatan + $jkk + $jkm + $jht + $jp + $seragam + $feeManajemen;
        $upahDiterimaPekerja  = round(($gajiHarian * $jumlahHariKerja) + $jlhLembur + $thr - $gajiHarian);
        $upahTotal            = round($upahDiterimaPekerja + $totalIuranPerusahaan + $gajiHarian); // Tambahkan gaji_harian untuk menyesuaikan total tagihan

        return [
            'bpjs_kesehatan'          => $bpjsKesehatan,
            'jkk'                     => $jkk,
            'jkm'                     => $jkm,
            'jht'                     => $jht,
            'jp'                      => $jp,
            'seragam_cs_dan_keamanan' => $seragam,
            'fee_manajemen'           => $feeManajemen,
            'upah_diterima_pekerja'   => $upahDiterimaPekerja,
            'upah_total'              => $upahTotal,
        ];
    }

    /**
     * Helper function untuk nama bulan Indonesia
     */
    private function getBulanIndonesia($bulan)
    {
        $bulanIndo = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        return $bulanIndo[(int)$bulan] ?? '';
    }

    /**
     * Helper function untuk mendapatkan label posisi
     */
    private function getPosisiLabel($posisi)
    {
        $labels = [
            'jasa' => 'JASA',
            'supir' => 'SUPIR',
            'keamanan' => 'KEAMANAN',
            'cleaning_service' => 'CLEANING SERVICE',
            'operator' => 'OPERATOR'
        ];
        
        return $labels[$posisi] ?? strtoupper($posisi);
    }
}