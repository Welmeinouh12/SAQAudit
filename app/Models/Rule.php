<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'label', 'severity', 'is_active'])]
class Rule extends Model
{
    /**
     * Sévérités : valeur en base => libellé affiché (la clé sert aussi de couleur pour <x-badge>)
     */
    public const SEVERITIES = [
        'critique' => 'Critique',
        'elevee' => 'Élevée',
        'moyenne' => 'Moyenne',
        'faible' => 'Faible',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function severityLabel(): string
    {
        return self::SEVERITIES[$this->severity] ?? $this->severity;
    }
}
