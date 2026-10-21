<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Administration') · SQLAudit</title>

    {{-- Polices : IBM Plex Sans (texte) et IBM Plex Mono (codes, e-mails, dates, IP) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-page font-sans text-ink antialiased">
@php
    // Liens de la barre latérale selon le profil : [route, libellé, motif pour le lien actif]
    $menus = [
        'admin' => [
            ['admin.dashboard',      'Tableau de bord',     'admin.dashboard'],
            ['admin.users.index',    'Utilisateurs',        'admin.users.*'],
            ['admin.rules.index',    'Catalogue de règles', 'admin.rules.*'],
            ['admin.settings.index', 'Paramètres',          'admin.settings.*'],
            ['admin.logs.index',     "Journal d'activité",  'admin.logs.*'],
        ],
        'dba' => [
            ['dashboard',       'Tableau de bord',     'dashboard'],
            ['instances.index', 'Instances',           'instances.*'],
            ['audits.index',    'Audits et constats',  'audits.*'],
            ['rules.index',     'Règles',              'rules.*'],
            ['reports.index',   'Rapports',            'reports.*'],
        ],
        'auditeur' => [
            ['dashboard',        'Tableau de bord',     'dashboard'],
            ['audits.index',     'Audits et constats',  ['audits.index', 'audits.show']],
            ['exceptions.index', 'Exceptions',          'exceptions.*'],
            ['audits.compare',   'Comparer les audits', 'audits.compare'],
            ['reports.index',    'Rapports',            'reports.*'],
        ],
        'lecteur' => [
            ['dashboard',       'Tableau de bord',      'dashboard'],
            ['syntheses.index', 'Rapports de synthèse', 'syntheses.*'],
        ],
    ];

    $role = auth()->user()->role;
    $menu = $menus[$role] ?? [];
    $home = $role === 'admin' ? route('admin.dashboard') : route('dashboard');

    // Pastille jaune du lien « Exceptions » : nombre de demandes en attente (auditeur)
    $badges = $role === 'auditeur'
        ? ['exceptions.index' => \App\Models\FindingException::where('status', 'pending')->count()]
        : [];
@endphp

<div class="flex min-h-screen flex-col md:flex-row">

    {{-- Barre latérale (au-dessus du contenu sur mobile) --}}
    <aside class="flex w-full shrink-0 flex-col bg-sidebar px-4 py-6 md:sticky md:top-0 md:h-screen md:w-[232px]">

        <a href="{{ $home }}" class="mb-7 flex items-center gap-2.5 px-2.5 text-lg font-bold text-white">
            {{-- Icône base de données --}}
            <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <ellipse cx="12" cy="5" rx="8" ry="3"/>
                <path d="M4 5v14c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/>
                <path d="M4 12c0 1.66 3.58 3 8 3s8-1.34 8-3"/>
            </svg>
            SQLAudit
        </a>

        <nav class="flex flex-row flex-wrap gap-1 md:flex-col md:gap-1.5">
            @foreach ($menu as [$routeName, $label, $pattern])
                @php $active = request()->routeIs(...(array) $pattern); @endphp
                <a href="{{ Route::has($routeName) ? route($routeName) : '#' }}"
                   @class([
                       'flex items-center justify-between gap-2 rounded-lg px-3 py-3 text-[15px] transition-colors',
                       'bg-sidebar-active font-semibold text-white' => $active,
                       'text-sidebar-link hover:bg-white/5 hover:text-white' => ! $active,
                   ])
                   @if ($active) aria-current="page" @endif>
                    {{ $label }}
                    @if (! empty($badges[$routeName]))
                        <span class="inline-flex h-[22px] min-w-[22px] items-center justify-center rounded-full bg-[#F2B544] px-1.5 text-[11px] font-bold text-[#13283F]"
                              aria-label="{{ $badges[$routeName] }} en attente">{{ $badges[$routeName] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        {{-- Bloc utilisateur connecté --}}
        <div class="mt-6 border-t border-white/10 pt-5 md:mt-auto">
            <p class="px-3 text-sm font-semibold text-white">{{ auth()->user()->name }}</p>
            <p class="px-3 text-[13px] text-sidebar-link/80">{{ $role === 'auditeur' ? 'Auditeur / Contrôle' : auth()->user()->roleLabel() }}</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-3 px-3">
                @csrf
                <button type="submit" class="text-[13px] text-sidebar-link underline hover:text-white">
                    Se déconnecter
                </button>
            </form>
        </div>
    </aside>

    {{-- Zone de contenu --}}
    <main class="min-w-0 flex-1 px-4 py-8 sm:px-10 sm:py-9">

        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                @yield('breadcrumb')
                <h1 class="text-[28px] font-semibold leading-tight">@yield('heading')</h1>
                <p class="mt-1.5 text-sm text-muted">@yield('subheading')</p>
            </div>
            @hasSection('actions')
                <div class="flex items-center gap-3">@yield('actions')</div>
            @endif
        </div>

        {{-- Message de confirmation après une action --}}
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-[#B9DCC6] bg-[#E3F1E8] px-4 py-3 text-sm text-[#1D5B36]" role="status">
                {{ session('status') }}
            </div>
        @endif

        {{-- Message d'erreur après une action (ex. test de connexion échoué) --}}
        @if (session('error'))
            <div class="mb-6 rounded-lg border border-[#F2C2C2] bg-[#FBE4E4] px-4 py-3 text-sm text-[#8B1A1A]" role="alert">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>
</div>
</body>
</html>
