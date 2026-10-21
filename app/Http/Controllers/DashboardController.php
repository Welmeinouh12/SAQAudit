<?php

namespace App\Http\Controllers;

use App\Models\AuditRun;
use App\Models\Finding;
use App\Models\FindingException;
use App\Models\Instance;
use App\Models\Rule;
use App\Services\AuditComparator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Tableau de bord DBA / auditeur.
     *
     * « Constats ouverts » = constats du DERNIER audit terminé de chaque instance
     * (un audit plus récent remplace les constats du précédent).
     */
    public function index(Request $request, AuditComparator $comparator): View
    {
        // L'auditeur a sa propre vue « Suivi de conformité »
        if ($request->user()->role === 'auditeur') {
            return $this->auditorDashboard($comparator);
        }

        // Le lecteur ne voit que des chiffres globaux
        if ($request->user()->role === 'lecteur') {
            return $this->readerDashboard();
        }

        $latestRunIds = $this->latestRunIds();

        // Nombre de constats ouverts par sévérité
        $severityCounts = Finding::whereIn('audit_run_id', $latestRunIds)
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $bySeverity = [];
        foreach (Rule::SEVERITIES as $severity => $label) {
            $bySeverity[$severity] = (int) ($severityCounts[$severity] ?? 0);
        }

        // Instances à risque : dernier audit de chaque instance, les plus critiques en premier
        $riskyRuns = AuditRun::whereIn('id', $latestRunIds)
            ->with('instance')
            ->withCount([
                'findings',
                'findings as critical_count' => fn ($query) => $query->where('severity', 'critique'),
            ])
            ->get()
            ->sortByDesc(fn ($run) => [$run->critical_count, $run->findings_count])
            ->take(5);

        return view('dashboard', [
            'instancesCount' => Instance::count(),
            'auditsThisMonth' => AuditRun::where('started_at', '>=', now()->startOfMonth())->count(),
            'openFindings' => array_sum($bySeverity),
            'criticalFindings' => $bySeverity['critique'],
            'bySeverity' => $bySeverity,
            'maxSeverity' => max(1, max($bySeverity)), // évite une division par zéro
            'riskyRuns' => $riskyRuns,
            'recentRuns' => AuditRun::with(['instance', 'user'])
                ->withCount('findings')
                ->latest('started_at')
                ->latest('id')
                ->take(5)
                ->get(),
        ]);
    }

    /**
     * Tableau de bord auditeur : risques ouverts, exceptions à valider, évolution entre audits.
     */
    protected function auditorDashboard(AuditComparator $comparator): View
    {
        $latestRunIds = $this->latestRunIds();

        // Constats critiques ouverts : dernier audit de chaque instance, hors exception validée en cours
        $criticalOpen = Finding::whereIn('audit_run_id', $latestRunIds)
            ->where('severity', 'critique')
            ->whereDoesntHave('exceptions', function ($query) {
                $query->where('status', 'approved')->whereDate('requested_expiration', '>=', today());
            })
            ->count();

        // Constats résolus ce mois : pour chaque audit du mois, comparaison avec l'audit précédent
        $resolvedThisMonth = 0;
        $runsThisMonth = AuditRun::where('status', 'completed')
            ->where('started_at', '>=', now()->startOfMonth())
            ->get();
        foreach ($runsThisMonth as $run) {
            if ($previous = $comparator->previousRun($run)) {
                $resolvedThisMonth += $comparator->compare($previous, $run)['resolved']->count();
            }
        }

        // Évolution par instance : dernier audit comparé au précédent
        $evolution = AuditRun::whereIn('id', $latestRunIds)
            ->with('instance')
            ->get()
            ->sortBy(fn ($run) => $run->instance->name)
            ->map(function ($run) use ($comparator) {
                $previous = $comparator->previousRun($run);
                $diff = $previous ? $comparator->compare($previous, $run) : null;

                return [
                    'instance' => $run->instance,
                    'new' => $diff ? $diff['new']->count() : null,
                    'resolved' => $diff ? $diff['resolved']->count() : null,
                    'open' => $run->findings()->count(),
                ];
            });

        return view('dashboard-auditor', [
            'criticalOpen' => $criticalOpen,
            'auditedInstances' => $latestRunIds->count(),
            'pendingCount' => FindingException::where('status', 'pending')->count(),
            'expiringSoon' => FindingException::where('status', 'approved')
                ->whereDate('requested_expiration', '>=', today())
                ->whereDate('requested_expiration', '<=', today()->addDays(30))
                ->count(),
            'resolvedThisMonth' => $resolvedThisMonth,
            'pendingExceptions' => FindingException::where('status', 'pending')
                ->with(['finding.rule', 'finding.auditRun.instance', 'requester'])
                ->oldest()
                ->take(5)
                ->get(),
            'evolution' => $evolution,
        ]);
    }

    /**
     * Tableau de bord lecteur : chiffres globaux uniquement (aucun login, aucune preuve).
     */
    protected function readerDashboard(): View
    {
        $latestRunIds = $this->latestRunIds();

        $severityCounts = Finding::whereIn('audit_run_id', $latestRunIds)
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $bySeverity = [];
        foreach (Rule::SEVERITIES as $severity => $label) {
            $bySeverity[$severity] = (int) ($severityCounts[$severity] ?? 0);
        }
        $openFindings = array_sum($bySeverity);

        // Niveau de risque : Élevé (au moins 1 critique), Modéré (des constats élevés), sinon Faible
        $riskLevels = AuditRun::whereIn('id', $latestRunIds)
            ->with('instance')
            ->withCount([
                'findings as critical_count' => fn ($query) => $query->where('severity', 'critique'),
                'findings as high_count' => fn ($query) => $query->where('severity', 'elevee'),
            ])
            ->get()
            ->sortBy(fn ($run) => $run->instance->name)
            ->map(fn ($run) => [
                'run' => $run,
                'level' => match (true) {
                    $run->critical_count > 0 => ['Élevé', 'critique'],
                    $run->high_count > 0 => ['Modéré', 'moyenne'],
                    default => ['Faible', 'actif'],
                },
            ]);

        // Évolution sur 6 mois : constats du dernier audit terminé de chaque instance dans le mois
        $monthNames = [1 => 'Janv.', 'Févr.', 'Mars', 'Avr.', 'Mai', 'Juin', 'Juil.', 'Août', 'Sept.', 'Oct.', 'Nov.', 'Déc.'];
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = now()->startOfMonth()->subMonthsNoOverflow($i);
            $months[] = [
                'label' => $monthNames[$start->month],
                'value' => $this->openFindingsInMonth($start),
                'current' => $i === 0,
            ];
        }

        // Variation par rapport au mois dernier (null si aucun audit le mois dernier)
        $lastMonth = $months[4]['value'];
        $delta = $this->hasAuditInMonth(now()->startOfMonth()->subMonthNoOverflow())
            ? $openFindings - $lastMonth
            : null;

        return view('lecteur.dashboard', [
            'auditedRecently' => AuditRun::where('status', 'completed')
                ->where('started_at', '>=', now()->subDays(30))
                ->distinct()
                ->count('instance_id'),
            'instancesCount' => Instance::count(),
            'openFindings' => $openFindings,
            'delta' => $delta,
            'criticalFindings' => $bySeverity['critique'],
            'approvedExceptions' => FindingException::where('status', 'approved')
                ->whereDate('requested_expiration', '>=', today())
                ->count(),
            'bySeverity' => $bySeverity,
            'maxSeverity' => max(1, max($bySeverity)),
            'riskLevels' => $riskLevels,
            'months' => $months,
            'maxMonth' => max(1, max(array_column($months, 'value'))),
        ]);
    }

    /**
     * Nombre de constats du dernier audit terminé de chaque instance, dans le mois donné.
     */
    protected function openFindingsInMonth($start): int
    {
        $runIds = AuditRun::where('status', 'completed')
            ->whereBetween('started_at', [$start, $start->copy()->endOfMonth()])
            ->selectRaw('MAX(id) as id')
            ->groupBy('instance_id')
            ->pluck('id');

        return Finding::whereIn('audit_run_id', $runIds)->count();
    }

    protected function hasAuditInMonth($start): bool
    {
        return AuditRun::where('status', 'completed')
            ->whereBetween('started_at', [$start, $start->copy()->endOfMonth()])
            ->exists();
    }

    /**
     * Identifiants du dernier audit terminé de chaque instance.
     */
    protected function latestRunIds(): Collection
    {
        return AuditRun::where('status', 'completed')
            ->selectRaw('MAX(id) as id')
            ->groupBy('instance_id')
            ->pluck('id');
    }
}
