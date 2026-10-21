<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Connexion · SQLAudit</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-page px-4 py-10 font-sans text-ink antialiased">

    <main class="w-full max-w-[400px] rounded-xl border border-line bg-white px-6 py-10 sm:px-10">
        <h1 class="text-[26px] font-semibold leading-tight">Connexion</h1>
        <p class="mt-2 text-[15px] text-muted">Saisissez vos identifiants de la plateforme.</p>

        {{-- Message d'information (ex. après réinitialisation du mot de passe) --}}
        @if (session('status'))
            <div class="mt-5 rounded-lg border border-[#B9DCC6] bg-[#E3F1E8] px-4 py-3 text-sm text-[#1D5B36]" role="status">
                {{ session('status') }}
            </div>
        @endif

        {{-- Erreur de connexion : identifiants incorrects, compte verrouillé ou désactivé --}}
        @if ($errors->any())
            <div class="mt-5 rounded-lg border border-[#F2C2C2] bg-[#FBE4E4] px-4 py-3 text-sm text-[#8B1A1A]" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label for="email" class="form-label">Adresse e-mail</label>
                <input id="email" name="email" type="email" class="form-input" placeholder="prenom.nom@exemple.mr"
                       value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>

            <div>
                <label for="password" class="form-label">Mot de passe</label>
                <input id="password" name="password" type="password" class="form-input" placeholder="••••••••"
                       required autocomplete="current-password">
            </div>

            <div class="flex items-center gap-3">
                <input id="remember_me" name="remember" type="checkbox"
                       class="h-[18px] w-[18px] rounded border-field text-primary focus:ring-primary">
                <label for="remember_me" class="text-[15px] text-[#3D4552]">Se souvenir de moi</label>
            </div>

            <button type="submit" class="btn-primary w-full text-[15px]">Se connecter</button>
        </form>

        <p class="mt-6 text-[13px] text-muted">
            Le compte est verrouillé après {{ \App\Models\Setting::get('lockout_attempts') }} tentatives échouées.
        </p>
    </main>

</body>
</html>
