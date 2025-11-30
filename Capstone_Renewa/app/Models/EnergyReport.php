<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnergyReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'power_plant_id',
        'reporting_period_start',
        'reporting_period_end',
        'amount_mwh',
        'proof_documentation_url',
        'status',
        'rejection_reason',
        // ⭐ BLOCKCHAIN FIELDS - PENTING untuk REC workflow
        'blockchain_energy_id',
        'blockchain_status', 
        'blockchain_response',
        'blockchain_verification_status',
        'blockchain_verification_response',
        'blockchain_verification_error',
        'blockchain_error',
        'supporting_document_path',
        // ⭐ NEW CERTIFICATE FIELDS
        'certificate_requested',
        'certificate_id',
        'certificate_status',
        'certificate_response',
        'certificate_requested_at',
    ];

    /**
     * KUNCI PERBAIKAN:
     * Menambahkan created_at dan updated_at ke dalam casts yang sudah ada.
     *
     * @var array
     */
    protected $casts = [
        'reporting_period_start' => 'date',
        'reporting_period_end' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'certificate_requested_at' => 'datetime',
        'certificate_requested' => 'boolean',
        'certificate_response' => 'array',
        'blockchain_response' => 'array',
        'blockchain_verification_response' => 'array',
    ];

    public function powerPlant()
    {
        return $this->belongsTo(PowerPlant::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }
}