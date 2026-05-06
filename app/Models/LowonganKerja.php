<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LowonganKerja extends Model
{
    use HasFactory, SoftDeletes;

    // Menentukan nama tabel secara eksplisit karena menggunakan snake_case
    protected $table = 'lowongan_kerja';

    /**
     * fillable
     *
     * @var array
     */
    protected $fillable = [
        'posisi',
        'lokasi_kerja',
        'jenis_kerja',
        'catatan',
        'range_gaji',
        'deadline_lowongan',
        'status_lowongan',
        'admin_id',
    ];

    /**
     * casts
     *
     * @var array
     */
    protected $casts = [
        'deadline_lowongan' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relasi dengan Admin (User)
     * Lowongan kerja diposting oleh seorang Admin
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Relasi dengan Rekruitmen
     * Satu lowongan bisa memiliki banyak pelamar (rekruitmen)
     */
    public function rekruitmen(): HasMany
    {
        // Sesuaikan 'lowongan_kerja_id' dengan nama foreign key di tabel rekruitmen Anda
        return $this->hasMany(Rekruitmen::class, 'lowongan_kerja_id');
    }

    /**
     * Scope untuk memfilter lowongan yang masih aktif
     */
    public function scopeAktif($query)
    {
        return $query->where('status_lowongan', 'aktif')
                    ->where('deadline_lowongan', '>=', now()->toDateString());
    }
}