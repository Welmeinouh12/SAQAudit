<?php

namespace App\Http\Requests\Auth;

use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $user = User::where('email', $this->string('email'))->first();

        // Compte verrouillé : on refuse sans même vérifier le mot de passe
        if ($user && $user->isLocked()) {
            ActivityLogger::log('connexion', 'Connexion échouée', 'Compte verrouillé', $user);

            throw ValidationException::withMessages([
                'email' => 'Compte verrouillé. Réessayez dans '.ceil(now()->diffInMinutes($user->locked_until)).' minute(s).',
            ]);
        }

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            $this->registerFailedAttempt($user);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // Compte désactivé par l'administrateur : on déconnecte aussitôt
        if (! $user->is_active) {
            Auth::guard('web')->logout();
            ActivityLogger::log('connexion', 'Connexion échouée', 'Compte désactivé', $user);

            throw ValidationException::withMessages([
                'email' => 'Ce compte est désactivé.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->save();
        ActivityLogger::log('connexion', 'Connexion réussie', null, $user);
    }

    /**
     * Compter l'échec et verrouiller le compte au-delà du seuil défini dans les paramètres.
     */
    protected function registerFailedAttempt(?User $user): void
    {
        if (! $user) {
            ActivityLogger::log('connexion', 'Connexion échouée', 'E-mail inconnu : '.$this->string('email'));

            return;
        }

        $maxAttempts = (int) Setting::get('lockout_attempts');
        $attempts = $user->failed_login_attempts + 1;

        if ($attempts >= $maxAttempts) {
            $user->forceFill([
                'failed_login_attempts' => 0,
                'locked_until' => now()->addMinutes((int) Setting::get('lockout_minutes')),
            ])->save();
            ActivityLogger::log('connexion', 'Connexion échouée', "Mot de passe incorrect ({$attempts}/{$maxAttempts}) · compte verrouillé", $user);
        } else {
            $user->forceFill(['failed_login_attempts' => $attempts])->save();
            ActivityLogger::log('connexion', 'Connexion échouée', "Mot de passe incorrect ({$attempts}/{$maxAttempts})", $user);
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
