<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProspekNasabah extends Model
{
    protected $table = 'prospek_nasabah';
    protected $fillable = [
        'tgl_terima',
        'nama_lengkap',
        'no_wa',
        'usia',
        'pekerjaan',
        'penghasilan',
        'sumber_informasi',
        'rangking',
        'id_marketing',
        'keterangan_belum',
        'no_ktp',
        'no_telp',
        'email',
        'stt_delete',
    ];

    public function marketing()
    {
        return $this->belongsTo(Marketing::class, 'id_marketing');
    }

    public $timestamps = false;
}
