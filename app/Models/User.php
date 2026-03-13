<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class);
    }

    public function tuyaAccounts(): HasMany
    {
        return $this->hasMany(TuyaAccount::class);
    }

    public function placeUsers(): HasMany
    {
        return $this->hasMany(PlaceUser::class);
    }

    public function places(): BelongsToMany
    {
        return $this->belongsToMany(Place::class, 'place_users')
            ->withPivot(['role', 'label'])
            ->withTimestamps();
    }

    public function startedImpersonationSessions(): HasMany
    {
        return $this->hasMany(ImpersonationSession::class, 'impersonator_user_id');
    }

    public function receivedImpersonationSessions(): HasMany
    {
        return $this->hasMany(ImpersonationSession::class, 'impersonated_user_id');
    }

    /**
     * Transitional compatibility helper while role system is removed.
     */
    public function hasRole(string $role): bool
    {
        if ($role !== 'super_admin') {
            return false;
        }

        $allowedEmails = collect(explode(',', (string) env('PORTATEC_SUPER_ADMIN_EMAILS', 'contato@medeirostec.com.br')))
            ->map(static fn (string $email): string => strtolower(trim($email)))
            ->filter();

        return $allowedEmails->contains(strtolower((string) $this->email));
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->hasRole('super_admin');
    }
}
