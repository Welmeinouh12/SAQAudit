@extends('layouts.main')

@section('title', "Journal d'activité")
@section('heading', "Journal d'activité")
@section('subheading', 'Toutes les actions des utilisateurs, avec date et adresse IP. Lecture seule : aucune ligne ne peut être modifiée ni supprimée.')

@php
    // Paramètres de période à conserver dans tous les liens
    $period = ['from' => $filters['from']->format('Y-m-d'), 'to' => $filters['to']->format('Y-m-d')];
@endphp

@section('actions')
    <a href="{{ route('admin.logs.export', array_filter($period + ['type' => $filters['type']])) }}" class="btn-secondary">Exporter CSV</a>
@endsection

@section('content')
    <x-card>
        <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
            {{-- Filtres par type --}}
            <nav class="flex flex-wrap gap-2" aria-label="Filtrer par type">
                <a href="{{ route('admin.logs.index', $period) }}" @class(['pill', 'pill-active' => $filters['type'] === null])>Tous</a>
                @foreach (\App\Models\ActivityLog::TYPES as $value => $label)
                    <a href="{{ route('admin.logs.index', $period + ['type' => $value]) }}"
                       @class(['pill', 'pill-active' => $filters['type'] === $value])>{{ $label }}</a>
                @endforeach
            </nav>

            {{-- Filtre par dates : la page se recharge dès qu'une date change --}}
            <form method="GET" action="{{ route('admin.logs.index') }}" class="flex flex-wrap items-center gap-2 text-sm text-muted">
                @if ($filters['type'])
                    <input type="hidden" name="type" value="{{ $filters['type'] }}">
                @endif
                <label for="from">Du</label>
                <input id="from" name="from" type="date" value="{{ $period['from'] }}" onchange="this.form.submit()"
                       class="form-input w-[150px] font-mono text-[13px]">
                <label for="to">au</label>
                <input id="to" name="to" type="date" value="{{ $period['to'] }}" onchange="this.form.submit()"
                       class="form-input w-[150px] font-mono text-[13px]">
                <noscript><button type="submit" class="btn-secondary btn-sm">Filtrer</button></noscript>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[960px] text-left text-sm">
                <thead>
                    <tr class="table-head">
                        <th class="pb-3 pr-4">Date</th>
                        <th class="pb-3 pr-4">Utilisateur</th>
                        <th class="pb-3 pr-4">Type</th>
                        <th class="pb-3 pr-4">Action</th>
                        <th class="pb-3 pr-4">Détail</th>
                        <th class="pb-3">Adresse IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="table-row">
                            <td class="whitespace-nowrap py-3.5 pr-4 font-mono text-[13px]">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            <td class="py-3.5 pr-4">{{ $log->user?->email ?? '—' }}</td>
                            <td class="py-3.5 pr-4"><x-badge :color="$log->type">{{ $log->typeLabel() }}</x-badge></td>
                            <td class="py-3.5 pr-4 font-medium">{{ $log->action }}</td>
                            <td class="py-3.5 pr-4 text-muted">{{ $log->detail ?? '—' }}</td>
                            <td class="py-3.5 font-mono text-[13px]">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-muted">Aucune entrée pour ces filtres.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-muted">
                {{ $logs->count() }} {{ $logs->count() > 1 ? 'entrées affichées' : 'entrée affichée' }}
                @if ($logs->total() > $logs->count())
                    sur {{ $logs->total() }} (page {{ $logs->currentPage() }} / {{ $logs->lastPage() }})
                @endif
            </p>

            <div class="flex gap-2">
                @if ($logs->onFirstPage())
                    <span class="btn-secondary btn-sm cursor-not-allowed opacity-50">Précédent</span>
                @else
                    <a href="{{ $logs->previousPageUrl() }}" class="btn-secondary btn-sm">Précédent</a>
                @endif

                @if ($logs->hasMorePages())
                    <a href="{{ $logs->nextPageUrl() }}" class="btn-secondary btn-sm">Suivant</a>
                @else
                    <span class="btn-secondary btn-sm cursor-not-allowed opacity-50">Suivant</span>
                @endif
            </div>
        </div>
    </x-card>
@endsection
