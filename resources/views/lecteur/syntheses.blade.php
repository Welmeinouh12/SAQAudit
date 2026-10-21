@extends('layouts.main')

@section('title', 'Rapports de synthèse')
@section('heading', 'Rapports de synthèse')
@section('subheading', "Rapports publiés par l'équipe d'audit. Vous pouvez les consulter et les télécharger.")

@php
    $filters = [
        'all' => 'Tous',
        'monthly' => 'Mensuels',
        'instance' => 'Par instance',
    ];

    $monthNames = [1 => 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
        'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
@endphp

@section('content')
    {{-- Filtres + nombre de rapports --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <nav class="flex flex-wrap gap-2" aria-label="Filtrer les rapports">
            @foreach ($filters as $value => $label)
                <a href="{{ route('syntheses.index', $value === 'all' ? [] : ['filter' => $value]) }}"
                   @class(['pill', 'pill-active' => $currentFilter === $value])>{{ $label }}</a>
            @endforeach
        </nav>
        <p class="text-sm text-muted">{{ $reports->count() }} {{ $reports->count() > 1 ? 'rapports' : 'rapport' }}</p>
    </div>

    @if ($reports->isEmpty())
        <x-card>
            <p class="py-4 text-center text-sm text-muted">Aucun rapport de synthèse publié pour le moment.</p>
        </x-card>
    @else
        {{-- Grille : colonnes automatiques, 300 px minimum --}}
        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr))">
            @foreach ($reports as $report)
                @php
                    $period = $monthNames[$report->created_at->month].' '.$report->created_at->year;
                    $scopeName = $report->instance?->name ?? ($report->instance_id ? explode(' · ', $report->scope)[0] : 'Toutes les instances');
                    $title = $report->instance_id ? "Synthèse {$scopeName} · {$period}" : "Synthèse de conformité · {$period}";
                    $trend = $trends[$report->id];
                @endphp

                <article class="flex flex-col rounded-xl border border-line bg-white p-[22px]">
                    <div class="flex items-start justify-between gap-3">
                        {{-- Icône de document PDF --}}
                        <span class="flex h-12 w-10 items-end justify-center rounded-md border border-[#C9D6E3] bg-[#F2F6FA] pb-1.5 font-mono text-[10px] font-bold text-primary" aria-hidden="true">PDF</span>

                        @if ($trend !== null && $trend !== 0)
                            <span @class([
                                'inline-flex rounded-full px-2.5 py-0.5 text-[13px] font-semibold',
                                'bg-[#E3F1E8] text-[#1D5B36]' => $trend < 0,
                                'bg-[#FBE4E4] text-[#8B1A1A]' => $trend > 0,
                            ])>
                                {{ $trend < 0 ? '−'.abs($trend) : '+'.$trend }} {{ abs($trend) > 1 ? 'constats' : 'constat' }}
                            </span>
                        @endif
                    </div>

                    <h2 class="mt-4 text-[17px] font-semibold leading-snug">{{ $title }}</h2>
                    <p class="mt-1 text-[13px] text-muted">{{ $scopeName }} · publié le {{ $report->created_at->format('d/m/Y') }}</p>

                    {{-- Chiffres figés au moment de la publication --}}
                    <dl class="mt-4 grid grid-cols-3 gap-2 rounded-lg bg-[#F7F8FA] px-3 py-3">
                        <div>
                            <dt class="text-xs text-muted">Constats</dt>
                            <dd class="mt-0.5 text-lg font-semibold">{{ $report->findings_count ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Critiques</dt>
                            <dd @class(['mt-0.5 text-lg font-semibold', 'text-[#8B1A1A]' => $report->critical_count > 0])>{{ $report->critical_count ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Instances</dt>
                            <dd class="mt-0.5 text-lg font-semibold">{{ $report->instances_count ?? '—' }}</dd>
                        </div>
                    </dl>

                    <a href="{{ route('syntheses.download', $report) }}"
                       class="mt-4 inline-flex h-[42px] w-full items-center justify-center gap-2 rounded-lg border border-field bg-white text-sm font-medium transition-colors hover:bg-page">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>
                        </svg>
                        Télécharger le PDF
                    </a>
                </article>
            @endforeach
        </div>
    @endif

    <p class="mt-6 text-[13px] text-muted">
        Le profil Lecteur n'a pas accès au détail des comptes et des permissions. Pour toute question, contactez l'équipe d'audit.
    </p>
@endsection
