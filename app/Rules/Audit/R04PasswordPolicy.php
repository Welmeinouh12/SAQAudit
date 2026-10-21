<?php

namespace App\Rules\Audit;

/**
 * R04 : logins SQL avec CHECK_POLICY = OFF (is_policy_checked = 0).
 */
class R04PasswordPolicy implements AuditRule
{
    public function evaluate(array $data): array
    {
        $findings = [];

        foreach ($data['sql_logins'] as $login) {
            // Les logins internes « ##MS_...## » sont créés par SQL Server lui-même
            if ($login['is_policy_checked'] || str_starts_with($login['name'], '##')) {
                continue;
            }

            $findings[] = [
                'object' => $login['name'],
                'evidence' => 'CHECK_POLICY = OFF'.($login['is_disabled'] ? ' · login désactivé' : ''),
                'recommendation' => 'Activer CHECK_POLICY sur le login',
            ];
        }

        return $findings;
    }
}
