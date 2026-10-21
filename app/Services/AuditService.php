<?php

namespace App\Services;

use App\Models\AuditRun;
use App\Models\Instance;
use App\Models\Rule;
use App\Models\Setting;
use App\Models\User;
use App\Rules\Audit\R01SysadminOutsideWhitelist;
use App\Rules\Audit\R03SaAccount;
use App\Rules\Audit\R04PasswordPolicy;
use App\Rules\Audit\R07OrphanUsers;
use App\Rules\Audit\R16DangerousOptions;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Exécute un audit complet sur une instance, directement (sans file d'attente) :
 * collecte en lecture seule → règles actives → constats → empreinte SHA-256.
 */
class AuditService
{
    /**
     * Règles programmées : code => classe. Les autres règles du catalogue sont ignorées.
     */
    public const RULES = [
        'R01' => R01SysadminOutsideWhitelist::class,
        'R03' => R03SaAccount::class,
        'R04' => R04PasswordPolicy::class,
        'R07' => R07OrphanUsers::class,
        'R16' => R16DangerousOptions::class,
    ];

    public function __construct(private InstanceConnector $connector)
    {
    }

    public function run(Instance $instance, ?User $user): AuditRun
    {
        set_time_limit(300);

        $run = $instance->auditRuns()->create([
            'user_id' => $user?->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        ActivityLogger::log('audit', 'Audit lancé', "{$instance->name} · audit #{$run->id}", $user);

        try {
            $connection = $this->connector->connect($instance);
            $collected = $this->collect($connection);
        } catch (Throwable $e) {
            $run->update([
                'status' => 'failed',
                'ended_at' => now(),
                'error_message' => $this->connector->friendlyError($e),
            ]);
            ActivityLogger::log('audit', 'Audit échoué', "{$instance->name} · audit #{$run->id}", $user);

            return $run;
        } finally {
            $this->connector->disconnect();
        }

        // La liste blanche est un paramètre de SQLAudit : elle n'entre pas dans l'empreinte
        $data = $collected + ['whitelist' => json_decode(Setting::get('sysadmin_whitelist') ?? '[]', true) ?: []];

        DB::transaction(function () use ($run, $data, $collected) {
            $activeRules = Rule::whereIn('code', array_keys(self::RULES))->where('is_active', true)->get();

            foreach ($activeRules as $rule) {
                $class = self::RULES[$rule->code];

                foreach ((new $class)->evaluate($data) as $finding) {
                    $run->findings()->create([
                        'rule_id' => $rule->id,
                        'severity' => $rule->severity,
                        'object_name' => $finding['object'],
                        'evidence' => $finding['evidence'],
                        'recommendation' => $finding['recommendation'],
                    ]);
                }
            }

            $run->update([
                'status' => 'completed',
                'ended_at' => now(),
                'logins_count' => count($collected['logins']),
                'databases_count' => count($collected['databases']),
                // Empreinte : prouve que les données collectées n'ont pas été modifiées depuis
                'fingerprint' => hash('sha256', json_encode($collected, JSON_UNESCAPED_UNICODE)),
            ]);
        });

        ActivityLogger::log('audit', 'Audit terminé', "{$instance->name} · ".$run->findings()->count().' constats', $user);

        return $run;
    }

    /**
     * Collecte des données sur l'instance : uniquement des SELECT sur les vues système.
     */
    protected function collect(Connection $connection): array
    {
        $select = fn (string $sql, array $bindings = []) => $this->connector->select($connection, $sql, $bindings);

        $data = [
            // Logins SQL, Windows et groupes Windows
            'logins' => $select("SELECT principal_id, name, type, type_desc, is_disabled
                FROM sys.server_principals
                WHERE type IN ('S', 'U', 'G')
                ORDER BY principal_id"),

            // Membres du rôle sysadmin (R01)
            'sysadmins' => $select("SELECT m.principal_id, m.name, m.type_desc, m.is_disabled
                FROM sys.server_role_members AS rm
                JOIN sys.server_principals AS r ON r.principal_id = rm.role_principal_id
                JOIN sys.server_principals AS m ON m.principal_id = rm.member_principal_id
                WHERE r.name = ?
                ORDER BY m.name", ['sysadmin']),

            // Compte sa, même renommé (R03)
            'sa' => $select('SELECT principal_id, name, is_disabled
                FROM sys.server_principals
                WHERE principal_id = ?', [1])[0] ?? null,

            // Logins SQL et leur politique de mot de passe (R04)
            'sql_logins' => $select('SELECT name, is_policy_checked, is_expiration_checked, is_disabled
                FROM sys.sql_logins
                ORDER BY name'),

            // Options sensibles (R16) : value_in_use est un sql_variant, on le convertit
            'configurations' => $select('SELECT name, CAST(value_in_use AS int) AS value_in_use
                FROM sys.configurations
                WHERE name IN (?, ?, ?)
                ORDER BY name', array_keys(R16DangerousOptions::OPTIONS)),

            // Toutes les bases
            'databases' => $select('SELECT database_id, name, state_desc
                FROM sys.databases
                ORDER BY database_id'),
        ];

        // Utilisateurs orphelins (R07), base par base : bases utilisateur en ligne et accessibles
        $data['orphans'] = [];
        $userDatabases = $select('SELECT name FROM sys.databases
            WHERE database_id > 4 AND state = 0 AND HAS_DBACCESS(name) = 1
            ORDER BY name');

        foreach ($userDatabases as $database) {
            // Un nom de base ne peut pas être un paramètre lié : on l'échappe comme QUOTENAME()
            $quoted = '['.str_replace(']', ']]', $database['name']).']';

            // authentication_type = 1 : utilisateur lié à un login de l'instance
            // principal_id > 4 : on ignore dbo, guest, INFORMATION_SCHEMA et sys
            $orphans = $select("SELECT dp.name
                FROM {$quoted}.sys.database_principals AS dp
                LEFT JOIN sys.server_principals AS sp ON sp.sid = dp.sid
                WHERE dp.type = 'S' AND dp.authentication_type = 1 AND dp.principal_id > 4 AND sp.sid IS NULL
                ORDER BY dp.name");

            if ($orphans) {
                $data['orphans'][$database['name']] = $orphans;
            }
        }

        return $data;
    }
}
