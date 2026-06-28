<?php

namespace App\Models;

use App\Models\Karyawan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Penggajian extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'penggajian';

    protected $fillable = [
        'karyawan_id',
        'admin_id',
        'updated_by',
        'jumlah_penghasilan_kotor',
        'bpjs_kesehatan',
        'bpjs_jht',
        'bpjs_jp',
        'total_bpjs',
        'uang_thr',
        'jumlah_hari_kerja',
        'gaji_harian',
        'jumlah_lembur',
        'upah_kotor_karyawan',
        'upah_diterima',
        'gajian_bulan',
        'status_penggajian',
        'tanggal_cetak'
    ];

    protected $casts = [
        'jumlah_penghasilan_kotor' => 'decimal:2',
        'bpjs_kesehatan' => 'decimal:2',
        'bpjs_jht' => 'decimal:2',
        'bpjs_jp' => 'decimal:2',
        'total_bpjs' => 'decimal:2',
        'uang_thr' => 'decimal:2',
        'jumlah_hari_kerja' => 'decimal:2',
        'gaji_harian' => 'decimal:2',
        'jumlah_lembur' => 'decimal:2',
        'upah_kotor_karyawan' => 'decimal:2',
        'upah_diterima' => 'decimal:2',
        'gajian_bulan' => 'date',
        'status_penggajian' => 'boolean',
        'tanggal_cetak' => 'date', // Di migrasi menggunakan date
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ========== RELASI ==========

    /**
     * Relasi ke karyawan — include soft-deleted agar data historis penggajian tetap terbaca
     */
    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id')->withTrashed();
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id')->withTrashed();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    // ========== boot ==========

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($penggajian) {
            if (empty($penggajian->gajian_bulan)) {
                $penggajian->gajian_bulan = Carbon::now()->startOfMonth();
            }
        });
    }

    // ========== getter nama bulan ==========

    public function getNamaBulanAttribute()
    {
        if (!$this->gajian_bulan) return null;
        
        $bulanIndo = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        return $bulanIndo[$this->gajian_bulan->format('n')] ?? null;
    }

    public function getBulanTahunAttribute()
    {
        if (!$this->gajian_bulan) return null;
        return $this->nama_bulan . ' ' . $this->gajian_bulan->format('Y');
    }

    // ========== SCOPES ==========

    public function scopeBulanTahunGajian($query, $bulan, $tahun)
    {
        return $query->whereMonth('gajian_bulan', $bulan)
                    ->whereYear('gajian_bulan', $tahun);
    }

    public function scopePosisi($query, $posisi)
    {
        return $query->whereHas('karyawan', fn($q) => $q->where('posisi', $posisi));
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status_penggajian', $status);
    }

    public function scopeSudahCetak($query)
    {
        return $query->whereNotNull('tanggal_cetak');
    }

    public function scopeBelumCetak($query)
    {
        return $query->whereNull('tanggal_cetak');
    }
    // ========== SCOPES UNTUK INDEX ==========

/**
 * Scope untuk filter posisi (digunakan di index)
 */
public function scopeFilterByPosisi($query, $posisi)
{
    return $query->where('posisi', $posisi);
}

/**
 * Scope untuk pencarian cepat (digunakan di index)
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
}