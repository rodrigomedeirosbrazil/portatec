<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingDeletionReasonEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'place_id',
        'integration_id',
        'guest_name',
        'check_in',
        'check_out',
        'source',
        'external_id',
        'deletion_reason',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'deletion_reason' => BookingDeletionReasonEnum::class,
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function accessCode(): HasOne
    {
        return $this->hasOne(AccessCode::class);
    }

    public function scopeCurrent(Builder $query, CarbonInterface $now): Builder
    {
        return $query->where('check_in', '<=', $now)->where('check_out', '>=', $now);
    }

    public function scopeFuture(Builder $query, CarbonInterface $now): Builder
    {
        return $query->where('check_in', '>', $now);
    }

    public function scopePast(Builder $query, CarbonInterface $now): Builder
    {
        return $query->where('check_out', '<', $now);
    }

    /**
     * Ordena em três grupos, nesta ordem e cada um com seu próprio
     * critério: em andamento por `check_out` asc (quem desocupa primeiro no
     * topo), futuras por `check_in` asc, concluídas por `check_out` desc (a
     * mais recente antes das antigas). `id` fecha como desempate, sem o qual
     * a paginação fica instável em empates de data.
     *
     * Usa `orderByRaw` porque essa ordenação condicional em três níveis não
     * é expressável no query builder; a sintaxe (`CASE WHEN` com parâmetros
     * bindados) é portável entre MySQL e SQLite.
     */
    public function scopeOrderByTimeline(Builder $query, CarbonInterface $now): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN check_out < ? THEN 2 WHEN check_in <= ? THEN 0 ELSE 1 END', [$now, $now])
            ->orderByRaw('CASE WHEN check_out < ? THEN NULL WHEN check_in <= ? THEN check_out ELSE check_in END', [$now, $now])
            ->orderByRaw('CASE WHEN check_out < ? THEN check_out ELSE NULL END DESC', [$now])
            ->orderBy('id');
    }
}
