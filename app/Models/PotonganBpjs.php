<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PotonganBpjs extends Model
{
    /**
     * Nama tabel yang terkait dengan model.
     *
     * @var string
     */
    protected $table = 'potongan_bpjs';

    /**
     * fillable
     *
     * @var array
     */
    protected $fillable = [
        'bpjs_kesehatan',
        'bpjs_jkk',
        'bpjs_jkm',
        'bpjs_jht',
        'bpjs_jp',
        'seragam_keamanan',
        'fee_manajemen',
        'total_potongan',
        'status_potongan',
    ];

    /**
     * Casting data
     *
     * @var array
     */
    protected $casts = [
        'bpjs_kesehatan' => 'decimal:2',
        'bpjs_jkk' => 'decimal:2',
        'bpjs_jkm' => 'decimal:2',
        'bpjs_jht' => 'decimal:2',
        'bpjs_jp' => 'decimal:2',
        'seragam_keamanan' => 'decimal:2',
        'fee_manajemen' => 'decimal:2',
        'total_potongan' => 'decimal:2',
        'status_potongan' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->calculateTotalPotongan();
        });

        static::updating(function ($model) {
            $model->calculateTotalPotongan();
        });
    }

    /**
     * Calculate total potongan
     */
    public function calculateTotalPotongan()
    {
        $this->total_potongan = 
            $this->bpjs_kesehatan +
            $this->bpjs_jkk +
            $this->bpjs_jkm +
            $this->bpjs_jht +
            $this->bpjs_jp +
            $this->seragam_keamanan +
            $this->fee_manajemen;
    }
}