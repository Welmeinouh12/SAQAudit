<?php

namespace App\Rules\Audit;

/**
 * R01 : membres du rôle sysadmin absents de la liste blanche validée.
 * Le compte sa (principal_id = 1) est traité par R03.
 */
class R01SysadminOutsideWhitelist implements AuditRule
{
    public function evaluate(array $data): array
    {
        // Comparaison sans tenir compte de la casse (comme SQL Server)
        $whitelist = array_map('mb_strtolower', $data['whitelist']);
        $findings = [];

        foreach ($data['sysadmins'] as $member) {
            if ((int) $member['principal_id'] === 1 || in_array(mb_strtolower($member['name']), $whitelist, true)) {
                continue;
            }

            $evidence = 'Membre du rôle sysadmin ('.$member['type_desc'].'), hors liste blanche';
            if ($member['is_disabled']) {
                $evidence .= ' · login désactivé';
            }

            $findings[] = [
                'object' => $member['name'],
                'evidence' => $evidence,
                'recommendation' => 'Retirer du rôle sysadmin ou ajouter à la liste blanche validée',
            ];
        }

        return $findings;
    }
}
