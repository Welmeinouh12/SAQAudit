@extends('layouts.main')

@section('title', 'Suivi de conformité')
@section('heading', 'Suivi de conformité')
@section('subheading', 'Risques ouverts, exceptions à valider et évolution entre les audits')

@php
    // Lien vers une page pas encore créée : renvoie vers #
    $link = fn (string $route) => Route::has($route) ? route($route) : '#';
@endphp

@section('content')
    {{-- Cartes chiffrées --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Constats critiques ouverts" :value="$criticalOpen" :danger="$criticalOpen > 0">
            sur {{ $auditedInstances }} {{ $auditedInstances > 1 ? 'instances' : 'instance' }}
        </x-stat-card>

        <x-stat-card label="Exceptions à valider" :value="$pendingCount" :tone="$pendingCount > 0 ? 'warning' : null">
            @if ($pendingCount > 0)
                <a href="{{ $link('exceptions.index') }}" class="text-primary underline">Traiter</a>
            @else
                Aucune demande en attente
            @endif
        </x-stat-card>

        <x-stat-card label="Exceptions expirant sous 30 j" :value="$expiringSoon">
            à réexaminer
        </x-stat-card>

        <x-stat-card label="Constats résolus ce mois" :value="$resolvedThisMonth" :tone="$resolvedThisMonth > 0 ? 'success' : null">
            depuis les audits précédents
        </x-stat-card>
    </div>

    <div class="mt-4 grid items-start gap-4 lg:grid-cols-2">
        {{-- Exceptions en attente --}}
        <x-card>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-[17px] font-semibold">Exceptions en attente</h2>
                <a href="{{ $link('exceptions.index') }}" class="text-sm text-primary underline">Tout voir</a>
            </div>

            <div class="space-y-2.5">
                @forelse ($pendingExceptions as $exception)
                    <a href="{{ $link('exceptions.index') }}" class="block rounded-lg border border-line px-4 py-3 transition-colors hover:bg-page">
                        <p class="text-sm font-semibold">
                            <span class="font-mono">{{ $exception->finding->rule->code }}</span>
                            · {{ $exception->finding->object_name }}
                            · <span class="font-mono">{{ $exception->finding->auditRun->instance->name }}</span>
                        </p>
                        <p class="mt-1 text-[13px] text-muted">
                            Demandée par {{ $exception->requester->email }}
                            · expiration {{ $exception->requested_expiration->format('d/m/Y') }}
                        </p>
                    </a>
                @empty
                    <p class="py-4 text-center text-sm text-muted">Aucune exception en attente de validation.</p>
                @endforelse
            </div>
        </x-card>

        {{-- Évolution depuis l'audit précédent --}}
        <x-card>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-[17px] font-semibold">Évolution depuis l'audit précédent</h2>
                <a href="{{ $link('audits.compare') }}" class="text-sm text-primary underline">Comparer</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[420px] text-left text-sm">
                    <thead>
                        <tr class="table-head">
                            <th class="pb-3 pr-4">Instance</th>
                            <th class="pb-3 pr-4">Nouveaux</th>
                            <th class="pb-3 pr-4">Résolus</th>
                            <th class="pb-3">Ouverts</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($evolution as $row)
                            <tr class="table-row">
                                <td class="py-3 pr-4 font-mono">{{ $row['instance']->name }}</td>
                                @if ($row['new'] === null)
                                    {{-- Premier audit de l'instance : rien à comparer --}}
                                    <td class="py-3 pr-4 text-muted" colspan="2">Premier audit</td>
                                @else
                                    <td @class(['py-3 pr-4', 'font-semibold text-[#8B1A1A]' => $row['new'] > 0])>
                                        {{ $row['new'] > 0 ? '+'.$row['new'] : 0 }}
                                    </td>
                                    <td @class(['py-3 pr-4', 'font-semibold text-[#1D5B36]' => $row['resolved'] > 0])>
                                        {{ $row['resolved'] > 0 ? '−'.$row['resolved'] : 0 }}
                                    </td>
                                @endif
                                <td class="py-3">{{ $row['open'] }}</td>
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
@endsection
