<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'stats' => [
                'karyawan' => [
                    'total' => $this['stats']['karyawan']['total'] ?? 0,
                    'aktif' => $this['stats']['karyawan']['aktif'] ?? 0,
                    'non_aktif' => $this['stats']['karyawan']['non_aktif'] ?? 0,
                    'by_posisi' => $this['stats']['karyawan']['by_posisi'] ?? []
                ],
                'penggajian' => [
                    'bulan_ini' => [
                        'total' => $this['stats']['penggajian']['bulan_ini']['total'] ?? 0,
                        'jumlah' => $this['stats']['penggajian']['bulan_ini']['jumlah'] ?? 0
                    ],
                    'tahun_ini' => $this['stats']['penggajian']['tahun_ini'] ?? 0,
                    'belum_dibayar' => $this['stats']['penggajian']['belum_dibayar'] ?? 0,
                    'sudah_dibayar' => $this['stats']['penggajian']['sudah_dibayar'] ?? 0
                ],
                'tagihan' => [
                    'bulan_ini' => $this['stats']['tagihan']['bulan_ini'] ?? 0,
                    'tahun_ini' => $this['stats']['tagihan']['tahun_ini'] ?? 0
                ],
                'rekruitmen' => [
                    'total' => $this['stats']['rekruitmen']['total'] ?? 0,
                    'pending' => $this['stats']['rekruitmen']['pending'] ?? 0,
                    'diterima' => $this['stats']['rekruitmen']['diterima'] ?? 0,
                    'ditolak' => $this['stats']['rekruitmen']['ditolak'] ?? 0,
                    'by_posisi' => $this['stats']['rekruitmen']['by_posisi'] ?? []
                ],
                'lowongan' => [
                    'total' => $this['stats']['lowongan']['total'] ?? 0,
                    'aktif' => $this['stats']['lowongan']['aktif'] ?? 0,
                    'non_aktif' => $this['stats']['lowongan']['non_aktif'] ?? 0
                ],
                'kontak' => [
                    'total' => $this['stats']['kontak']['total'] ?? 0,
                    'belum_dibaca' => $this['stats']['kontak']['belum_dibaca'] ?? 0,
                    'sudah_dibaca' => $this['stats']['kontak']['sudah_dibaca'] ?? 0
                ],
                'timestamp' => $this['stats']['timestamp'] ?? now()->toDateTimeString()
            ],
            'summary' => [
                'karyawan_aktif' => $this['summary']['karyawan_aktif'] ?? 0,
                'total_gaji_bulan_ini' => $this['summary']['total_gaji_bulan_ini'] ?? 0,
                'total_tagihan_bulan_ini' => $this['summary']['total_tagihan_bulan_ini'] ?? 0,
                'pelamar_pending' => $this['summary']['pelamar_pending'] ?? 0,
                'lowongan_aktif' => $this['summary']['lowongan_aktif'] ?? 0,
                'pesan_belum_dibaca' => $this['summary']['pesan_belum_dibaca'] ?? 0
            ],
            'charts' => [
                'gaji' => $this['charts']['gaji'] ?? null,
                'pelamar' => $this['charts']['pelamar'] ?? null
            ],
            'recent_data' => [
                'karyawan' => $this['recent_data']['karyawan'] ?? [],
                'penggajian' => $this['recent_data']['penggajian'] ?? [],
                'rekruitmen' => $this['recent_data']['rekruitmen'] ?? [],
                'lowongan' => $this['recent_data']['lowongan'] ?? []
            ],
            'success' => $this['success'] ?? true,
            'message' => $this['message'] ?? 'Data dashboard berhasil diambil'
        ];
    }
}