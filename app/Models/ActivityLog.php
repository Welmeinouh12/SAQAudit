<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'type', 'action', 'detail', 'ip_address'])]
class ActivityLog extends Model
{
    // La table n'a que created_at : une ligne du journal n'est jamais modifiée
    public const UPDATED_AT = null;

    /**
     * Types : valeur en base => libellé affiché (la clé sert aussi de couleur pour <x-badge>)
     */
    public const TYPES = [
        'connexion' => 'Connexion',
        'audit' => 'Audit',
        'export' => 'Export',
        'administration' => 'Administration',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
