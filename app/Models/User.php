<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens ,HasFactory, Notifiable ;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_photo', // profil fotoğrafı alanını ekleyin
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

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_users');
    }

    public function getPPAttribute() 
    {
        if ($this->profile_photo) {
            return asset('storage/' . $this->profile_photo);
        } else {
            return false; // Profil fotoğrafı yoksa 'false' döndür
        }
    }

    /**
     * Bu kullanıcının müşteri olarak verdiği siparişleri getirir.
     */
    public function ordersAsCustomer(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    /**
     * Bu kullanıcının üretici olarak dahil olduğu siparişleri getirir.
     */
    public function ordersAsManufacturer(): HasMany
    {
        return $this->hasMany(Order::class, 'manufacturer_id');
    }
}