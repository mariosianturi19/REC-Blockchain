<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Company;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'phone',
        'nik',
        'address',
        'province',
        'regency',
        'district',
        'village',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function powerPlants(): HasMany
    {
        return $this->hasMany(PowerPlant::class);
    }
    
    public function issuerProfile(): HasOne
    {
        return $this->hasOne(IssuerProfile::class);
    }
    
    public function issuedCertificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'issuer_id');
    }
    
    public function ownedCertificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'owner_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function company()
    {
        return $this->hasOne(Company::class);
    }

}
