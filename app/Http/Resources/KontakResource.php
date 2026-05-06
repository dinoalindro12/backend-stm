<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KontakResource extends JsonResource
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
            'nama' => $this->nama,
            'no_wa'=> $this->no_wa,
            'email' => $this->email,
            'perusahaan' => $this->perusahaan,
            'subjek' => $this->subjek,
            'isi' => $this->isi,
            'status_dibaca' => $this->status_dibaca,
            'dibaca_pada' => $this->dibaca_pada?->format('Y-m-d H:i:s'),
            'dibaca_oleh' => $this->whenLoaded('admin', fn() => $this->admin ? [
                'id'    => $this->admin->id,
                'name'  => $this->admin->name,
                'email' => $this->admin->email,
            ] : null),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
        ];
    }
}
