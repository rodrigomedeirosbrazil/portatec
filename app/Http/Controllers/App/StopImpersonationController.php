<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ImpersonationSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class StopImpersonationController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|SymfonyResponse
    {
        $impersonatorId = (int) $request->session()->get('impersonator_id');
        $impersonationSessionId = (int) $request->session()->get('impersonation_session_id');
        $currentUserId = (int) optional($request->user())->id;

        if ($impersonatorId <= 0 || $impersonationSessionId <= 0) {
            return redirect('/app/dashboard')->with('status', 'Nao existe sessao assumida ativa.');
        }

        $impersonator = User::query()->find($impersonatorId);

        if (! $impersonator) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/app/login')->with('status', 'Sessao de impersonate invalida. Faça login novamente.');
        }

        $session = ImpersonationSession::query()
            ->whereKey($impersonationSessionId)
            ->where('impersonator_user_id', $impersonatorId)
            ->where('impersonated_user_id', $currentUserId)
            ->whereNull('ended_at')
            ->first();

        if (! $session) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/app/login')->with('status', 'Sessao de impersonate invalida. Faça login novamente.');
        }

        $session->update([
            'ended_at' => now(),
            'ended_ip' => $request->ip(),
            'ended_user_agent' => (string) $request->userAgent(),
        ]);

        Auth::login($impersonator);
        $request->session()->regenerate();
        $request->session()->forget(['impersonator_id', 'impersonation_session_id']);

        $request->session()->flash('status', 'Sessao assumida finalizada.');

        // O painel /admin e Filament, nao Inertia. Um redirect comum faria o cliente
        // Inertia seguir o 302 por XHR, receber HTML sem o cabecalho x-inertia e
        // despejar o painel inteiro num modal de depuracao em vez de navegar.
        // Inertia::location devolve 409 com X-Inertia-Location, que manda o cliente
        // fazer uma visita de pagina inteira - e, fora do Inertia, vira um 302 normal.
        return Inertia::location('/admin');
    }
}
