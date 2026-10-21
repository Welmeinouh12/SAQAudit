<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accès refusé · SQLAudit</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-page px-4 py-10 font-sans text-ink antialiased">
@php
    // Page d'accueil selon le profil de l'utilisateur connecté
    $user = auth()->user();
    // null : profil sans page d'accueil
    $home = match (true) {
        $user === null => route('login'),
        $user->role === 'admin' => route('admin.dashboard'),
        in_array($user->role, ['dba', 'auditeur', 'lecteur'], true) => route('dashboard'),
        default => null,
    };
@endphp

    <main class="w-full max-w-[440px] rounded-xl border border-line bg-white px-6 py-10 text-center sm:px-10">
        <p class="font-mono text-sm text-[#8B1A1A]">Erreur 403</p>
        <h1 class="mt-2 text-[26px] font-semibold leading-tight">Accès refusé</h1>
        <p class="mt-3 text-[15px] text-muted">
            @if ($user)
                Votre profil <strong class="font-semibold text-ink">{{ $user->roleLabel() }}</strong>
                ne permet pas d'ouvrir cette page.
            @else
                Vous devez être connecté pour ouvrir cette page.
            @endif
        </p>

        @if ($home)
            <a href="{{ $home }}" class="btn-primary mt-7 w-full">
                {{ $user ? 'Retour à mon tableau de bord' : 'Se connecter' }}
            </a>
        @else
            <form method="POST" action="{{ route('logout') }}" class="mt-7">
                @csrf
                <button type="submit" class="btn-primary w-full">Se déconnecter</button>
            </form>
        @endif
    </main>

</body>
</html>
