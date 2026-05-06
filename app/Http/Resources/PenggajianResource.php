<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PenggajianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'gajian_bulan'            => $this->gajian_bulan?->format('Y-m-d'),
            'bulan_tahun'             => $this->bulan_tahun, // accessor dari model

            // Data karyawan via relasi
            'karyawan'                => $this->whenLoaded('karyawan', fn() => [
                'id'           => $this->karyawan?->id,
                'nomor_induk'  => $this->karyawan?->nomor_induk,
                'nik'          => $this->karyawan?->nik,
                'nama_lengkap' => $this->karyawan?->nama_lengkap,
                'posisi'       => $this->karyawan?->posisi,
                'no_rek_bri'   => $this->karyawan?->no_rek_bri,
                'no_wa'        => $this->karyawan?->no_wa,
            ]),

            // Input manual
            'jumlah_penghasilan_kotor' => $this->jumlah_penghasilan_kotor,
            'jumlah_hari_kerja'        => $this->jumlah_hari_kerja,
            'gaji_harian'              => $this->gaji_harian,
            'jumlah_lembur'            => $this->jumlah_lembur,
            'uang_thr'                 => $this->uang_thr,

            // Hasil kalkulasi otomatis
            'bpjs_kesehatan'           => $this->bpjs_kesehatan,
            'bpjs_jht'                 => $this->bpjs_jht,
            'bpjs_jp'                  => $this->bpjs_jp,
            'total_bpjs'               => $this->total_bpjs,
            'upah_kotor_karyawan'      => $this->upah_kotor_karyawan,
            'upah_diterima'            => $this->upah_diterima,

            // Status
            'status_penggajian'        => $this->status_penggajian,
            'tanggal_cetak'            => $this->tanggal_cetak?->format('Y-m-d'),

            // Info admin yang membuat
            'dibuat_oleh'              => $this->whenLoaded('admin', fn() => [
                'id'    => $this->admin?->id,
                'name'  => $this->admin?->name,
                'email' => $this->admin?->email,
            ]),

            // Info admin yang terakhir mengubah
            'diubah_oleh'              => $this->whenLoaded('updatedBy', fn() => [
                'id'          => $this->updatedBy?->id,
                'name'        => $this->updatedBy?->name,
                'email'       => $this->updatedBy?->email,
                'diubah_pada' => $this->updated_at?->format('Y-m-d H:i:s'),
            ]),

            'created_at'               => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'               => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function with(Request $request): array
    {
        return ['success' => true];
    }
}
