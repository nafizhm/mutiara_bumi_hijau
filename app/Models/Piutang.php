<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Piutang extends Model
{
    use HasFactory;

    protected $table = 'piutang';

    public $timestamps = false;

    protected $fillable = [
        'id_invoice',
        'tanggal_piutang',
        'id_bank',
        'id_customer',
        'deskripsi',
        'nominal',
        'lampiran',
        'status',
        'terbayar',
        'sisa_bayar',
        'tgl_pelunasan',
        'id_kategori_transaksi',
    ];

    public function kategori()
    {
        return $this->belongsTo(KategoriTransaksi::class, 'id_kategori_transaksi');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'id_customer');
    }

    public function payments()
    {
        return $this->hasMany(Pemasukan::class, 'id_piutang');
    }

    public function scopeNonSales($query)
    {
        return $query->where(fn ($q) => $q->whereNull('id_customer')->orWhere('id_customer', 0));
    }
}
