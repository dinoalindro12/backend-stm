<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Karyawan extends Model
{
    /**
     * fillable
     *
     * @var array
     */
    protected $fillable = [
        'nomor_induk',
        'nik',
        'no_rek_bri',
        'nama_lengkap',
        'email',
        'posisi',
        'no_wa',
        'alamat',
        'image',
        'tanggal_masuk',
        'tanggal_keluar',
        'status_aktif',
    ];
    protected $casts = [
        'tanggal_masuk' => 'date',
        'tanggal_keluar' => 'date',
        'status_aktif' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * image
     *
     * @return Attribute
     */
    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn ($image) => $image ? url('/storage/karyawans/' . $image) : null,
        );
    }
}