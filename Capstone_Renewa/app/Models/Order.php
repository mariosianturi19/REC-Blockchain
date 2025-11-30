<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    /**
     * Status constants for REC workflow
     */
    const STATUS_PENDING = 'pending';
    const STATUS_VERIFIED = 'verified';
    const STATUS_REQUESTED = 'requested';
    const STATUS_ISSUED = 'issued';
    const STATUS_PURCHASE_REQUESTED = 'purchase_requested';
    const STATUS_PURCHASED = 'purchased';
    
    // Legacy status untuk backward compatibility
    const STATUS_PENDING_PAYMENT = 'pending_payment';
    const STATUS_AWAITING_CONFIRMATION = 'awaiting_confirmation';
    const STATUS_COMPLETED = 'completed';

    /**
     * Atribut yang boleh diisi secara massal.
     *
     * @var array
     */
    protected $fillable = [
        'order_uid',
        'buyer_id',
        'total_price',
        'virtual_account_number',
        'status',
        'category',
        'payment_verified_at',
    ];

    /**
     * Memberitahu Laravel untuk selalu memperlakukan kolom-kolom ini
     * sebagai objek tanggal (Carbon), yang mencegah error format().
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'payment_confirmed_at' => 'datetime',
        'payment_verified_at' => 'datetime',
    ];

    /**
     * Get blockchain status based on certificates
     */
    public function getBlockchainStatusAttribute()
    {
        // Jika tidak ada certificate, return status order
        if ($this->certificates->isEmpty()) {
            return $this->mapLegacyStatusToBlockchain($this->status);
        }

        // Ambil status dari certificate yang paling advanced
        $latestCertificate = $this->certificates()
            ->whereNotNull('blockchain_status')
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($latestCertificate && $latestCertificate->blockchain_status) {
            return $this->mapBlockchainStatus($latestCertificate->blockchain_status);
        }

        return $this->mapLegacyStatusToBlockchain($this->status);
    }

    /**
     * Map legacy status to blockchain status
     */
    private function mapLegacyStatusToBlockchain($legacyStatus)
    {
        $mapping = [
            self::STATUS_PENDING_PAYMENT => self::STATUS_ISSUED,
            self::STATUS_AWAITING_CONFIRMATION => self::STATUS_PURCHASE_REQUESTED,
            self::STATUS_COMPLETED => self::STATUS_PURCHASED,
        ];

        return $mapping[$legacyStatus] ?? self::STATUS_PENDING;
    }

    /**
     * Map blockchain status to user-friendly format
     */
    private function mapBlockchainStatus($blockchainStatus)
    {
        $mapping = [
            'PENDING' => self::STATUS_PENDING,
            'VERIFIED' => self::STATUS_VERIFIED,
            'REQUESTED' => self::STATUS_REQUESTED,
            'ISSUED' => self::STATUS_ISSUED,
            'CERTIFICATE_ISSUED' => self::STATUS_ISSUED,
            'PURCHASE_REQUESTED' => self::STATUS_PURCHASE_REQUESTED,
            'PURCHASED' => self::STATUS_PURCHASED,
            'COMPLETED' => self::STATUS_PURCHASED, // ✅ FIXED: Map COMPLETED to PURCHASED
            // Legacy mapping
            'pending_payment' => self::STATUS_PENDING_PAYMENT,
            'awaiting_confirmation' => self::STATUS_AWAITING_CONFIRMATION,
            'completed' => self::STATUS_COMPLETED,
        ];

        return $mapping[$blockchainStatus] ?? self::STATUS_PENDING;
    }

    /**
     * Get status label for display
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            self::STATUS_PENDING => 'Menunggu Verifikasi',
            self::STATUS_VERIFIED => 'Data Terverifikasi',
            self::STATUS_REQUESTED => 'Sertifikat Diminta',
            self::STATUS_ISSUED => 'Sertifikat Diterbitkan',
            self::STATUS_PURCHASE_REQUESTED => 'Permintaan Pembelian',
            self::STATUS_PURCHASED => 'Selesai',
            // Legacy labels
            self::STATUS_PENDING_PAYMENT => 'Menunggu Pembayaran',
            self::STATUS_AWAITING_CONFIRMATION => 'Menunggu Konfirmasi',
            self::STATUS_COMPLETED => 'Selesai',
        ];

        $currentStatus = $this->blockchain_status ?? $this->status;
        return $labels[$currentStatus] ?? 'Status Tidak Dikenal';
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute()
    {
        $colors = [
            self::STATUS_PENDING => 'yellow',
            self::STATUS_VERIFIED => 'blue',
            self::STATUS_REQUESTED => 'indigo',
            self::STATUS_ISSUED => 'purple',
            self::STATUS_PURCHASE_REQUESTED => 'orange',
            self::STATUS_PURCHASED => 'green',
            // Legacy colors
            self::STATUS_PENDING_PAYMENT => 'yellow',
            self::STATUS_AWAITING_CONFIRMATION => 'blue',
            self::STATUS_COMPLETED => 'green',
        ];

        $currentStatus = $this->blockchain_status ?? $this->status;
        return $colors[$currentStatus] ?? 'gray';
    }

    /**
     * Mendefinisikan relasi bahwa pesanan ini dimiliki oleh satu User (pembeli).
     */
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * Mendefinisikan relasi bahwa satu pesanan dapat memiliki banyak sertifikat.
     */
    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }
}