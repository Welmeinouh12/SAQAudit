{{--
    Carte chiffrée du tableau de bord.
    Utilisation : <x-stat-card label="Règles actives" value="20" suffix="/ 22">2 règles désactivées</x-stat-card>
    danger = true : chiffre en rouge (#8B1A1A)
    tone = "warning" (orange #9A3412) ou "success" (vert #1D5B36) : autres couleurs du chiffre
--}}
@props(['label', 'value', 'suffix' => null, 'danger' => false, 'tone' => null])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-line bg-white px-5 py-6']) }}>
    <p class="text-[13px] font-medium text-[#3D4552]">{{ $label }}</p>

    <p class="mt-3 flex items-baseline gap-2">
        <span @class([
            'text-[32px] font-semibold leading-none',
            'text-[#8B1A1A]' => $danger,
            'text-[#9A3412]' => $tone === 'warning',
            'text-[#1D5B36]' => $tone === 'success',
        ])>{{ $value }}</span>
        @if ($suffix)
            <span class="text-lg text-muted">{{ $suffix }}</span>
        @endif
    </p>

    <div class="mt-4 text-[13px] text-muted">{{ $slot }}</div>
</div>
