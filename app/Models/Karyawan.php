<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Karyawan extends Model
{
    use SoftDeletes;

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
        'admin_id',
        'updated_by',
    ];

    protected $casts = [
        'tanggal_masuk' => 'date',
        'tanggal_keluar' => 'date',
        'status_aktif' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ========== RELASI ==========

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id')->withTrashed();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    // ========== ACCESSORS ==========

    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? url('/storage/' . $value) : null,
        );
    }

    // ========== SCOPES ==========

    /**
     * Scope untuk karyawan aktif
     */
    public function scopeAktif($query)
    {
        return $query->where('status_aktif', true);
    }

    /**
     * Scope untuk filter posisi
     */
    public function scopeFilterByPosisi($query, $posisi)
    {
        return $query->where('posisi', $posisi);
    }

    /**
     * Scope untuk pencarian cepat (5 field untuk hasil maksimal)
     */
    public function scopeSearch($query, $keyword)
    {
        return $query->where(function($q) use ($keyword) {
            $q->where('nama_lengkap', 'LIKE', "%{$keyword}%")
              ->orWhere('nomor_induk', 'LIKE', "%{$keyword}%")
              ->orWhere('nik', 'LIKE', "%{$keyword}%")
              ->orWhere('email', 'LIKE', "%{$keyword}%")
              ->orWhere('no_wa', 'LIKE', "%{$keyword}%");
        });
    }

    // ========== BOOT ==========

    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (self $karyawan) {
            $karyawan->status_aktif = false;
            $karyawan->saveQuietly();
        });
    }
}