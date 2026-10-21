<?php

namespace App\Http\Controllers;

use App\Models\Instance;
use App\Models\ReportExport;
use App\Services\ActivityLogger;
use App\Services\ReportGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    /** Formats disponibles pour l'instant (Excel viendra avec maatwebsite/excel) */
    public const AVAILABLE_FORMATS = ['pdf', 'csv'];

    public function index(): View
    {
        return view('reports.index', [
            // Seules les instances qui ont au moins un audit terminé
            'instances' => Instance::whereHas('auditRuns', fn ($query) => $query->where('status', 'completed'))
                ->orderBy('name')
                ->get(),
            'exports' => ReportExport::latest()->latest('id')->take(10)->get(),
        ]);
    }

    public function store(Request $request, ReportGenerator $generator): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(ReportExport::TYPES))],
            'scope' => ['required'], // « all » ou un identifiant d'instance, vérifié juste après
            'format' => ['required', Rule::in(self::AVAILABLE_FORMATS)],
            'include_exceptions' => ['nullable', 'boolean'],
        ], [
            'format.in' => 'Le format Excel n’est pas encore disponible : choisissez PDF ou CSV.',
        ], [
            'type' => 'type de rapport',
            'scope' => 'périmètre',
        ]);

        // Périmètre : « all » ou l'identifiant d'une instance auditée
        $instance = null;
        if ($validated['scope'] !== 'all') {
            $instance = Instance::whereKey($validated['scope'])
                ->whereHas('auditRuns', fn ($query) => $query->where('status', 'completed'))
                ->first();

            if (! $instance) {
                return back()->withInput()->withErrors(['scope' => 'Choisissez une instance qui a au moins un audit terminé.']);
            }
        }

        $export = $generator->generate(
            $request->user(),
            $validated['type'],
            $instance,
            $validated['format'],
            $request->boolean('include_exceptions'),
        );

        return redirect()->route('reports.index')
            ->with('status', "Le rapport « {$export->typeLabel()} » ({$export->formatLabel()}) a été généré.");
    }

    /**
     * Téléchargement via une route protégée : le fichier n'est jamais dans public.
     */
    public function download(ReportExport $reportExport): BinaryFileResponse
    {
        $path = storage_path('app/'.$reportExport->file_path);
        abort_unless(is_file($path), 404, 'Fichier introuvable.');

        ActivityLogger::log('export', 'Rapport téléchargé', "{$reportExport->typeLabel()} · {$reportExport->scope} · {$reportExport->formatLabel()}");

        return response()->download($path, basename($path));
    }
}
