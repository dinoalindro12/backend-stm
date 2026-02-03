<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TagihanPerusahaan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tagihan_perusahaan';

    protected $fillable = [
        'no_induk',
        'nik',
        'nama',
        'no_rek_bri',
        'posisi',
        'jumlah_hari_kerja',
        'gaji_harian',
        'lembur',
        'thr',
        'bpjs_kesehatan',
        'jkk',
        'jkm',
        'jht',
        'jp',
        'seragam_cs_dan_keamanan',
        'fee_manajemen',
        'jumlah_iuran_bpjs',
        'upa_pekerja',
        'upah_yang_diterima_pekerja',
        'total_diterima',
        'periode_awal',
        'periode_akhir',
        'tanggal_cetak',
    ];

    protected $casts = [
        'jumlah_hari_kerja' => 'decimal:2',
        'gaji_harian' => 'decimal:2',
        'lembur' => 'decimal:2',
        'thr' => 'decimal:2',
        'bpjs_kesehatan' => 'decimal:2',
        'jkk' => 'decimal:2',
        'jkm' => 'decimal:2',
        'jht' => 'decimal:2',
        'jp' => 'decimal:2',
        'seragam_cs_dan_keamanan' => 'decimal:2',
        'fee_manajemen' => 'decimal:2',
        'jumlah_iuran_bpjs' => 'decimal:2',
        'upa_pekerja' => 'decimal:2',
        'upah_yang_diterima_pekerja' => 'decimal:2',
        'total_diterima' => 'decimal:2',
        'periode_awal' => 'date',
        'periode_akhir' => 'date',
        'tanggal_cetak' => 'datetime',
    ];

    /**
     * Relasi ke Karyawan
     */
    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'no_induk', 'nomor_induk');
    }

    /**
     * Boot method untuk auto-calculate
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($tagihan) {
            
            // Hitung iuran dan bpjs
            $tagihan->jumlah_iuran_bpjs = 
                $tagihan->bpjs_kesehatan + 
                $tagihan->jkk + 
                $tagihan->jkm + 
                $tagihan->jht + 
                $tagihan->jp + 
                $tagihan->seragam_cs_dan_keamanan + 
                $tagihan->fee_manajemen;
            // Hitung upa yang diterima pekerja
            $tagihan->upa_pekerja = 
            ($tagihan->gaji_harian * $tagihan->jumlah_hari_kerja) + 
            ($tagihan->lembur ?? 0) + 
            ($tagihan->thr ?? 0);
            // gaji yang diterima pekerja
            $tagihan->upah_yang_diterima_pekerja = 
                $tagihan->upa_pekerja - 149316;
            
                // Hitung total tagihan
            $tagihan->total_diterima = 
            $tagihan->upa_pekerja + 
            $tagihan->jumlah_iuran_bpjs;
        });
    }
    
    // ========== SCOPES BARU ==========

/**
 * Scope untuk filter berdasarkan bulan tagihan
 * @param int $bulan 1-12
 */
public function scopeBulanTagihan($query, $bulan)
{
    return $query->whereMonth('periode_awal', $bulan);
}

/**
 * Scope untuk filter berdasarkan tahun tagihan
 */
public function scopeTahunTagihan($query, $tahun)
{
    return $query->whereYear('periode_awal', $tahun);
}

/**
 * Scope untuk filter berdasarkan bulan dan tahun tagihan
 */
public function scopeBulanTahunTagihan($query, $bulan, $tahun)
{
    return $query->whereMonth('periode_awal', $bulan)
                ->whereYear('periode_awal', $tahun);
}

/**
 * Scope untuk filter berdasarkan status tagihan (jika ada field status)
 */
public function scopeStatus($query, $status)
{
    // Jika ada field status, sesuaikan dengan nama field yang benar
    // return $query->where('status_tagihan', $status);
    return $query; // sementara return query as-is jika tidak ada field status
}

// ========== ACCESSORS ==========

/**
 * Ambil bulan dari periode_awal
 */
public function getBulanAttribute()
{
    return $this->periode_awal ? date('n', strtotime($this->periode_awal)) : null;
}

/**
 * Ambil tahun dari periode_awal
 */
public function getTahunAttribute()
{
    return $this->periode_awal ? date('Y', strtotime($this->periode_awal)) : null;
}

/**
 * Ambil nama bulan dari periode_awal
 */
public function getNamaBulanAttribute()
{
    if (!$this->periode_awal) return null;
    
    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $bulanAngka = date('n', strtotime($this->periode_awal));
    return $bulan[$bulanAngka] ?? null;
}

/**
 * Format tanggal cetak
 */
public function getTanggalCetakFormattedAttribute()
{
    return $this->tanggal_cetak ? date('d/m/Y', strtotime($this->tanggal_cetak)) : '-';
}

/**
 * Format periode (dari - sampai)
 */
public function getPeriodeFormattedAttribute()
{
    if (!$this->periode_awal || !$this->periode_akhir) return '-';
    
    $awal = date('d/m/Y', strtotime($this->periode_awal));
    $akhir = date('d/m/Y', strtotime($this->periode_akhir));
    return $awal . ' - ' . $akhir;
}

/**
 * Getter untuk total gaji harian (jumlah_hari_kerja * gaji_harian)
 */
public function getTotalGajiHarianAttribute()
{
    return $this->jumlah_hari_kerja * $this->gaji_harian;
}

/**
 * Getter untuk total penghasilan kotor (upa_pekerja + jumlah_iuran_bpjs)
 */
public function getTotalPenghasilanKotorAttribute()
{
    return ($this->upa_pekerja ?? 0) + ($this->jumlah_iuran_bpjs ?? 0);
}

/**
 * Setter untuk memastikan nilai null diubah ke 0
 */
public function setBpjsKesehatanAttribute($value)
{
    $this->attributes['bpjs_kesehatan'] = $value ?: 0;
}

public function setJkkAttribute($value)
{
    $this->attributes['jkk'] = $value ?: 0;
}

public function setJkmAttribute($value)
{
    $this->attributes['jkm'] = $value ?: 0;
}

public function setJhtAttribute($value)
{
    $this->attributes['jht'] = $value ?: 0;
}

public function setJpAttribute($value)
{
    $this->attributes['jp'] = $value ?: 0;
}

public function setSeragamCsDanKeamananAttribute($value)
{
    $this->attributes['seragam_cs_dan_keamanan'] = $value ?: 0;
}

public function setFeeManajemenAttribute($value)
{
    $this->attributes['fee_manajemen'] = $value ?: 0;
}

public function setLemburAttribute($value)
{
    $this->attributes['lembur'] = $value ?: 0;
}

public function setThrAttribute($value)
{
    $this->attributes['thr'] = $value ?: 0;
}

/**
 * Scope untuk filter berdasarkan periode (existing - tetap dipertahankan)
 */
public function scopePeriode($query, $periodeAwal, $periodeAkhir)
{
    return $query->where('periode_awal', '>=', $periodeAwal)
                ->where('periode_akhir', '<=', $periodeAkhir);
}

/**
 * Scope untuk filter berdasarkan posisi (existing - tetap dipertahankan)
 */
public function scopePosisi($query, $posisi)
{
    return $query->where('posisi', $posisi);
}

/**
 * Scope untuk filter berdasarkan karyawan (no_induk)
 */
public function scopeKaryawan($query, $noInduk)
{
    return $query->where('no_induk', $noInduk);
}

/**
 * Scope untuk filter berdasarkan NIK
 */
public function scopeNik($query, $nik)
{
    return $query->where('nik', $nik);
}
}