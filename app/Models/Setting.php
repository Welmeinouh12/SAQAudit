<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /**
     * Valeurs par défaut, utilisées si la clé n'existe pas encore en base.
     */
    public const DEFAULTS = [
        'lockout_attempts' => '5',        // échecs avant verrouillage
        'lockout_minutes' => '15',        // durée du verrouillage
        'session_minutes' => '30',        // expiration de session
        'query_timeout' => '30',          // délai d'expiration d'une requête (s)
        'query_retries' => '2',           // nouvelles tentatives en cas d'échec
        'password_max_age_days' => '90',  // règle R19
        'retention_years' => '3',
        'auto_purge' => '1',
        'sysadmin_whitelist' => '["sa_dba_principal","DOMAINE\\\\Equipe-DBA","NT SERVICE\\\\SQLSERVERAGENT"]',
    ];

    /**
     * Lire un paramètre : Setting::get('lockout_attempts')
     */
    public static function get(string $key): ?string
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : (self::DEFAULTS[$key] ?? null);
    }

    /**
     * Écrire un paramètre : Setting::set('lockout_attempts', 5)
     */
    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
