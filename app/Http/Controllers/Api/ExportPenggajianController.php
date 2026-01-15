<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Penggajian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Exports\PenggajianExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ExportPenggajianController extends Controller
{
    /**
     * PREVIEW data sebelum export
     */
    public function previewExport(Request $request)
    {
        Log::info('Preview export dipanggil', $request->all());
        
        try {
            $validator = Validator::make($request->all(), [
                'bulan' => 'required|integer|min:1|max:12',
                'tahun' => 'required|integer|min:2000|max:' . (date('Y') + 5),
                'posisi' => 'nullable|in:jasa,supir,keamanan,cleaning_service,operator',
            ]);
            
            if ($validator->fails()) {
                Log::error('Validasi gagal', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $bulan = $request->bulan;
            $tahun = $request->tahun;
            $posisi = $request->posisi;
            
            Log::info("Mencari data untuk bulan: {$bulan}, tahun: {$tahun}, posisi: {$posisi}");
            
            // Query sederhana berdasarkan gajian_bulan
            $query = Penggajian::with('karyawan')
                ->whereMonth('gajian_bulan', $bulan)
                ->whereYear('gajian_bulan', $tahun);
            
            if ($posisi) {
                $query->where('posisi', $posisi);
            }
            
            // Debug query
            $sql = $query->toSql();
            Log::info("SQL Query: {$sql}", $query->getBindings());
            
            $totalData = $query->count();
            Log::info("Total data ditemukan: {$totalData}");
            
            if ($totalData === 0) {
                // Cek apakah ada data di tabel penggajian
                $totalAllData = Penggajian::count();
                Log::info("Total semua data penggajian: {$totalAllData}");
                
                // Tampilkan bulan-bulan yang tersedia
                $availableMonths = Penggajian::selectRaw('
                    DISTINCT MONTH(gajian_bulan) as bulan, 
                    YEAR(gajian_bulan) as tahun
                ')->get();
                
                $monthsInfo = $availableMonths->map(function($item) {
                    $namaBulan = Carbon::create()->month($item->bulan)->monthName;
                    return "{$namaBulan} {$item->tahun}";
                })->toArray();
                
                Log::info("Bulan tersedia: " . implode(', ', $monthsInfo));
                
                return response()->json([
                    'success' => false,
                    'message' => "Tidak ada data penggajian untuk {$this->getMonthName($bulan)} {$tahun}",
                    'suggestion' => [
                        'available_months' => $monthsInfo,
                        'total_data_in_database' => $totalAllData,
                    ]
                ], 404);
            }
            
            // Ambil 3 data sample
            $sampleData = $query->orderBy('nama')->limit(3)->get();
            Log::info("Sample data ditemukan: {$sampleData->count()} records");
            
            // Format sample
            $formattedSample = $sampleData->map(function($item) {
                return [
                    'no_rek_bri' => $item->no_rek_bri ?? '-',
                    'nik' => $item->nik,
                    'nama' => $item->nama,
                    'posisi' => $item->posisi,
                    'jumlah_hari_kerja' => $item->jumlah_hari_kerja,
                    'gaji_harian' => 'Rp ' . number_format($item->gaji_harian, 0, ',', '.'),
                    'upah_kotor' => 'Rp ' . number_format($item->upah_kotor_karyawan, 0, ',', '.'),
                    'total_bpjs' => 'Rp ' . number_format($item->total_bpjs, 0, ',', '.'),
                    'upah_diterima' => 'Rp ' . number_format($item->upah_diterima, 0, ',', '.'),
                ];
            });
            
            $namaBulan = $this->getMonthName($bulan);
            
            return response()->json([
                'success' => true,
                'message' => 'Preview export berhasil',
                'data' => [
                    'periode' => [
                        'bulan' => $namaBulan,
                        'tahun' => $tahun,
                        'bulan_angka' => $bulan,
                        'posisi' => $posisi ?: 'Semua'
                    ],
                    'summary' => [
                        'total_data' => $totalData,
                        'total_upah' => number_format($query->sum('upah_diterima'), 0, ',', '.'),
                        'total_karyawan' => $totalData,
                        'total_upah_formatted' => 'Rp ' . number_format($query->sum('upah_diterima'), 0, ',', '.'),
                    ],
                    'sample_data' => $formattedSample,
                    'download_info' => [
                        'filename' => $this->generateFilename($bulan, $tahun, $posisi),
                        'total_rows' => $totalData,
                        'estimated_size_kb' => round($totalData * 0.5, 2)
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error preview export: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null,
                'debug_info' => env('APP_DEBUG') ? [
                    'request_params' => $request->all(),
                    'error_line' => $e->getLine(),
                    'error_file' => $e->getFile()
                ] : null
            ], 500);
        }
    }
    
    /**
     * EXPORT Excel berdasarkan bulan gajian
     */
    public function exportExcel(Request $request)
    {
        Log::info('Export Excel dipanggil', $request->all());
        
        try {
            $validator = Validator::make($request->all(), [
                'bulan' => 'required|integer|min:1|max:12',
                'tahun' => 'required|integer|min:2000|max:' . (date('Y') + 5),
                'posisi' => 'nullable|in:jasa,supir,keamanan,cleaning_service,operator',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $bulan = $request->bulan;
            $tahun = $request->tahun;
            $posisi = $request->posisi;
            
            // Cek apakah ada data
            $query = Penggajian::whereMonth('gajian_bulan', $bulan)
                ->whereYear('gajian_bulan', $tahun);
            
            if ($posisi) {
                $query->where('posisi', $posisi);
            }
            
            $totalData = $query->count();
            
            if ($totalData === 0) {
                $namaBulan = $this->getMonthName($bulan);
                return response()->json([
                    'success' => false,
                    'message' => "Tidak ada data penggajian untuk {$namaBulan} {$tahun}"
                ], 404);
            }
            
            $filename = $this->generateFilename($bulan, $tahun, $posisi);
            
            Log::info("Exporting {$totalData} records to {$filename}");
            
            return Excel::download(
                new PenggajianExport($bulan, $tahun, $posisi),
                $filename
            );
            
        } catch (\Exception $e) {
            Log::error('Error export Excel: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal export Excel',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }
    
    /**
     * Dapatkan bulan-bulan yang tersedia untuk export
     */
    public function getAvailableMonths()
    {
        try {
            $availableMonths = Penggajian::selectRaw('
                MONTH(gajian_bulan) as bulan,
                YEAR(gajian_bulan) as tahun,
                COUNT(*) as total,
                SUM(upah_diterima) as total_upah
            ')
            ->groupByRaw('YEAR(gajian_bulan), MONTH(gajian_bulan)')
            ->orderBy('tahun', 'desc')
            ->orderBy('bulan', 'desc')
            ->get()
            ->map(function($item) {
                return [
                    'bulan' => (int)$item->bulan,
                    'tahun' => (int)$item->tahun,
                    'nama_bulan' => $this->getMonthName($item->bulan),
                    'total_data' => $item->total,
                    'total_upah' => (float)$item->total_upah,
                    'total_upah_formatted' => 'Rp ' . number_format($item->total_upah, 0, ',', '.'),
                    'rata_upah' => $item->total > 0 ? round($item->total_upah / $item->total, 0) : 0,
                    'rata_upah_formatted' => 'Rp ' . number_format($item->total > 0 ? round($item->total_upah / $item->total, 0) : 0, 0, ',', '.'),
                ];
            });
            
            $totalAllData = Penggajian::count();
            
            return response()->json([
                'success' => true,
                'message' => 'Bulan tersedia',
                'data' => [
                    'available_months' => $availableMonths,
                    'total_all_data' => $totalAllData,
                    'total_months' => $availableMonths->count(),
                    'suggestion' => $availableMonths->isNotEmpty() 
                        ? 'Gunakan salah satu bulan di atas untuk export' 
                        : 'Tidak ada data penggajian. Jalankan seeder terlebih dahulu.'
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data bulan',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }
    
    /**
     * TEST endpoint untuk cek data
     */
    public function testData(Request $request)
    {
        try {
            // Cek total data
            $totalPenggajian = Penggajian::count();
            
            // Cek data terbaru
            $latestData = Penggajian::latest()->take(3)->get([
                'id', 'nama', 'gajian_bulan', 'posisi', 'upah_diterima'
            ]);
            
            // Cek bulan yang ada
            $months = Penggajian::selectRaw('
                DISTINCT MONTH(gajian_bulan) as bulan,
                YEAR(gajian_bulan) as tahun
            ')->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Test data penggajian',
                'data' => [
                    'total_data' => $totalPenggajian,
                    'latest_data' => $latestData,
                    'available_months' => $months->map(function($item) {
                        return [
                            'bulan' => $item->bulan,
                            'tahun' => $item->tahun,
                            'nama_bulan' => $this->getMonthName($item->bulan)
                        ];
                    }),
                    'database_status' => 'OK',
                    'suggestion' => $totalPenggajian === 0 
                        ? 'Jalankan: php artisan db:seed --class=PenggajianSeeder'
                        : 'Data tersedia. Gunakan bulan yang ada di available_months'
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test gagal',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Helper: Dapatkan nama bulan
     */
    private function getMonthName($monthNumber)
    {
        $bulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        return $bulan[$monthNumber] ?? 'Bulan tidak valid';
    }
    
    /**
     * Generate filename untuk export
     */
    private function generateFilename($bulan, $tahun, $posisi = null)
    {
        $namaBulan = $this->getMonthName($bulan);
        $bulanFormatted = str_pad($bulan, 2, '0', STR_PAD_LEFT);
        
        $filename = "penggajian-{$tahun}-{$bulanFormatted}-{$namaBulan}";
        
        if ($posisi) {
            $filename .= "-{$posisi}";
        }
        
        $filename .= ".xlsx";
        
        return $filename;
    }
}