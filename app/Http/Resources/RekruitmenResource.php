<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RekruitmenResource extends JsonResource
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
            'lowongan_kerja' => new LowonganKerjaResource($this->whenLoaded('lowonganKerja')),
            'nik' => $this->nik,
            'nama' => $this->nama,
            'nama_lengkap' => $this->nama_lengkap,
            'posisi_dilamar' => $this->posisi_dilamar,
            'no_wa' => $this->no_wa,
            'alamat' => $this->alamat,
            'foto_ktp' => $this->foto_ktp ? url('storage/' . $this->foto_ktp) : null,
            'foto_kk' => $this->foto_kk ? url('storage/' . $this->foto_kk) : null,
            'foto_skck' => $this->foto_skck ? url('storage/' . $this->foto_skck) : null,
            'pas_foto' => $this->pas_foto ? url('storage/' . $this->pas_foto) : null,
            'surat_sehat' => $this->surat_sehat ? url('storage/' . $this->surat_sehat) : null,
            'surat_anti_narkoba' => $this->surat_anti_narkoba ? url('storage/' . $this->surat_anti_narkoba) : null,
            'surat_lamaran' => $this->surat_lamaran ? url('storage/' . $this->surat_lamaran) : null,
            'cv' => $this->cv ? url('storage/' . $this->cv) : null,
            'token_pendaftaran' => $this->token_pendaftaran,
            'status_terima' => $this->status_terima,
            'catatan' => $this->catatan,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}