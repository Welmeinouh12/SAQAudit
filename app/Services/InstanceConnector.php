<?php

namespace App\Services;

use App\Models\Instance;
use App\Models\Setting;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Connexion aux instances SQL Server auditées, en LECTURE SEULE.
 *
 * Toutes les requêtes passent par select() : seules les instructions SELECT
 * (ou WITH ... SELECT) sont acceptées, avec des paramètres liés.
 */
class InstanceConnector
{
    public const CONNECTION = 'audit_target';

    /**
     * Ouvrir une connexion vers l'instance (avec nouvelles tentatives en cas d'échec).
     */
    public function connect(Instance $instance): Connection
    {
        $timeout = (int) Setting::get('query_timeout');
        $retries = (int) Setting::get('query_retries');

        // Laisser à PHP le temps de faire toutes les tentatives
        set_time_limit(($retries + 1) * (min($timeout, 10) + 2) + $timeout + 30);

        config(['database.connections.'.self::CONNECTION => [
            'driver' => 'sqlsrv',
            'host' => $instance->host,
            'port' => $instance->port,
            'database' => 'master',
            'username' => $instance->username,
            'password' => $instance->password,     // déchiffré ici uniquement
            'charset' => 'utf8',
            'prefix' => '',
            'encrypt' => 'yes',
            'trust_server_certificate' => true,     // développement : certificat auto-signé accepté
            'readonly' => true,                     // ApplicationIntent=ReadOnly
            'login_timeout' => min($timeout, 10),   // ouverture de connexion : 10 s max par tentative
            'appname' => 'SQLAudit',
            'options' => [
                PDO::SQLSRV_ATTR_QUERY_TIMEOUT => $timeout,
            ],
        ]]);

        // retry() : 1 essai + N nouvelles tentatives, 1 seconde entre chaque.
        // Pas de nouvelle tentative si le mot de passe est refusé (inutile, et risque de verrouiller le compte).
        return retry($retries + 1, function () {
            DB::purge(self::CONNECTION);
            $connection = DB::connection(self::CONNECTION);
            $connection->getPdo(); // force la connexion maintenant

            return $connection;
        }, 1000, fn (Throwable $e) => ! $this->isAuthenticationError($e));
    }

    protected function isAuthenticationError(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, '28000') || str_contains($message, '18456') || str_contains($message, 'Login failed');
    }

    /**
     * Exécuter une requête de lecture et renvoyer les lignes sous forme de tableaux.
     */
    public function select(Connection $connection, string $sql, array $bindings = []): array
    {
        if (! preg_match('/^\s*(SELECT|WITH)\b/i', $sql)) {
            throw new InvalidArgumentException('Seules les requêtes SELECT sont autorisées sur une instance auditée.');
        }

        return array_map(fn ($row) => (array) $row, $connection->select($sql, $bindings));
    }

    /**
     * Fermer la connexion.
     */
    public function disconnect(): void
    {
        DB::purge(self::CONNECTION);
    }

    /**
     * Tester la connexion. Renvoie ['ok' => bool, 'message' => string, 'version' => ?string].
     * N'enregistre rien : c'est le contrôleur qui décide de sauvegarder le résultat.
     */
    public function test(Instance $instance): array
    {
        try {
            $connection = $this->connect($instance);

            $row = $this->select($connection, "SELECT
                    @@VERSION AS version,
                    CAST(SERVERPROPERTY('Edition') AS nvarchar(128)) AS edition,
                    CAST(SERVERPROPERTY('IsIntegratedSecurityOnly') AS int) AS windows_only")[0];

            // Première ligne de @@VERSION, ex. « Microsoft SQL Server 2022 (RTM) - 16.0.1000.6 (X64) »
            $version = trim(strtok($row['version'], "\n"));
            $auth = $row['windows_only'] ? 'authentification Windows uniquement' : 'authentification mixte';

            return [
                'ok' => true,
                'message' => "Connexion réussie · {$version} · {$row['edition']} · {$auth}",
                'version' => $row['version'],
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->friendlyError($e), 'version' => null];
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Transformer l'erreur technique du pilote en message lisible.
     */
    public function friendlyError(Throwable $e): string
    {
        $message = $e->getMessage();

        if ($this->isAuthenticationError($e)) {
            return "Échec d'authentification : vérifiez le compte de collecte et le mot de passe.";
        }
        if (str_contains($message, 'HYT00') || str_contains($message, 'timeout') || str_contains($message, 'Délai')) {
            return "Délai dépassé : l'instance ne répond pas (hôte, port ou pare-feu).";
        }
        if (str_contains($message, '08001') || str_contains($message, 'TCP Provider')) {
            return "Instance injoignable : vérifiez l'hôte, le port et que SQL Server accepte les connexions TCP.";
        }

        // Autre erreur : on retire les préfixes techniques du pilote
        $message = preg_replace('/\s*\(Connection: .*$/s', '', $message);
        $message = preg_replace('/SQLSTATE\[\w+\]:?\s*(\[[^\]]*\])*\s*/', '', $message);

        return 'Erreur : '.mb_strimwidth(trim($message), 0, 300, '…');
    }
}
