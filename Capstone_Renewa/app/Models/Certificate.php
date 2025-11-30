<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Pastikan hanya BelongsTo yang di-import untuk relasi ini

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'energy_report_id', 'issuer_id', 'owner_id', 'certificate_uid',
        'amount_mwh', 'generation_start_date', 'generation_end_date',
        'status', 'order_id',
    ];

    protected $casts = [
        'generation_start_date' => 'date',
        'generation_end_date' => 'date',
    ];

    public function energyReport(): BelongsTo
    {
        return $this->belongsTo(EnergyReport::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issuer_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}