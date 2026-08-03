<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - Atividades Físicas</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        :root {
            --bg-color: #ffffff;
            --text-main: #111827;
            --text-muted: #6b7280;
            --card-border: #e5e7eb;
            --card-shadow: rgba(0, 0, 0, 0.08);

            --cardio-color: #22c55e;
            --cardio-bg: #eaffef;

            --musc-color: #a855f7;
            --musc-bg: #f4edff;

            --total-color: #3b82f6;
            --total-bg: #eff6ff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            -webkit-font-smoothing: antialiased;
        }

        .mobile-container {
            max-width: 480px;
            margin: 0 auto;
            padding: 1.5rem;
            animation: dashboardFadeIn 0.35s ease-out;
        }

        @keyframes dashboardFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        header h1 {
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .icon {
            width: 24px;
            height: 24px;
            cursor: pointer;
            color: var(--text-main);
        }

        .title-icon {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            color: var(--text-muted);
        }

        .subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.85rem;
            line-height: 1.4;
            margin-bottom: 1.5rem;
        }

        .card {
            background: #ffffff;
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 1.25rem;
            box-shadow: 0 4px 10px -2px var(--card-shadow);
            margin-bottom: 1.25rem;
        }

        /* Calendário */
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
        }

        .nav-btn {
            background: transparent;
            border: 1px solid var(--card-border);
            border-radius: 10px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            text-decoration: none;
        }

        .month-title {
            font-size: 0.95rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px 4px;
            text-align: center;
        }

        .weekday {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .day-cell {
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
        }

        .day-cell {
            position: relative;
        }

        .day-cell.is-today {
            font-weight: 700;
        }

        .day-cell.is-today::after {
            content: '';
            position: absolute;
            bottom: 1px;
            left: 50%;
            transform: translateX(-50%);
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: var(--total-color);
            box-shadow: 0 0 0 2px var(--bg-color);
            z-index: 1;
        }

        .activity-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .activity-cardio {
            background-color: var(--cardio-bg);
            color: var(--cardio-color);
        }

        .activity-musc {
            background-color: var(--musc-bg);
            color: var(--musc-color);
        }

        /* Legenda */
        .legend {
            display: flex;
            gap: 1.25rem;
            padding: 0 0.25rem;
            margin-bottom: 1.25rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .legend-icon {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }

        /* Resumo do mês */
        .summary-title {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        .stat-box {
            border-radius: 12px;
            padding: 1rem 0.75rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stat-box.cardio { background-color: var(--cardio-bg); }
        .stat-box.musc { background-color: var(--musc-bg); }

        .stat-icon {
            width: 22px;
            height: 22px;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .stat-box.cardio .stat-icon { color: var(--cardio-color); }
        .stat-box.musc .stat-icon { color: var(--musc-color); }

        .stat-info { display: flex; flex-direction: column; }
        .stat-number { font-size: 1.1rem; font-weight: 700; }
        .stat-label { font-size: 0.7rem; color: var(--text-muted); }

        .total-box {
            background-color: var(--total-bg);
            border-radius: 12px;
            padding: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .total-box svg {
            width: 16px;
            height: 16px;
            color: var(--total-color);
        }

        .total-number { color: var(--total-color); font-weight: 700; }
    </style>
</head>
<body>

<div class="mobile-container">
    <header>
        @include('dashboard.partials.menu', ['current' => 'exercises'])
        <h1>
            <svg class="title-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6.5 6.5v11"></path><path d="M17.5 6.5v11"></path><path d="M6.5 12h11"></path><rect x="3" y="9" width="3" height="6" rx="1"></rect><rect x="18" y="9" width="3" height="6" rx="1"></rect></svg>
            Atividades Físicas
        </h1>
    </header>

    <p class="subtitle">Acompanhe os dias em que você se exercitou.</p>

    <!-- Calendário -->
    <div class="card">
        <div class="calendar-header">
            <a class="nav-btn" href="?mes={{ $mesReferencia->copy()->subMonth()->month }}&ano={{ $mesReferencia->copy()->subMonth()->year }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </a>
            <div class="month-title">
                @php
                    $nomesMeses = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
                @endphp
                {{ $nomesMeses[$mesReferencia->month] }} {{ $mesReferencia->year }}
            </div>
            <a class="nav-btn" href="?mes={{ $mesReferencia->copy()->addMonth()->month }}&ano={{ $mesReferencia->copy()->addMonth()->year }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
        </div>

        <div class="calendar-grid">
            @foreach(['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $diaSemana)
                <div class="weekday">{{ $diaSemana }}</div>
            @endforeach

            @php
                $diasVaziosNoInicio = $mesReferencia->copy()->startOfMonth()->dayOfWeek;
                $totalDiasNoMes = $mesReferencia->daysInMonth;
                $hoje = now();
            @endphp

            @for($i = 0; $i < $diasVaziosNoInicio; $i++)
                <div></div>
            @endfor

            @for($dia = 1; $dia <= $totalDiasNoMes; $dia++)
                @php
                    $ehHoje = $hoje->day === $dia && $hoje->month === $mesReferencia->month && $hoje->year === $mesReferencia->year;
                    $tipoAtividade = $atividadesPorDia[$dia] ?? null;
                @endphp
                <div class="day-cell {{ $ehHoje ? 'is-today' : '' }}">
                    @if($tipoAtividade === 'musculacao')
                        <div class="activity-icon activity-musc"><i class="ph-fill ph-barbell"></i></div>
                    @elseif($tipoAtividade === 'cardio')
                        <div class="activity-icon activity-cardio"><i class="ph-fill ph-person-simple-run"></i></div>
                    @else
                        {{ $dia }}
                    @endif
                </div>
            @endfor
        </div>
    </div>

    <!-- Legenda -->
    <div class="legend">
        <div class="legend-item">
            <div class="legend-icon activity-cardio"><i class="ph-fill ph-person-simple-run"></i></div>
            Cardio
        </div>
        <div class="legend-item">
            <div class="legend-icon activity-musc"><i class="ph-fill ph-barbell"></i></div>
            Musculação
        </div>
    </div>

    <!-- Resumo do Mês -->
    <div class="card">
        <h2 class="summary-title">Resumo do mês</h2>

        <div class="stats-grid">
            <div class="stat-box cardio">
                <i class="ph-fill ph-person-simple-run stat-icon"></i>
                <div class="stat-info">
                    <span class="stat-number">{{ $diasCardio }} @if ($diasCardio === 1) dia @else dias @endif</span>
                    <span class="stat-label">Cardio</span>
                </div>
            </div>

            <div class="stat-box musc">
                <i class="ph-fill ph-barbell stat-icon"></i>
                <div class="stat-info">
                    <span class="stat-number">{{ $diasMusculacao }} @if ($diasMusculacao === 1) dia @else dias @endif</span>
                    <span class="stat-label">Academia</span>
                </div>
            </div>
        </div>

        <div class="total-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            <span>Total de <span class="total-number">{{ $diasCardio + $diasMusculacao }}</span> dias com atividades</span>
        </div>
    </div>
</div>
</body>
</html>
