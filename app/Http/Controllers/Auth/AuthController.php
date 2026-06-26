<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected const MAX_ATTEMPTS = 5;
    protected const LOCKOUT_MINUTES = 5;

    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = User::create([
            'full_name' => $request->full_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'guest',
            'is_active' => true,
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $user->update(['last_activity_at' => now()]);

        return redirect()->route('storefront.index');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $user = User::where('email', $request->email)->first();

        if ($user && $user->isLocked()) {
            throw ValidationException::withMessages([
                'email' => 'Too many failed attempts. Try again in a few minutes.',
            ]);
        }

        if ($user && ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated. Contact an administrator.',
            ]);
        }

        if (! $user || ! Hash::check($request->password, $user->password)) {
            if ($user) {
                $this->recordFailedAttempt($user);
                $user->refresh();

                if ($user->isLocked()) {
                    throw ValidationException::withMessages([
                        'email' => 'Too many failed attempts. Try again in a few minutes.',
                    ]);
                }
            }

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        // Successful login — reset lockout counters
        $user->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_activity_at' => now(),
        ]);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return $user->isAdmin()
            ? redirect()->route('cms.dashboard')
            : redirect()->route('storefront.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    protected function recordFailedAttempt(User $user): void
    {
        $attempts = $user->failed_login_attempts + 1;

        $user->failed_login_attempts = $attempts;

        if ($attempts >= self::MAX_ATTEMPTS) {
            $user->locked_until = Carbon::now()->addMinutes(self::LOCKOUT_MINUTES);
        }

        $user->save();
    }
}
