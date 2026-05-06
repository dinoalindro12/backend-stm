<?php

namespace App\Http\Controllers\Api;

use App\Exports\TagihanPerusahaanExport;
use App\Http\Controllers\Controller;
use App\Models\TagihanPerusahaan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ExportTagihanPerusahaanController extends Controller
{
    /**
     * Preview data sebelum export
     */
    public function previewExport(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tagihan_bulan' => 'required|date',
                'posisi'        => 'nullable|in:jasa,supir,keamanan,cleaning_service,operator',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $tagihanBulan = Carbon::parse($request->tagihan_bulan)->startOfMonth();
            $posisi       = $request->posisi;

            $query = TagihanPerusahaan::with('karyawan')
                ->whereYear('tagihan_bulan', $tagihanBulan->year)
                ->whereMonth('tagihan_bulan', $tagihanBulan->month);

            if ($posisi) {
                $query->whereHas('karyawan', fn($q) => $q->where('posisi', $posisi));
            }

            $totalData = $query->count();

            if ($totalData === 0) {
                $availableMonths = TagihanPerusahaan::select('tagihan_bulan')
                    ->distinct()
                    ->orderBy('tagihan_bulan', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($item) {
                        $bulan = Carbon::parse($item->tagihan_bulan);
                        return [
                            'value' => $bulan->format('Y-m-d'),
                            'label' => $this->getBulanIndonesia($bulan->format('n')) . ' ' . $bulan->format('Y'),
                        ];
                    })
                    ->unique('label')
                    ->values();

                return response()->json([
                    'success'    => false,
                    'message'    => 'Tidak ada data tagihan untuk bulan ' .
                                   $this->getBulanIndonesia($tagihanBulan->format('n')) . ' ' . $tagihanBulan->format('Y'),
                    'suggestion' => [
                        'available_months'      => $availableMonths,
                        'total_data_in_database' => TagihanPerusahaan::count(),
                    ]
                ], 404);
            }

            // Sample 3 data
            $sampleData = $query->limit(3)->get()->map(function ($item) {
                $k = $item->karyawan;
                return [
                    'no_induk'              => optional($k)->nomor_induk ?? '-',
                    'nik'                   => optional($k)->nik ?? '-',
                    'nama'                  => optional($k)->nama_lengkap ?? '-',
                    'posisi'                => optional($k)->posisi ?? '-',
                    'jumlah_hari_kerja'     => $item->jumlah_hari_kerja,
                    'gaji_harian'           => 'Rp ' . number_format($item->gaji_harian ?? 0, 0, ',', '.'),
                    'upah_diterima_pekerja' => 'Rp ' . number_format($item->upah_diterima_pekerja ?? 0, 0, ',', '.'),
                    'upah_total'            => 'Rp ' . number_format($item->upah_total ?? 0, 0, ',', '.'),
                ];
            });

            // Summary
            $summary = TagihanPerusahaan::whereYear('tagihan_bulan', $tagihanBulan->year)
                ->whereMonth('tagihan_bulan', $tagihanBulan->month)
                ->when($posisi, fn($q) => $q->whereHas('karyawan', fn($k) => $k->where('posisi', $posisi)))
                ->select(
                    DB::raw('SUM(upah_diterima_pekerja) as total_upah_diterima'),
                    DB::raw('SUM(upah_total) as total_tagihan'),
                    DB::raw('SUM(bpjs_kesehatan + jkk + jkm + jht + jp) as total_bpjs')
                )->first();

            return response()->json([
                'success' => true,
                'message' => 'Preview export berhasil',
                'data'    => [
                    'periode' => [
                        'bulan'           => $tagihanBulan->format('n'),
                        'tahun'           => $tagihanBulan->format('Y'),
                        'bulan_formatted' => $this->getBulanIndonesia($tagihanBulan->format('n')) . ' ' . $tagihanBulan->format('Y'),
                        'posisi'          => $posisi ?: 'Semua',
                    ],
                    'summary' => [
                        'total_data'            => $totalData,
                        'total_tagihan'         => 'Rp ' . number_format($summary->total_tagihan ?? 0, 0, ',', '.'),
                        'total_upah_diterima'   => 'Rp ' . number_format($summary->total_upah_diterima ?? 0, 0, ',', '.'),
                        'total_bpjs'            => 'Rp ' . number_format($summary->total_bpjs ?? 0, 0, ',', '.'),
                    ],
                    'sample_data'   => $sampleData,
                    'download_info' => [
                        'filename'          => $this->generateFilename($tagihanBulan, $posisi),
                        'total_rows'        => $totalData,
                        'estimated_size_kb' => round($totalData * 0.8, 2),
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error preview export tagihan: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error'   => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Export ke Excel
     */
    public function exportExcel(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tagihan_bulan' => 'required|date',
                'posisi'        => 'nullable|in:jasa,supir,keamanan,cleaning_service,operator',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $tagihanBulan = Carbon::parse($request->tagihan_bulan)->startOfMonth();
            $posisi       = $request->posisi;

            // Cek ketersediaan data
            $query = TagihanPerusahaan::whereYear('tagihan_bulan', $tagihanBulan->year)
                ->whereMonth('tagihan_bulan', $tagihanBulan->month);

            if ($posisi) {
                $query->whereHas('karyawan', fn($q) => $q->where('posisi', $posisi));
            }

            $totalData = $query->count();

            if ($totalData === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data tagihan untuk bulan ' .
                                 $this->getBulanIndonesia($tagihanBulan->format('n')) . ' ' . $tagihanBulan->format('Y'),
                ], 404);
            }

            $filename = $this->generateFilename($tagihanBulan, $posisi);

            Log::info("Export tagihan: {$totalData} records → {$filename}");

            return Excel::download(
                new TagihanPerusahaanExport($tagihanBulan->format('Y-m-d'), $posisi),
                $filename
            );

        } catch (\Exception $e) {
            Log::error('Error export Excel tagihan: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal export Excel',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Daftar bulan yang tersedia untuk export
     */
    public function getAvailableMonths()
    {
        try {
            $availableMonths = TagihanPerusahaan::select(
                    'tagihan_bulan',
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(upah_total) as total_tagihan'),
                    DB::raw('SUM(upah_diterima_pekerja) as total_upah_diterima')
                )
                ->groupBy('tagihan_bulan')
                ->orderBy('tagihan_bulan', 'desc')
                ->get()
                ->map(function ($item) {
                    $bulan = Carbon::parse($item->tagihan_bulan);
                    return [
                        'value'                      => $bulan->format('Y-m-d'),
                        'bulan'                      => $bulan->format('n'),
                        'tahun'                      => $bulan->format('Y'),
                        'nama_bulan'                 => $this->getBulanIndonesia($bulan->format('n')),
                        'label'                      => $this->getBulanIndonesia($bulan->format('n')) . ' ' . $bulan->format('Y'),
                        'total_data'                 => $item->total,
                        'total_tagihan'              => (float) $item->total_tagihan,
                        'total_tagihan_formatted'    => 'Rp ' . number_format($item->total_tagihan, 0, ',', '.'),
                        'total_upah_diterima'        => (float) $item->total_upah_diterima,
                        'total_upah_diterima_formatted' => 'Rp ' . number_format($item->total_upah_diterima, 0, ',', '.'),
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Daftar bulan tagihan berhasil diambil',
                'data'    => [
                    'available_months' => $availableMonths,
                    'total_all_data'   => TagihanPerusahaan::count(),
                    'total_bulan'      => $availableMonths->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getAvailableMonths tagihan: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar bulan',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function generateFilename(Carbon $bulan, ?string $posisi = null): string
    {
        $filename = 'tagihan-perusahaan-' . $bulan->format('Y-m');
        if ($posisi) {
            $filename .= '-' . $posisi;
        }
        return $filename . '.xlsx';
    }

    private function getBulanIndonesia(int|string $bulan): string
    {
        $names = [
            1 => 'Januari',   2 => 'Februari', 3 => 'Maret',    4 => 'April',
            5 => 'Mei',       6 => 'Juni',     7 => 'Juli',      8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        return $names[(int) $bulan] ?? '';
    }
}
