<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogger
{
    /**
     * Écrire une ligne dans le journal d'activité.
     *
     * Exemple : ActivityLogger::log('administration', 'Règle modifiée', 'R14 désactivée');
     *
     * Si $user n'est pas fourni, on prend l'utilisateur connecté.
     */
    public static function log(string $type, string $action, ?string $detail = null, ?User $user = null): void
    {
        ActivityLog::create([
            'user_id' => $user?->id ?? auth()->id(),
            'type' => $type,
            'action' => $action,
            'detail' => $detail,
            'ip_address' => request()->ip(),
        ]);
    }
}
