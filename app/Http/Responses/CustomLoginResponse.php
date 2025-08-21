<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class CustomLoginResponse implements LoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $superAdmin = auth()->user()->hasRole('super_admin');

        if (session()->has('url.intended')) {
            $url = session()->get('url.intended');
            session()->forget('url.intended');

            return redirect()->to($url);
        }

        return redirect()->intended($superAdmin ? '/admin' : '/main');
    }
}
