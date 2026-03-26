<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'timezone',
        'theme',
        'tier',
        'encrypted_vault_key',
        'vault_nonce',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'pending_email',
        'onboarded_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'encrypted_vault_key',
        'vault_nonce',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'array',
            'onboarded_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function notebooks()
    {
        return $this->hasMany(Notebook::class);
    }

    public function isFree(): bool
    {
        return $this->tier === 'free';
    }

    public function isPro(): bool
    {
        return $this->tier === 'pro';
    }

    public function hasMfaEnabled(): bool
    {
        return !is_null($this->two_factor_confirmed_at);
    }
}
