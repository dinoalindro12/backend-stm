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
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ========== BOOT METHOD ==========

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($penggajian) {
            if (empty($penggajian->gajian_bulan)) {
                $penggajian->gajian_bulan = Carbon::now()->startOfMonth();
            }
        });

        static::saving(function ($penggajian) {
            // Hitung BPJS otomatis (1% Kesehatan, 2% JHT, 1% JP)
            $penggajian->bpjs_kesehatan = $penggajian->jumlah_penghasilan_kotor * 0.01;
            $penggajian->bpjs_jht = $penggajian->jumlah_penghasilan_kotor * 0.02;
            $penggajian->bpjs_jp = $penggajian->jumlah_penghasilan_kotor * 0.01;
            
            // Aturan khusus: Jika hari kerja < 7, BPJS 0
            if ($penggajian->jumlah_hari_kerja < 7) {
                $penggajian->bpjs_kesehatan = 0;
                $penggajian->bpjs_jht = 0;
                $penggajian->bpjs_jp = 0;
            }
            
            $penggajian->total_bpjs = 
                $penggajian->bpjs_kesehatan + 
                $penggajian->bpjs_jht + 
                $penggajian->bpjs_jp;

            // Hitung Upah Kotor Karyawan
            $penggajian->upah_kotor_karyawan = 
                ($penggajian->gaji_harian * $penggajian->jumlah_hari_kerja) + 
                $penggajian->jumlah_lembur + 
                ($penggajian->uang_thr ?? 0);

            // Hitung Upah Netto (Diterima)
            $penggajian->upah_diterima = 
                $penggajian->upah_kotor_karyawan - 
                $penggajian->total_bpjs;
        });
    }

    // ========== ACCESSORS ==========

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
}