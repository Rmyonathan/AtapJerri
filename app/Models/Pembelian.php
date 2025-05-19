<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pembelian extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pembelian';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
    'nota',
    'tanggal',
    'kode_supplier',
    'pembayaran',
    'cara_bayar',
    'subtotal',
    'diskon',
    'ppn',
    'grand_total',
    'status',
    'canceled_by',
    'canceled_at',
    'cancel_reason'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tanggal' => 'date',
        'subtotal' => 'decimal:2',
        'diskon' => 'decimal:2',
        'ppn' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    /**
     * Get the supplier associated with the purchase.
     */
    public function supplierRelation()
    {
        return $this->belongsTo(Supplier::class, 'kode_supplier', 'kode_supplier');
    }
    public function stokOwner()
    {
        return $this->belongsTo(StokOwner::class, 'cabang', 'kode_stok_owner');
    }

    /**
     * Get the items for the purchase.
     */
    public function items()
    {
        return $this->hasMany(PembelianItem::class, 'nota', 'nota');
    }
}
