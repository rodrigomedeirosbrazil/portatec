<?php

namespace App\Models;

use App\Enums\PlaceRoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Place extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'role',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'role' => PlaceRoleEnum::class,
    ];

    public function placeUsers(): HasMany
    {
        return $this->hasMany(PlaceUser::class);
    }
}
