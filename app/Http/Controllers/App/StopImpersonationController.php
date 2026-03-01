<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ImpersonationSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StopImpersonationController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
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

        return redirect('/admin')->with('status', 'Sessao assumida finalizada.');
    }
}
