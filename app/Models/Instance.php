<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'host', 'port', 'environment', 'username', 'password'])]
#[Hidden(['password'])]
class Instance extends Model
{
    /**
     * Environnements : valeur en base => libellé affiché
     */
    public const ENVIRONMENTS = [
        'production' => 'Production',
        'recette' => 'Recette',
        'developpement' => 'Développement',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            // Chiffré en base avec APP_KEY, déchiffré seulement par InstanceConnector
            'password' => 'encrypted',
            'last_tested_at' => 'datetime',
        ];
    }

    public function auditRuns(): HasMany
    {
        return $this->hasMany(AuditRun::class);
    }

    public function latestAuditRun(): HasOne
    {
        return $this->hasOne(AuditRun::class)->latestOfMany();
    }

    public function environmentLabel(): string
    {
        return self::ENVIRONMENTS[$this->environment] ?? $this->environment;
    }
}
