@extends('layouts.main')

@section('title', 'Comparer deux audits')
@section('heading', 'Comparer deux audits')
@section('subheading', "Ce qui est apparu, ce qui a été corrigé et ce qui reste ouvert entre deux audits d'une même instance")

@php
    // Évolution : [libellé, couleur du badge]
    $evolutions = [
        'new' => ['Nouveau', 'desactive'],
        'resolved' => ['Résolu', 'actif'],
        'unchanged' => ['Inchangé', 'gris'],
    ];

    // Paramètres à conserver dans les liens des filtres
    $params = $diff ? ['instance' => $instance->id, 'reference' => $reference->id, 'compared' => $compared->id] : [];
    $runLabel = fn ($run) => '#'.$run->id.' · '.$run->started_at?->format('d/m/Y');
@endphp

@section('content')
    @if ($instances->isEmpty())
        <x-card>
            <p class="py-4 text-center text-sm text-muted">Aucun audit terminé pour le moment : il faut au moins deux audits d'une même instance pour comparer.</p>
        </x-card>
    @else
        {{-- Choix des audits --}}
        <x-card>
            <form method="GET" action="{{ route('audits.compare') }}" class="grid items-end gap-4 md:grid-cols-[1fr_1fr_1fr_auto]">
                <div>
                    <label for="instance" class="form-label">Instance</label>
                    {{-- Changer d'instance recharge la page pour mettre à jour la liste des audits --}}
                    <select id="instance" name="instance" class="form-input font-mono" onchange="this.form.submit()">
                        @foreach ($instances as $item)
                            <option value="{{ $item->id }}" @selected($item->is($instance))>{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="reference" class="form-label">Audit de référence</label>
                    <select id="reference" name="reference" class="form-input font-mono">
                        @foreach ($runs as $run)
                            <option value="{{ $run->id }}" @selected($reference && $run->is($reference))>{{ $runLabel($run) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="compared" class="form-label">Audit à comparer</label>
                    <select id="compared" name="compared" class="form-input font-mono">
                        @foreach ($runs as $run)
                            <option value="{{ $run->id }}" @selected($compared && $run->is($compared))>{{ $runLabel($run) }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-primary">Comparer</button>
            </form>
        </x-card>

        @if (! $diff)
            <x-card class="mt-6">
                <p class="py-4 text-center text-sm text-muted">
                    {{ $runs->count() < 2
                        ? "Cette instance n'a qu'un seul audit terminé : lancez un nouvel audit pour pouvoir comparer."
                        : 'Choisissez deux audits différents.' }}
                </p>
            </x-card>
        @else
            @php
                $newCritical = $diff['new']->where('severity', 'critique')->count();
            @endphp

            {{-- Cartes de synthèse --}}
            <div class="mt-6 grid gap-4 md:grid-cols-3">
                <x-stat-card label="Nouveaux constats" :value="($diff['new']->count() > 0 ? '+' : '').$diff['new']->count()" :danger="$diff['new']->count() > 0">
                    @if ($newCritical)
                        dont {{ $newCritical }} {{ $newCritical > 1 ? 'critiques' : 'critique' }}
                    @else
                        apparus depuis l'audit #{{ $reference->id }}
                    @endif
                </x-stat-card>
                <x-stat-card label="Constats résolus" :value="($diff['resolved']->count() > 0 ? '−' : '').$diff['resolved']->count()" :tone="$diff['resolved']->count() > 0 ? 'success' : null">
                    corrigés depuis l'audit #{{ $reference->id }}
                </x-stat-card>
                <x-stat-card label="Inchangés" :value="$diff['unchanged']->count()">
                    toujours ouverts
                </x-stat-card>
            </div>

            {{-- Tableau des différences --}}
            <x-card class="mt-6">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-[17px] font-semibold">Différences ({{ $rows->count() }})</h2>

                    <nav class="flex flex-wrap gap-2" aria-label="Filtrer par évolution">
                        <a href="{{ route('audits.compare', $params) }}" @class(['pill', 'pill-active' => $currentFilter === null])>Tous</a>
                        @foreach ($evolutions as $value => [$label])
                            <a href="{{ route('audits.compare', $params + ['filter' => $value]) }}"
                               @class(['pill', 'pill-active' => $currentFilter === $value])>{{ $label }}</a>
                        @endforeach
                    </nav>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead>
                            <tr class="table-head">
                                <th class="pb-3 pr-4">Évolution</th>
                                <th class="pb-3 pr-4">Règle</th>
                                <th class="pb-3 pr-4">Sévérité</th>
                                <th class="pb-3 pr-4">Objet</th>
                                <th class="pb-3">Détail</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                @php [$evolution, $finding] = [$row['evolution'], $row['finding']]; @endphp
                                <tr class="table-row">
                                    <td class="py-3 pr-4">
                                        <x-badge :color="$evolutions[$evolution][1]" class="font-semibold">{{ $evolutions[$evolution][0] }}</x-badge>
                                    </td>
                                    <td class="py-3 pr-4">
                                        <span class="font-mono">{{ $finding->rule->code }}</span> · {{ $finding->rule->label }}
                                    </td>
                                    <td class="py-3 pr-4"><x-badge :color="$finding->severity" class="font-semibold">{{ $finding->severityLabel() }}</x-badge></td>
                                    <td class="py-3 pr-4 font-mono">{{ $finding->object_name }}</td>
                                    <td class="py-3 text-[#3D4552]">
                                        @switch($evolution)
                                            @case('new')
                                                {{ $finding->evidence }} · apparu depuis l'audit #{{ $reference->id }}
                                                @break
                                            @case('resolved')
                                                Absent de l'audit #{{ $compared->id }}
                                                @break
                                            @default
                                                {{ $finding->evidence }}
                                        @endswitch
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-muted">Aucune différence pour ce filtre.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endif
    @endif
@endsection
