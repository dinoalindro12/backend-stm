<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TagihanPerusahaan;
use App\Exports\TagihanPerusahaanExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ExportTagihanPerusahaanController extends Controller
{
    /**
     * PREVIEW data sebelum export
     */
    public function previewExport(Request $request)
    {
        Log::info('Preview export tagihan dipanggil', $request->all());
        
        try {
            $validator = Validator::make($request->all(), [
                'periode_awal' => 'required|date',
                'periode_akhir' => 'required|date|after_or_equal:periode_awal',
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
            
            $periodeAwal = $request->periode_awal;
            $periodeAkhir = $request->periode_akhir;
            $posisi = $request->posisi;
            
            Log::info("Mencari data untuk periode: {$periodeAwal} - {$periodeAkhir}, posisi: {$posisi}");
            
            // Query data
            $query = TagihanPerusahaan::with('karyawan')
                ->where('periode_awal', $periodeAwal)
                ->where('periode_akhir', $periodeAkhir);
            
            if ($posisi) {
                $query->where('posisi', $posisi);
            }
            
            $totalData = $query->count();
            Log::info("Total data ditemukan: {$totalData}");
            
            if ($totalData === 0) {
                // Tampilkan periode yang tersedia
                $availablePeriodes = TagihanPerusahaan::selectRaw('
                    DISTINCT periode_awal, periode_akhir
                ')->orderBy('periode_awal', 'desc')->limit(5)->get();
                
                $periodesInfo = $availablePeriodes->map(function($item) {
                    return date('d/m/Y', strtotime($item->periode_awal)) . ' - ' . date('d/m/Y', strtotime($item->periode_akhir));
                })->toArray();
                
                Log::info("Periode tersedia: " . implode(', ', $periodesInfo));
                
                return response()->json([
                    'success' => false,
                    'message' => "Tidak ada data tagihan untuk periode {$periodeAwal} - {$periodeAkhir}",
                    'suggestion' => [
                        'available_periodes' => $periodesInfo,
                        'total_data_in_database' => TagihanPerusahaan::count(),
                        'hint' => 'Gunakan API GET /api/tagihan-perusahaan/export/available-periodes'
                    ]
                ], 404);
            }
            
            // Ambil 3 data sample
            $sampleData = $query->orderBy('nama')->limit(3)->get();
            Log::info("Sample data ditemukan: {$sampleData->count()} records");
            
            // Format sample
            $formattedSample = $sampleData->map(function($item) {
                return [
                    'no_induk' => $item->no_induk,
                    'nik' => $item->nik,
                    'nama' => $item->nama,
                    'posisi' => $item->posisi,
                    'jumlah_gaji_diterima' => 'Rp ' . number_format($item->jumlah_gaji_diterima ?? 0, 0, ',', '.'),
                    'jumlah_iuran' => 'Rp ' . number_format($item->jumlah_iuran ?? 0, 0, ',', '.'),
                    'upa_pekerja' => 'Rp ' . number_format($item->upa_yang_diterima_pekerja ?? 0, 0, ',', '.'),
                    'total_diterima' => 'Rp ' . number_format($item->total_diterima ?? 0, 0, ',', '.'),
                ];
            });
            
            return response()->json([
                'success' => true,
                'message' => 'Preview export berhasil',
                'data' => [
                    'periode' => [
                        'awal' => $periodeAwal,
                        'akhir' => $periodeAkhir,
                        'awal_formatted' => date('d/m/Y', strtotime($periodeAwal)),
                        'akhir_formatted' => date('d/m/Y', strtotime($periodeAkhir)),
                        'posisi' => $posisi ?: 'Semua'
                    ],
                    'summary' => [
                        'total_data' => $totalData,
                        'total_tagihan' => 'Rp ' . number_format($query->sum('total_diterima'), 0, ',', '.'),
                        'total_upa_pekerja' => 'Rp ' . number_format($query->sum('upa_yang_diterima_pekerja'), 0, ',', '.'),
                        'total_iuran' => 'Rp ' . number_format($query->sum('jumlah_iuran'), 0, ',', '.'),
                    ],
                    'sample_data' => $formattedSample,
                    'download_info' => [
                        'filename' => $this->generateFilename($periodeAwal, $periodeAkhir, $posisi),
                        'total_rows' => $totalData,
                        'estimated_size_kb' => round($totalData * 0.8, 2)
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
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }
    
    /**
     * EXPORT Excel
     */
    public function exportExcel(Request $request)
    {
        Log::info('Export Excel tagihan dipanggil', $request->all());
        
        try {
            $validator = Validator::make($request->all(), [
                'periode_awal' => 'required|date',
                'periode_akhir' => 'required|date|after_or_equal:periode_awal',
                'posisi' => 'nullable|in:jasa,supir,keamanan,cleaning_service,operator',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $periodeAwal = $request->periode_awal;
            $periodeAkhir = $request->periode_akhir;
            $posisi = $request->posisi;
            
            // Cek apakah ada data
            $query = TagihanPerusahaan::where('periode_awal', $periodeAwal)
                ->where('periode_akhir', $periodeAkhir);
            
            if ($posisi) {
                $query->where('posisi', $posisi);
            }
            
            $totalData = $query->count();
            
            if ($totalData === 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Tidak ada data tagihan untuk periode {$periodeAwal} - {$periodeAkhir}"
                ], 404);
            }
            
            $filename = $this->generateFilename($periodeAwal, $periodeAkhir, $posisi);
            
            Log::info("Exporting {$totalData} records to {$filename}");
            
            return Excel::download(
                new TagihanPerusahaanExport($periodeAwal, $periodeAkhir, $posisi),
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
     * Dapatkan periode yang tersedia untuk export
     */
    public function getAvailablePeriodes()
    {
        try {
            $availablePeriodes = TagihanPerusahaan::selectRaw('
                periode_awal,
                periode_akhir,
                COUNT(*) as total,
                SUM(total_diterima) as total_tagihan,
                SUM(upa_yang_diterima_pekerja) as total_upa_pekerja
            ')
            ->groupBy('periode_awal', 'periode_akhir')
            ->orderBy('periode_awal', 'desc')
            ->get()
            ->map(function($item) {
                return [
                    'periode_awal' => $item->periode_awal,
                    'periode_akhir' => $item->periode_akhir,
                    'periode_formatted' => date('d/m/Y', strtotime($item->periode_awal)) . ' - ' . date('d/m/Y', strtotime($item->periode_akhir)),
                    'total_data' => $item->total,
                    'total_tagihan' => (float)$item->total_tagihan,
                    'total_tagihan_formatted' => 'Rp ' . number_format($item->total_tagihan, 0, ',', '.'),
                    'total_upa_pekerja' => (float)$item->total_upa_pekerja,
                    'total_upa_pekerja_formatted' => 'Rp ' . number_format($item->total_upa_pekerja, 0, ',', '.'),
                    'rata_tagihan' => $item->total > 0 ? round($item->total_tagihan / $item->total, 0) : 0,
                    'rata_tagihan_formatted' => 'Rp ' . number_format($item->total > 0 ? round($item->total_tagihan / $item->total, 0) : 0, 0, ',', '.'),
                ];
            });
            
            $totalAllData = TagihanPerusahaan::count();
            
            return response()->json([
                'success' => true,
                'message' => 'Periode tersedia',
                'data' => [
                    'available_periodes' => $availablePeriodes,
                    'total_all_data' => $totalAllData,
                    'total_periodes' => $availablePeriodes->count(),
                    'suggestion' => $availablePeriodes->isNotEmpty() 
                        ? 'Gunakan salah satu periode di atas untuk export' 
                        : 'Tidak ada data tagihan. Tambahkan data terlebih dahulu.'
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data periode',
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
            $totalTagihan = TagihanPerusahaan::count();
            
            // Cek data terbaru
            $latestData = TagihanPerusahaan::latest()->take(3)->get([
                'id', 'nama', 'periode_awal', 'periode_akhir', 'posisi', 'total_diterima'
            ]);
            
            // Cek periode yang ada
            $periodes = TagihanPerusahaan::selectRaw('
                DISTINCT periode_awal, periode_akhir
            ')->orderBy('periode_awal', 'desc')->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Test data tagihan perusahaan',
                'data' => [
                    'total_data' => $totalTagihan,
                    'latest_data' => $latestData,
                    'available_periodes' => $periodes->map(function($item) {
                        return [
                            'periode_awal' => $item->periode_awal,
                            'periode_akhir' => $item->periode_akhir,
                            'formatted' => date('d/m/Y', strtotime($item->periode_awal)) . ' - ' . date('d/m/Y', strtotime($item->periode_akhir))
                        ];
                    }),
                    'database_status' => 'OK',
                    'suggestion' => $totalTagihan === 0 
                        ? 'Tambahkan data tagihan terlebih dahulu via API POST /api/tagihan-perusahaan'
                        : 'Data tersedia. Gunakan periode yang ada di available_periodes'
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
     * Generate filename untuk export
     */
    private function generateFilename($periodeAwal, $periodeAkhir, $posisi = null)
    {
        $awal = date('Ymd', strtotime($periodeAwal));
        $akhir = date('Ymd', strtotime($periodeAkhir));
        
        $filename = "tagihan-perusahaan-{$awal}-{$akhir}";
        
        if ($posisi) {
            $filename .= "-{$posisi}";
        }
        
        $filename .= ".xlsx";
        
        return $filename;
    }
}