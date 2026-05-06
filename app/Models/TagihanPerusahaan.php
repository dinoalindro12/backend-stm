<?php

namespace App\Models;

use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class TagihanPerusahaan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tagihan_perusahaan';

    protected $fillable = [
        'karyawan_id',
        'admin_id',
        'updated_by',
        'jumlah_penghasilan_kotor',
        'bpjs_kesehatan',
        'jkk',
        'jkm',
        'jht',
        'jp',
        'seragam_cs_dan_keamanan',
        'fee_manajemen',
        'thr',
        'jumlah_hari_kerja',
        'gaji_harian',
        'jlh_lembur',
        'upah_diterima_pekerja',
        'upah_total',
        'tagihan_bulan',
    ];

    protected $casts = [
        'jumlah_penghasilan_kotor' => 'decimal:2',
        'bpjs_kesehatan' => 'decimal:2',
        'jkk' => 'decimal:2',
        'jkm' => 'decimal:2',
        'jht' => 'decimal:2',
        'jp' => 'decimal:2',
        'seragam_cs_dan_keamanan' => 'decimal:2',
        'fee_manajemen' => 'decimal:2',
        'thr' => 'decimal:2',
        'jumlah_hari_kerja' => 'decimal:2',
        'gaji_harian' => 'decimal:2',
        'jlh_lembur' => 'decimal:2',
        'upah_diterima_pekerja' => 'decimal:2',
        'upah_total' => 'decimal:2',
        'tagihan_bulan' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relasi ke karyawan — include soft-deleted agar data historis tagihan tetap terbaca
     */
    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id')->withTrashed();
    }

    /**
     * Relasi ke Admin (User)
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Admin yang terakhir mengubah tagihan
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Boot method untuk auto-calculate
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tagihan) {
            if (empty($tagihan->tagihan_bulan)) {
                $tagihan->tagihan_bulan = Carbon::now()->startOfMonth();
            }
        });

        static::saving(function ($tagihan) {
            // 1. Hitung Komponen BPJS (Persentase Perusahaan)
            // Sesuaikan persentase ini dengan kebijakan terbaru perusahaan Anda
            $tagihan->bpjs_kesehatan = $tagihan->jumlah_penghasilan_kotor * 0.04; 
            $tagihan->jht = $tagihan->jumlah_penghasilan_kotor * 0.037;
            $tagihan->jp = $tagihan->jumlah_penghasilan_kotor * 0.02;
            $tagihan->jkk = $tagihan->jumlah_penghasilan_kotor * 0.0024;
            $tagihan->jkm = $tagihan->jumlah_penghasilan_kotor * 0.003;

            // 2. Hitung Dasar Upah Pekerja (Gaji + Lembur + THR)
            $upah_dasar = ($tagihan->gaji_harian * $tagihan->jumlah_hari_kerja) + 
                        ($tagihan->jlh_lembur ?? 0) + 
                        ($tagihan->thr ?? 0);

            // 3. Kondisi Khusus Hari Kerja
            if ($tagihan->jumlah_hari_kerja < 7) {
                $tagihan->bpjs_kesehatan = 0;
                $tagihan->jht = 0;
                $tagihan->jp = 0;
                $tagihan->jkk = 0;
                $tagihan->jkm = 0;
                $tagihan->seragam_cs_dan_keamanan = 0;
                $tagihan->fee_manajemen = 0;
                $tagihan->upah_diterima_pekerja = $upah_dasar;
                $tagihan->upah_total = $upah_dasar;
            } else {
                // Total Biaya Tambahan Perusahaan
                $total_iuran_perusahaan = 
                    $tagihan->bpjs_kesehatan + $tagihan->jkk + $tagihan->jkm + 
                    $tagihan->jht + $tagihan->jp + 
                    ($tagihan->seragam_cs_dan_keamanan ?? 0) + 
                    ($tagihan->fee_manajemen ?? 0);
                
                // Upah yang dikirim ke rekening pekerja (setelah dikurangi biaya tertentu jika ada)
                // Di sini contohnya dikurangi 1 kali gaji harian sebagai admin fee/simpanan (sesuaikan logic Anda)
                $tagihan->upah_diterima_pekerja = $upah_dasar - $tagihan->gaji_harian - $tagihan->total_iuran_perusahaan; // Contoh pengurangan gaji harian sebagai biaya tambahan, sesuaikan dengan kebutuhan Anda

                // Total Tagihan ke Perusahaan (Upah Dasar + Iuran/Fee)
                $tagihan->upah_total = $tagihan->upah_diterima_pekerja + $total_iuran_perusahaan + $tagihan->gaji_harian; // Tambahkan gaji harian sebagai biaya tambahan jika diperlukan
            }
        });
    }

    // ========== ACCESSORS ==========

    public function getNamaBulanAttribute()
    {
        if (!$this->tagihan_bulan) return null;
        $bulanIndo = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        return $bulanIndo[(int)$this->tagihan_bulan->format('n')] ?? null;
    }

    public function getBulanTahunAttribute()
    {
        if (!$this->tagihan_bulan) return null;
        return $this->nama_bulan . ' ' . $this->tagihan_bulan->format('Y');
    }

    // ========== SCOPES ==========

    public function scopeBulanTahun($query, $bulan, $tahun)
    {
        return $query->whereMonth('tagihan_bulan', $bulan)
                    ->whereYear('tagihan_bulan', $tahun);
    }

    public function scopeBulanTahunTagihan($query, $bulan, $tahun)
    {
        return $query->whereMonth('tagihan_bulan', $bulan)
                    ->whereYear('tagihan_bulan', $tahun);
    }

    public function scopeBulanTagihan($query, $bulan)
    {
        return $query->whereMonth('tagihan_bulan', $bulan);
    }

    public function scopeTahunTagihan($query, $tahun)
    {
        return $query->whereYear('tagihan_bulan', $tahun);
    }

    public function scopePeriode($query, $awal, $akhir)
    {
        return $query->whereBetween('tagihan_bulan', [$awal, $akhir]);
    }

    /**
     * Filter berdasarkan posisi karyawan via relasi
     */
    public function scopePosisi($query, $posisi)
    {
        return $query->whereHas('karyawan', fn($q) => $q->where('posisi', $posisi));
    }

    /**
     * Filter berdasarkan nomor induk karyawan
     */
    public function scopeKaryawan($query, $nomorInduk)
    {
        return $query->whereHas('karyawan', fn($q) => $q->where('nomor_induk', $nomorInduk));
    }

    /**
     * Filter berdasarkan NIK karyawan
     */
    public function scopeNik($query, $nik)
    {
        return $query->whereHas('karyawan', fn($q) => $q->where('nik', $nik));
    }
}