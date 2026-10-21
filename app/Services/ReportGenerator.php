<?php

namespace App\Services;

use App\Models\AuditRun;
use App\Models\Instance;
use App\Models\ReportExport;
use App\Models\Rule;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Génère un rapport (PDF ou CSV), l'enregistre dans storage/app/reports
 * (jamais dans public) et le trace dans report_exports et activity_logs.
 */
class ReportGenerator
{
    public function generate(User $user, string $type, ?Instance $instance, string $format, bool $includeExceptions): ReportExport
    {
        $runs = $this->latestRuns($instance);
        $scope = $instance
            ? $instance->name.($runs->first() ? ' · #'.$runs->first()->id : '')
            : 'Toutes les instances';

        // Empreinte : celle de l'audit, ou une empreinte des empreintes s'il y a plusieurs audits
        $fingerprint = $runs->count() === 1
            ? $runs->first()->fingerprint
            : ($runs->isNotEmpty() ? hash('sha256', $runs->pluck('fingerprint')->implode('|')) : null);

        $data = [
            'type' => $type,
            'typeLabel' => ReportExport::TYPES[$type],
            'scope' => $scope,
            'runs' => $runs,
            'includeExceptions' => $includeExceptions,
            'fingerprint' => $fingerprint,
            'generatedAt' => now(),
            'generatedBy' => $user,
        ];

        $content = $format === 'pdf'
            ? Pdf::loadView('reports.pdf', $data)->setPaper('a4', $type === 'detail' ? 'landscape' : 'portrait')->output()
            : $this->csv($type, $runs, $includeExceptions);

        // storage/app/reports : dossier privé, téléchargement uniquement via une route protégée
        $relativePath = 'reports/'.now()->format('Ymd-His').'-'.$type.'-'.str()->slug($scope).'.'.$format;
        File::ensureDirectoryExists(storage_path('app/reports'));
        File::put(storage_path('app/'.$relativePath), $content);

        // Chiffres figés au moment de la génération (cartes du lecteur)
        $findings = $runs->flatMap(fn ($run) => $this->visibleFindings($run, $includeExceptions));

        $export = $user->reportExports()->create([
            'type' => $type,
            'scope' => $scope,
            'format' => $format,
            'file_path' => $relativePath,
            'fingerprint' => $fingerprint,
            'instance_id' => $instance?->id,
            'findings_count' => $findings->count(),
            'critical_count' => $findings->where('severity', 'critique')->count(),
            'instances_count' => $runs->count(),
        ]);

        ActivityLogger::log('export', 'Rapport généré', "{$data['typeLabel']} · {$scope} · ".strtoupper($format), $user);

        return $export;
    }

    /**
     * Dernier audit terminé de l'instance choisie, ou de chaque instance.
     * Les constats sont chargés avec leur règle et leur exception validée en cours.
     */
    public function latestRuns(?Instance $instance): Collection
    {
        $ids = AuditRun::where('status', 'completed')
            ->when($instance, fn ($query) => $query->where('instance_id', $instance->id))
            ->selectRaw('MAX(id) as id')
            ->groupBy('instance_id')
            ->pluck('id');

        $order = array_flip(array_keys(Rule::SEVERITIES));

        return AuditRun::whereIn('id', $ids)
            ->with(['instance', 'user', 'findings.rule', 'findings.activeException'])
            ->get()
            ->sortBy(fn ($run) => $run->instance->name)
            ->each(fn ($run) => $run->setRelation('findings', $run->findings
                ->sortBy(fn ($finding) => [$order[$finding->severity] ?? 9, $finding->rule->code])
                ->values()))
            ->values();
    }

    /**
     * Contenu CSV : séparateur « ; », UTF-8 avec BOM pour Excel.
     */
    protected function csv(string $type, Collection $runs, bool $includeExceptions): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");

        if ($type === 'detail') {
            fputcsv($out, ['Instance', 'Audit', 'Date', 'Règle', 'Libellé', 'Sévérité', 'Objet', 'Preuve', 'Recommandation', 'Exception validée jusqu’au', 'Empreinte SHA-256'], ';');
            foreach ($runs as $run) {
                foreach ($this->visibleFindings($run, $includeExceptions) as $finding) {
                    fputcsv($out, [
                        $run->instance->name, '#'.$run->id, $run->started_at?->format('d/m/Y H:i'),
                        $finding->rule->code, $finding->rule->label, $finding->severityLabel(),
                        $finding->object_name, $finding->evidence, $finding->recommendation,
                        $finding->activeException?->requested_expiration->format('d/m/Y') ?? '',
                        $run->fingerprint,
                    ], ';');
                }
            }
        } else {
            fputcsv($out, ['Instance', 'Dernier audit', 'Date', 'Critique', 'Élevée', 'Moyenne', 'Faible', 'Total', 'Empreinte SHA-256'], ';');
            foreach ($runs as $run) {
                $findings = $this->visibleFindings($run, $includeExceptions);
                $line = [$run->instance->name, '#'.$run->id, $run->started_at?->format('d/m/Y H:i')];
                foreach (array_keys(Rule::SEVERITIES) as $severity) {
                    $line[] = $findings->where('severity', $severity)->count();
                }
                fputcsv($out, [...$line, $findings->count(), $run->fingerprint], ';');
            }
        }

        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);

        return $content;
    }

    /**
     * Constats à inclure : sans « Inclure les exceptions validées », les constats sous exception sont retirés.
     */
    public function visibleFindings(AuditRun $run, bool $includeExceptions): Collection
    {
        return $includeExceptions
            ? $run->findings
            : $run->findings->reject(fn ($finding) => $finding->activeException);
    }
}
