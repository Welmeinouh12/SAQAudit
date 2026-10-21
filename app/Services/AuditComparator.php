<?php

namespace App\Services;

use App\Models\AuditRun;

/**
 * Compare deux audits d'une même instance.
 * Un constat est identifié par (rule_id + object_name) :
 *   présent seulement dans le 2e  = nouveau
 *   présent seulement dans le 1er = résolu
 *   présent dans les deux         = inchangé
 */
class AuditComparator
{
    public function compare(AuditRun $reference, AuditRun $compared): array
    {
        $key = fn ($finding) => $finding->rule_id.'|'.$finding->object_name;

        $before = $reference->findings()->with('rule')->get()->keyBy($key);
        $after = $compared->findings()->with('rule')->get()->keyBy($key);

        return [
            'new' => $after->diffKeys($before)->values(),
            'resolved' => $before->diffKeys($after)->values(),
            'unchanged' => $after->intersectByKeys($before)->values(),
        ];
    }

    /**
     * Audit terminé précédent de la même instance (ou null s'il n'y en a pas).
     */
    public function previousRun(AuditRun $run): ?AuditRun
    {
        return AuditRun::where('instance_id', $run->instance_id)
            ->where('status', 'completed')
            ->where('id', '<', $run->id)
            ->orderByDesc('id')
            ->first();
    }
}
