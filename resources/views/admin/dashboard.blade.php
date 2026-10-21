@extends('layouts.main')

@section('title', 'Tableau de bord')
@section('heading', 'Administration de la plateforme')
@section('subheading', "Comptes, règles d'audit, paramètres et traçabilité")

@php
    // Libellés et couleurs des barres de la répartition des profils
    $profileStyles = [
        'admin'    => ['Administrateur',      'bg-[#4B2E83]'],
        'dba'      => ['DBA',                 'bg-[#1F4E79]'],
        'auditeur' => ['Auditeur / Contrôle', 'bg-[#155E57]'],
        'lecteur'  => ['Lecteur',             'bg-[#6E7681]'],
    ];

    // Liens des actions rapides (une page pas encore créée renvoie vers #)
    $link = fn (string $route, string $anchor = '') => Route::has($route) ? route($route).$anchor : '#';

    $quickActions = [
        ['Créer un utilisateur',              $link('admin.users.index', '#creer')],
        ['Activer ou désactiver une règle',   $link('admin.rules.index')],
        ['Modifier la liste blanche sysadmin', $link('admin.settings.index', '#liste-blanche')],
    ];
@endphp

@section('content')
    {{-- Cartes chiffrées --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Utilisateurs actifs" :value="$activeUsers">
            {{ $inactiveUsers }} {{ $inactiveUsers > 1 ? 'comptes désactivés' : 'compte désactivé' }}
        </x-stat-card>

        <x-stat-card label="Règles actives" :value="$activeRules" :suffix="'/ '.$totalRules">
            {{ $totalRules - $activeRules }} {{ $totalRules - $activeRules > 1 ? 'règles désactivées' : 'règle désactivée' }}
        </x-stat-card>

        <x-stat-card label="Connexions échouées (24 h)" :value="$failedLogins" :danger="$failedLogins > 0">
            Verrouillage après {{ $lockoutAttempts }} échecs
        </x-stat-card>

        <x-stat-card label="Comptes verrouillés" :value="$lockedUsers" :danger="$lockedUsers > 0">
            @if ($lockedUsers > 0)
                <a href="{{ $link('admin.users.index') }}" class="text-primary underline">Débloquer</a>
            @else
                Aucun compte verrouillé
            @endif
        </x-stat-card>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        {{-- Répartition des profils --}}
        <x-card title="Répartition des profils">
            <ul class="space-y-4">
                @foreach ($profiles as $role => $count)
                    <li>
                        <div class="mb-1.5 flex justify-between text-sm">
                            <span>{{ $profileStyles[$role][0] }}</span>
                            <span>{{ $count }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-line-soft">
                            <div class="h-full rounded-full {{ $profileStyles[$role][1] }}"
                                 style="width: {{ round($count / $maxProfile * 100) }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-card>

        {{-- Actions rapides --}}
        <x-card title="Actions rapides">
            <div class="space-y-3">
                @foreach ($quickActions as [$label, $url])
                    <a href="{{ $url }}"
                       class="flex items-center justify-between rounded-lg border border-line px-4 py-4 text-sm font-medium transition-colors hover:border-field hover:bg-page">
                        {{ $label }}
                        <span class="text-primary" aria-hidden="true">→</span>
                    </a>
                @endforeach
            </div>
        </x-card>
    </div>

    {{-- Activité récente --}}
    <x-card class="mt-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-[17px] font-semibold">Activité récente</h2>
            <a href="{{ $link('admin.logs.index') }}" class="text-sm text-primary underline">Tout le journal</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-left text-sm">
                <thead>
                    <tr class="border-b border-line-soft text-xs uppercase tracking-wide text-muted">
                        <th class="pb-3 font-semibold">Date</th>
                        <th class="pb-3 font-semibold">Utilisateur</th>
                        <th class="pb-3 font-semibold">Action</th>
                        <th class="pb-3 font-semibold">Détail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentLogs as $log)
                        <tr class="border-b border-line-soft last:border-0">
                            <td class="py-3.5 pr-4 font-mono text-[13px]">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            <td class="py-3.5 pr-4">{{ $log->user?->email ?? '—' }}</td>
                            <td class="py-3.5 pr-4">{{ $log->action }}</td>
                            <td class="py-3.5 text-muted">{{ $log->detail ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-muted">Aucune activité enregistrée pour le moment.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
