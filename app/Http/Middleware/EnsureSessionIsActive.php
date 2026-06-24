<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionIsActive
{
    protected const IDLE_MINUTES = 30;

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            if (
                $user->last_activity_at
                && $user->last_activity_at->addMinutes(self::IDLE_MINUTES)->isPast()
            ) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('message', 'You have been logged out due to inactivity.');
            }

            // Still active — bump the timestamp so the clock resets on every request
            $user->update(['last_activity_at' => now()]);
        }

        return $next($request);
    }
}