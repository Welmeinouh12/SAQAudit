@extends('layouts.main')

@section('title', 'Exceptions')
@section('heading', 'Exceptions (acceptations de risque)')
@section('subheading')
    <span class="block max-w-[720px]">
        Le DBA propose une exception quand un constat est justifié. L'auditeur la valide ou la refuse.
        Une exception validée masque le constat jusqu'à sa date d'expiration.
    </span>
@endsection

@php
    // Onglets : [valeur, libellé, compteur affiché ?]
    $tabs = [
        ['pending', 'En attente', true],
        ['approved', 'Validées', true],
        ['rejected', 'Refusées', true],
        ['all', 'Toutes', false],
    ];
@endphp

@section('content')
    <div class="max-w-[1104px]">
        {{-- Onglets-pilules --}}
        <nav class="mb-6 flex flex-wrap gap-2" aria-label="Filtrer par statut">
            @foreach ($tabs as [$value, $label, $withCount])
                <a href="{{ route('exceptions.index', ['status' => $value]) }}"
                   @class(['pill h-10 px-4 text-sm', 'pill-active' => $currentStatus === $value])>
                    {{ $label }}@if ($withCount) ({{ $counts[$value] ?? 0 }})@endif
                </a>
            @endforeach
        </nav>

        <div class="space-y-6">
            @forelse ($exceptions as $exception)
                @php
                    $finding = $exception->finding;
                    $isOwnRequest = $exception->requested_by === (int) auth()->id();
                    $bag = $errors->getBag("review{$exception->id}");
                @endphp

                <x-card>
                    {{-- En-tête : règle, sévérité, statut --}}
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="flex flex-wrap items-center gap-2 text-[15px] font-semibold">
                                <span class="font-mono">{{ $finding->rule->code }}</span>
                                {{ $finding->rule->label }}
                                <x-badge :color="$finding->severity" class="font-semibold">{{ $finding->severityLabel() }}</x-badge>
                            </h2>
                            <p class="mt-1.5 text-sm text-[#3D4552]">
                                Objet <span class="font-mono">{{ $finding->object_name }}</span>
                                sur <span class="font-mono">{{ $finding->auditRun->instance->name }}</span>
                                · <a href="{{ route('audits.show', $finding->auditRun) }}" class="text-primary underline">audit #{{ $finding->audit_run_id }}</a>
                            </p>
                        </div>
                        <x-badge :color="$exception->statusColor()" class="font-semibold">
                            {{ $exception->statusLabel() }}@if ($exception->status === 'approved' && ! $exception->isActive()) · expirée @endif
                        </x-badge>
                    </div>

                    {{-- Bloc gris : demandeur, dates --}}
                    <dl class="mt-5 grid gap-4 rounded-lg bg-page px-4 py-4 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-muted">Demandeur</dt>
                            <dd class="mt-1 text-sm">{{ $exception->requester->email }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-muted">Date de demande</dt>
                            <dd class="mt-1 text-sm">{{ $exception->created_at->format('d/m/Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-muted">Expiration demandée</dt>
                            <dd class="mt-1 text-sm">{{ $exception->requested_expiration->format('d/m/Y') }}</dd>
                        </div>
                    </dl>

                    {{-- Motif --}}
                    <div class="mt-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted">Motif</p>
                        <p class="mt-1.5 text-[15px]">{{ $exception->reason }}</p>
                    </div>

                    <div class="mt-5 border-t border-line-soft pt-5">
                        @if ($exception->status === 'pending')
                            @if ($isOwnRequest)
                                {{-- Séparation des tâches --}}
                                <p class="text-sm text-muted">Vous avez demandé cette exception : un autre auditeur doit la traiter.</p>
                            @else
                                <form method="POST" action="{{ route('exceptions.approve', $exception) }}">
                                    @csrf
                                    @method('PATCH')

                                    <label for="comment-{{ $exception->id }}" class="form-label">Commentaire de l'auditeur</label>
                                    <textarea id="comment-{{ $exception->id }}" name="review_comment" rows="2"
                                              placeholder="Obligatoire en cas de refus"
                                              class="w-full rounded-lg border-field text-sm placeholder:text-[#8A919C] focus:border-primary focus:ring-primary">{{ $bag->any() ? old('review_comment') : '' }}</textarea>
                                    @if ($bag->has('review_comment')) <p class="form-error">{{ $bag->first('review_comment') }}</p> @endif

                                    <div class="mt-3 flex flex-wrap justify-end gap-2.5">
                                        {{-- formaction : même formulaire, envoyé vers la route de refus --}}
                                        <button type="submit" formaction="{{ route('exceptions.reject', $exception) }}"
                                                class="btn-secondary border-[#8B1A1A] px-5 font-semibold text-[#8B1A1A] hover:bg-[#FBE4E4]">
                                            Refuser
                                        </button>
                                        <button type="submit" class="btn-primary">Valider l'exception</button>
                                    </div>
                                </form>
                            @endif
                        @else
                            <p class="text-sm">
                                {{ $exception->status === 'approved' ? 'Validée' : 'Refusée' }}
                                par <strong class="font-semibold">{{ $exception->reviewer?->name ?? '—' }}</strong>
                                le {{ $exception->reviewed_at?->format('d/m/Y à H:i') }}
                            </p>
                            @if ($exception->review_comment)
                                <p class="mt-1 text-sm text-muted">« {{ $exception->review_comment }} »</p>
                            @endif
                        @endif
                    </div>
                </x-card>
            @empty
                <x-card>
                    <p class="py-4 text-center text-sm text-muted">Aucune exception dans cette catégorie.</p>
                </x-card>
            @endforelse
        </div>

        <p class="mt-6 text-[13px] text-muted">
            Séparation des tâches : un utilisateur ne peut jamais valider une exception qu'il a lui-même demandée.
        </p>
    </div>
@endsection
