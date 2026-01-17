<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatusTerimaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token_pendaftaran' => $this->token_pendaftaran,
            'nama_lengkap' => $this->nama_lengkap,
            'status_terima' => $this->status_terima,
            'catatan' => $this->catatan,
        ];
    }
}