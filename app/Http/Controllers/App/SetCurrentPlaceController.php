<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\CurrentPlaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SetCurrentPlaceController extends Controller
{
    public function __invoke(Request $request, CurrentPlaceService $currentPlace): RedirectResponse
    {
        $placeId = $request->filled('place_id') ? $request->integer('place_id') : null;

        $currentPlace->set(Auth::user(), $placeId);

        return redirect()->back();
    }
}
