@extends('layouts.main')

@section('title', 'Catalogue de règles')
@section('heading', 'Catalogue de règles')
@section('subheading', $canEdit
    ? 'Règles appliquées à chaque audit. Une règle désactivée ne produit plus de constats.'
    : "Règles appliquées à chaque audit. Consultation seule : seul l'administrateur peut les activer ou les désactiver.")

@section('actions')
    <p class="text-[15px] font-semibold sm:mt-9">{{ $activeCount }} / {{ $totalCount }} actives</p>
@endsection

@section('content')
    <x-card>
        {{-- Filtres par sévérité --}}
        <nav class="mb-5 flex flex-wrap gap-2" aria-label="Filtrer par sévérité">
            <a href="{{ route($listRoute) }}" @class(['pill', 'pill-active' => $currentSeverity === null])>Toutes</a>
            @foreach (\App\Models\Rule::SEVERITIES as $value => $label)
                <a href="{{ route($listRoute, ['severity' => $value]) }}"
                   @class(['pill', 'pill-active' => $currentSeverity === $value])>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead>
                    <tr class="table-head">
                        <th class="w-[76px] pb-3">Code</th>
                        <th class="pb-3 pr-4">Règle</th>
                        <th class="w-[150px] pb-3">Sévérité</th>
                        <th class="w-[70px] pb-3 text-right">Active</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rules as $rule)
                        <tr @class(['table-row', 'text-muted' => ! $rule->is_active])>
                            <td class="py-3 font-mono text-[13px]">{{ $rule->code }}</td>
                            <td class="py-3 pr-4">{{ $rule->label }}</td>
                            <td class="py-3"><x-badge :color="$rule->severity" class="font-semibold">{{ $rule->severityLabel() }}</x-badge></td>
                            <td class="py-2 text-right">
                                @if (! $canEdit)
                                    {{-- Consultation seule : interrupteur affiché mais non cliquable --}}
                                    <span role="switch" aria-checked="{{ $rule->is_active ? 'true' : 'false' }}" aria-disabled="true"
                                          aria-label="Règle {{ $rule->code }} {{ $rule->is_active ? 'active' : 'inactive' }}"
                                          @class([
                                              'relative inline-flex h-7 w-12 items-center rounded-full opacity-80',
                                              'bg-primary' => $rule->is_active,
                                              'bg-field' => ! $rule->is_active,
                                          ])>
                                        <span @class([
                                            'inline-block h-5 w-5 rounded-full bg-white shadow',
                                            'translate-x-6' => $rule->is_active,
                                            'translate-x-1' => ! $rule->is_active,
                                        ])></span>
                                    </span>
                                @else
                                {{-- Interrupteur : un clic envoie le formulaire et inverse l'état --}}
                                <form method="POST" action="{{ route('admin.rules.toggle', $rule) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" role="switch"
                                            aria-checked="{{ $rule->is_active ? 'true' : 'false' }}"
                                            aria-label="{{ $rule->is_active ? 'Désactiver' : 'Activer' }} la règle {{ $rule->code }}"
                                            @class([
                                                'relative inline-flex h-7 w-12 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2',
                                                'bg-primary' => $rule->is_active,
                                                'bg-field' => ! $rule->is_active,
                                            ])>
                                        <span @class([
                                            'inline-block h-5 w-5 rounded-full bg-white shadow transition-transform',
                                            'translate-x-6' => $rule->is_active,
                                            'translate-x-1' => ! $rule->is_active,
                                        ])></span>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-muted">
                                Aucune règle. Lancez <code class="font-mono">php artisan db:seed --class=RuleSeeder</code>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
