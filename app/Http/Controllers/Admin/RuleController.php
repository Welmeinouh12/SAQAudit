<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rule;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RuleController extends Controller
{
    /**
     * Catalogue des règles R01 à R22, filtrable par sévérité.
     */
    public function index(Request $request): View
    {
        // Filtre ignoré si la valeur n'est pas une sévérité connue
        $severity = (string) $request->query('severity');
        $severity = isset(Rule::SEVERITIES[$severity]) ? $severity : null;

        // Même page pour l'admin (/admin/rules) et en consultation pour DBA et auditeur (/rules)
        return view('admin.rules.index', [
            'canEdit' => $request->user()->role === 'admin',
            'listRoute' => $request->route()->getName(),
            'rules' => Rule::when($severity, fn ($query) => $query->where('severity', $severity))
                ->orderBy('code')
                ->get(),
            'currentSeverity' => $severity,
            'activeCount' => Rule::where('is_active', true)->count(),
            'totalCount' => Rule::count(),
        ]);
    }

    /**
     * Activer ou désactiver une règle (l'interrupteur de la colonne « Active »).
     */
    public function toggle(Rule $rule): RedirectResponse
    {
        $rule->is_active = ! $rule->is_active;
        $rule->save();

        $state = $rule->is_active ? 'activée' : 'désactivée';
        ActivityLogger::log('administration', 'Règle modifiée', "{$rule->code} {$state}");

        return back()->with('status', "La règle {$rule->code} a été {$state}.");
    }
}
