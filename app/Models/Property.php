<?php

namespace App\Models;

use App\Enums\PropertyRoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'role',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'role' => PropertyRoleEnum::class,
    ];

    public function propertyUsers(): HasMany
    {
        return $this->hasMany(PropertyUser::class);
    }
}
