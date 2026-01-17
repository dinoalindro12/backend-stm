<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LowonganKerjaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'posisi' => $this->posisi,
            'lokasi_kerja' => $this->lokasi_kerja,
            'jenis_kerja' => $this->jenis_kerja,
            'catatan' => $this->catatan,
            'range_gaji' => $this->range_gaji,
            'deadline_lowongan' => $this->deadline_lowongan->format('Y-m-d'),
            'status_lowongan' => $this->status_lowongan,
            'jumlah_pelamar' => $this->rekruitmen()->count(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
    
}