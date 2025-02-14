<?php

namespace App\Http\Responses;

use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as Responsable;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class CustomLoginResponse implements Responsable
{
    public function toResponse($request): RedirectResponse | Redirector
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
