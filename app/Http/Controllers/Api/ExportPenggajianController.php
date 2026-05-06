<?php

namespace App\Http\Controllers\Api;

use App\Exports\PenggajianExport;
use App\Http\Controllers\Controller;
use App\Models\Penggajian;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ExportPenggajianController extends Controller
{
    /**
     * Preview data sebelum export
     */
    public function previewExport(Request $request)
    {
        try {
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

            $gajianBulan = Carbon::parse($request->gajian_bulan)->startOfMonth();
            $posisi      = $request->posisi;

            $query = Penggajian::with('karyawan')
                ->whereYear('gajian_bulan', $gajianBulan->year)
                ->whereMonth('gajian_bulan', $gajianBulan->month);

            if ($posisi) {
                $query->whereHas('karyawan', fn($q) => $q->where('posisi', $posisi));
            }

            $totalData = $query->count();

            if ($totalData === 0) {
                $availableMonths = Penggajian::selectRaw('strftime("%m", gajian_bulan) as bulan, strftime("%Y", gajian_bulan) as tahun')
                    ->groupByRaw('strftime("%Y", gajian_bulan), strftime("%m", gajian_bulan)')
                    ->orderByRaw('strftime("%Y", gajian_bulan) DESC, strftime("%m", gajian_bulan) DESC')
                    ->get()
                    ->map(fn($item) => [
                        'value' => Carbon::create($item->tahun, $item->bulan, 1)->format('Y-m-d'),
                        'label' => $this->getMonthName((int) $item->bulan) . ' ' . $item->tahun,
                    ]);

                return response()->json([
                    'success'    => false,
                    'message'    => 'Tidak ada data penggajian untuk ' . $this->getMonthName($gajianBulan->month) . ' ' . $gajianBulan->year,
                    'suggestion' => [
                        'available_months'       => $availableMonths,
                        'total_data_in_database' => Penggajian::count(),
                    ]
                ], 404);
            }

            // Sample 3 data
            $sampleData = $query->limit(3)->get()->map(function ($item) {
                $k = $item->karyawan;
                return [
                    'no_rek_bri'        => optional($k)->no_rek_bri ?? '-',
                    'nik'               => optional($k)->nik ?? '-',
                    'nama'              => optional($k)->nama_lengkap ?? '-',
                    'posisi'            => optional($k)->posisi ?? '-',
                    'jumlah_hari_kerja' => $item->jumlah_hari_kerja,
                    'gaji_harian'       => 'Rp ' . number_format($item->gaji_harian ?? 0, 0, ',', '.'),
                    'upah_kotor'        => 'Rp ' . number_format($item->upah_kotor_karyawan ?? 0, 0, ',', '.'),
                    'total_bpjs'        => 'Rp ' . number_format($item->total_bpjs ?? 0, 0, ',', '.'),
                    'upah_diterima'     => 'Rp ' . number_format($item->upah_diterima ?? 0, 0, ',', '.'),
                ];
            });

            // Summary
            $summary = Penggajian::whereYear('gajian_bulan', $gajianBulan->year)
                ->whereMonth('gajian_bulan', $gajianBulan->month)
                ->when($posisi, fn($q) => $q->whereHas('karyawan', fn($k) => $k->where('posisi', $posisi)))
                ->selectRaw('SUM(upah_diterima) as total_upah, SUM(upah_kotor_karyawan) as total_kotor, SUM(total_bpjs) as total_bpjs')
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Preview export berhasil',
                'data'    => [
                    'periode' => [
                        'bulan'           => $this->getMonthName($gajianBulan->month),
                        'tahun'           => $gajianBulan->year,
                        'bulan_formatted' => $this->getMonthName($gajianBulan->month) . ' ' . $gajianBulan->year,
                        'posisi'          => $posisi ?: 'Semua',
                    ],
                    'summary' => [
                        'total_data'          => $totalData,
                        'total_upah_diterima' => 'Rp ' . number_format($summary->total_upah ?? 0, 0, ',', '.'),
                        'total_upah_kotor'    => 'Rp ' . number_format($summary->total_kotor ?? 0, 0, ',', '.'),
                        'total_bpjs'          => 'Rp ' . number_format($summary->total_bpjs ?? 0, 0, ',', '.'),
                    ],
                    'sample_data'   => $sampleData,
                    'download_info' => [
                        'filename'          => $this->generateFilename($gajianBulan, $posisi),
                        'total_rows'        => $totalData,
                        'estimated_size_kb' => round($totalData * 0.5, 2),
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error preview export penggajian: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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

            $gajianBulan = Carbon::parse($request->gajian_bulan)->startOfMonth();
            $posisi      = $request->posisi;

            $query = Penggajian::whereYear('gajian_bulan', $gajianBulan->year)
                ->whereMonth('gajian_bulan', $gajianBulan->month);

            if ($posisi) {
                $query->whereHas('karyawan', fn($q) => $q->where('posisi', $posisi));
            }

            $totalData = $query->count();

            if ($totalData === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data penggajian untuk ' . $this->getMonthName($gajianBulan->month) . ' ' . $gajianBulan->year,
                ], 404);
            }

            $filename = $this->generateFilename($gajianBulan, $posisi);

            Log::info("Export penggajian: {$totalData} records → {$filename}");

            return Excel::download(
                new PenggajianExport($gajianBulan->month, $gajianBulan->year, $posisi),
                $filename
            );

        } catch (\Exception $e) {
            Log::error('Error export Excel penggajian: ' . $e->getMessage());
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
            // Gunakan strftime untuk kompatibilitas SQLite
            $availableMonths = Penggajian::selectRaw('
                strftime("%m", gajian_bulan) as bulan,
                strftime("%Y", gajian_bulan) as tahun,
                COUNT(*) as total,
                SUM(upah_diterima) as total_upah
            ')
            ->groupByRaw('strftime("%Y", gajian_bulan), strftime("%m", gajian_bulan)')
            ->orderByRaw('strftime("%Y", gajian_bulan) DESC, strftime("%m", gajian_bulan) DESC')
            ->get()
            ->map(function ($item) {
                $bulan = (int) $item->bulan;
                $tahun = (int) $item->tahun;
                return [
                    'bulan'                => $bulan,
                    'tahun'                => $tahun,
                    'nama_bulan'           => $this->getMonthName($bulan),
                    'label'                => $this->getMonthName($bulan) . " {$tahun}",
                    'total_data'           => (int) $item->total,
                    'total_upah'           => (float) $item->total_upah,
                    'total_upah_formatted' => 'Rp ' . number_format($item->total_upah, 0, ',', '.'),
                    'rata_upah'            => $item->total > 0 ? round($item->total_upah / $item->total, 0) : 0,
                    'rata_upah_formatted'  => 'Rp ' . number_format($item->total > 0 ? round($item->total_upah / $item->total, 0) : 0, 0, ',', '.'),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Daftar bulan penggajian berhasil diambil',
                'data'    => [
                    'available_months' => $availableMonths,
                    'total_all_data'   => Penggajian::count(),
                    'total_months'     => $availableMonths->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getAvailableMonths penggajian: ' . $e->getMessage());
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
        $nama     = $this->getMonthName($bulan->month);
        $bulanStr = str_pad($bulan->month, 2, '0', STR_PAD_LEFT);
        $filename = "penggajian-{$bulan->year}-{$bulanStr}-{$nama}";
        if ($posisi) {
            $filename .= "-{$posisi}";
        }
        return $filename . '.xlsx';
    }

    private function getMonthName(int|string $bulan): string
    {
        $names = [
            1 => 'Januari',   2 => 'Februari', 3 => 'Maret',    4 => 'April',
            5 => 'Mei',       6 => 'Juni',     7 => 'Juli',      8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        return $names[(int) $bulan] ?? '';
    }
}
