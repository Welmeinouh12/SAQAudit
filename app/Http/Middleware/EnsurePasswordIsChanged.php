<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Tant que l'utilisateur a un mot de passe temporaire, il ne peut accéder
     * qu'à la page de profil (pour le changer) et à la déconnexion.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! $request->routeIs('profile.edit', 'password.update', 'logout')) {
            return redirect()->route('profile.edit')
                ->with('must_change', 'Votre mot de passe est temporaire : choisissez-en un nouveau pour continuer.');
        }

        return $next($request);
    }
}
