<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough; // <-- PENTING: Tambahkan import ini

class PowerPlant extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'energy_type',
        'capacity',
        'operational_permit_path',
        'image_url',
    ];

    /**
     * Relasi ke User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke EnergyReport.
     */
    public function energyReports(): HasMany
    {
        return $this->hasMany(EnergyReport::class);
    }

    /**
     * Relasi "jalan pintas" ke Certificate melalui EnergyReport.
     * Sebuah PowerPlant memiliki banyak Sertifikat melalui Laporan Energi.
     */
    public function certificates(): HasManyThrough
    {
        return $this->hasManyThrough(Certificate::class, EnergyReport::class);
    }
}
