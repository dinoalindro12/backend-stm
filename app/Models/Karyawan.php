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
        'admin_id',    // admin yang menambahkan
        'updated_by',  // admin yang terakhir mengubah
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
     * Admin yang pertama kali menambahkan karyawan ini
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Admin yang terakhir melakukan perubahan pada data karyawan ini
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Accessor untuk memformat URL gambar.
     * Mengembalikan full URL atau null jika tidak ada gambar.
     */
    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? url('/storage/' . $value) : null,
        );
    }

    /**
     * Auto-generate nomor_induk saat karyawan baru dibuat.
     * Format: KRY-001, KRY-002, dst.
     * CATATAN: harus dipanggil di dalam DB::transaction() agar lockForUpdate efektif.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $karyawan) {
            if (empty($karyawan->nomor_induk)) {
                $karyawan->nomor_induk = static::generateNomorInduk();
            }
        });

        static::deleting(function (self $karyawan) {
            // Set status_aktif menjadi false saat karyawan dihapus (soft delete)
            $karyawan->status_aktif = false;
            $karyawan->saveQuietly();
        });
    }

    /**
     * Generate nomor induk berikutnya.
     * lockForUpdate mencegah race condition — harus dipanggil dalam transaksi.
     */
    public static function generateNomorInduk(): string
    {
        $last = static::withTrashed()
            ->where('nomor_induk', 'like', 'KRY-%')
            ->orderByRaw("CAST(SUBSTR(nomor_induk, 5) AS UNSIGNED) DESC")
            ->lockForUpdate()
            ->first();

        $nextNumber = $last
            ? ((int) substr($last->nomor_induk, 4)) + 1  // ambil angka setelah "KRY-"
            : 1;

        return 'KRY-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
}
