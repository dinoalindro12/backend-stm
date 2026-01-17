<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rekruitmen extends Model
{
    use HasFactory;

    protected $table = 'rekruitmen';

    protected $fillable = [
        'lowongan_kerja_id',
        'nik',
        'nama',
        'nama_lengkap',
        'posisi_dilamar',
        'no_wa',
        'alamat',
        'foto_ktp',
        'foto_kk',
        'foto_skck',
        'pas_foto',
        'surat_sehat',
        'surat_anti_narkoba',
        'surat_lamaran',
        'cv',
        'token_pendaftaran',
        'status_terima',
        'catatan',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relasi dengan Lowongan Kerja
     */
    public function lowonganKerja()
    {
        return $this->belongsTo(LowonganKerja::class);
    }
}