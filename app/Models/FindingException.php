<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Exception (acceptation de risque) sur un constat.
 * Nommée FindingException pour ne pas confondre avec la classe \Exception de PHP.
 */
#[Fillable(['finding_id', 'requested_by', 'reason', 'requested_expiration', 'status', 'reviewed_by', 'reviewed_at', 'review_comment'])]
class FindingException extends Model
{
    protected $table = 'exceptions';

    /**
     * Statuts : valeur en base => [libellé, couleur du badge]
     */
    public const STATUSES = [
        'pending' => ['En attente', 'moyenne'],
        'approved' => ['Validée', 'actif'],
        'rejected' => ['Refusée', 'desactive'],
    ];

    protected function casts(): array
    {
        return [
            // Entiers garantis : la séparation des tâches compare requested_by à l'id de l'utilisateur
            'finding_id' => 'integer',
            'requested_by' => 'integer',
            'reviewed_by' => 'integer',
            'requested_expiration' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'gris';
    }

    /**
     * Validée et pas encore expirée : le constat est masqué.
     */
    public function isActive(): bool
    {
        return $this->status === 'approved' && $this->requested_expiration->endOfDay()->isFuture();
    }
}
