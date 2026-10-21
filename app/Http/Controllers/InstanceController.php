<?php

namespace App\Http\Controllers;

use App\Models\Instance;
use App\Services\ActivityLogger;
use App\Services\InstanceConnector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstanceController extends Controller
{
    /**
     * Liste des instances (avec recherche par nom ou hôte) + formulaire d'ajout.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $instances = Instance::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('host', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->get();

        return view('instances.index', [
            'instances' => $instances,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $instance = Instance::create($this->validateInstance($request));

        ActivityLogger::log('administration', 'Instance ajoutée', "{$instance->name} · {$instance->host}:{$instance->port}");

        return redirect()->route('instances.index')->with('status', "L'instance {$instance->name} a été ajoutée. Cliquez sur « Tester » pour vérifier la connexion.");
    }

    /**
     * Bouton « Tester » d'une ligne du tableau : le résultat est enregistré sur l'instance.
     */
    public function test(Instance $instance, InstanceConnector $connector): RedirectResponse
    {
        $result = $connector->test($instance);

        $instance->forceFill([
            'last_test_status' => $result['ok'] ? 'ok' : 'failed',
            'last_test_message' => $result['message'],
            'last_tested_at' => now(),
            'sql_version' => $result['version'] ?? $instance->sql_version,
        ])->save();

        ActivityLogger::log('audit', 'Test de connexion', "{$instance->name} · ".($result['ok'] ? 'réussi' : 'échec'));

        return back()->with($result['ok'] ? 'status' : 'error', "{$instance->name} : {$result['message']}");
    }

    /**
     * Bouton « Tester la connexion » du formulaire : teste sans enregistrer l'instance.
     */
    public function testNew(Request $request, InstanceConnector $connector): RedirectResponse
    {
        $instance = new Instance($this->validateInstance($request));
        $result = $connector->test($instance);

        ActivityLogger::log('audit', 'Test de connexion', "{$instance->name} (non enregistrée) · ".($result['ok'] ? 'réussi' : 'échec'));

        // Le mot de passe n'est jamais renvoyé au formulaire
        return back()
            ->withInput($request->except('password'))
            ->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    /**
     * Validation commune à l'ajout et au test.
     */
    protected function validateInstance(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.\-]+$/', 'unique:instances,name'],
            'host' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.\-]+$/'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'environment' => ['required', Rule::in(array_keys(Instance::ENVIRONMENTS))],
            'username' => ['required', 'string', 'max:128'],
            'password' => ['required', 'string', 'max:128'],
        ], [
            'name.regex' => 'Le nom ne peut contenir que des lettres, chiffres, points, tirets et soulignés.',
            'host.regex' => "L'hôte doit être un nom de serveur ou une adresse IP (le port se saisit à part).",
        ], [
            'name' => 'nom',
            'host' => 'hôte',
            'port' => 'port',
            'environment' => 'environnement',
            'username' => 'compte de collecte',
        ]);
    }
}
