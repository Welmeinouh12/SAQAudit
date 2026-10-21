{{--
    Carte blanche : bordure #DDE1E6, coins 12 px, padding 24 px.
    Utilisation : <x-card title="Sécurité des comptes" subtitle="...">...</x-card>
--}}
@props(['title' => null, 'subtitle' => null])

<section {{ $attributes->merge(['class' => 'rounded-xl border border-line bg-white p-6']) }}>
    @if ($title)
        <header class="mb-4">
            <h2 class="text-[17px] font-semibold">{{ $title }}</h2>
            @if ($subtitle)
                <p class="mt-1 text-[13px] text-muted">{{ $subtitle }}</p>
            @endif
        </header>
    @endif

    {{ $slot }}
</section>
