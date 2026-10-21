<?php

namespace App\Rules\Audit;

/**
 * R16 : xp_cmdshell, clr enabled ou Ole Automation Procedures activés (value_in_use = 1).
 */
class R16DangerousOptions implements AuditRule
{
    /** Options surveillées => recommandation */
    public const OPTIONS = [
        'xp_cmdshell' => 'Désactiver avec sp_configure',
        'clr enabled' => 'Désactiver CLR si aucun assembly .NET n’est nécessaire (sp_configure)',
        'Ole Automation Procedures' => 'Désactiver avec sp_configure',
    ];

    public function evaluate(array $data): array
    {
        $findings = [];

        foreach ($data['configurations'] as $option) {
            if ((int) $option['value_in_use'] !== 1 || ! isset(self::OPTIONS[$option['name']])) {
                continue;
            }

            $findings[] = [
                'object' => $option['name'],
                'evidence' => 'value_in_use = 1',
                'recommendation' => self::OPTIONS[$option['name']],
            ];
        }

        return $findings;
    }
}
