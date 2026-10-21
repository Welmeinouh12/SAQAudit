@extends('layouts.main')

@section('title', 'Paramètres')
@section('heading', 'Paramètres')
@section('subheading', 'Réglages globaux de la plateforme. Chaque modification est tracée dans le journal.')

@section('actions')
    {{-- Ce bouton est hors du formulaire : l'attribut form="settings-form" le relie au formulaire --}}
    <button type="submit" form="settings-form" class="btn-primary">Enregistrer</button>
@endsection

@php
    $numeric = \App\Http\Controllers\Admin\SettingController::NUMERIC;

    // Cartes de paramètres numériques : [titre, sous-titre, clés]
    $numericCards = [
        ['Sécurité des comptes', 'Connexion à la plateforme (EF-01)', ['lockout_attempts', 'lockout_minutes', 'session_minutes']],
        ['Collecte et audit', 'Comportement de la connexion aux instances', ['query_timeout', 'query_retries', 'password_max_age_days']],
    ];
@endphp

@section('content')
    @if ($errors->hasAny(array_keys($numeric)) || $errors->hasAny(['retention_years', 'auto_purge']))
        <div class="mb-6 rounded-lg border border-[#F2C2C2] bg-[#FBE4E4] px-4 py-3 text-sm text-[#8B1A1A]" role="alert">
            Les paramètres n'ont pas été enregistrés : corrigez les champs signalés.
        </div>
    @endif

    <form id="settings-form" method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="grid items-start gap-4 lg:grid-cols-2 xl:grid-cols-3">

            {{-- Sécurité des comptes / Collecte et audit --}}
            @foreach ($numericCards as [$title, $subtitle, $keys])
                <x-card :title="$title" :subtitle="$subtitle">
                    <div class="space-y-4">
                        @foreach ($keys as $key)
                            @php [$label, $min, $max] = $numeric[$key]; @endphp
                            <div>
                                <div class="flex items-center justify-between gap-4">
                                    <label for="{{ $key }}" class="text-sm font-medium">{{ $label }}</label>
                                    <input id="{{ $key }}" name="{{ $key }}" type="number" min="{{ $min }}" max="{{ $max }}"
                                           value="{{ old($key, $values[$key]) }}" required
                                           class="form-input w-24 shrink-0 @error($key) border-[#8B1A1A] @enderror">
                                </div>
                                @error($key) <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endforeach

            {{-- Liste blanche sysadmin : ses boutons envoient les formulaires séparés plus bas --}}
            <x-card id="liste-blanche" title="Liste blanche sysadmin"
                    subtitle="Comptes autorisés dans sysadmin : ils ne déclenchent pas la règle R01">
                <ul class="space-y-2.5">
                    @forelse ($whitelist as $i => $account)
                        <li class="flex h-[46px] items-center justify-between rounded-lg border border-line px-4">
                            <span class="truncate font-mono text-[13px]">{{ $account }}</span>
                            <button type="submit" form="wl-remove-{{ $i }}" class="ml-3 text-muted hover:text-[#8B1A1A]"
                                    aria-label="Retirer {{ $account }} de la liste blanche">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                    <path d="M6 6l12 12M18 6L6 18"/>
                                </svg>
                            </button>
                        </li>
                    @empty
                        <li class="text-sm text-muted">Aucun compte dans la liste blanche.</li>
                    @endforelse
                </ul>

                <div class="mt-3 flex gap-2">
                    <label for="account" class="sr-only">Compte à ajouter</label>
                    <input id="account" name="account" type="text" form="wl-add" placeholder="DOMAINE\compte"
                           value="{{ old('account') }}" class="form-input font-mono text-[13px]">
                    <button type="submit" form="wl-add" class="btn-secondary h-[42px] shrink-0 px-4">Ajouter</button>
                </div>
                @error('account') <p class="form-error">{{ $message }}</p> @enderror
            </x-card>

            {{-- Rétention --}}
            <x-card title="Rétention" subtitle="Historique des audits et du journal (minimum 3 ans)">
                <div class="flex items-center justify-between gap-4">
                    <label for="retention_years" class="text-sm font-medium">Durée de conservation</label>
                    <select id="retention_years" name="retention_years" class="form-input w-[92px] shrink-0">
                        @foreach (\App\Http\Controllers\Admin\SettingController::RETENTION_YEARS as $years)
                            <option value="{{ $years }}" @selected((int) old('retention_years', $values['retention_years']) === $years)>{{ $years }} ans</option>
                        @endforeach
                    </select>
                </div>
                @error('retention_years') <p class="form-error">{{ $message }}</p> @enderror

                <div class="mt-5 flex items-center gap-3">
                    {{-- Champ caché : envoie 0 quand la case est décochée --}}
                    <input type="hidden" name="auto_purge" value="0">
                    <input id="auto_purge" name="auto_purge" type="checkbox" value="1"
                           class="h-[18px] w-[18px] rounded border-field text-primary focus:ring-primary"
                           @checked(old('auto_purge', $values['auto_purge']) == '1')>
                    <label for="auto_purge" class="text-sm">Purge automatique des données expirées</label>
                </div>
            </x-card>
        </div>
    </form>

    {{-- Formulaires de la liste blanche (hors du formulaire principal : on ne peut pas imbriquer des <form>) --}}
    <form id="wl-add" method="POST" action="{{ route('admin.settings.whitelist.store') }}">
        @csrf
    </form>
    @foreach ($whitelist as $i => $account)
        <form id="wl-remove-{{ $i }}" method="POST" action="{{ route('admin.settings.whitelist.destroy') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="account" value="{{ $account }}">
        </form>
    @endforeach
@endsection
