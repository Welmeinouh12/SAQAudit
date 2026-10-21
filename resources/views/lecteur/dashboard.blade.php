@extends('layouts.main')

@section('title', 'Synthèse de la sécurité')
@section('heading', 'Synthèse de la sécurité SQL Server')
@section('subheading', 'Vue globale, en consultation seule · données du dernier audit de chaque instance')

@section('actions')
    <span class="inline-flex items-center gap-1.5 rounded-full bg-[#EEF0F3] px-3 py-1.5 text-[13px] font-medium text-[#3D4552] sm:mt-4">
        {{-- Icône œil en trait fin --}}
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/>
            <circle cx="12" cy="12" r="3"/>
        </svg>
        Lecture seule
    </span>
@endsection

@php
    // Libellés et couleurs des barres « Constats par sévérité »
    $severityBars = [
        'critique' => ['Critique', 'bg-[#8B1A1A]'],
        'elevee'   => ['Élevée',   'bg-[#C2410C]'],
        'moyenne'  => ['Moyenne',  'bg-[#D4A017]'],
        'faible'   => ['Faible',   'bg-[#6B8BB0]'],
    ];

    // Description du graphique pour les lecteurs d'écran
    $chartLabel = 'Constats ouverts sur 6 mois : '
        .collect($months)->map(fn ($month) => "{$month['label']} {$month['value']}")->implode(', ');
@endphp

@section('content')
    {{-- Cartes chiffrées --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Instances auditées" :value="$auditedRecently" :suffix="'/ '.$instancesCount">
            au cours des 30 derniers jours
        </x-stat-card>

        <x-stat-card label="Constats ouverts" :value="$openFindings">
            @if ($delta === null)
                aucun audit le mois dernier
            @elseif ($delta < 0)
                <span class="font-medium text-[#1D5B36]">−{{ abs($delta) }} par rapport au mois dernier</span>
            @elseif ($delta > 0)
                <span class="font-medium text-[#8B1A1A]">+{{ $delta }} par rapport au mois dernier</span>
            @else
                stable par rapport au mois dernier
            @endif
        </x-stat-card>

        <x-stat-card label="Risques critiques" :value="$criticalFindings" :danger="$criticalFindings > 0">
            en cours de traitement
        </x-stat-card>

        <x-stat-card label="Exceptions validées" :value="$approvedExceptions">
            risques acceptés et suivis
        </x-stat-card>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        {{-- Constats par sévérité --}}
        <x-card title="Constats par sévérité">
            <ul class="space-y-4">
                @foreach ($bySeverity as $severity => $count)
                    <li>
                        <div class="mb-1.5 flex justify-between text-sm">
                            <span>{{ $severityBars[$severity][0] }}</span>
                            <span class="font-mono">{{ $count }}</span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-[#EEF0F3]">
                            <div class="h-full rounded-full {{ $severityBars[$severity][1] }}"
                                 style="width: {{ round($count / $maxSeverity * 100) }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-card>

        {{-- Niveau de risque par instance --}}
        <x-card title="Niveau de risque par instance">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[440px] text-left text-sm">
                    <thead>
                        <tr class="table-head">
                            <th class="pb-3 pr-4">Instance</th>
                            <th class="pb-3 pr-4">Environnement</th>
                            <th class="pb-3 pr-4">Dernier audit</th>
                            <th class="pb-3">Niveau</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($riskLevels as $row)
                            <tr class="table-row">
                                <td class="py-3 pr-4 font-mono">{{ $row['run']->instance->name }}</td>
                                <td class="py-3 pr-4">{{ $row['run']->instance->environmentLabel() }}</td>
                                <td class="py-3 pr-4 font-mono text-[13px]">{{ $row['run']->started_at?->format('d/m/Y') }}</td>
                                <td class="py-3"><x-badge :color="$row['level'][1]" class="font-semibold">{{ $row['level'][0] }}</x-badge></td>
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

    {{-- Évolution des constats ouverts --}}
    <x-card class="mt-4">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-[17px] font-semibold">Évolution des constats ouverts (6 derniers mois)</h2>
            <a href="{{ Route::has('syntheses.index') ? route('syntheses.index') : '#' }}" class="text-sm text-primary underline">Voir les rapports de synthèse</a>
        </div>

        {{-- Histogramme : simples <div>, hauteur proportionnelle (max 110 px) --}}
        <div class="flex items-end justify-around gap-3" role="img" aria-label="{{ $chartLabel }}">
            @foreach ($months as $month)
                <div class="flex w-14 flex-col items-center">
                    <span class="mb-1.5 font-mono text-[13px]">{{ $month['value'] }}</span>
                    <div @class([
                            'w-full rounded-t-md',
                            'bg-primary' => $month['current'],
                            'bg-[#A9BED3]' => ! $month['current'],
                         ])
                         style="height: {{ round($month['value'] / $maxMonth * 110) }}px"></div>
                    <span class="mt-2 text-[13px] text-muted">{{ $month['label'] }}</span>
                </div>
            @endforeach
        </div>
    </x-card>
@endsection
