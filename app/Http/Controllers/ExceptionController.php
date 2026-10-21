<?php

namespace App\Http\Controllers;

use App\Models\Finding;
use App\Models\FindingException;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Exceptions (acceptations de risque) :
 * le DBA propose (store), l'auditeur valide (approve) ou refuse (reject).
 */
class ExceptionController extends Controller
{
    /**
     * Page de l'auditeur, avec onglets En attente / Validées / Refusées / Toutes.
     */
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'pending');
        $status = isset(FindingException::STATUSES[$status]) || $status === 'all' ? $status : 'pending';

        $counts = FindingException::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $exceptions = FindingException::with(['finding.rule', 'finding.auditRun.instance', 'requester', 'reviewer'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return view('exceptions.index', [
            'exceptions' => $exceptions,
            'currentStatus' => $status,
            'counts' => $counts,
        ]);
    }

    /**
     * Le DBA propose une exception sur un constat (formulaire de la page Résultats).
     */
    public function store(Request $request, Finding $finding): RedirectResponse
    {
        // Erreurs rangées par constat, pour rouvrir le bon formulaire
        $validated = $request->validateWithBag("exception{$finding->id}", [
            'reason' => ['required', 'string', 'max:2000'],
            'requested_expiration' => ['required', 'date', 'after:today'],
        ], [
            'requested_expiration.after' => "La date d'expiration doit être dans le futur.",
        ], [
            'reason' => 'motif',
            'requested_expiration' => "date d'expiration",
        ]);

        if ($finding->exceptions()->where('status', 'pending')->exists()) {
            return back()->with('error', 'Une exception est déjà en attente de validation pour ce constat.');
        }

        $finding->exceptions()->create($validated + ['requested_by' => $request->user()->id]);

        $finding->load(['rule', 'auditRun.instance']);
        ActivityLogger::log('administration', 'Exception proposée',
            "{$finding->rule->code} · {$finding->object_name} · {$finding->auditRun->instance->name}");

        return back()->with('status', "Exception proposée pour {$finding->object_name}. Elle doit maintenant être validée par un auditeur.");
    }

    public function approve(Request $request, FindingException $exception): RedirectResponse
    {
        $validated = $this->review($request, $exception, required: false);

        $exception->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_comment' => $validated['review_comment'] ?? null,
        ]);

        ActivityLogger::log('administration', 'Exception validée', $this->describe($exception));

        return back()->with('status', 'Exception validée jusqu’au '.$exception->requested_expiration->format('d/m/Y').'.');
    }

    public function reject(Request $request, FindingException $exception): RedirectResponse
    {
        $validated = $this->review($request, $exception, required: true);

        $exception->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_comment' => $validated['review_comment'],
        ]);

        ActivityLogger::log('administration', 'Exception refusée', $this->describe($exception));

        return back()->with('status', 'Exception refusée.');
    }

    /**
     * Contrôles communs à la validation et au refus.
     */
    protected function review(Request $request, FindingException $exception, bool $required): array
    {
        // Séparation des tâches : on ne valide jamais sa propre demande
        if ($exception->requested_by === (int) $request->user()->id) {
            abort(403, 'Vous ne pouvez pas traiter une exception que vous avez demandée.');
        }

        if ($exception->status !== 'pending') {
            abort(409, 'Cette exception a déjà été traitée.');
        }

        // Commentaire obligatoire en cas de refus. Erreurs rangées par exception.
        return $request->validateWithBag("review{$exception->id}", [
            'review_comment' => [$required ? 'required' : 'nullable', 'string', 'max:2000'],
        ], [
            'review_comment.required' => 'Le commentaire est obligatoire pour refuser une exception.',
        ]);
    }

    protected function describe(FindingException $exception): string
    {
        $exception->load(['finding.rule', 'finding.auditRun.instance']);

        return "{$exception->finding->rule->code} · {$exception->finding->object_name} · {$exception->finding->auditRun->instance->name}";
    }
}
