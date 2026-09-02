<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Place;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * O "local atual" é uma conveniência de navegação guardada em sessão. Ele NÃO é
 * fronteira de segurança: o escopo real continua sendo o `whereIn('place_id',
 * $userPlaceIds)` de cada consulta. Ainda assim, `get()` revalida o vínculo a
 * cada leitura — sem isso, quem perdesse acesso a um local continuaria vendo os
 * dados dele até trocar de seleção.
 */
class CurrentPlaceService
{
    public const SESSION_KEY = 'current_place_id';

    public function get(User $user): ?Place
    {
        $placeId = session(self::SESSION_KEY);

        if ($placeId === null) {
            return null;
        }

        $place = Place::query()
            ->whereKey($placeId)
            ->whereHas('placeUsers', fn ($query) => $query->where('user_id', $user->id))
            ->first();

        if ($place === null) {
            session()->forget(self::SESSION_KEY);
        }

        return $place;
    }

    public function set(User $user, ?int $placeId): void
    {
        if ($placeId === null) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        $belongs = $user->placeUsers()->where('place_id', $placeId)->exists();

        if (! $belongs) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        session([self::SESSION_KEY => $placeId]);
    }

    /**
     * Regra de precedência do spec 4.4: um `place_id` explícito e válido na query
     * string atualiza a sessão antes de filtrar. É isso que mantém o seletor
     * dizendo a verdade sobre o que está na tela, e que faz os links diretos do
     * dashboard e dos tiles continuarem funcionando.
     */
    public function resolveForRequest(Request $request, User $user): ?int
    {
        if ($request->filled('place_id')) {
            $this->set($user, $request->integer('place_id'));
        }

        return $this->get($user)?->id;
    }
}
