@extends('layouts.main')

@section('title', 'Rapports')
@section('heading', 'Rapports')
@section('subheading', "Générer les rapports d'audit pour la direction, le contrôle interne ou les auditeurs externes")

@php
    // Grandes cartes « Type de rapport » : [valeur, titre, description]
    $types = [
        ['detail', 'Rapport détaillé d’audit', 'Tous les constats avec preuves et recommandations'],
        ['synthese', 'Synthèse de conformité', 'Chiffres clés et risques majeurs, pour la direction'],
    ];
    $available = \App\Http\Controllers\ReportController::AVAILABLE_FORMATS;
@endphp

@section('content')
    <div class="grid items-start gap-6 xl:grid-cols-[360px_1fr]">

        {{-- Nouveau rapport --}}
        <x-card title="Nouveau rapport">
            @if ($instances->isEmpty())
                {{-- Aucun audit terminé : le rapport serait vide --}}
                <div class="mb-5 rounded-lg border border-[#F5D98B] bg-[#FEF3C7] px-4 py-3 text-sm text-[#854D0E]" role="status">
                    Aucun audit terminé pour le moment : le rapport serait vide.
                    @if (auth()->user()->role === 'dba')
                        Lancez d'abord un audit depuis
                        <a href="{{ route('instances.index') }}" class="font-semibold underline">Instances</a> (bouton « Auditer »).
                    @else
                        Un DBA doit d'abord lancer un audit.
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('reports.store') }}" class="space-y-6">
                @csrf

                {{-- Type : boutons radio affichés comme de grandes cartes cliquables --}}
                <fieldset>
                    <legend class="form-label">Type de rapport</legend>
                    <div class="space-y-2.5">
                        @foreach ($types as [$value, $title, $description])
                            <label class="block cursor-pointer">
                                <input type="radio" name="type" value="{{ $value }}" class="peer sr-only"
                                       @checked(old('type', 'detail') === $value)>
                                <span class="block rounded-lg border border-field px-4 py-3.5 transition-colors hover:bg-page
                                             peer-checked:border-2 peer-checked:border-primary peer-checked:bg-[#F2F6FA]
                                             peer-focus-visible:ring-2 peer-focus-visible:ring-primary peer-focus-visible:ring-offset-2">
                                    <span class="block text-[15px] font-semibold">{{ $title }}</span>
                                    <span class="mt-0.5 block text-[13px] leading-snug text-muted">{{ $description }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('type') <p class="form-error">{{ $message }}</p> @enderror
                </fieldset>

                <div>
                    <label for="scope" class="form-label">Périmètre</label>
                    <select id="scope" name="scope" class="form-input">
                        <option value="all">Toutes les instances</option>
                        @foreach ($instances as $instance)
                            <option value="{{ $instance->id }}" @selected(old('scope') == $instance->id)>{{ $instance->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-xs text-muted">Le rapport porte sur le dernier audit terminé de chaque instance.</p>
                    @error('scope') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                {{-- Format : boutons radio affichés comme des pilules --}}
                <fieldset>
                    <legend class="form-label">Format</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (\App\Models\ReportExport::FORMATS as $value => $label)
                            @php $enabled = in_array($value, $available, true); @endphp
                            <label @class(['cursor-pointer' => $enabled, 'cursor-not-allowed' => ! $enabled])
                                   @unless ($enabled) title="Bientôt disponible" @endunless>
                                <input type="radio" name="format" value="{{ $value }}" class="peer sr-only"
                                       @checked(old('format', 'pdf') === $value) @disabled(! $enabled)>
                                <span class="inline-flex h-10 items-center rounded-lg border border-field bg-white px-4 text-sm transition-colors
                                             peer-checked:border-primary peer-checked:bg-primary peer-checked:font-semibold peer-checked:text-white
                                             peer-disabled:opacity-50 peer-focus-visible:ring-2 peer-focus-visible:ring-primary peer-focus-visible:ring-offset-2">
                                    {{ $label }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('format') <p class="form-error">{{ $message }}</p> @enderror
                </fieldset>

                <div class="flex items-center gap-3">
                    <input id="include_exceptions" name="include_exceptions" type="checkbox" value="1"
                           class="h-[18px] w-[18px] rounded border-field text-primary focus:ring-primary"
                           @checked(old('include_exceptions', true))>
                    <label for="include_exceptions" class="text-sm">Inclure les exceptions validées</label>
                </div>

                <button type="submit" class="btn-primary w-full">Générer le rapport</button>
            </form>
        </x-card>

        {{-- Exports récents --}}
        <x-card title="Exports récents">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead>
                        <tr class="table-head">
                            <th class="pb-3 pr-4">Rapport</th>
                            <th class="pb-3 pr-4">Périmètre</th>
                            <th class="pb-3 pr-4">Format</th>
                            <th class="pb-3 pr-4">Date</th>
                            <th class="pb-3 text-right">Fichier</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($exports as $export)
                            <tr class="table-row">
                                <td class="py-3 pr-4 font-medium">{{ $export->typeLabel() }}</td>
                                <td class="py-3 pr-4 text-[#3D4552]">{{ $export->scope }}</td>
                                <td class="py-3 pr-4">
                                    <span class="inline-flex rounded bg-line-soft px-2 py-0.5 font-mono text-xs font-semibold text-[#3D4552]">{{ $export->formatLabel() }}</span>
                                </td>
                                <td class="whitespace-nowrap py-3 pr-4 font-mono text-[13px]">{{ $export->created_at->format('d/m/Y H:i') }}</td>
                                <td class="py-3 text-right">
                                    <a href="{{ route('reports.download', $export) }}" class="text-primary underline">Télécharger</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-muted">Aucun rapport généré pour le moment.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-4 text-[13px] text-muted">
                Chaque rapport porte l'empreinte SHA-256 de l'audit et chaque export est enregistré dans le journal.
            </p>
        </x-card>
    </div>
@endsection
