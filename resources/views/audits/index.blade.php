@extends('layouts.main')

@section('title', 'Audits et constats')
@section('heading', 'Audits et constats')
@section('subheading', 'Tous les audits lancés sur les instances. Cliquez sur une ligne pour voir ses constats.')

@section('content')
    <x-card>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-[17px] font-semibold">Audits ({{ $runs->total() }})</h2>

            {{-- Filtre par instance : la page se recharge dès que le choix change --}}
            <form method="GET" action="{{ route('audits.index') }}" class="flex items-center gap-2">
                <label for="instance" class="text-sm text-muted">Instance</label>
                <select id="instance" name="instance" class="form-input w-[200px] font-mono" onchange="this.form.submit()">
                    <option value="">Toutes</option>
                    @foreach ($instances as $instance)
                        <option value="{{ $instance->id }}" @selected($currentInstance?->is($instance))>{{ $instance->name }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="btn-secondary btn-sm">Filtrer</button></noscript>
            </form>
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
                    @forelse ($runs as $run)
                        {{-- Toute la ligne est cliquable ; le lien du N° reste utilisable au clavier --}}
                        <tr class="table-row cursor-pointer transition-colors hover:bg-page"
                            onclick="window.location='{{ route('audits.show', $run) }}'">
                            <td class="py-3.5 pr-4 font-mono">
                                <a href="{{ route('audits.show', $run) }}" class="hover:underline">#{{ $run->id }}</a>
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
                                {{ $currentInstance ? 'Aucun audit pour cette instance.' : 'Aucun audit pour le moment.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($runs->hasPages())
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-muted">Page {{ $runs->currentPage() }} / {{ $runs->lastPage() }}</p>
                <div class="flex gap-2">
                    @if ($runs->onFirstPage())
                        <span class="btn-secondary btn-sm cursor-not-allowed opacity-50">Précédent</span>
                    @else
                        <a href="{{ $runs->previousPageUrl() }}" class="btn-secondary btn-sm">Précédent</a>
                    @endif
                    @if ($runs->hasMorePages())
                        <a href="{{ $runs->nextPageUrl() }}" class="btn-secondary btn-sm">Suivant</a>
                    @else
                        <span class="btn-secondary btn-sm cursor-not-allowed opacity-50">Suivant</span>
                    @endif
                </div>
            </div>
        @endif
    </x-card>
@endsection
