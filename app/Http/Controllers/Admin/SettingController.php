<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    /**
     * Paramètres numériques : clé => [libellé, min, max]
     */
    public const NUMERIC = [
        'lockout_attempts' => ['Échecs avant verrouillage', 1, 20],
        'lockout_minutes' => ['Durée du verrouillage (minutes)', 1, 1440],
        'session_minutes' => ['Expiration de session (minutes)', 5, 1440],
        'query_timeout' => ["Délai d'expiration d'une requête (s)", 5, 600],
        'query_retries' => ["Nouvelles tentatives en cas d'échec", 0, 10],
        'password_max_age_days' => ['R19 : ancienneté max. du mot de passe (jours)', 1, 3650],
    ];

    /** Durées de conservation proposées (minimum 3 ans) */
    public const RETENTION_YEARS = [3, 5, 7, 10];

    public function index(): View
    {
        $values = [];
        foreach (array_keys(Setting::DEFAULTS) as $key) {
            $values[$key] = Setting::get($key);
        }

        return view('admin.settings.index', [
            'values' => $values,
            'whitelist' => $this->whitelist(),
        ]);
    }

    /**
     * Enregistrer les cartes Sécurité, Collecte et Rétention.
     */
    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'retention_years' => ['required', 'integer', 'in:'.implode(',', self::RETENTION_YEARS)],
            'auto_purge' => ['required', 'boolean'],
        ];
        foreach (self::NUMERIC as $key => [$label, $min, $max]) {
            $rules[$key] = ['required', 'integer', "min:$min", "max:$max"];
        }

        $validated = $request->validate($rules, [], $this->attributeNames());

        // On écrit seulement ce qui a changé, et chaque changement va dans le journal
        foreach ($validated as $key => $value) {
            $old = Setting::get($key);
            $new = (string) (int) $value;

            if ($old !== $new) {
                Setting::set($key, $new);
                ActivityLogger::log('administration', 'Paramètre modifié', ($this->attributeNames()[$key] ?? $key)." : {$old} → {$new}");
            }
        }

        return redirect()->route('admin.settings.index')->with('status', 'Les paramètres ont été enregistrés.');
    }

    /**
     * Ajouter un compte à la liste blanche sysadmin (règle R01).
     */
    public function addWhitelist(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account' => ['required', 'string', 'max:128'],
        ], [], ['account' => 'compte']);

        $account = trim($validated['account']);
        $whitelist = $this->whitelist();

        // Les noms de login SQL Server ne tiennent pas compte de la casse
        $exists = collect($whitelist)->contains(fn ($item) => strcasecmp($item, $account) === 0);
        if ($exists) {
            return back()->withInput()->withErrors(['account' => 'Ce compte est déjà dans la liste blanche.']);
        }

        $whitelist[] = $account;
        Setting::set('sysadmin_whitelist', json_encode($whitelist));
        ActivityLogger::log('administration', 'Liste blanche modifiée', "Ajout : {$account}");

        return redirect()->route('admin.settings.index')->withFragment('liste-blanche')
            ->with('status', "{$account} a été ajouté à la liste blanche.");
    }

    /**
     * Retirer un compte de la liste blanche.
     */
    public function removeWhitelist(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account' => ['required', 'string'],
        ]);

        $whitelist = array_values(array_filter($this->whitelist(), fn ($item) => $item !== $validated['account']));
        Setting::set('sysadmin_whitelist', json_encode($whitelist));
        ActivityLogger::log('administration', 'Liste blanche modifiée', "Retrait : {$validated['account']}");

        return redirect()->route('admin.settings.index')->withFragment('liste-blanche')
            ->with('status', "{$validated['account']} a été retiré de la liste blanche.");
    }

    /**
     * La liste blanche est stockée en JSON dans la table settings.
     */
    protected function whitelist(): array
    {
        return json_decode(Setting::get('sysadmin_whitelist') ?? '[]', true) ?: [];
    }

    /**
     * Libellés français utilisés dans les messages d'erreur et le journal.
     */
    protected function attributeNames(): array
    {
        $names = ['retention_years' => 'Durée de conservation (ans)', 'auto_purge' => 'Purge automatique'];
        foreach (self::NUMERIC as $key => [$label]) {
            $names[$key] = $label;
        }

        return $names;
    }
}
