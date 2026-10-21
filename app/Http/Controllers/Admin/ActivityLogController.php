<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Journal en LECTURE SEULE : il n'existe aucune route pour modifier ou supprimer une ligne.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('admin.logs.index', [
            'logs' => $this->query($filters)->paginate(20)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    /**
     * Export CSV avec les mêmes filtres que l'écran.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $logs = $this->query($filters)->get();

        ActivityLogger::log('export', "Journal d'activité exporté", 'CSV · '.$logs->count().' lignes');

        $filename = 'journal-activite-'.$filters['from']->format('Ymd').'-'.$filters['to']->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM : accents corrects dans Excel
            fputcsv($out, ['Date', 'Utilisateur', 'Type', 'Action', 'Détail', 'Adresse IP'], ';');

            foreach ($logs as $log) {
                fputcsv($out, [
                    $log->created_at->format('d/m/Y H:i:s'),
                    $log->user?->email ?? '',
                    $log->typeLabel(),
                    $log->action,
                    $log->detail ?? '',
                    $log->ip_address ?? '',
                ], ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Lire et valider les filtres : type + période (7 derniers jours par défaut).
     */
    protected function filters(Request $request): array
    {
        // Un filtre invalide (ex. date mal saisie dans l'URL) est simplement ignoré
        $validated = Validator::make($request->query(), [
            'type' => ['nullable', 'in:'.implode(',', array_keys(ActivityLog::TYPES))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ])->valid();

        return [
            'type' => $validated['type'] ?? null,
            'from' => isset($validated['from']) ? Carbon::parse($validated['from']) : today()->subDays(7),
            'to' => isset($validated['to']) ? Carbon::parse($validated['to']) : today(),
        ];
    }

    protected function query(array $filters): Builder
    {
        return ActivityLog::with('user')
            ->when($filters['type'], fn ($query, $type) => $query->where('type', $type))
            ->where('created_at', '>=', $filters['from']->copy()->startOfDay())
            ->where('created_at', '<', $filters['to']->copy()->addDay()->startOfDay())
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
