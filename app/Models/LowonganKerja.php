<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LowonganKerja extends Model
{
    use HasFactory;

    protected $table = 'lowongan_kerja';

    protected $fillable = [
        'posisi',
        'lokasi_kerja',
        'jenis_kerja',
        'catatan',
        'range_gaji',
        'deadline_lowongan',
        'status_lowongan',
    ];

    protected $casts = [
        'deadline_lowongan' => 'date',
    ];

    /**
     * Relasi dengan Rekruitmen
     */
    public function rekruitmen()
    {
        return $this->hasMany(Rekruitmen::class);
    }

    /**
     * Scope untuk lowongan aktif
     */
    public function scopeAktif($query)
    {
        return $query->where('status_lowongan', 'aktif')
                    ->where('deadline_lowongan', '>=', now());
    }
}