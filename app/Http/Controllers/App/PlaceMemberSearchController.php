<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaceMemberSearchController extends Controller
{
    public function __invoke(Request $request, Place $place): JsonResponse
    {
        $this->authorize('manageMembers', $place);

        $email = trim((string) $request->query('email', ''));

        if ($email === '') {
            return response()->json(['data' => []]);
        }

        $existingIds = $place->placeUsers()->pluck('user_id')->all();

        $users = User::query()
            ->whereNotIn('id', $existingIds)
            ->where('email', $email)
            ->limit(1)
            ->get(['id', 'name', 'email']);

        return response()->json(['data' => $users]);
    }
}
