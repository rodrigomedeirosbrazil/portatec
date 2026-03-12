<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Models\TuyaCredential;
use App\Services\Tuya\Client as TuyaClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class TuyaOAuthController extends Controller
{
    public function redirect(Request $request, Place $place): RedirectResponse
    {
        $this->authorizePlaceAccess($request, $place);

        $redirectUri = route('app.tuya.callback');
        $state = Crypt::encryptString((string) $place->id);

        $params = http_build_query([
            'client_id' => config('tuya.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'api',
            'state' => $state,
        ]);

        $authUrl = config('tuya.oauth_authorize_url').'?'.$params;

        return redirect()->away($authUrl);
    }

    public function callback(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $placeId = (int) Crypt::decryptString($request->input('state'));
        $place = Place::query()->findOrFail($placeId);
        $this->authorizePlaceAccess($request, $place);

        $client = TuyaClient::fromConfig();
        $redirectUri = route('app.tuya.callback');
        $dto = $client->exchangeCodeForToken($request->input('code'), $redirectUri);

        if ($dto === null) {
            throw ValidationException::withMessages([
                'code' => [__('Tuya authorization failed. Please try again.')],
            ]);
        }

        $expiresAt = now()->addSeconds((int) $dto->expireTime);

        TuyaCredential::query()->updateOrCreate(
            ['place_id' => $place->id],
            [
                'access_token' => $dto->accessToken,
                'refresh_token' => $dto->refreshToken,
                'expires_at' => $expiresAt,
                'uid' => $dto->uid,
                'region' => null,
            ]
        );

        return redirect()
            ->route('app.places.show', ['place' => $place])
            ->with('status', __('Tuya account linked successfully.'));
    }

    private function authorizePlaceAccess(Request $request, Place $place): void
    {
        $user = $request->user();
        if ($user === null || ! $place->hasAccessToPlace($user)) {
            abort(403);
        }
    }
}
