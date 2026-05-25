<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rekruitmen extends Model
{
    use HasFactory, SoftDeletes;

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
        'admin_id', 
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relasi dengan Admin (User)
     * Rekruitmen dikelola oleh seorang Admin
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id')->withTrashed();
    }

    /**
     * Relasi dengan Lowongan Kerja
     * (Pastikan kolom lowongan_kerja_id ada di tabel rekruitmen)
     */
    public function lowonganKerja(): BelongsTo
    {
        return $this->belongsTo(LowonganKerja::class, 'lowongan_kerja_id');
    }
}