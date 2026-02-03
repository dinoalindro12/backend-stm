<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\Penggajian;
use App\Models\TagihanPerusahaan;
use App\Models\Rekruitmen;
use App\Models\LowonganKerja;
use App\Models\Kontak;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use App\Http\Resources\DashboardResource;

class DashboardController extends Controller
{
    /**
     * mendapatkan semua data dashboard
     */
    public function getDashboardData(Request $request): JsonResponse
    {
        $data = [
            'stats' => $this->getStatsData(),
            'summary' => $this->getSummaryData(),
            'charts' => [
                'gaji' => $this->getGajiChartData($request),
                'pelamar' => $this->getPelamarChartData($request)
            ],
            'recent_data' => $this->getRecentData(),
            'success' => true,
            'message' => 'Data Dashboard berhasil diambil'
        ];

        return response()->json(new DashboardResource($data));
    }

    /**
     * mendapatkan statistik data dari seluruh database mulai dari karyawan penggajian, tagihan, rekrutimen, lowongan dan kontak
     */
    private function getStatsData(): array
    {
        return [
            'karyawan' => [
                'total' => Karyawan::count(),
                'aktif' => Karyawan::where('status_aktif', true)->count(),
                'non_aktif' => Karyawan::where('status_aktif', false)->count(),
                'by_posisi' => Karyawan::selectRaw('posisi, COUNT(*) as total')
                    ->groupBy('posisi')
                    ->get()
                    ->mapWithKeys(fn($item) => [$item->posisi => $item->total])
            ],
            'penggajian' => [
                'bulan_ini' => [
                    'total' => Penggajian::whereMonth('gajian_bulan', now()->month)
                        ->whereYear('gajian_bulan', now()->year)
                        ->sum('upah_diterima'),
                    'jumlah' => Penggajian::whereMonth('gajian_bulan', now()->month)
                        ->whereYear('gajian_bulan', now()->year)
                        ->count()
                ],
                'tahun_ini' => Penggajian::whereYear('gajian_bulan', now()->year)
                    ->sum('upah_diterima'),
                'belum_dibayar' => Penggajian::where('status_penggajian', false)->count(),
                'sudah_dibayar' => Penggajian::where('status_penggajian', true)->count()
            ],
            'tagihan' => [
                'bulan_ini' => TagihanPerusahaan::whereMonth('periode_awal', now()->month)
                    ->whereYear('periode_awal', now()->year)
                    ->sum('total_diterima'),
                'tahun_ini' => TagihanPerusahaan::whereYear('periode_awal', now()->year)
                    ->sum('total_diterima')
            ],
            'rekruitmen' => [
                'total' => Rekruitmen::count(),
                'pending' => Rekruitmen::where('status_terima', 'pending')->count(),
                'diterima' => Rekruitmen::where('status_terima', 'diterima')->count(),
                'ditolak' => Rekruitmen::where('status_terima', 'ditolak')->count(),
                'by_posisi' => Rekruitmen::selectRaw('posisi_dilamar, COUNT(*) as total')
                    ->groupBy('posisi_dilamar')
                    ->get()
                    ->map(fn($item) => [
                        'posisi' => $item->posisi_dilamar,
                        'total' => $item->total
                    ])
            ],
            'lowongan' => [
                'total' => LowonganKerja::count(),
                'aktif' => LowonganKerja::where('status_lowongan', 'aktif')
                    ->where('deadline_lowongan', '>=', now())
                    ->count(),
                'non_aktif' => LowonganKerja::where('status_lowongan', 'tidak_aktif')->count()
            ],
            'kontak' => [
                'total' => Kontak::count(),
                'belum_dibaca' => Kontak::where('status_dibaca', 'belum_dibaca')->count(),
                'sudah_dibaca' => Kontak::where('status_dibaca', '!=', 'belum_dibaca')->count()
            ],
            'timestamp' => now()->toDateTimeString()
        ];
    }

    /**
     * mendapatkan ringkasan data penting utama 
     */
    private function getSummaryData(): array
    {
        return [
            'karyawan_aktif' => Karyawan::where('status_aktif', true)->count(),
            'total_gaji_bulan_ini' => Penggajian::whereMonth('gajian_bulan', now()->month)
                ->whereYear('gajian_bulan', now()->year)
                ->sum('upah_diterima'),
            'total_tagihan_bulan_ini' => TagihanPerusahaan::whereMonth('periode_awal', now()->month)
                ->whereYear('periode_awal', now()->year)
                ->sum('total_diterima'),
            'pelamar_pending' => Rekruitmen::where('status_terima', 'pending')->count(),
            'lowongan_aktif' => LowonganKerja::where('status_lowongan', 'aktif')
                ->where('deadline_lowongan', '>=', now())
                ->count(),
            'pesan_belum_dibaca' => Kontak::where('status_dibaca', 'belum_dibaca')->count()
        ];
    }

    /**
     * mendaparkan chart data gaji dan tagihan
     */
    private function getGajiChartData(Request $request): array
    {
        $months = $request->get('months', 6);
        $startDate = now()->subMonths($months - 1)->startOfMonth();

        $gajiData = Penggajian::selectRaw('
                DATE_FORMAT(gajian_bulan, "%Y-%m") as bulan,
                SUM(upah_diterima) as total_gaji,
                COUNT(*) as jumlah_karyawan,
                AVG(upah_diterima) as rata_rata_gaji
            ')
            ->where('gajian_bulan', '>=', $startDate)
            ->groupBy('bulan')
            ->orderBy('bulan')
            ->get();

        $tagihanData = TagihanPerusahaan::selectRaw('
                DATE_FORMAT(periode_awal, "%Y-%m") as bulan,
                SUM(total_diterima) as total_tagihan
            ')
            ->where('periode_awal', '>=', $startDate)
            ->groupBy('bulan')
            ->orderBy('bulan')
            ->get();

        $chartData = [];
        foreach ($gajiData as $gaji) {
            $tagihan = $tagihanData->firstWhere('bulan', $gaji->bulan);
            $chartData[] = [
                'bulan' => $gaji->bulan,
                'total_gaji' => (float) $gaji->total_gaji,
                'total_tagihan' => $tagihan ? (float) $tagihan->total_tagihan : 0,
                'jumlah_karyawan' => (int) $gaji->jumlah_karyawan,
                'rata_rata_gaji' => (float) $gaji->rata_rata_gaji
            ];
        }

        return [
            'labels' => collect($chartData)->pluck('bulan')->toArray(),
            'datasets' => [
                [
                    'label' => 'Total Gaji',
                    'data' => collect($chartData)->pluck('total_gaji')->toArray(),
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgba(54, 162, 235, 1)'
                ],
                [
                    'label' => 'Total Tagihan',
                    'data' => collect($chartData)->pluck('total_tagihan')->toArray(),
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor' => 'rgba(255, 99, 132, 1)'
                ]
            ],
            'raw_data' => $chartData,
            'months' => $months
        ];
    }

    /**
     * mendapatkan chart data pelamar
     */
    private function getPelamarChartData(Request $request): array
    {
        $months = $request->get('months', 6);
        $startDate = now()->subMonths($months - 1)->startOfMonth();

        $pelamarData = Rekruitmen::selectRaw('
                DATE_FORMAT(created_at, "%Y-%m") as bulan,
                COUNT(*) as total_pelamar,
                SUM(CASE WHEN status_terima = "pending" THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status_terima = "diterima" THEN 1 ELSE 0 END) as diterima,
                SUM(CASE WHEN status_terima = "ditolak" THEN 1 ELSE 0 END) as ditolak
            ')
            ->where('created_at', '>=', $startDate)
            ->groupBy('bulan')
            ->orderBy('bulan')
            ->get();

        return [
            'labels' => $pelamarData->pluck('bulan')->toArray(),
            'datasets' => [
                [
                    'label' => 'Pending',
                    'data' => $pelamarData->pluck('pending')->toArray(),
                    'backgroundColor' => 'rgba(255, 206, 86, 0.2)',
                    'borderColor' => 'rgba(255, 206, 86, 1)'
                ],
                [
                    'label' => 'Diterima',
                    'data' => $pelamarData->pluck('diterima')->toArray(),
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor' => 'rgba(75, 192, 192, 1)'
                ],
                [
                    'label' => 'Ditolak',
                    'data' => $pelamarData->pluck('ditolak')->toArray(),
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor' => 'rgba(255, 99, 132, 1)'
                ]
            ],
            'raw_data' => $pelamarData->toArray(),
            'months' => $months
        ];
    }

    /**
     * mendapatkan data terbaru
     */
    private function getRecentData(): array
    {
        return [
            'karyawan' => Karyawan::select('id', 'nomor_induk', 'nama_lengkap', 'posisi', 'tanggal_masuk', 'status_aktif')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function($karyawan) {
                    return [
                        'id' => $karyawan->id,
                        'nomor_induk' => $karyawan->nomor_induk,
                        'nama_lengkap' => $karyawan->nama_lengkap,
                        'posisi' => $karyawan->posisi,
                        'tanggal_masuk' => $karyawan->tanggal_masuk,
                        'status_aktif' => $karyawan->status_aktif
                    ];
                })->toArray(),
            'penggajian' => Penggajian::with(['karyawan' => function($q) {
                    $q->select('nomor_induk', 'nama_lengkap');
                }])
                ->select('id', 'no_induk', 'gajian_bulan', 'upah_diterima', 'status_penggajian', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function($penggajian) {
                    return [
                        'id' => $penggajian->id,
                        'no_induk' => $penggajian->no_induk,
                        'nama_karyawan' => $penggajian->karyawan->nama_lengkap ?? 'N/A',
                        'gajian_bulan' => $penggajian->gajian_bulan,
                        'upah_diterima' => $penggajian->upah_diterima,
                        'status_penggajian' => $penggajian->status_penggajian,
                        'created_at' => $penggajian->created_at
                    ];
                })->toArray(),
            'rekruitmen' => Rekruitmen::with(['lowonganKerja' => function($q) {
                    $q->select('id', 'posisi', 'lokasi_kerja');
                }])
                ->select('id', 'nama', 'posisi_dilamar', 'status_terima', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function($rekruitmen) {
                    return [
                        'id' => $rekruitmen->id,
                        'nama' => $rekruitmen->nama,
                        'posisi_dilamar' => $rekruitmen->posisi_dilamar,
                        'status_terima' => $rekruitmen->status_terima,
                        'created_at' => $rekruitmen->created_at,
                        'lowongan_info' => $rekruitmen->lowonganKerja ? [
                            'posisi' => $rekruitmen->lowonganKerja->posisi,
                            'lokasi_kerja' => $rekruitmen->lowonganKerja->lokasi_kerja
                        ] : null
                    ];
                })->toArray(),
            'lowongan' => LowonganKerja::select('id', 'posisi', 'lokasi_kerja', 'deadline_lowongan', 'status_lowongan')
                ->where('status_lowongan', 'aktif')
                ->orderBy('deadline_lowongan', 'asc')
                ->limit(5)
                ->get()
                ->map(function($lowongan) {
                    return [
                        'id' => $lowongan->id,
                        'posisi' => $lowongan->posisi,
                        'lokasi_kerja' => $lowongan->lokasi_kerja,
                        'deadline_lowongan' => $lowongan->deadline_lowongan,
                        'status_lowongan' => $lowongan->status_lowongan
                    ];
                })->toArray()
        ];
    }
}