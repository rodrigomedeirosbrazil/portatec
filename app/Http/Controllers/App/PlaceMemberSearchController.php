<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaceMemberSearchController extends Controller
{
    public function __invoke(Request $request, Place $place): JsonResponse
    {
        $this->authorize('manageMembers', $place);

        $search = (string) $request->query('search', '');

        if (mb_strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        $existingIds = $place->placeUsers()->pluck('user_id')->all();
        $term = '%'.addcslashes($search, '%_').'%';

        $users = User::query()
            ->whereNotIn('id', $existingIds)
            ->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email']);

        return response()->json(['data' => $users]);
    }
}
