<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kontak extends Model
{
    /** @use HasFactory<\Database\Factories\KontakFactory> */
    use HasFactory;
    protected $table = 'kontaks';
    protected $fillable = [
        'nama',
        'email',
        'perusahaan',
        'subjek',
        'isi',
        'status_dibaca'
    ];
}
