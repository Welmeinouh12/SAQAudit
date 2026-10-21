@extends('layouts.main')

@section('title', 'Tableau de bord')
@section('heading', 'Tableau de bord')
@section('subheading', "Vue d'ensemble des derniers audits")

@if (auth()->user()->role === 'dba')
    @section('actions')
        {{-- On lance un audit depuis la page Instances (bouton « Auditer » de chaque ligne) --}}
        <a href="{{ route('instances.index') }}" class="btn-primary">Lancer un audit</a>
    @endsection
@endif

@php
    // Libellés et couleurs des barres « Constats par sévérité »
    $severityBars = [
        'critique' => ['Critique', 'bg-[#8B1A1A]'],
        'elevee'   => ['Élevée',   'bg-[#C2410C]'],
        'moyenne'  => ['Moyenne',  'bg-[#D4A017]'],
        'faible'   => ['Faible',   'bg-[#6F8FB3]'],
    ];

    // Lien vers une page pas encore créée : renvoie vers #
    $link = fn (string $route, $params = []) => Route::has($route) ? route($route, $params) : '#';
@endphp

@section('content')
    {{-- Cartes chiffrées --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Instances déclarées" :value="$instancesCount" />
        <x-stat-card label="Audits ce mois" :value="$auditsThisMonth" />
        <x-stat-card label="Constats ouverts" :value="$openFindings" />
        <x-stat-card label="Constats critiques" :value="$criticalFindings" :danger="$criticalFindings > 0" />
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        {{-- Constats par sévérité --}}
        <x-card title="Constats par sévérité">
            <ul class="space-y-4">
                @foreach ($bySeverity as $severity => $count)
                    <li>
                        <div class="mb-1.5 flex justify-between text-sm">
                            <span>{{ $severityBars[$severity][0] }}</span>
                            <span>{{ $count }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-line-soft">
                            <div class="h-full rounded-full {{ $severityBars[$severity][1] }}"
                                 style="width: {{ round($count / $maxSeverity * 100) }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-card>

        {{-- Instances à risque --}}
        <x-card title="Instances à risque">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[420px] text-left text-sm">
                    <thead>
                        <tr class="table-head">
                            <th class="pb-3 pr-4">Instance</th>
                            <th class="pb-3 pr-4">Env.</th>
                            <th class="pb-3 pr-4">Critiques</th>
                            <th class="pb-3">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($riskyRuns as $run)
                            <tr class="table-row">
                                <td class="py-3 pr-4 font-mono">{{ $run->instance->name }}</td>
                                <td class="py-3 pr-4">{{ $run->instance->environmentLabel() }}</td>
                                <td @class(['py-3 pr-4', 'font-semibold text-[#8B1A1A]' => $run->critical_count > 0])>{{ $run->critical_count }}</td>
                                <td class="py-3">{{ $run->findings_count }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-muted">Aucun audit terminé pour le moment.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    {{-- Derniers audits --}}
    <x-card class="mt-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-[17px] font-semibold">Derniers audits</h2>
            <a href="{{ $link('audits.index') }}" class="text-sm text-primary underline">Voir tout</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead>
                    <tr class="table-head">
                        <th class="pb-3 pr-4">N°</th>
                        <th class="pb-3 pr-4">Instance</th>
                        <th class="pb-3 pr-4">Date</th>
                        <th class="pb-3 pr-4">Lancé par</th>
                        <th class="pb-3 pr-4">Statut</th>
                        <th class="pb-3">Constats</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentRuns as $run)
                        <tr class="table-row">
                            <td class="py-3.5 pr-4 font-mono">
                                <a href="{{ $link('audits.show', $run) }}" class="hover:underline">#{{ $run->id }}</a>
                            </td>
                            <td class="py-3.5 pr-4 font-mono">{{ $run->instance->name }}</td>
                            <td class="py-3.5 pr-4">{{ $run->started_at?->format('d/m/Y H:i') }}</td>
                            <td class="py-3.5 pr-4">{{ $run->user?->name ?? '—' }}</td>
                            <td class="py-3.5 pr-4"><x-badge :color="$run->statusColor()">{{ $run->statusLabel() }}</x-badge></td>
                            <td class="py-3.5">{{ $run->status === 'completed' ? $run->findings_count : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-muted">
                                Aucun audit pour le moment.
                                @if (auth()->user()->role === 'dba')
                                    <a href="{{ route('instances.index') }}" class="text-primary underline">Lancez le premier depuis la page Instances.</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
