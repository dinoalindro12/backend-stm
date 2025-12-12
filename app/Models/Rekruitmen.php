<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rekruitmen extends Model
{
    protected $table = 'rekruitmen';

    protected $primaryKey = 'id';

    protected $fillable = [
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
        'catatan'
    ];

    protected $casts = [
        'status_terima' => 'string'
    ];
}