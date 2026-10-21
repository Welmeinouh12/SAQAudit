@extends('layouts.main')

@section('title', 'Utilisateurs')
@section('heading', 'Gestion des utilisateurs')
@section('subheading', "Comptes de la plateforme et profils d'accès. Pas d'inscription libre : seul l'administrateur crée les comptes.")

@php
    // Filtres par profil : [valeur, libellé du bouton]
    $filters = [
        [null, 'Tous'],
        ['admin', 'Admin'],
        ['dba', 'DBA'],
        ['auditeur', 'Auditeur'],
        ['lecteur', 'Lecteur'],
    ];
@endphp

@section('content')
    <div class="grid items-start gap-6 xl:grid-cols-[1fr_320px]">

        {{-- Liste des utilisateurs --}}
        <x-card>
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-[17px] font-semibold">Utilisateurs ({{ $users->count() }})</h2>

                <nav class="flex flex-wrap gap-2" aria-label="Filtrer par profil">
                    @foreach ($filters as [$value, $label])
                        <a href="{{ route('admin.users.index', $value ? ['role' => $value] : []) }}"
                           @class(['pill', 'pill-active' => $currentRole === $value])>
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead>
                        <tr class="table-head">
                            <th class="pb-3 pr-4">Nom</th>
                            <th class="pb-3 pr-4">E-mail</th>
                            <th class="pb-3 pr-4">Profil</th>
                            <th class="pb-3 pr-4">Statut</th>
                            <th class="pb-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr class="table-row">
                                <td class="py-2.5 pr-4 font-medium">{{ $user->name }}</td>
                                <td class="py-2.5 pr-4 font-mono text-[13px]">{{ $user->email }}</td>
                                <td class="py-2.5 pr-4"><x-badge :color="$user->role">{{ $user->roleLabel() }}</x-badge></td>
                                <td class="py-2.5 pr-4">
                                    @if ($user->is_active)
                                        <x-badge color="actif">Actif</x-badge>
                                    @else
                                        <x-badge color="desactive">Désactivé</x-badge>
                                    @endif
                                    @if ($user->isLocked())
                                        <x-badge color="elevee" class="ml-1">Verrouillé</x-badge>
                                    @endif
                                </td>
                                <td class="py-2.5">
                                    <div class="flex justify-end gap-2">
                                        @if ($user->isLocked())
                                            <form method="POST" action="{{ route('admin.users.unlock', $user) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn-secondary btn-sm">Débloquer</button>
                                            </form>
                                        @endif

                                        <a href="{{ route('admin.users.edit', $user) }}#creer" class="btn-secondary btn-sm">Modifier</a>

                                        @unless ($user->is(auth()->user()))
                                            <form method="POST" action="{{ route('admin.users.toggle', $user) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn-secondary btn-sm">
                                                    {{ $user->is_active ? 'Désactiver' : 'Activer' }}
                                                </button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-muted">Aucun utilisateur pour ce profil.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- Formulaire de création (ou de modification) --}}
        <x-card id="creer" :title="$editing ? 'Modifier l\'utilisateur' : 'Créer un utilisateur'">
            <form method="POST"
                  action="{{ $editing ? route('admin.users.update', $editing) : route('admin.users.store') }}"
                  class="space-y-5">
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                <div>
                    <label for="name" class="form-label">Nom complet</label>
                    <input id="name" name="name" type="text" class="form-input" placeholder="Prénom Nom"
                           value="{{ old('name', $editing?->name) }}" required>
                    @error('name') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="form-label">Adresse e-mail</label>
                    <input id="email" name="email" type="email" class="form-input" placeholder="prenom.nom@exemple.mr"
                           value="{{ old('email', $editing?->email) }}" required>
                    @error('email') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="role" class="form-label">Profil</label>
                    <select id="role" name="role" class="form-input" required>
                        @foreach (\App\Models\User::ROLES as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', $editing?->role ?? 'dba') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('role') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="form-label">
                        {{ $editing ? 'Nouveau mot de passe temporaire' : 'Mot de passe temporaire' }}
                    </label>
                    <input id="password" name="password" type="password" class="form-input" placeholder="••••••••"
                           autocomplete="new-password" @required(! $editing)>
                    <p class="mt-1.5 text-xs leading-snug text-muted">
                        @if ($editing)
                            Laisser vide pour ne pas changer.
                        @endif
                        Haché en base (bcrypt). L'utilisateur le change à la première connexion.
                    </p>
                    @error('password') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="btn-primary w-full">
                    {{ $editing ? 'Enregistrer les modifications' : 'Créer le compte' }}
                </button>

                @if ($editing)
                    <a href="{{ route('admin.users.index') }}" class="btn-secondary w-full">Annuler</a>
                @endif
            </form>
        </x-card>
    </div>
@endsection
