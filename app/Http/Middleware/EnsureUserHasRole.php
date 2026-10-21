<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Utilisation : ->middleware('role:admin') ou ->middleware('role:admin,dba')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Compte désactivé ou profil non autorisé : accès refusé
        if (! $user || ! $user->is_active || ! in_array($user->role, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
