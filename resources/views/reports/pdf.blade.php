<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $typeLabel }} · SQLAudit</title>
    {{-- Vue imprimée par dompdf : CSS simple, police DejaVu Sans (gère les accents) --}}
    <style>
        @page { margin: 28px 32px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #161B22; }
        .mono { font-family: "DejaVu Sans Mono", monospace; }
        .muted { color: #5A6270; }
        .header { border-bottom: 2px solid #1F4E79; padding-bottom: 8px; margin-bottom: 14px; }
        .logo { font-size: 16px; font-weight: bold; color: #13283F; }
        h1 { font-size: 18px; margin: 6px 0 2px; }
        h2 { font-size: 13px; margin: 18px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th { text-align: left; font-size: 8px; text-transform: uppercase; color: #5A6270; border-bottom: 1px solid #DDE1E6; padding: 5px 4px; }
        td { border-bottom: 1px solid #EEF0F3; padding: 5px 4px; vertical-align: top; }
        .badge { padding: 1px 6px; border-radius: 8px; font-size: 8px; font-weight: bold; }
        .critique { background: #FBE4E4; color: #8B1A1A; }
        .elevee { background: #FDEBDD; color: #9A3412; }
        .moyenne { background: #FEF3C7; color: #854D0E; }
        .faible { background: #E5EDF6; color: #1F4E79; }
        .exception { background: #E3F1E8; color: #1D5B36; }
        .box { background: #F4F5F7; padding: 6px 8px; margin-top: 6px; }
        .footer { margin-top: 18px; font-size: 8px; color: #5A6270; }
    </style>
</head>
<body>
@php
    $severities = \App\Models\Rule::SEVERITIES;
    $generator = app(\App\Services\ReportGenerator::class);
@endphp

    <div class="header">
        <div class="logo">SQLAudit</div>
        <h1>{{ $typeLabel }}</h1>
        <div class="muted">
            Périmètre : {{ $scope }} · généré le {{ $generatedAt->format('d/m/Y à H:i') }} par {{ $generatedBy->name }}
            · exceptions validées {{ $includeExceptions ? 'incluses' : 'exclues' }}
        </div>
        @if ($fingerprint)
            <div class="muted">Empreinte SHA-256 : <span class="mono">{{ $fingerprint }}</span></div>
        @endif
    </div>

    @if ($runs->isEmpty())
        <p>Aucun audit terminé dans ce périmètre.</p>
    @endif

    @if ($type === 'synthese')
        {{-- Synthèse : chiffres clés par instance, puis risques critiques --}}
        <h2>Chiffres clés</h2>
        <table>
            <thead>
                <tr>
                    <th>Instance</th><th>Dernier audit</th>
                    @foreach ($severities as $label) <th>{{ $label }}</th> @endforeach
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($runs as $run)
                    @php $findings = $generator->visibleFindings($run, $includeExceptions); @endphp
                    <tr>
                        <td class="mono">{{ $run->instance->name }}</td>
                        <td>#{{ $run->id }} · {{ $run->started_at?->format('d/m/Y') }}</td>
                        @foreach (array_keys($severities) as $severity)
                            <td>{{ $findings->where('severity', $severity)->count() }}</td>
                        @endforeach
                        <td><strong>{{ $findings->count() }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>Risques majeurs (critiques)</h2>
        <table>
            <thead><tr><th>Instance</th><th>Règle</th><th>Objet</th><th>Recommandation</th></tr></thead>
            <tbody>
                @php $hasCritical = false; @endphp
                @foreach ($runs as $run)
                    @foreach ($generator->visibleFindings($run, $includeExceptions)->where('severity', 'critique') as $finding)
                        @php $hasCritical = true; @endphp
                        <tr>
                            <td class="mono">{{ $run->instance->name }}</td>
                            <td><span class="mono">{{ $finding->rule->code }}</span> · {{ $finding->rule->label }}</td>
                            <td class="mono">{{ $finding->object_name }}</td>
                            <td>{{ $finding->recommendation }}</td>
                        </tr>
                    @endforeach
                @endforeach
                @unless ($hasCritical)
                    <tr><td colspan="4" class="muted">Aucun constat critique.</td></tr>
                @endunless
            </tbody>
        </table>
    @else
        {{-- Rapport détaillé : un tableau de constats par instance --}}
        @foreach ($runs as $run)
            <h2><span class="mono">{{ $run->instance->name }}</span> · audit #{{ $run->id }}</h2>
            <div class="box muted">
                {{ $run->started_at?->format('d/m/Y H:i') }} → {{ $run->ended_at?->format('H:i') }}
                · lancé par {{ $run->user?->name ?? '—' }}
                · {{ $run->logins_count }} logins, {{ $run->databases_count }} bases
                · empreinte <span class="mono">{{ $run->fingerprint }}</span>
            </div>

            <table>
                <thead>
                    <tr><th style="width: 16%">Règle</th><th style="width: 8%">Sévérité</th><th style="width: 16%">Objet</th><th>Preuve</th><th>Recommandation</th></tr>
                </thead>
                <tbody>
                    @forelse ($generator->visibleFindings($run, $includeExceptions) as $finding)
                        <tr>
                            <td><span class="mono">{{ $finding->rule->code }}</span><br><span class="muted">{{ $finding->rule->label }}</span></td>
                            <td><span class="badge {{ $finding->severity }}">{{ $finding->severityLabel() }}</span></td>
                            <td class="mono">{{ $finding->object_name }}</td>
                            <td>{{ $finding->evidence }}</td>
                            <td>
                                {{ $finding->recommendation }}
                                @if ($finding->activeException)
                                    <br><span class="badge exception">Exception validée jusqu'au {{ $finding->activeException->requested_expiration->format('d/m/Y') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">Aucun constat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endforeach
    @endif

    <div class="footer">
        Rapport généré par SQLAudit. Les données ont été collectées en lecture seule ; l'empreinte SHA-256 permet de vérifier qu'elles n'ont pas été modifiées.
    </div>
</body>
</html>
