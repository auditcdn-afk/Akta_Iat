<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalisaUpload extends Model
{
    protected $table = 'analisa_uploads';

    protected $fillable = [
        'jenis', 'unit_usaha_code', 'tanggal', 'source_hash',
        'source_filename', 'row_count', 'uploaded_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];
}
