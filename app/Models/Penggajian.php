<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Penggajian extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'penggajian';

    protected $fillable = [
        'no_induk',
        'nik',
        'nama',
        'no_rek_bri',
        'posisi',
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
        'periode_awal',
        'periode_akhir',
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
        'periode_awal' => 'date',
        'periode_akhir' => 'date',
        'tanggal_cetak' => 'date'
    ];

    // Relasi ke Karyawan
    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'no_induk', 'nomor_induk');
    }

    // Mutator untuk perhitungan otomatis
    protected static function boot()
{
    parent::boot();

    static::saving(function ($penggajian) {
        // Cek jika jumlah hari kerja kurang dari 7
        if ($penggajian->jumlah_hari_kerja < 7) {
            // Set semua BPJS ke 0
            $penggajian->bpjs_kesehatan = 0;
            $penggajian->bpjs_jht = 0;
            $penggajian->bpjs_jp = 0;
        }

        // Hitung Total BPJS
        $penggajian->total_bpjs = 
            $penggajian->bpjs_kesehatan + 
            $penggajian->bpjs_jht + 
            $penggajian->bpjs_jp;

        // Hitung Jumlah Penghasilan Kotor
        // (Gaji Harian * Jumlah Hari Kerja) + Lembur + THR
        $penggajian->upah_kotor_karyawan = 
            ($penggajian->gaji_harian * $penggajian->jumlah_hari_kerja) + 
            $penggajian->jumlah_lembur + 
            ($penggajian->uang_thr ?? 0);

        // Hitung Upah Kotor Karyawan (sama dengan jumlah penghasilan kotor)


        // Hitung Upah Diterima (Upah Kotor - Total BPJS)
        $penggajian->upah_diterima = 
            $penggajian->upah_kotor_karyawan - 
            $penggajian->total_bpjs;
    });
}
    // ========== SCOPES BARU ==========
    
    /**
     * Scope untuk filter berdasarkan bulan gajian
     * @param int $bulan 1-12
     */
    public function scopeBulanGajian($query, $bulan)
    {
        return $query->whereMonth('gajian_bulan', $bulan);
    }
    
    /**
     * Scope untuk filter berdasarkan tahun gajian
     */
    public function scopeTahunGajian($query, $tahun)
    {
        return $query->whereYear('gajian_bulan', $tahun);
    }
    
    /**
     * Scope untuk filter berdasarkan bulan dan tahun
     */
    public function scopeBulanTahunGajian($query, $bulan, $tahun)
    {
        return $query->whereMonth('gajian_bulan', $bulan)
                    ->whereYear('gajian_bulan', $tahun);
    }

    // ========== ACCESSORS ==========
    
    /**
     * Ambil bulan dari gajian_bulan
     */
    public function getBulanAttribute()
    {
        return $this->gajian_bulan ? date('n', strtotime($this->gajian_bulan)) : null;
    }
    
    /**
     * Ambil tahun dari gajian_bulan
     */
    public function getTahunAttribute()
    {
        return $this->gajian_bulan ? date('Y', strtotime($this->gajian_bulan)) : null;
    }
    
    /**
     * Ambil nama bulan dari gajian_bulan
     */
    public function getNamaBulanAttribute()
    {
        if (!$this->gajian_bulan) return null;
        
        $bulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        $bulanAngka = date('n', strtotime($this->gajian_bulan));
        return $bulan[$bulanAngka] ?? null;
    }


    // Scope untuk filter berdasarkan periode
    public function scopePeriode($query, $start, $end)
    {
        return $query->whereBetween('gajian_bulan', [$start, $end]);
    }

    // Scope untuk filter status penggajian
    public function scopeStatus($query, $status)
    {
        return $query->where('status_penggajian', $status);
    }

    // Scope untuk filter berdasarkan posisi
    public function scopePosisi($query, $posisi)
    {
        return $query->where('posisi', $posisi);
    }
}