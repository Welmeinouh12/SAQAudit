<?php

namespace App\Rules\Audit;

/**
 * R07 : utilisateurs orphelins (SID d'un utilisateur SQL absent de sys.server_principals),
 * dans chaque base utilisateur en ligne.
 */
class R07OrphanUsers implements AuditRule
{
    public function evaluate(array $data): array
    {
        $findings = [];

        foreach ($data['orphans'] as $database => $users) {
            foreach ($users as $user) {
                $findings[] = [
                    'object' => $database.'.'.$user['name'],
                    'evidence' => 'SID sans login correspondant',
                    'recommendation' => "Supprimer l'utilisateur ou le rattacher à un login (ALTER USER … WITH LOGIN)",
                ];
            }
        }

        return $findings;
    }
}
