@extends('layouts.main')

@section('title', 'Instances')
@section('heading', 'Instances SQL Server')
@section('subheading', "Serveurs déclarés pour l'audit, interrogés en lecture seule")

@php
    // Statut du dernier test de connexion : [libellé, couleur du badge]
    $testStatuses = [
        'ok' => ['Connectée', 'actif'],
        'failed' => ['Échec', 'desactive'],
    ];
@endphp

@section('content')
    <div class="grid items-start gap-6 xl:grid-cols-[1fr_320px]">

        {{-- Liste des instances --}}
        <x-card>
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-[17px] font-semibold">Liste des instances</h2>

                <form method="GET" action="{{ route('instances.index') }}" class="flex items-center gap-2">
                    <label for="q" class="text-sm text-muted">Rechercher</label>
                    <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Nom ou hôte"
                           class="form-input w-[200px]">
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead>
                        <tr class="table-head">
                            <th class="pb-3 pr-4">Nom</th>
                            <th class="pb-3 pr-4">Hôte : port</th>
                            <th class="pb-3 pr-4">Environnement</th>
                            <th class="pb-3 pr-4">Connexion</th>
                            <th class="pb-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($instances as $instance)
                            @php [$statusLabel, $statusColor] = $testStatuses[$instance->last_test_status] ?? ['Non testée', 'gris']; @endphp
                            <tr class="table-row">
                                <td class="py-2.5 pr-4 font-mono">{{ $instance->name }}</td>
                                <td class="py-2.5 pr-4 font-mono text-[13px] text-[#3D4552]">{{ $instance->host }} : {{ $instance->port }}</td>
                                <td class="py-2.5 pr-4">{{ $instance->environmentLabel() }}</td>
                                <td class="py-2.5 pr-4">
                                    <x-badge :color="$statusColor"
                                             :title="$instance->last_test_message ? $instance->last_test_message.' ('.$instance->last_tested_at?->format('d/m/Y H:i').')' : null">
                                        {{ $statusLabel }}
                                    </x-badge>
                                </td>
                                <td class="py-2.5">
                                    <div class="flex justify-end gap-2">
                                        <form method="POST" action="{{ route('instances.test', $instance) }}">
                                            @csrf
                                            <button type="submit" class="btn-secondary btn-sm">Tester</button>
                                        </form>

                                        @if (Route::has('audits.store'))
                                            <form method="POST" action="{{ route('audits.store', $instance) }}">
                                                @csrf
                                                <button type="submit" class="btn-primary btn-sm font-semibold">Auditer</button>
                                            </form>
                                        @else
                                            <button type="button" class="btn-primary btn-sm cursor-not-allowed font-semibold opacity-50" disabled
                                                    title="Disponible à l'étape suivante">Auditer</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-muted">
                                    {{ $search !== '' ? 'Aucune instance ne correspond à la recherche.' : 'Aucune instance déclarée. Ajoutez-en une avec le formulaire.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- Formulaire d'ajout --}}
        <x-card id="ajouter" title="Ajouter une instance">
            <form method="POST" action="{{ route('instances.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="name" class="form-label">Nom</label>
                    <input id="name" name="name" type="text" class="form-input font-mono" placeholder="SRV-PROD-01"
                           value="{{ old('name') }}" required>
                    @error('name') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-[1fr_72px] gap-3">
                    <div>
                        <label for="host" class="form-label">Hôte</label>
                        <input id="host" name="host" type="text" class="form-input font-mono" placeholder="10.0.0.15"
                               value="{{ old('host') }}" required>
                    </div>
                    <div>
                        <label for="port" class="form-label">Port</label>
                        <input id="port" name="port" type="number" min="1" max="65535" class="form-input px-2"
                               value="{{ old('port', 1433) }}" required>
                    </div>
                </div>
                @error('host') <p class="form-error -mt-3">{{ $message }}</p> @enderror
                @error('port') <p class="form-error -mt-3">{{ $message }}</p> @enderror

                <div>
                    <label for="environment" class="form-label">Environnement</label>
                    <select id="environment" name="environment" class="form-input" required>
                        @foreach (\App\Models\Instance::ENVIRONMENTS as $value => $label)
                            <option value="{{ $value }}" @selected(old('environment', 'production') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('environment') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="username" class="form-label">Compte de collecte</label>
                    <input id="username" name="username" type="text" class="form-input" placeholder="sqlaudit_collect"
                           value="{{ old('username', 'sqlaudit_collect') }}" required autocomplete="off">
                    @error('username') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="form-label">Mot de passe</label>
                    {{-- Jamais pré-rempli : le mot de passe n'est jamais renvoyé à la page --}}
                    <input id="password" name="password" type="password" class="form-input" placeholder="••••••••"
                           required autocomplete="new-password">
                    <p class="mt-1.5 text-xs text-muted">Stocké chiffré, jamais affiché.</p>
                    @error('password') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    {{-- formaction : même formulaire, mais envoyé vers la route de test (rien n'est enregistré) --}}
                    <button type="submit" formaction="{{ route('instances.test-new') }}" class="btn-secondary px-2 leading-tight">
                        Tester la connexion
                    </button>
                    <button type="submit" class="btn-primary px-2">Enregistrer</button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
