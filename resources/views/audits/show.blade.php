@extends('layouts.main')

@section('title', "Audit #{$run->id}")

@php
    $isDba = auth()->user()->role === 'dba';

    // Couleur du statut dans la première carte
    $statusText = [
        'completed' => 'text-[#1D5B36]',
        'failed' => 'text-[#8B1A1A]',
        'running' => 'text-primary',
    ];

    // Empreinte abrégée : 4 premiers … 4 derniers caractères
    $shortFingerprint = $run->fingerprint ? substr($run->fingerprint, 0, 4).'…'.substr($run->fingerprint, -4) : '—';

    $backUrl = Route::has('audits.index') ? route('audits.index') : route('dashboard');
    $hiddenCount = $findings->filter(fn ($finding) => $finding->activeException)->count();
@endphp

@section('breadcrumb')
    <a href="{{ $backUrl }}" class="mb-1 inline-block text-[13px] text-primary underline">← Retour</a>
@endsection

{{-- Titre avec le nom de l'instance en IBM Plex Mono --}}
@section('heading')
    Audit #{{ $run->id }} · <span class="font-mono">{{ $run->instance->name }}</span>
@endsection

@section('subheading')
    {{ $run->started_at?->format('d/m/Y') }} de {{ $run->started_at?->format('H:i') }}
    @if ($run->ended_at) à {{ $run->ended_at->format('H:i') }} @endif
    · lancé par {{ $run->user?->name ?? '—' }}
@endsection

@section('actions')
    <a href="{{ route('audits.csv', $run) }}" class="btn-secondary">Exporter CSV</a>
    @if ($isDba)
        {{-- Relancer = nouvel audit de la même instance --}}
        <form method="POST" action="{{ route('audits.store', $run->instance) }}">
            @csrf
            <button type="submit" class="btn-primary">Relancer l'audit</button>
        </form>
    @endif
@endsection

@section('content')
    {{-- Cartes de synthèse --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-line bg-white px-5 py-5">
            <p class="text-[13px] font-medium text-[#3D4552]">Statut</p>
            <p class="mt-2 text-xl font-semibold {{ $statusText[$run->status] ?? '' }}">{{ $run->statusLabel() }}</p>
        </div>
        <div class="rounded-xl border border-line bg-white px-5 py-5">
            <p class="text-[13px] font-medium text-[#3D4552]">Logins collectés</p>
            <p class="mt-2 text-xl font-semibold">{{ $run->logins_count ?? '—' }}</p>
        </div>
        <div class="rounded-xl border border-line bg-white px-5 py-5">
            <p class="text-[13px] font-medium text-[#3D4552]">Bases collectées</p>
            <p class="mt-2 text-xl font-semibold">{{ $run->databases_count ?? '—' }}</p>
        </div>
        <div class="rounded-xl border border-line bg-white px-5 py-5">
            <p class="text-[13px] font-medium text-[#3D4552]">Empreinte SHA-256</p>
            <p class="mt-2.5 font-mono text-sm" title="{{ $run->fingerprint }}">{{ $shortFingerprint }}</p>
        </div>
    </div>

    @if ($run->status === 'failed' && $run->error_message)
        <div class="mt-4 rounded-lg border border-[#F2C2C2] bg-[#FBE4E4] px-4 py-3 text-sm text-[#8B1A1A]" role="alert">
            {{ $run->error_message }}
        </div>
    @endif

    {{-- Constats --}}
    <x-card class="mt-6">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-[17px] font-semibold">
                Constats ({{ $findings->count() }})
                @if ($hiddenCount)
                    <span class="text-sm font-normal text-muted">· {{ $hiddenCount }} sous exception validée</span>
                @endif
            </h2>

            <nav class="flex flex-wrap gap-2" aria-label="Filtrer par sévérité">
                <a href="{{ route('audits.show', $run) }}" @class(['pill', 'pill-active' => $currentSeverity === null])>Toutes</a>
                @foreach (\App\Models\Rule::SEVERITIES as $value => $label)
                    <a href="{{ route('audits.show', [$run, 'severity' => $value]) }}"
                       @class(['pill', 'pill-active' => $currentSeverity === $value])>{{ $label }}</a>
                @endforeach
            </nav>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[960px] text-left text-sm">
                <thead>
                    <tr class="table-head">
                        <th class="w-[150px] pb-3 pr-4">Règle</th>
                        <th class="pb-3 pr-4">Sévérité</th>
                        <th class="pb-3 pr-4">Objet</th>
                        <th class="pb-3 pr-4">Preuve</th>
                        <th class="pb-3">Recommandation</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($findings as $finding)
                        @php
                            $pending = $finding->exceptions->firstWhere('status', 'pending');
                            $lastException = $finding->exceptions->first();
                            $bag = $errors->getBag("exception{$finding->id}");
                        @endphp
                        <tr @class(['table-row align-top', 'opacity-60' => $finding->activeException])>
                            <td class="py-3.5 pr-4">
                                <span class="font-mono">{{ $finding->rule->code }}</span>
                                <span class="mt-0.5 block text-[13px] leading-snug text-muted">{{ $finding->rule->label }}</span>
                            </td>
                            <td class="py-3.5 pr-4"><x-badge :color="$finding->severity" class="font-semibold">{{ $finding->severityLabel() }}</x-badge></td>
                            <td class="py-3.5 pr-4 font-mono">{{ $finding->object_name }}</td>
                            <td class="py-3.5 pr-4">{{ $finding->evidence }}</td>
                            <td class="py-3.5">
                                {{ $finding->recommendation }}

                                {{-- État de l'exception sur ce constat --}}
                                <div class="mt-2">
                                    @if ($finding->activeException)
                                        <x-badge color="actif">Exception validée jusqu'au {{ $finding->activeException->requested_expiration->format('d/m/Y') }}</x-badge>
                                    @elseif ($pending)
                                        <x-badge color="moyenne">Exception en attente de validation</x-badge>
                                    @else
                                        @if ($lastException?->status === 'rejected')
                                            <p class="mb-1 text-xs text-[#8B1A1A]">Dernière exception refusée : {{ $lastException->review_comment }}</p>
                                        @endif

                                        @if ($isDba)
                                            {{-- <details> : le formulaire s'ouvre au clic, sans JavaScript --}}
                                            <details @if ($bag->any()) open @endif>
                                                <summary class="cursor-pointer text-[13px] text-primary underline">Proposer une exception</summary>

                                                <form method="POST" action="{{ route('exceptions.store', $finding) }}" class="mt-3 space-y-3 rounded-lg border border-line bg-page p-3">
                                                    @csrf
                                                    <div>
                                                        <label for="reason-{{ $finding->id }}" class="form-label text-[13px]">Motif</label>
                                                        <textarea id="reason-{{ $finding->id }}" name="reason" rows="2" required
                                                                  class="w-full rounded-lg border-field text-sm focus:border-primary focus:ring-primary"
                                                                  placeholder="Pourquoi ce constat est-il justifié ?">{{ $bag->any() ? old('reason') : '' }}</textarea>
                                                        @if ($bag->has('reason')) <p class="form-error">{{ $bag->first('reason') }}</p> @endif
                                                    </div>
                                                    <div>
                                                        <label for="expiration-{{ $finding->id }}" class="form-label text-[13px]">Date d'expiration</label>
                                                        <input id="expiration-{{ $finding->id }}" name="requested_expiration" type="date" required
                                                               min="{{ today()->addDay()->format('Y-m-d') }}"
                                                               value="{{ $bag->any() ? old('requested_expiration') : '' }}"
                                                               class="form-input w-[180px]">
                                                        @if ($bag->has('requested_expiration')) <p class="form-error">{{ $bag->first('requested_expiration') }}</p> @endif
                                                    </div>
                                                    <button type="submit" class="btn-primary btn-sm font-semibold">Envoyer la demande</button>
                                                </form>
                                            </details>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-muted">
                                {{ $run->status === 'completed' ? 'Aucun constat pour ce filtre.' : 'Aucun constat : l’audit n’est pas terminé.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
