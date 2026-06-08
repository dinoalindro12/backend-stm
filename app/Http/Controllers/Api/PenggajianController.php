<?php

namespace App\Http\Controllers\Api;

use App\Models\Penggajian;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\PenggajianResource;
use Illuminate\Support\Facades\Validator;

class PenggajianController extends Controller
{
    /**
     * Display a listing of penggajian.
     */
    public function index(Request $request)
    {
    try {
        $query = Penggajian::with(['karyawan', 'admin', 'updatedBy']);

        // Filter berdasarkan posisi
        if ($request->has('posisi')) {
            $query->posisi($request->posisi);
        }

        // Filter berdasarkan status penggajian
        if ($request->has('status')) {
            $query->status($request->status);
        }
        // Filter berdasarkan bulan gajian
        if ($request->has('bulan')) {
            $query->whereMonth('gajian_bulan', $request->bulan);
        }

        if ($request->has('tahun')) {
            $query->whereYear('gajian_bulan', $request->tahun);
        }

        // Filter berdasarkan gajian_bulan sebagai date (misal: 2026-06-01)
        if ($request->filled('gajian_bulan')) {
            $bulan = Carbon::parse($request->gajian_bulan);
            $query->whereYear('gajian_bulan', $bulan->year)
                  ->whereMonth('gajian_bulan', $bulan->month);
        }

        // Filter sudah/belum cetak
        if ($request->has('cetak_status')) {
            if ($request->cetak_status === 'sudah') {
                $query->sudahCetak();
            } elseif ($request->cetak_status === 'belum') {
                $query->belumCetak();
            }
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'gajian_bulan');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $penggajian = $query->paginate($perPage);

        return PenggajianResource::collection($penggajian)->additional([
            'success' => true,
            'message' => 'Data penggajian berhasil diambil',
            'meta' => [
                'current_page' => $penggajian->currentPage(),
                'last_page'    => $penggajian->lastPage(),
                'per_page'     => $penggajian->perPage(),
                'total'        => $penggajian->total(),
            ],
        ]);

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
        // Hitung jumlah hari dalam bulan gajian (default bulan ini jika tidak diisi)
        $maxHariKerja = $request->filled('gajian_bulan')
            ? Carbon::parse($request->gajian_bulan)->daysInMonth
            : Carbon::now()->daysInMonth;

        $validator = Validator::make($request->all(), [
            'karyawan_id'              => 'required|exists:karyawans,id',
            'jumlah_penghasilan_kotor' => 'required|numeric|min:0',
            'uang_thr'                 => 'nullable|numeric|min:0',
            'jumlah_hari_kerja'        => "required|numeric|min:0|max:{$maxHariKerja}",
            'gaji_harian'              => 'required|numeric|min:0',
            'jumlah_lembur'            => 'nullable|numeric|min:0',
            'gajian_bulan'             => 'nullable|date',
        ], [
            'jumlah_hari_kerja.max' => "Jumlah hari kerja tidak boleh melebihi {$maxHariKerja} hari (jumlah hari dalam bulan tersebut).",
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

            // Format gajian_bulan ke tanggal pertama bulan
            $bulanGajian = $request->gajian_bulan
                ? Carbon::parse($request->gajian_bulan)->startOfMonth()
                : Carbon::now()->startOfMonth();

            // Cek duplikasi
            $existingPenggajian = Penggajian::where('karyawan_id', $request->karyawan_id)
                ->where('gajian_bulan', $bulanGajian->format('Y-m-d'))
                ->first();

            if ($existingPenggajian) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Karyawan sudah memiliki penggajian untuk ' . $bulanGajian->translatedFormat('F Y')
                ], 409);
            }

            $kalkulasi = $this->hitungPenggajian(
                jumlahPenghasilanKotor: $request->jumlah_penghasilan_kotor,
                jumlahHariKerja:        $request->jumlah_hari_kerja,
                gajiHarian:             $request->gaji_harian,
                jumlahLembur:           $request->jumlah_lembur ?? 0,
                uangThr:                $request->uang_thr ?? 0,
            );

            $penggajian = Penggajian::create([
                'karyawan_id'              => $request->karyawan_id,
                'admin_id'                 => $request->user()->id,
                'updated_by'               => $request->user()->id,
                'jumlah_penghasilan_kotor' => $request->jumlah_penghasilan_kotor,
                'uang_thr'                 => $request->uang_thr ?? 0,
                'jumlah_hari_kerja'        => $request->jumlah_hari_kerja,
                'gaji_harian'              => $request->gaji_harian,
                'jumlah_lembur'            => $request->jumlah_lembur ?? 0,
                'gajian_bulan'             => $bulanGajian,
                ...$kalkulasi,
            ]);

            DB::commit();

            return (new PenggajianResource($penggajian->load(['karyawan', 'admin', 'updatedBy'])))
                ->additional([
                    'success' => true,
                    'message' => 'Data penggajian berhasil ditambahkan',
                ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan data penggajian',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified penggajian.
     */
    public function show($id)
    {
        try {
            $penggajian = Penggajian::with(['karyawan', 'admin', 'updatedBy'])->findOrFail($id);

            return (new PenggajianResource($penggajian))
                ->additional([
                    'success' => true,
                    'message' => 'Detail penggajian berhasil diambil',
                ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data penggajian tidak ditemukan',
                'error'   => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified penggajian.
     */
    public function update(Request $request, $id)
    {
        // Hitung jumlah hari dalam bulan gajian
        // Jika gajian_bulan tidak dikirim, ambil dari data existing
        $maxHariKerja = 31; // fallback default
        if ($request->filled('gajian_bulan')) {
            $maxHariKerja = Carbon::parse($request->gajian_bulan)->daysInMonth;
        } else {
            $existingPenggajian = Penggajian::find($id);
            if ($existingPenggajian && $existingPenggajian->gajian_bulan) {
                $maxHariKerja = Carbon::parse($existingPenggajian->gajian_bulan)->daysInMonth;
            }
        }

        $validator = Validator::make($request->all(), [
            'karyawan_id'              => 'sometimes|exists:karyawans,id',
            'jumlah_penghasilan_kotor' => 'sometimes|numeric|min:0',
            'uang_thr'                 => 'nullable|numeric|min:0',
            'jumlah_hari_kerja'        => "sometimes|numeric|min:0|max:{$maxHariKerja}",
            'gaji_harian'              => 'sometimes|numeric|min:0',
            'jumlah_lembur'            => 'sometimes|numeric|min:0',
            'gajian_bulan'             => 'sometimes|date',
            'status_penggajian'        => 'sometimes|boolean',
        ], [
            'jumlah_hari_kerja.max' => "Jumlah hari kerja tidak boleh melebihi {$maxHariKerja} hari (jumlah hari dalam bulan tersebut).",
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

            $penggajian = Penggajian::findOrFail($id);

            // Format gajian_bulan jika diupdate
            if ($request->has('gajian_bulan')) {
                $request->merge(['gajian_bulan' => Carbon::parse($request->gajian_bulan)->startOfMonth()]);
            }

            // Cek duplikasi jika ada perubahan karyawan_id atau gajian_bulan
            if ($request->has('karyawan_id') || $request->has('gajian_bulan')) {
                $karyawanId  = $request->karyawan_id ?? $penggajian->karyawan_id;
                $gajianBulan = $request->gajian_bulan ?? $penggajian->gajian_bulan;

                $existingPenggajian = Penggajian::where('karyawan_id', $karyawanId)
                    ->where('gajian_bulan', $gajianBulan)
                    ->where('id', '!=', $id)
                    ->first();

                if ($existingPenggajian) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Penggajian gagal diupdate',
                        'error'   => 'Karyawan sudah memiliki data penggajian untuk ' .
                                    Carbon::parse($gajianBulan)->translatedFormat('F Y')
                    ], 409);
                }
            }

            $updateData = $request->only([
                'karyawan_id', 'jumlah_penghasilan_kotor', 'uang_thr',
                'jumlah_hari_kerja', 'gaji_harian', 'jumlah_lembur',
                'gajian_bulan', 'status_penggajian',
            ]);
            $updateData['updated_by'] = $request->user()->id;

            // Hitung ulang kalkulasi dengan nilai terbaru (merge request dengan data existing)
            $kalkulasi = $this->hitungPenggajian(
                jumlahPenghasilanKotor: $updateData['jumlah_penghasilan_kotor'] ?? $penggajian->jumlah_penghasilan_kotor,
                jumlahHariKerja:        $updateData['jumlah_hari_kerja']        ?? $penggajian->jumlah_hari_kerja,
                gajiHarian:             $updateData['gaji_harian']              ?? $penggajian->gaji_harian,
                jumlahLembur:           $updateData['jumlah_lembur']            ?? $penggajian->jumlah_lembur,
                uangThr:                $updateData['uang_thr']                 ?? $penggajian->uang_thr,
            );

            $penggajian->update(array_merge($updateData, $kalkulasi));

            DB::commit();

            return (new PenggajianResource($penggajian->load(['karyawan', 'admin', 'updatedBy'])))
                ->additional([
                    'success' => true,
                    'message' => 'Data penggajian berhasil diupdate',
                ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate data penggajian',
                'error'   => $e->getMessage()
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
            
            if ($penggajian->tanggal_cetak) {
                return response()->json([
                    'success' => false,
                    'message' => 'Slip gaji sudah pernah dicetak pada ' . 
                                $penggajian->tanggal_cetak->format('d/m/Y H:i')
                ], 400);
            }

            $penggajian->update([
                'tanggal_cetak'    => now()->toDateString(),
                'status_penggajian' => true
            ]);

            DB::commit();

            return (new PenggajianResource($penggajian->load(['karyawan', 'admin', 'updatedBy'])))
                ->additional([
                    'success' => true,
                    'message' => 'Slip gaji berhasil dicetak',
                ]);

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
     * Bulk store — setiap item memiliki field lengkap seperti method store.
     * Berbeda dengan batchStore yang pakai gajian_bulan global.
     */
    public function bulkStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'data'                                => 'required|array|min:1',
            'data.*.karyawan_id'                  => 'required|exists:karyawans,id',
            'data.*.jumlah_penghasilan_kotor'     => 'required|numeric|min:0',
            'data.*.uang_thr'                     => 'nullable|numeric|min:0',
            'data.*.jumlah_hari_kerja'            => 'required|numeric|min:0',
            'data.*.gaji_harian'                  => 'required|numeric|min:0',
            'data.*.jumlah_lembur'                => 'nullable|numeric|min:0',
            'data.*.gajian_bulan'                 => 'nullable|date',
            'data.*.status_penggajian'            => 'nullable|boolean',
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

            foreach ($request->data as $index => $item) {
                $bulanGajian = isset($item['gajian_bulan']) && $item['gajian_bulan']
                    ? Carbon::parse($item['gajian_bulan'])->startOfMonth()
                    : Carbon::now()->startOfMonth();

                // Validasi max hari kerja per bulan
                $maxHariKerja = $bulanGajian->daysInMonth;
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

                // Cek duplikasi
                $existing = Penggajian::where('karyawan_id', $item['karyawan_id'])
                    ->whereYear('gajian_bulan', $bulanGajian->year)
                    ->whereMonth('gajian_bulan', $bulanGajian->month)
                    ->exists();

                if ($existing) {
                    $duplicates[] = [
                        'index'       => $index,
                        'karyawan_id' => $item['karyawan_id'],
                        'message'     => 'Sudah memiliki penggajian untuk ' . $bulanGajian->translatedFormat('F Y'),
                    ];
                    continue;
                }

                $kalkulasi = $this->hitungPenggajian(
                    jumlahPenghasilanKotor: $item['jumlah_penghasilan_kotor'],
                    jumlahHariKerja:        $item['jumlah_hari_kerja'],
                    gajiHarian:             $item['gaji_harian'],
                    jumlahLembur:           $item['jumlah_lembur'] ?? 0,
                    uangThr:                $item['uang_thr'] ?? 0,
                );

                $penggajian = Penggajian::create([
                    'karyawan_id'              => $item['karyawan_id'],
                    'admin_id'                 => $adminId,
                    'updated_by'               => $adminId,
                    'jumlah_penghasilan_kotor' => $item['jumlah_penghasilan_kotor'],
                    'uang_thr'                 => $item['uang_thr'] ?? 0,
                    'jumlah_hari_kerja'        => $item['jumlah_hari_kerja'],
                    'gaji_harian'              => $item['gaji_harian'],
                    'jumlah_lembur'            => $item['jumlah_lembur'] ?? 0,
                    'gajian_bulan'             => $bulanGajian,
                    'status_penggajian'        => $item['status_penggajian'] ?? false,
                    ...$kalkulasi,
                ]);

                $created[] = $penggajian->load(['karyawan', 'admin', 'updatedBy']);
            }

            DB::commit();

            // Jika semua duplikat
            if (empty($created)) {
                return response()->json([
                    'success'    => false,
                    'message'    => 'Semua data gagal dibuat karena sudah ada penggajian di bulan tersebut',
                    'duplicates' => $duplicates,
                ], 409);
            }

            $response = PenggajianResource::collection(collect($created))
                ->additional([
                    'success'       => true,
                    'message'       => count($created) . ' data penggajian berhasil ditambahkan',
                    'created_count' => count($created),
                    'skipped_count' => count($duplicates),
                ]);

            if (!empty($duplicates)) {
                $response->additional(array_merge($response->additional ?? [], [
                    'duplicates' => $duplicates,
                ]));
            }

            return $response->response()->setStatusCode(201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan data penggajian',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch create penggajian untuk multiple karyawan.
     */
    public function batchStore(Request $request)
    {
        // Hitung jumlah hari dalam bulan gajian
        $maxHariKerja = $request->filled('gajian_bulan')
            ? Carbon::parse($request->gajian_bulan)->daysInMonth
            : Carbon::now()->daysInMonth;

        $validator = Validator::make($request->all(), [
            'data'                          => 'required|array',
            'data.*.karyawan_id'            => 'required|exists:karyawans,id',
            'data.*.jumlah_hari_kerja'      => "required|numeric|min:0|max:{$maxHariKerja}",
            'data.*.gaji_harian'            => 'required|numeric|min:0',
            'data.*.jumlah_lembur'          => 'nullable|numeric|min:0',
            'data.*.jumlah_penghasilan_kotor' => 'required|numeric|min:0',
            'data.*.uang_thr'               => 'nullable|numeric|min:0',
            'gajian_bulan'                  => 'required|date',
        ], [
            'data.*.jumlah_hari_kerja.max' => "Jumlah hari kerja tidak boleh melebihi {$maxHariKerja} hari (jumlah hari dalam bulan tersebut).",
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

            $results    = [];
            $duplicates = [];
            $adminId    = $request->user()->id;

            // Format bulan gajian
            $gajianBulan = Carbon::parse($request->gajian_bulan)->startOfMonth();

            // Cek duplikasi untuk semua karyawan sekaligus
            $karyawanIdList = array_column($request->data, 'karyawan_id');
            $existingPenggajian = Penggajian::whereIn('karyawan_id', $karyawanIdList)
                ->where('gajian_bulan', $gajianBulan)
                ->pluck('karyawan_id')
                ->toArray();

            foreach ($request->data as $data) {
                // Skip jika sudah ada penggajian di bulan tersebut
                if (in_array($data['karyawan_id'], $existingPenggajian)) {
                    $duplicates[] = [
                        'karyawan_id' => $data['karyawan_id'],
                        'message'     => 'Sudah memiliki penggajian untuk ' . $gajianBulan->translatedFormat('F Y')
                    ];
                    continue;
                }

                $penggajian = Penggajian::create([
                    'karyawan_id'              => $data['karyawan_id'],
                    'admin_id'                 => $adminId,
                    'updated_by'               => $adminId,
                    'jumlah_penghasilan_kotor' => $data['jumlah_penghasilan_kotor'],
                    'uang_thr'                 => $data['uang_thr'] ?? 0,
                    'jumlah_hari_kerja'        => $data['jumlah_hari_kerja'],
                    'gaji_harian'              => $data['gaji_harian'],
                    'jumlah_lembur'            => $data['jumlah_lembur'] ?? 0,
                    'gajian_bulan'             => $gajianBulan,
                    ...$this->hitungPenggajian(
                        jumlahPenghasilanKotor: $data['jumlah_penghasilan_kotor'],
                        jumlahHariKerja:        $data['jumlah_hari_kerja'],
                        gajiHarian:             $data['gaji_harian'],
                        jumlahLembur:           $data['jumlah_lembur'] ?? 0,
                        uangThr:                $data['uang_thr'] ?? 0,
                    ),
                ]);
                $results[] = $penggajian;
            }

            DB::commit();

            $response = [
                'success' => true,
                'message' => 'Batch penggajian berhasil dibuat',
                'data'    => [
                    'periode'       => $gajianBulan->translatedFormat('F Y'),
                    'created'       => $results,
                    'created_count' => count($results)
                ]
            ];

            if (!empty($duplicates)) {
                $response['data']['duplicates']       = $duplicates;
                $response['data']['duplicates_count'] = count($duplicates);
                $response['message'] = 'Batch penggajian selesai dengan ' . count($duplicates) . ' data duplikat yang dilewati';
            }

            return response()->json($response, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat batch penggajian',
                'error'   => $e->getMessage()
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
            } elseif ($request->has('gajian_bulan')) {
                $gajianBulan = Carbon::parse($request->gajian_bulan)->startOfMonth();
                $query->where('gajian_bulan', $gajianBulan);
            }

            $summary = [
                'periode' => $request->has('gajian_bulan') 
                    ? Carbon::parse($request->gajian_bulan)->translatedFormat('F Y')
                    : 'Semua Periode',
                'total_karyawan'      => $query->count(),
                'total_upah_kotor'    => $query->sum('upah_kotor_karyawan'),
                'total_bpjs'          => $query->sum('total_bpjs'),
                'total_upah_diterima' => $query->sum('upah_diterima'),
                'total_thr'           => $query->sum('uang_thr'),
                'total_lembur'        => $query->sum('jumlah_lembur'),
                'by_posisi'           => $query->with('karyawan')->get()
                    ->groupBy(fn($p) => optional($p->karyawan)->posisi)
                    ->map(fn($group, $posisi) => [
                        'posisi'         => $posisi,
                        'jumlah'         => $group->count(),
                        'total_upah'     => $group->sum('upah_diterima'),
                        'rata_rata_upah' => $group->avg('upah_diterima'),
                    ])->values(),
                'status_cetak' => [
                    'sudah_cetak' => Penggajian::query()->whereNotNull('tanggal_cetak')->count(),
                    'belum_cetak' => Penggajian::query()->whereNull('tanggal_cetak')->count(),
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

    /**
     * Kirim slip gaji ke WhatsApp
     */
    public function sendWhatsApp($id)
    {
        try {
            $penggajian = Penggajian::with('karyawan')->findOrFail($id);
            
            if (empty($penggajian->karyawan->no_wa)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor WhatsApp karyawan tidak tersedia'
                ], 400);
            }
            
            $pesan = $this->formatPesanSlip($penggajian);
            $nomor = $this->formatNomorWA($penggajian->karyawan->no_wa);
            
            $url = "https://wa.me/{$nomor}?text=" . urlencode($pesan);
            
            return response()->json([
                'success' => true,
                'message' => 'URL WhatsApp berhasil dibuat',
                'data'    => [
                    'penggajian_id'  => $penggajian->id,
                    'karyawan'       => optional($penggajian->karyawan)->nama_lengkap,
                    'no_wa'          => $penggajian->karyawan->no_wa,
                    'whatsapp_url'   => $url,
                    'preview_pesan'  => $pesan
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data penggajian tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat link WhatsApp',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Kirim slip gaji ke WhatsApp untuk semua karyawan dalam satu bulan
     */
    public function sendWhatsAppBulk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gajian_bulan' => 'required|date',
            'posisi'       => 'nullable|in:jasa,supir,keamanan,cleaning_service,operator',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $gajianBulan = Carbon::parse($request->gajian_bulan)->startOfMonth();

            $query = Penggajian::with('karyawan')
                ->whereYear('gajian_bulan', $gajianBulan->year)
                ->whereMonth('gajian_bulan', $gajianBulan->month);

            if ($request->filled('posisi')) {
                $query->whereHas('karyawan', fn($q) => $q->where('posisi', $request->posisi));
            }

            $penggajianList = $query->get();

            if ($penggajianList->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data penggajian untuk ' . $gajianBulan->translatedFormat('F Y'),
                ], 404);
            }

            $berhasil      = [];
            $gagal         = [];

            foreach ($penggajianList as $penggajian) {
                $karyawan = $penggajian->karyawan;

                if (empty($karyawan?->no_wa)) {
                    $gagal[] = [
                        'penggajian_id' => $penggajian->id,
                        'nama'          => optional($karyawan)->nama_lengkap ?? 'N/A',
                        'alasan'        => 'Nomor WhatsApp tidak tersedia',
                    ];
                    continue;
                }

                $pesan  = $this->formatPesanSlip($penggajian);
                $nomor  = $this->formatNomorWA($karyawan->no_wa);
                $url    = 'https://wa.me/' . $nomor . '?text=' . urlencode($pesan);

                $berhasil[] = [
                    'penggajian_id' => $penggajian->id,
                    'nama'          => $karyawan->nama_lengkap,
                    'nomor_induk'   => $karyawan->nomor_induk,
                    'no_wa'         => $karyawan->no_wa,
                    'whatsapp_url'  => $url,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => count($berhasil) . ' slip gaji siap dikirim, ' . count($gagal) . ' gagal.',
                'data'    => [
                    'periode'         => $gajianBulan->translatedFormat('F Y'),
                    'total'           => $penggajianList->count(),
                    'berhasil_count'  => count($berhasil),
                    'gagal_count'     => count($gagal),
                    'berhasil'        => $berhasil,
                    'gagal'           => $gagal,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pengiriman bulk WhatsApp',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format pesan slip gaji
     */
    private function formatPesanSlip($penggajian)
    {
        $bulanTahun = $penggajian->gajian_bulan->translatedFormat('F Y');

        $pesan = "* 🧾 PT SURYA TAMADO MANDIRI *";
        $pesan .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $pesan .= "MASA KERJA : {$bulanTahun}\n";
        $pesan .= "*📋 Informasi Karyawan:*\n";
        $pesan .= "NAMA: " . optional($penggajian->karyawan)->nama_lengkap . "\n";
        $pesan .= "No. INDUK: " . optional($penggajian->karyawan)->nomor_induk . "\n";
        $pesan .= "Posisi: " . ucfirst(str_replace('_', ' ', optional($penggajian->karyawan)->posisi)) . "\n\n";
        
        $pesan .= "*💰 Rincian Gaji:*\n";
        $pesan .= "Gaji Pokok: Rp " . number_format($penggajian->jumlah_penghasilan_kotor, 0, ',', '.') . "\n";
        $pesan .= "Gaji Harian: Rp " . number_format($penggajian->gaji_harian, 0, ',', '.') . "\n";
        $pesan .= "Hari Kerja: {$penggajian->jumlah_hari_kerja} hari\n";
        $pesan .= "Lembur: {$penggajian->jumlah_lembur} jam\n";
        
        if ($penggajian->uang_thr > 0) {
            $pesan .= "Uang THR: Rp " . number_format($penggajian->uang_thr, 0, ',', '.') . "\n";
        }
        
        $pesan .= "\n*📊 Potongan:*\n";
        $pesan .= "BPJS Kesehatan: Rp " . number_format($penggajian->bpjs_kesehatan, 0, ',', '.') . "\n";
        $pesan .= "BPJS JHT: Rp " . number_format($penggajian->bpjs_jht, 0, ',', '.') . "\n";
        $pesan .= "BPJS JP: Rp " . number_format($penggajian->bpjs_jp, 0, ',', '.') . "\n";
        $pesan .= "Total Potongan: Rp " . number_format($penggajian->total_bpjs, 0, ',', '.') . "\n\n";
        
        $pesan .= "━━━━━━━━━━━━━━━━━━━━\n";
        $pesan .= "*✅ TOTAL DITERIMA:*\n";
        $pesan .= "*Rp " . number_format($penggajian->upah_diterima, 0, ',', '.') . "*\n";
        $pesan .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        
        $pesan .= "No. Rek BRI: " . optional($penggajian->karyawan)->no_rek_bri . "\n";
        
        if ($penggajian->tanggal_cetak) {
            $pesan .= "Dicetak: " . $penggajian->tanggal_cetak->format('d/m/Y H:i') . "\n";
        }
        
        $pesan .= "\n_Terima kasih atas kerja keras Anda! 🙏_";

        return $pesan;
    }

    /**
     * Format nomor WhatsApp ke format internasional
     */
    private function formatNomorWA($nomor)
    {
        $nomor = preg_replace('/[^0-9]/', '', $nomor);

        if (str_starts_with($nomor, '0')) {
            $nomor = '62' . substr($nomor, 1);
        }

        if (!str_starts_with($nomor, '62')) {
            $nomor = '62' . $nomor;
        }

        return $nomor;
    }

    /**
     * Hitung komponen penggajian.
     *
     * Aturan:
     * - BPJS Kesehatan = 1% penghasilan kotor  (0 jika hari kerja < 7)
     * - BPJS JHT       = 2% penghasilan kotor  (0 jika hari kerja < 7)
     * - BPJS JP        = 1% penghasilan kotor  (0 jika hari kerja < 7)
     * - Upah Kotor     = (gaji_harian × hari_kerja) + lembur + thr
     * - Upah Diterima  = upah_kotor - total_bpjs
     *
     * @return array kolom-kolom hasil kalkulasi siap di-merge ke data create/update
     */
    private function hitungPenggajian(
        float $jumlahPenghasilanKotor,
        float $jumlahHariKerja,
        float $gajiHarian,
        float $jumlahLembur,
        float $uangThr,
    ): array {
        if ($jumlahHariKerja < 7) {
            $bpjsKesehatan = 0;
            $bpjsJht       = 0;
            $bpjsJp        = 0;
        } else {
            $bpjsKesehatan = $jumlahPenghasilanKotor * 0.01;
            $bpjsJht       = $jumlahPenghasilanKotor * 0.02;
            $bpjsJp        = $jumlahPenghasilanKotor * 0.01;
        }

        $totalBpjs        = $bpjsKesehatan + $bpjsJht + $bpjsJp;
        $upahKotorKaryawan = ($gajiHarian * $jumlahHariKerja) + $jumlahLembur + $uangThr;
        $upahDiterima      = $upahKotorKaryawan - $totalBpjs;

        return [
            'bpjs_kesehatan'      => $bpjsKesehatan,
            'bpjs_jht'            => $bpjsJht,
            'bpjs_jp'             => $bpjsJp,
            'total_bpjs'          => $totalBpjs,
            'upah_kotor_karyawan' => $upahKotorKaryawan,
            'upah_diterima'       => $upahDiterima,
        ];
    }

    /**
     * Copy penggajian dari bulan sebelumnya
     */
    public function copyFromPreviousMonth(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bulan_referensi'   => 'required|date',
            'bulan_baru'        => 'required|date|after:bulan_referensi',
            'karyawan_id'       => 'nullable|array',
            'karyawan_id.*'     => 'exists:karyawans,id',
            'copy_all'          => 'nullable|boolean',
            'adjust_thr'        => 'nullable|boolean',
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

            $bulanReferensi = Carbon::parse($request->bulan_referensi)->startOfMonth();
            $bulanBaru      = Carbon::parse($request->bulan_baru)->startOfMonth();
            $adminId        = $request->user()->id;

            // Query penggajian bulan referensi
            $query = Penggajian::whereYear('gajian_bulan', $bulanReferensi->year)
                ->whereMonth('gajian_bulan', $bulanReferensi->month);

            if ($request->has('karyawan_id') && !empty($request->karyawan_id)) {
                $query->whereIn('karyawan_id', $request->karyawan_id);
            }

            $penggajianReferensi = $query->get();

            if ($penggajianReferensi->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data penggajian pada ' . $bulanReferensi->translatedFormat('F Y')
                ], 404);
            }

            // Cek duplikasi untuk bulan baru
            $karyawanIdList = $penggajianReferensi->pluck('karyawan_id')->toArray();
            $existingPenggajian = Penggajian::whereIn('karyawan_id', $karyawanIdList)
                ->whereYear('gajian_bulan', $bulanBaru->year)
                ->whereMonth('gajian_bulan', $bulanBaru->month)
                ->pluck('karyawan_id')
                ->toArray();

            $created    = [];
            $skipped    = [];
            $adjustThr  = $request->get('adjust_thr', true);

            foreach ($penggajianReferensi as $referensi) {
                if (in_array($referensi->karyawan_id, $existingPenggajian)) {
                    $skipped[] = [
                        'karyawan_id' => $referensi->karyawan_id,
                        'nama'        => optional($referensi->karyawan)->nama_lengkap,
                        'message'     => 'Sudah memiliki penggajian untuk ' . $bulanBaru->translatedFormat('F Y')
                    ];
                    continue;
                }

                $dataBaru = [
                    'karyawan_id'              => $referensi->karyawan_id,
                    'admin_id'                 => $adminId,
                    'updated_by'               => $adminId,
                    'jumlah_penghasilan_kotor' => $referensi->jumlah_penghasilan_kotor,
                    'uang_thr'                 => $adjustThr ? 0 : $referensi->uang_thr,
                    'jumlah_hari_kerja'        => 0,
                    'gaji_harian'              => $referensi->gaji_harian,
                    'jumlah_lembur'            => 0,
                    'gajian_bulan'             => $bulanBaru,
                    'status_penggajian'        => false,
                    'tanggal_cetak'            => null,
                    ...$this->hitungPenggajian(
                        jumlahPenghasilanKotor: $referensi->jumlah_penghasilan_kotor,
                        jumlahHariKerja:        $referensi->jumlah_hari_kerja, // pakai referensi agar BPJS tidak 0
                        gajiHarian:             $referensi->gaji_harian,
                        jumlahLembur:           0,
                        uangThr:                $adjustThr ? 0 : $referensi->uang_thr,
                    ),
                ];

                $penggajianBaru = Penggajian::create($dataBaru);
                $created[] = $penggajianBaru;
            }

            DB::commit();

            // Semua data sudah ada, tidak ada yang berhasil dibuat
            if (empty($created)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak berhasil dibuat, record penggajian ' . $bulanBaru->translatedFormat('F Y') . ' sudah ada',
                    'data'    => [
                        'bulan_referensi' => $bulanReferensi->translatedFormat('F Y'),
                        'bulan_baru'      => $bulanBaru->translatedFormat('F Y'),
                        'created_count'   => 0,
                        'skipped_count'   => count($skipped),
                        'skipped'         => $skipped,
                    ]
                ], 409);
            }

            $response = [
                'success' => true,
                'message' => count($created) . ' data penggajian berhasil dibuat',
                'data'    => [
                    'bulan_referensi' => $bulanReferensi->translatedFormat('F Y'),
                    'bulan_baru'      => $bulanBaru->translatedFormat('F Y'),
                    'created'         => $created,
                    'created_count'   => count($created),
                    'skipped_count'   => count($skipped)
                ]
            ];

            if (!empty($skipped)) {
                $response['data']['skipped'] = $skipped;
                $response['message'] .= ', ' . count($skipped) . ' data dilewati karena sudah ada';
            }

            return response()->json($response, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyalin data penggajian',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list bulan-bulan yang sudah ada penggajian
     */
    public function getAvailableMonths()
    {
        try {
            $months = Penggajian::select(
                    DB::raw('DISTINCT gajian_bulan as bulan'),
                    DB::raw('COUNT(*) as jumlah_karyawan')
                )
                ->groupBy('gajian_bulan')
                ->orderBy('bulan', 'desc')
                ->get()
                ->map(function ($item) {
                    $bulan = Carbon::parse($item->bulan);
                    return [
                        'value' => $item->bulan,
                        'text' => $bulan->translatedFormat('F Y'),
                        'jumlah_karyawan' => $item->jumlah_karyawan
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Daftar bulan penggajian berhasil diambil',
                'data' => $months
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar bulan penggajian',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview data yang akan dicopy
     */
    public function previewCopy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bulan_referensi' => 'required|date',
            'bulan_baru'      => 'required|date|after:bulan_referensi',
            'karyawan_id'     => 'nullable|array',
            'karyawan_id.*'   => 'exists:karyawans,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $bulanReferensi = Carbon::parse($request->bulan_referensi)->startOfMonth();
            $bulanBaru      = Carbon::parse($request->bulan_baru)->startOfMonth();

            $query = Penggajian::with('karyawan')->where('gajian_bulan', $bulanReferensi);

            if ($request->has('karyawan_id') && !empty($request->karyawan_id)) {
                $query->whereIn('karyawan_id', $request->karyawan_id);
            }

            $penggajianReferensi = $query->get();

            if ($penggajianReferensi->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data penggajian pada ' . $bulanReferensi->translatedFormat('F Y')
                ], 404);
            }

            // Cek yang sudah ada di bulan baru
            $karyawanIdList = $penggajianReferensi->pluck('karyawan_id')->toArray();
            $existingPenggajian = Penggajian::whereIn('karyawan_id', $karyawanIdList)
                ->where('gajian_bulan', $bulanBaru)
                ->pluck('karyawan_id')
                ->toArray();

            $willBeCreated = [];
            $willBeSkipped = [];

            foreach ($penggajianReferensi as $referensi) {
                $data = [
                    'karyawan_id'  => $referensi->karyawan_id,
                    'nama'         => optional($referensi->karyawan)->nama_lengkap,
                    'posisi'       => optional($referensi->karyawan)->posisi,
                    'gaji_pokok'   => $referensi->jumlah_penghasilan_kotor,
                    'gaji_harian'  => $referensi->gaji_harian,
                ];

                if (in_array($referensi->karyawan_id, $existingPenggajian)) {
                    $willBeSkipped[] = $data;
                } else {
                    $willBeCreated[] = $data;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Preview data penggajian',
                'data'    => [
                    'bulan_referensi'        => $bulanReferensi->translatedFormat('F Y'),
                    'bulan_baru'             => $bulanBaru->translatedFormat('F Y'),
                    'will_be_created'        => $willBeCreated,
                    'will_be_created_count'  => count($willBeCreated),
                    'will_be_skipped'        => $willBeSkipped,
                    'will_be_skipped_count'  => count($willBeSkipped),
                    'total_referensi'        => $penggajianReferensi->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat preview',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}