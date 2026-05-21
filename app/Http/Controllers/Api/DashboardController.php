<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Models\Karyawan;
use App\Models\Kontak;
use App\Models\LowonganKerja;
use App\Models\Penggajian;
use App\Models\Rekruitmen;
use App\Models\TagihanPerusahaan;
use Carbon\Carbon;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Mendapatkan semua data dashboard
     */
    public function getDashboardData(Request $request): JsonResponse
    {
        $data = [
            'stats'        => $this->getStatsData(),
            'summary'      => $this->getSummaryData(),
            'charts'       => [
                'gaji'    => $this->getGajiChartData($request),
                'pelamar' => $this->getPelamarChartData($request),
            ],
            'recent_data'  => $this->getRecentData(),
            'success'      => true,
            'message'      => 'Data Dashboard berhasil diambil',
        ];

        return response()->json(new DashboardResource($data));
    }

    /**
     * Mendapatkan statistik data dari seluruh database
     */
    private function getStatsData(): array
    {
        return [
            'karyawan' => [
                'total'      => Karyawan::count(),
                'aktif'      => Karyawan::where('status_aktif', true)->count(),
                'non_aktif'  => Karyawan::where('status_aktif', false)->count(),
                'by_posisi'  => Karyawan::selectRaw('posisi, COUNT(*) as total')
                    ->groupBy('posisi')
                    ->get()
                    ->mapWithKeys(fn($item) => [$item->posisi => $item->total]),
            ],
            'penggajian' => [
                'bulan_ini' => [
                    'total'  => Penggajian::whereMonth('gajian_bulan', now()->month)
                        ->whereYear('gajian_bulan', now()->year)
                        ->sum('upah_diterima'),
                    'jumlah' => Penggajian::whereMonth('gajian_bulan', now()->month)
                        ->whereYear('gajian_bulan', now()->year)
                        ->count(),
                ],
                'tahun_ini'      => Penggajian::whereYear('gajian_bulan', now()->year)->sum('upah_diterima'),
                'belum_dibayar'  => Penggajian::where('status_penggajian', false)->count(),
                'sudah_dibayar'  => Penggajian::where('status_penggajian', true)->count(),
            ],
            'tagihan' => [
                'bulan_ini' => TagihanPerusahaan::whereMonth('tagihan_bulan', now()->month)
                    ->whereYear('tagihan_bulan', now()->year)
                    ->sum('upah_total'),
                'tahun_ini' => TagihanPerusahaan::whereYear('tagihan_bulan', now()->year)
                    ->sum('upah_total'),
            ],
            'rekruitmen' => [
                'total'     => Rekruitmen::count(),
                'pending'   => Rekruitmen::where('status_terima', 'pending')->count(),
                'diterima'  => Rekruitmen::where('status_terima', 'diterima')->count(),
                'ditolak'   => Rekruitmen::where('status_terima', 'ditolak')->count(),
                'by_posisi' => Rekruitmen::selectRaw('posisi_dilamar, COUNT(*) as total')
                    ->groupBy('posisi_dilamar')
                    ->get()
                    ->map(fn($item) => [
                        'posisi' => $item->posisi_dilamar,
                        'total'  => $item->total,
                    ]),
            ],
            'lowongan' => [
                'total'      => LowonganKerja::count(),
                'aktif'      => LowonganKerja::where('status_lowongan', 'aktif')
                    ->where('deadline_lowongan', '>=', now())
                    ->count(),
                'non_aktif'  => LowonganKerja::where('status_lowongan', 'tidak_aktif')->count(),
            ],
            'kontak' => [
                'total'         => Kontak::count(),
                'belum_dibaca'  => Kontak::where('status_dibaca', 'belum_dibaca')->count(),
                'sudah_dibaca'  => Kontak::where('status_dibaca', '!=', 'belum_dibaca')->count(),
            ],
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    /**
     * Mendapatkan ringkasan data penting utama
     */
    private function getSummaryData(): array
    {
        return [
            'karyawan_aktif'          => Karyawan::where('status_aktif', true)->count(),
            'total_gaji_bulan_ini'    => Penggajian::whereMonth('gajian_bulan', now()->month)
                ->whereYear('gajian_bulan', now()->year)
                ->sum('upah_diterima'),
            'total_tagihan_bulan_ini' => TagihanPerusahaan::whereMonth('tagihan_bulan', now()->month)
                ->whereYear('tagihan_bulan', now()->year)
                ->sum('upah_total'),
            'pelamar_pending'         => Rekruitmen::where('status_terima', 'pending')->count(),
            'lowongan_aktif'          => LowonganKerja::where('status_lowongan', 'aktif')
                ->where('deadline_lowongan', '>=', now())
                ->count(),
            'pesan_belum_dibaca'      => Kontak::where('status_dibaca', 'belum_dibaca')->count(),
        ];
    }

    /**
     * Mendapatkan chart data gaji dan tagihan (kompatibel SQLite)
     */
    private function getGajiChartData(Request $request): array
    {
        $months    = (int) $request->get('months', 6);
        $startDate = now()->subMonths($months - 1)->startOfMonth();

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            $bulanExprGaji = "DATE_FORMAT(gajian_bulan, '%Y-%m')";
            $bulanExprTagihan = "DATE_FORMAT(tagihan_bulan, '%Y-%m')";
        } else {
            $bulanExprGaji = "strftime('%Y-%m', gajian_bulan)";
            $bulanExprTagihan = "strftime('%Y-%m', tagihan_bulan)";
        }

        $gajiData = Penggajian::selectRaw("
                $bulanExprGaji as bulan,
                SUM(upah_diterima) as total_gaji,
                COUNT(*) as jumlah_karyawan,
                AVG(upah_diterima) as rata_rata_gaji
            ")
            ->where('gajian_bulan', '>=', $startDate)
            ->groupByRaw($bulanExprGaji)
            ->orderByRaw($bulanExprGaji)
            ->get();

        $tagihanData = TagihanPerusahaan::selectRaw("
                $bulanExprTagihan as bulan,
                SUM(upah_total) as total_tagihan
            ")
            ->where('tagihan_bulan', '>=', $startDate)
            ->groupByRaw($bulanExprTagihan)
            ->orderByRaw($bulanExprTagihan)
            ->get();

        $chartData = [];
        foreach ($gajiData as $gaji) {
            $tagihan = $tagihanData->firstWhere('bulan', $gaji->bulan);
            $chartData[] = [
                'bulan'           => $gaji->bulan,
                'total_gaji'      => (float) $gaji->total_gaji,
                'total_tagihan'   => $tagihan ? (float) $tagihan->total_tagihan : 0,
                'jumlah_karyawan' => (int) $gaji->jumlah_karyawan,
                'rata_rata_gaji'  => (float) $gaji->rata_rata_gaji,
            ];
        }

        return [
            'labels'   => collect($chartData)->pluck('bulan')->toArray(),
            'datasets' => [
                [
                    'label'           => 'Total Gaji',
                    'data'            => collect($chartData)->pluck('total_gaji')->toArray(),
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor'     => 'rgba(54, 162, 235, 1)',
                ],
                [
                    'label'           => 'Total Tagihan',
                    'data'            => collect($chartData)->pluck('total_tagihan')->toArray(),
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor'     => 'rgba(255, 99, 132, 1)',
                ],
            ],
            'raw_data' => $chartData,
            'months'   => $months,
        ];
    }

    /**
     * Mendapatkan chart data pelamar (kompatibel SQLite)
     */
    private function getPelamarChartData(Request $request): array
    {
        $months    = (int) $request->get('months', 6);
        $startDate = now()->subMonths($months - 1)->startOfMonth();

        $driver = \DB::getDriverName();
        if ($driver === 'mysql') {
            $bulanExpr = "DATE_FORMAT(created_at, '%Y-%m')";
        } else {
            $bulanExpr = "strftime('%Y-%m', created_at)";
        }

        $pelamarData = Rekruitmen::selectRaw("
                $bulanExpr as bulan,
                COUNT(*) as total_pelamar,
                SUM(CASE WHEN status_terima = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status_terima = 'diterima' THEN 1 ELSE 0 END) as diterima,
                SUM(CASE WHEN status_terima = 'ditolak' THEN 1 ELSE 0 END) as ditolak
            ")
            ->where('created_at', '>=', $startDate)
            ->groupByRaw($bulanExpr)
            ->orderByRaw($bulanExpr)
            ->get();

        return [
            'labels'   => $pelamarData->pluck('bulan')->toArray(),
            'datasets' => [
                [
                    'label'           => 'Pending',
                    'data'            => $pelamarData->pluck('pending')->toArray(),
                    'backgroundColor' => 'rgba(255, 206, 86, 0.2)',
                    'borderColor'     => 'rgba(255, 206, 86, 1)',
                ],
                [
                    'label'           => 'Diterima',
                    'data'            => $pelamarData->pluck('diterima')->toArray(),
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor'     => 'rgba(75, 192, 192, 1)',
                ],
                [
                    'label'           => 'Ditolak',
                    'data'            => $pelamarData->pluck('ditolak')->toArray(),
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor'     => 'rgba(255, 99, 132, 1)',
                ],
            ],
            'raw_data' => $pelamarData->toArray(),
            'months'   => $months,
        ];
    }

    /**
     * Mendapatkan data terbaru
     */
    private function getRecentData(): array
    {
        return [
            'karyawan' => Karyawan::select('id', 'nomor_induk', 'nama_lengkap', 'posisi', 'tanggal_masuk', 'status_aktif')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(fn($k) => [
                    'id'            => $k->id,
                    'nomor_induk'   => $k->nomor_induk,
                    'nama_lengkap'  => $k->nama_lengkap,
                    'posisi'        => $k->posisi,
                    'tanggal_masuk' => $k->tanggal_masuk,
                    'status_aktif'  => $k->status_aktif,
                ])
                ->toArray(),

            'penggajian' => Penggajian::with('karyawan:id,nomor_induk,nama_lengkap')
                ->select('id', 'karyawan_id', 'gajian_bulan', 'upah_diterima', 'status_penggajian', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(fn($p) => [
                    'id'                => $p->id,
                    'nomor_induk'       => optional($p->karyawan)->nomor_induk ?? '-',
                    'nama_karyawan'     => optional($p->karyawan)->nama_lengkap ?? 'N/A',
                    'gajian_bulan'      => $p->gajian_bulan,
                    'upah_diterima'     => $p->upah_diterima,
                    'status_penggajian' => $p->status_penggajian,
                    'created_at'        => $p->created_at,
                ])
                ->toArray(),

            'rekruitmen' => Rekruitmen::with('lowonganKerja:id,posisi,lokasi_kerja')
                ->select('id', 'lowongan_kerja_id', 'nama', 'posisi_dilamar', 'status_terima', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(fn($r) => [
                    'id'             => $r->id,
                    'nama'           => $r->nama,
                    'posisi_dilamar' => $r->posisi_dilamar,
                    'status_terima'  => $r->status_terima,
                    'created_at'     => $r->created_at,
                    'lowongan_info'  => $r->lowonganKerja ? [
                        'posisi'       => $r->lowonganKerja->posisi,
                        'lokasi_kerja' => $r->lowonganKerja->lokasi_kerja,
                    ] : null,
                ])
                ->toArray(),

            'lowongan' => LowonganKerja::select('id', 'posisi', 'lokasi_kerja', 'deadline_lowongan', 'status_lowongan')
                ->where('status_lowongan', 'aktif')
                ->orderBy('deadline_lowongan', 'asc')
                ->limit(5)
                ->get()
                ->map(fn($l) => [
                    'id'                 => $l->id,
                    'posisi'             => $l->posisi,
                    'lokasi_kerja'       => $l->lokasi_kerja,
                    'deadline_lowongan'  => $l->deadline_lowongan,
                    'status_lowongan'    => $l->status_lowongan,
                ])
                ->toArray(),
        ];
    }
}
