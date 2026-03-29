<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'avatar',
        'timezone',
        'theme',

        'encrypted_vault_key',
        'vault_nonce',
        'salt',
        'recovery_encrypted_vault_key',
        'recovery_vault_nonce',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'pending_email',
        'onboarded_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'encrypted_vault_key',
        'vault_nonce',
        'salt',
        'recovery_encrypted_vault_key',
        'recovery_vault_nonce',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'array',
            'onboarded_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function notebooks()
    {
        return $this->hasMany(Notebook::class);
    }

    public function isFree(): bool
    {
        return !$this->isPro();
    }

    public function isPro(): bool
    {
        // Self-hosted mode: everyone is pro
        if (config('app.self_hosted')) {
            return true;
        }

        return $this->subscriptions()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function activePlan(): ?Plan
    {
        return $this->activeSubscription()?->plan;
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasMfaEnabled(): bool
    {
        return !is_null($this->two_factor_confirmed_at);
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }
}
