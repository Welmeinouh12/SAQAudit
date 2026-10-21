<?php

namespace App\Http\Controllers;

use App\Models\AuditRun;
use App\Models\Instance;
use App\Models\Rule;
use App\Services\ActivityLogger;
use App\Services\AuditComparator;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditController extends Controller
{
    /**
     * Bouton « Auditer » (ou « Relancer l'audit ») : l'audit s'exécute directement,
     * puis on affiche la page Résultats.
     */
    public function store(Request $request, Instance $instance, AuditService $audits): RedirectResponse
    {
        $run = $audits->run($instance, $request->user());

        if ($run->status === 'failed') {
            return redirect()->route('audits.show', $run)
                ->with('error', "L'audit de {$instance->name} a échoué : {$run->error_message}");
        }

        return redirect()->route('audits.show', $run)
            ->with('status', "Audit terminé : {$run->findings()->count()} constats.");
    }

    /**
     * Liste de tous les audits, filtrable par instance.
     */
    public function index(Request $request): View
    {
        $instances = Instance::orderBy('name')->get();

        // Filtre ignoré si l'instance n'existe pas
        $current = $instances->firstWhere('id', (int) $request->query('instance'));

        $runs = AuditRun::with(['instance', 'user'])
            ->withCount('findings')
            ->when($current, fn ($query) => $query->where('instance_id', $current->id))
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('audits.index', [
            'runs' => $runs,
            'instances' => $instances,
            'currentInstance' => $current,
        ]);
    }

    /**
     * Page Résultats d'un audit, avec filtre par sévérité.
     */
    public function show(Request $request, AuditRun $auditRun): View
    {
        $severity = (string) $request->query('severity');
        $severity = isset(Rule::SEVERITIES[$severity]) ? $severity : null;

        // Ordre d'affichage : Critique, Élevée, Moyenne, Faible, puis code de la règle
        $order = array_flip(array_keys(Rule::SEVERITIES));

        $findings = $auditRun->findings()
            ->with(['rule', 'activeException', 'exceptions' => fn ($query) => $query->latest('id')])
            ->when($severity, fn ($query) => $query->where('severity', $severity))
            ->get()
            ->sortBy(fn ($finding) => [$order[$finding->severity] ?? 9, $finding->rule->code, $finding->object_name])
            ->values();

        return view('audits.show', [
            'run' => $auditRun->load(['instance', 'user']),
            'findings' => $findings,
            'currentSeverity' => $severity,
        ]);
    }

    /**
     * Comparer deux audits terminés d'une même instance.
     * Par défaut : la première instance ayant au moins deux audits, ses deux derniers audits.
     */
    public function compare(Request $request, AuditComparator $comparator): View
    {
        // Un paramètre invalide (ex. modifié dans l'URL) est simplement ignoré
        $input = Validator::make($request->query(), [
            'instance' => ['nullable', 'integer'],
            'reference' => ['nullable', 'integer'],
            'compared' => ['nullable', 'integer'],
            'filter' => ['nullable', 'in:new,resolved,unchanged'],
        ])->valid();

        // Instances qui ont au moins un audit terminé
        $instances = Instance::whereHas('auditRuns', fn ($query) => $query->where('status', 'completed'))
            ->withCount(['auditRuns as completed_count' => fn ($query) => $query->where('status', 'completed')])
            ->orderBy('name')
            ->get();

        $instance = $instances->firstWhere('id', $input['instance'] ?? null)
            ?? $instances->firstWhere(fn ($item) => $item->completed_count >= 2)
            ?? $instances->first();

        // Audits terminés de l'instance choisie, du plus récent au plus ancien
        $runs = $instance
            ? $instance->auditRuns()->where('status', 'completed')->orderByDesc('id')->get()
            : collect();

        $compared = $runs->firstWhere('id', $input['compared'] ?? null) ?? $runs->first();
        $reference = $runs->firstWhere('id', $input['reference'] ?? null)
            ?? $runs->first(fn ($run) => $compared && $run->id < $compared->id);

        $diff = null;
        $rows = collect();
        if ($reference && $compared && $reference->id !== $compared->id) {
            $diff = $comparator->compare($reference, $compared);

            // Lignes du tableau : nouveaux, puis résolus, puis inchangés ; par sévérité dans chaque groupe
            $order = array_flip(array_keys(Rule::SEVERITIES));
            foreach (['new', 'resolved', 'unchanged'] as $evolution) {
                if (! empty($input['filter']) && $input['filter'] !== $evolution) {
                    continue;
                }
                $group = $diff[$evolution]->sortBy(fn ($finding) => [$order[$finding->severity] ?? 9, $finding->rule->code]);
                foreach ($group as $finding) {
                    $rows->push(['evolution' => $evolution, 'finding' => $finding]);
                }
            }
        }

        return view('audits.compare', [
            'instances' => $instances,
            'instance' => $instance,
            'runs' => $runs,
            'reference' => $reference,
            'compared' => $compared,
            'diff' => $diff,
            'rows' => $rows,
            'currentFilter' => $input['filter'] ?? null,
        ]);
    }

    /**
     * Export CSV des constats (séparateur « ; », UTF-8 avec BOM pour Excel).
     */
    public function csv(AuditRun $auditRun): StreamedResponse
    {
        $auditRun->load('instance');
        $findings = $auditRun->findings()->with(['rule', 'activeException'])->orderBy('id')->get();

        ActivityLogger::log('export', 'Constats exportés', "{$auditRun->instance->name} · audit #{$auditRun->id} · CSV");

        $filename = "audit-{$auditRun->id}-{$auditRun->instance->name}.csv";

        return response()->streamDownload(function () use ($findings) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM : accents corrects dans Excel
            fputcsv($out, ['Règle', 'Libellé', 'Sévérité', 'Objet', 'Preuve', 'Recommandation', 'Exception validée jusqu’au'], ';');

            foreach ($findings as $finding) {
                fputcsv($out, [
                    $finding->rule->code,
                    $finding->rule->label,
                    $finding->severityLabel(),
                    $finding->object_name,
                    $finding->evidence,
                    $finding->recommendation,
                    $finding->activeException?->requested_expiration->format('d/m/Y') ?? '',
                ], ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
