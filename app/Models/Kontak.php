<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kontak extends Model
{
    /** @use HasFactory<\Database\Factories\KontakFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'kontaks';

    protected $fillable = [
        'nama',
        'email',
        'no_wa',
        'perusahaan',
        'subjek',
        'isi',
        'status_dibaca',
        'dibaca_pada',
        'admin_id',
    ];

    protected $casts = [
        'status_dibaca' => 'string',
        'dibaca_pada' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relasi ke User (Admin) yang membaca pesan ini
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Scope untuk pesan yang belum dibaca
     */
    public function scopePending($query)
    {
        return $query->where('status_dibaca', 'pending');
    }

    /**
     * Scope untuk pesan yang sudah dibaca
     */
    public function scopeDibaca($query)
    {
        return $query->where('status_dibaca', 'dibaca');
    }

    /**
     * Tandai pesan sebagai sudah dibaca oleh admin tertentu
     */
    public function tandaiDibaca(int $adminId): void
    {
        if ($this->status_dibaca === 'pending') {
            $this->update([
                'status_dibaca' => 'dibaca',
                'dibaca_pada' => now(),
                'admin_id' => $adminId,
            ]);
        }
    }
}
