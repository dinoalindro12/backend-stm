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
        return $this->belongsTo(User::class, 'admin_id')->withTrashed();
    }

    /**
     * Admin yang terakhir melakukan perubahan pada data karyawan ini
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
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
     * Auto-set status_aktif menjadi false saat karyawan dihapus (soft delete).
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (self $karyawan) {
            $karyawan->status_aktif = false;
            $karyawan->saveQuietly();
        });
    }
}
