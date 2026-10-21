<?php

namespace Database\Seeders;

use App\Models\Rule;
use Illuminate\Database\Seeder;

class RuleSeeder extends Seeder
{
    /**
     * Les 22 règles d'audit R01 à R22.
     * Relancer le seeder met à jour libellés et sévérités sans toucher à is_active.
     */
    public function run(): void
    {
        $rules = [
            ['R01', 'Membres du rôle sysadmin hors liste blanche validée', 'critique'],
            ['R02', 'Principal disposant de CONTROL SERVER ou IMPERSONATE ANY LOGIN', 'critique'],
            ['R03', 'Compte sa activé ou non renommé', 'elevee'],
            ['R04', 'Login SQL avec CHECK_POLICY = OFF', 'elevee'],
            ['R05', 'Login SQL avec CHECK_EXPIRATION = OFF', 'moyenne'],
            ['R06', 'Groupe BUILTIN\Administrators présent comme login', 'elevee'],
            ['R07', 'Utilisateur orphelin (SID sans login correspondant)', 'moyenne'],
            ['R08', 'Utilisateur guest avec droit CONNECT dans une base utilisateur', 'elevee'],
            ['R09', 'Permissions accordées directement au rôle public', 'elevee'],
            ['R10', 'Membres de db_owner hors propriétaire applicatif identifié', 'moyenne'],
            ['R11', 'Base TRUSTWORTHY = ON dont le propriétaire est sysadmin', 'critique'],
            ['R12', 'Base dont le propriétaire est un compte personnel ou inexistant', 'moyenne'],
            ['R13', 'Permissions accordées WITH GRANT OPTION', 'moyenne'],
            ['R14', 'Permissions accordées à un utilisateur nominatif plutôt qu’à un rôle', 'faible'],
            ['R15', 'Login désactivé conservant des rôles ou permissions', 'faible'],
            ['R16', 'xp_cmdshell, CLR ou Ole Automation Procedures activés', 'elevee'],
            ['R17', 'Chaînage de propriétés inter-bases activé au niveau serveur', 'moyenne'],
            ['R18', 'Authentification mixte sans justification documentée', 'moyenne'],
            ['R19', 'Mot de passe SQL non changé depuis plus de N jours', 'moyenne'],
            ['R20', 'Utilisateur de base autonome (contained) avec mot de passe', 'moyenne'],
            ['R21', 'Nouveau membre d’un rôle privilégié depuis le dernier audit', 'elevee'],
            ['R22', 'Compte de collecte avec des droits supérieurs au nécessaire', 'moyenne'],
        ];

        foreach ($rules as [$code, $label, $severity]) {
            Rule::updateOrCreate(
                ['code' => $code],
                ['label' => $label, 'severity' => $severity]
            );
        }
    }
}
