<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImpersonationSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StartImpersonationController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        $impersonator = $request->user();

        if (! $impersonator || ! $impersonator->hasRole('super_admin')) {
            abort(403);
        }

        if ((int) $impersonator->id === (int) $user->id) {
            return back()->with('status', 'Nao e permitido assumir a propria conta.');
        }

        if ($request->session()->has('impersonator_id')) {
            return redirect('/app/dashboard')->with('status', 'Finalize a sessao assumida atual antes de iniciar outra.');
        }

        $session = ImpersonationSession::create([
            'impersonator_user_id' => $impersonator->id,
            'impersonated_user_id' => $user->id,
            'started_at' => now(),
            'started_ip' => $request->ip(),
            'started_user_agent' => (string) $request->userAgent(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put([
            'impersonator_id' => $impersonator->id,
            'impersonation_session_id' => $session->id,
        ]);

        return redirect('/app/dashboard')->with('status', "Sessao assumida iniciada para {$user->email}.");
    }
}
