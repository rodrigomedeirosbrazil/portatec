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
     *
     * A lista vem de `config('portatec.super_admin_emails')`, já normalizada.
     * Ler o `env()` daqui não funcionava em produção: com o config cacheado
     * pelo `artisan optimize` do entrypoint, o Laravel não carrega o `.env`, e
     * o `.env` chega ao container como arquivo montado, não como variável de
     * ambiente — então `env()` caía sempre no default e a lista configurada
     * não tinha efeito nenhum. Veja o comentário em `config/portatec.php`.
     */
    public function hasRole(string $role): bool
    {
        if ($role !== 'super_admin') {
            return false;
        }

        return in_array(
            strtolower((string) $this->email),
            (array) config('portatec.super_admin_emails', []),
            true,
        );
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->hasRole('super_admin');
    }
}
