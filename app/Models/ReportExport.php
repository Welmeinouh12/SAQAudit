<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'type', 'scope', 'format', 'file_path', 'fingerprint',
    'instance_id', 'findings_count', 'critical_count', 'instances_count'])]
class ReportExport extends Model
{
    protected function casts(): array
    {
        return [
            'instance_id' => 'integer',
            'findings_count' => 'integer',
            'critical_count' => 'integer',
            'instances_count' => 'integer',
        ];
    }

    /** Types de rapport : valeur en base => libellé */
    public const TYPES = [
        'detail' => 'Rapport détaillé d’audit',
        'synthese' => 'Synthèse de conformité',
    ];

    /** Formats : valeur en base => libellé du badge */
    public const FORMATS = [
        'pdf' => 'PDF',
        'xlsx' => 'Excel',
        'csv' => 'CSV',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Instance du périmètre (null = toutes les instances) */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function formatLabel(): string
    {
        return self::FORMATS[$this->format] ?? $this->format;
    }
}
