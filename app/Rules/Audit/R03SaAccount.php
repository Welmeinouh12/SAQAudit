<?php

namespace App\Rules\Audit;

/**
 * R03 : compte sa (principal_id = 1) activé ou non renommé.
 */
class R03SaAccount implements AuditRule
{
    public function evaluate(array $data): array
    {
        $sa = $data['sa'];

        if ($sa === null) {
            return [];
        }

        $enabled = ! $sa['is_disabled'];
        $notRenamed = strcasecmp($sa['name'], 'sa') === 0;

        if (! $enabled && ! $notRenamed) {
            return [];
        }

        $evidence = match (true) {
            $enabled && $notRenamed => 'Compte activé et non renommé',
            $enabled => 'Compte activé (renommé en « '.$sa['name'].' »)',
            default => 'Compte désactivé mais non renommé',
        };

        return [[
            'object' => $sa['name'],
            'evidence' => $evidence,
            'recommendation' => $enabled ? 'Désactiver le compte sa' : 'Renommer le compte sa',
        ]];
    }
}
