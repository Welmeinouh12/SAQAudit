<?php

namespace App\Http\Controllers;

use App\Models\ReportExport;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Rapports de synthèse publiés, consultés par le lecteur.
 * Seules les synthèses PDF sont visibles : jamais les rapports détaillés.
 */
class SyntheseController extends Controller
{
    /** Filtres : Tous, Mensuels (toutes les instances), Par instance */
    public const FILTERS = ['all', 'monthly', 'instance'];

    public function index(Request $request): View
    {
        $filter = (string) $request->query('filter', 'all');
        $filter = in_array($filter, self::FILTERS, true) ? $filter : 'all';

        $reports = $this->syntheses()
            ->with('instance')
            ->when($filter === 'monthly', fn ($query) => $query->whereNull('instance_id'))
            ->when($filter === 'instance', fn ($query) => $query->whereNotNull('instance_id'))
            ->latest()
            ->latest('id')
            ->get();

        // Tendance : écart avec la synthèse précédente du même périmètre
        $trends = [];
        foreach ($reports as $report) {
            $previous = $this->syntheses()
                ->where('instance_id', $report->instance_id)
                ->where('created_at', '<', $report->created_at)
                ->whereNotNull('findings_count')
                ->latest()
                ->latest('id')
                ->first();

            $trends[$report->id] = ($previous && $report->findings_count !== null)
                ? $report->findings_count - $previous->findings_count
                : null;
        }

        return view('lecteur.syntheses', [
            'reports' => $reports,
            'trends' => $trends,
            'currentFilter' => $filter,
        ]);
    }

    public function download(ReportExport $report): BinaryFileResponse
    {
        // Uniquement les synthèses PDF : un rapport détaillé contient des noms de logins
        abort_unless($report->type === 'synthese' && $report->format === 'pdf', 403);

        $path = storage_path('app/'.$report->file_path);
        abort_unless(is_file($path), 404, 'Fichier introuvable.');

        ActivityLogger::log('export', 'Synthèse téléchargée', "{$report->scope} · ".$report->created_at->format('d/m/Y'));

        return response()->download($path, basename($path));
    }

    /**
     * Requête de base : synthèses de conformité au format PDF.
     */
    protected function syntheses()
    {
        return ReportExport::where('type', 'synthese')->where('format', 'pdf');
    }
}
