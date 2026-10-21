<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['instance_id', 'user_id', 'status', 'started_at', 'ended_at', 'logins_count', 'databases_count', 'fingerprint', 'error_message'])]
class AuditRun extends Model
{
    /**
     * Statuts : valeur en base => [libellé, couleur du badge]
     */
    public const STATUSES = [
        'running' => ['En cours', 'faible'],
        'completed' => ['Terminé', 'actif'],
        'failed' => ['Échec', 'desactive'],
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'logins_count' => 'integer',
            'databases_count' => 'integer',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'gris';
    }
}
