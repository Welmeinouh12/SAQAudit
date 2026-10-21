{{--
    Badge « pilule » coloré.
    Utilisation : <x-badge color="critique">Critique</x-badge>
--}}
@props(['color' => 'gris'])

@php
    $colors = [
        // Sévérités
        'critique' => 'bg-[#FBE4E4] text-[#8B1A1A]',
        'elevee'   => 'bg-[#FDEBDD] text-[#9A3412]',
        'moyenne'  => 'bg-[#FEF3C7] text-[#854D0E]',
        'faible'   => 'bg-[#E5EDF6] text-[#1F4E79]',
        // Profils
        'admin'    => 'bg-[#E6E1F3] text-[#4B2E83]',
        'dba'      => 'bg-[#E5EDF6] text-[#1F4E79]',
        'auditeur' => 'bg-[#DFF0EE] text-[#155E57]',
        'lecteur'  => 'bg-[#EEF0F3] text-[#3D4552]',
        // Statuts
        'actif'     => 'bg-[#E3F1E8] text-[#1D5B36]',
        'desactive' => 'bg-[#FBE4E4] text-[#8B1A1A]',
        // Types du journal d'activité
        'connexion'      => 'bg-[#E5EDF6] text-[#1F4E79]',
        'audit'          => 'bg-[#DFF0EE] text-[#155E57]',
        'export'         => 'bg-[#EEF0F3] text-[#3D4552]',
        'administration' => 'bg-[#E6E1F3] text-[#4B2E83]',
        // Par défaut
        'gris' => 'bg-[#EEF0F3] text-[#3D4552]',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-[13px] font-medium '.($colors[$color] ?? $colors['gris'])]) }}>
    {{ $slot }}
</span>
