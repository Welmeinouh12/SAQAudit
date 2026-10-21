<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['audit_run_id', 'rule_id', 'severity', 'object_name', 'evidence', 'recommendation'])]
class Finding extends Model
{
    public function auditRun(): BelongsTo
    {
        return $this->belongsTo(AuditRun::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(Rule::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(FindingException::class);
    }

    /**
     * Exception validée et non expirée (la plus récente) : si elle existe, le constat est masqué.
     */
    public function activeException(): HasOne
    {
        return $this->hasOne(FindingException::class)->ofMany(['id' => 'max'], function ($query) {
            $query->where('status', 'approved')->whereDate('requested_expiration', '>=', today());
        });
    }

    public function severityLabel(): string
    {
        return Rule::SEVERITIES[$this->severity] ?? $this->severity;
    }
}
