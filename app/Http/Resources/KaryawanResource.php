<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KaryawanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'nomor_induk'    => $this->nomor_induk,
            'nik'            => $this->nik,
            'no_rek_bri'     => $this->no_rek_bri,
            'nama_lengkap'   => $this->nama_lengkap,
            'email'          => $this->email,
            'posisi'         => $this->posisi,
            'no_wa'          => $this->no_wa,
            'alamat'         => $this->alamat,
            'image'          => $this->image, // sudah full URL via accessor
            'tanggal_masuk'  => $this->tanggal_masuk?->format('Y-m-d'),
            'tanggal_keluar' => $this->tanggal_keluar?->format('Y-m-d'),
            'status_aktif'   => $this->status_aktif,

            // Info admin yang menambahkan
            'ditambahkan_oleh' => $this->whenLoaded('admin', fn() => [
                'id'    => $this->admin?->id,
                'name'  => $this->admin?->name,
                'email' => $this->admin?->email,
            ]),

            // Info admin yang terakhir mengubah
            'diubah_oleh' => $this->whenLoaded('updatedBy', fn() => [
                'id'         => $this->updatedBy?->id,
                'name'       => $this->updatedBy?->name,
                'email'      => $this->updatedBy?->email,
                'diubah_pada' => $this->updated_at?->format('Y-m-d H:i:s'),
            ]),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Tambahkan wrapper success ke semua response
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
        ];
    }
}
