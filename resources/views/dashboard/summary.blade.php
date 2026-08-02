<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard Nutricional</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-color: #ffffff;
            --text-main: #1a1a1a;
            --text-muted: #6b7280;
            --card-border: #e5e7eb;
            --card-shadow: rgba(0, 0, 0, 0.08);

            /* Cores dos Macros */
            --color-kcal: #34c759;
            --color-protein: #af52de;
            --color-fat: #ffcc00;
            --color-carbs: #007aff;
            --color-danger: #ff3b30;

            --track-bg: #f3f4f6;
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
            overflow-x: hidden;
        }

        .mobile-container {
            max-width: 480px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        @keyframes dashboardFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Aplicado só nos blocos sem gráfico — o carrossel de gráficos usa
           a própria animação de entrada do Chart.js, que já é suave sozinha;
           somar um fade do container por cima dela ficava estranho. */
        header, .section-title, .macros-grid, .meal-list {
            animation: dashboardFadeIn 0.35s ease-out;
        }

        /* Header */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        header h1 {
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .icon {
            width: 24px;
            height: 24px;
            cursor: pointer;
        }

        .title-icon {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            color: var(--text-muted);
        }

        /* Títulos de Seção */
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            margin-top: 1.5rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Streak de dias na meta */
        .streak-banner {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            border-radius: 12px;
            background: linear-gradient(135deg, #fff4e6, #ffe8d1);
            box-shadow: 0 4px 10px -2px var(--card-shadow);
        }

        .streak-fire {
            font-size: 1.6rem;
            display: inline-block;
            transform-origin: 50% 100%;
            filter: drop-shadow(0 0 6px rgba(255, 140, 40, 0.45));
            animation: streakFire 1.9s ease-in-out infinite;
        }

        @keyframes streakFire {
            0%   { transform: scaleY(1)    scaleX(1)    skewX(0deg); }
            20%  { transform: scaleY(1.07) scaleX(0.95) skewX(-3deg); }
            40%  { transform: scaleY(0.95) scaleX(1.05) skewX(3deg); }
            60%  { transform: scaleY(1.04) scaleX(0.97) skewX(-2deg); }
            80%  { transform: scaleY(0.97) scaleX(1.03) skewX(2deg); }
            100% { transform: scaleY(1)    scaleX(1)    skewX(0deg); }
        }

        @media (prefers-reduced-motion: reduce) {
            .streak-fire {
                animation: none;
            }
        }

        .streak-text {
            font-size: 0.9rem;
            font-weight: 700;
            color: #b45309;
        }

        /* Grid de Resumo */
        .macros-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .macro-card {
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 4px 10px -2px var(--card-shadow);
        }

        .macro-values {
            margin-bottom: 4px;
        }

        .macro-current {
            font-size: 1.25rem;
            font-weight: 700;
        }

        .macro-target {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .macro-name {
            font-size: 0.85rem;
            color: var(--text-muted);
            display: block;
            margin-bottom: 0.75rem;
        }

        .progress-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .progress-track {
            flex: 1;
            height: 6px;
            background: var(--track-bg);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 4px;
        }

        .progress-pct {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-muted);
        }

        /* Cores específicas dos cards */
        .card-kcal .progress-fill { background-color: var(--color-kcal); }
        .card-protein .progress-fill { background-color: var(--color-protein); }
        .card-fat .progress-fill { background-color: var(--color-fat); }
        .card-carbs .progress-fill { background-color: var(--color-carbs); }

        /* Macro com meta estourada */
        .macro-card.over-goal {
            border-color: var(--color-danger);
        }

        .macro-card.over-goal .progress-fill {
            background-color: var(--color-danger);
        }

        .macro-card.over-goal .progress-pct {
            color: var(--color-danger);
        }

        /* Gráfico */
        .chart-wrapper {
            width: 100%;
            height: 220px;
            margin-bottom: 1rem;
        }

        /* Carrossel de gráficos por macro */
        .chart-carousel {
            display: flex;
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }

        .chart-carousel::-webkit-scrollbar {
            display: none;
        }

        .chart-slide {
            flex: 0 0 100%;
            max-width: 100%;
            min-width: 0;
            scroll-snap-align: start;
            border: 1px solid var(--card-border);
            border-radius: 12px;
            margin-bottom: 5px;
            padding: 1rem;
            box-shadow: 0 4px 10px -2px var(--card-shadow);
        }

        .chart-slide .chart-wrapper {
            max-width: 100%;
            margin-bottom: 0;
        }

        .chart-slide canvas {
            max-width: 100%;
        }

        .chart-slide-title {
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .carousel-dots {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-bottom: 1.5rem;
        }

        .carousel-dots .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--track-bg);
            transition: background-color 0.2s;
        }

        .carousel-dots .dot.active {
            background: var(--text-muted);
        }

        /* Lista de Refeições */
        .meal-list {
            display: flex;
            flex-direction: column;
            gap: 0;
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 0 1rem;
            box-shadow: 0 4px 10px -2px var(--card-shadow);
        }

        .meal-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--card-border);
        }

        .meal-item:last-child {
            border-bottom: none;
        }

        .meal-icon-bg {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        .bg-green { background-color: #e6f9ed; }
        .bg-purple { background-color: #f3e8fc; }
        .bg-orange { background-color: #fef3e6; }

        .meal-info {
            flex: 1;
        }

        .meal-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .meal-time {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .meal-stats {
            text-align: right;
            margin-right: 12px;
        }

        .meal-kcal {
            font-weight: 700;
            font-size: 0.9rem;
        }

        .meal-kcal span {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-muted);
        }

        .meal-macros {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .meal-macros .macro-letter {
            font-weight: 700;
            color: #4b5563;
        }

    </style>
</head>
<body>

<div class="mobile-container">
    <!-- Cabeçalho -->
    <header>
        @include('dashboard.partials.menu', ['current' => $current ?? null])
        @if (isset($last7DaysMeals) && !empty($last7DaysMeals))
        <h1>
            <svg class="title-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            Olá, {{ $userName }}
        </h1>
        @else
         <h2 class="section-title">
            <svg class="title-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            Resumo {{ $summary['period_formatado'] }}
        </h2>
        @endif
    </header>

    <!-- Resumo de Hoje -->
    @if (isset($last7DaysMeals) && !empty($last7DaysMeals))
        <h2 class="section-title">
            <svg class="title-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            Resumo de hoje
        </h2>
    @endif
    @if(($streak ?? 0) > 0)
        <div class="streak-banner">
            <span class="streak-fire">🔥</span>
            <span class="streak-text">{{ $streak }} {{ $streak === 1 ? 'dia consecutivo na meta' : 'dias consecutivos na meta' }}</span>
        </div>
    @endif
    <div class="macros-grid">
        <!-- Calorias -->
        <div class="macro-card card-kcal @if((int) $summary['%_calories'] > 100) over-goal @endif">
            <div class="macro-values">
                <span class="macro-current">{{ $summary['total_calories_kcal'] }}</span>
                @if($summary['user_calories_goal_kcal'] > 0)
                    <span class="macro-target">/ {{ $summary['user_calories_goal_kcal'] }}kcal</span>
                @endif
            </div>
            <span class="macro-name">Calorias</span>
            <div class="progress-container">
                <div class="progress-track"><div class="progress-fill" style="width: {{ $summary['%_calories'] ?: '100%' }}"></div></div>
                @if($summary['%_calories'])
                    <span class="progress-pct">{{ $summary['%_calories'] }}</span>
                @endif
            </div>
        </div>

        <!-- Proteína -->
        <div class="macro-card card-protein @if((int) $summary['%_protein'] > 100) over-goal @endif">
            <div class="macro-values">
                <span class="macro-current">{{ $summary['total_protein_g'] }}</span>
                @if ($summary['user_protein_goal_g'] > 0)
                    <span class="macro-target">/ {{ $summary['user_protein_goal_g'] }}g</span>
                @endif
            </div>
            <span class="macro-name">Proteína</span>
            <div class="progress-container">
                <div class="progress-track"><div class="progress-fill" style="width: {{ $summary['%_protein'] ?: '100%' }}"></div></div>
                @if($summary['%_protein'])
                    <span class="progress-pct">{{ $summary['%_protein'] }}</span>
                @endif
            </div>
        </div>

        <!-- Gordura -->
        <div class="macro-card card-fat @if((int) $summary['%_fat'] > 100) over-goal @endif">
            <div class="macro-values">
                <span class="macro-current">{{ $summary['total_fat_g'] }}</span>
                @if ($summary['user_fat_goal_g'] > 0)
                    <span class="macro-target">/ {{ $summary['user_fat_goal_g'] }}g</span>
                @endif
            </div>
            <span class="macro-name">Gordura</span>
            <div class="progress-container">
                <div class="progress-track"><div class="progress-fill" style="width: {{ $summary['%_fat'] ?: '100%' }}"></div></div>
                @if($summary['%_fat'])
                    <span class="progress-pct">{{ $summary['%_fat'] }}</span>
                @endif
            </div>
        </div>

        <!-- Carboidrato -->
        <div class="macro-card card-carbs @if((int) $summary['%_carbohydrate'] > 100) over-goal @endif">
            <div class="macro-values">
                <span class="macro-current">{{ $summary['total_carbohydrate_g'] }}</span>
                @if ($summary['user_carbohydrate_goal_g'] > 0)
                    <span class="macro-target">/ {{ $summary['user_carbohydrate_goal_g'] }}g</span>
                @endif
            </div>
            <span class="macro-name">Carboidrato</span>
            <div class="progress-container">
                <div class="progress-track"><div class="progress-fill" style="width: {{ $summary['%_carbohydrate'] ?: '100%' }}"></div></div>
                @if($summary['%_carbohydrate'])
                    <span class="progress-pct">{{ $summary['%_carbohydrate'] }}</span>
                @endif
            </div>
        </div>
    </div>

    @if (isset($last7DaysMeals) && !empty($last7DaysMeals))

    <!-- Evolução da Semana -->
    <h2 class="section-title">
        <svg class="title-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
        Evolução da semana
    </h2>
    <div class="chart-carousel">
        <div class="chart-slide">
            <div class="chart-slide-title" style="color: var(--color-kcal)">Calorias</div>
            <div class="chart-wrapper"><canvas id="chartKcal"></canvas></div>
        </div>
        <div class="chart-slide">
            <div class="chart-slide-title" style="color: var(--color-protein)">Proteína</div>
            <div class="chart-wrapper"><canvas id="chartProtein"></canvas></div>
        </div>
        <div class="chart-slide">
            <div class="chart-slide-title" style="color: var(--color-fat)">Gordura</div>
            <div class="chart-wrapper"><canvas id="chartFat"></canvas></div>
        </div>
        <div class="chart-slide">
            <div class="chart-slide-title" style="color: var(--color-carbs)">Carboidrato</div>
            <div class="chart-wrapper"><canvas id="chartCarbs"></canvas></div>
        </div>
    </div>

    <div class="carousel-dots">
        <span class="dot active"></span>
        <span class="dot"></span>
        <span class="dot"></span>
        <span class="dot"></span>
    </div>
    @endif
    <!-- Últimas Refeições -->
    @if (!isset($last7DaysMeals) && empty($last7DaysMeals))
        <h2 class="section-title">
            <svg class="title-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path><path d="M7 2v20"></path><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path></svg>
            Refeições do dia
        </h2>
    @else
        <h2 class="section-title">
            <svg class="title-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path><path d="M7 2v20"></path><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path></svg>
            Últimas refeições
        </h2>
    @endif
    <div class="meal-list">
        @forelse($dayMeals as $meal)
            @php
                $hora = $meal->consumed_at->hour;
                if ($hora < 12) {
                    $periodo = 'Manhã';
                    $corClasse = 'bg-green';
                    $corIcone = '#2c854b';
                    $iconePath = '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M19.07 4.93l-1.41 1.41M6.34 17.66l-1.41 1.41"/>';
                } elseif ($hora < 18) {
                    $periodo = 'Tarde';
                    $corClasse = 'bg-orange';
                    $corIcone = '#d97706';
                    $iconePath = '<path d="M17.5 19H9a5 5 0 1 1 .5-9.9A5.5 5.5 0 0 1 20 12.5a4.5 4.5 0 0 1-2.5 4.5"/>';
                } else {
                    $periodo = 'Noite';
                    $corClasse = 'bg-purple';
                    $corIcone = '#8b5cf6';
                    $iconePath = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';
                }
            @endphp
            <div class="meal-item">
                <div class="meal-icon-bg {{ $corClasse }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="{{ $corIcone }}" stroke-width="2">{!! $iconePath !!}</svg>
                </div>
                <div class="meal-info">
                    <div class="meal-name">{{ $periodo }}</div>
                    <div class="meal-time">{{ $meal->consumed_at->format('H:i') }}</div>
                </div>
                <div class="meal-stats">
                    <div class="meal-kcal">{{ round($meal->total_calories_kcal) }} <span>kcal</span></div>
                    <div class="meal-macros"><span class="macro-letter">P</span> {{ round($meal->total_protein_g) }}g &nbsp; <span class="macro-letter">G</span> {{ round($meal->total_fat_g) }}g &nbsp; <span class="macro-letter">C</span> {{ round($meal->total_carbohydrate_g) }}g</div>
                </div>
            </div>
        @empty
            <p style="color: var(--text-muted); font-size: 0.85rem; text-align: center; padding: 1rem 0;">Nenhuma refeição registrada hoje.</p>
        @endforelse
    </div>
</div>

@if (isset($last7DaysMeals) && !empty($last7DaysMeals))
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Configuração de estilo global do Chart.js
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = '#6b7280';

        const labels = @json($chartLabels);

        // Converte uma cor hex em rgba, pra montar o degradê do preenchimento
        function hexParaRgba(hex, alpha) {
            const valor = hex.replace('#', '');
            const r = parseInt(valor.substring(0, 2), 16);
            const g = parseInt(valor.substring(2, 4), 16);
            const b = parseInt(valor.substring(4, 6), 16);
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }

        function criarGradiente(ctx, cor) {
            const gradiente = ctx.createLinearGradient(0, 0, 0, 220);
            gradiente.addColorStop(0, hexParaRgba(cor, 0.35));
            gradiente.addColorStop(1, hexParaRgba(cor, 0));
            return gradiente;
        }

        function criarGraficoMacro(canvasId, cor, dados) {
            const ctx = document.getElementById(canvasId).getContext('2d');
            return new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        data: dados,
                        borderColor: cor,
                        backgroundColor: criarGradiente(ctx, cor),
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointBackgroundColor: cor,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: true }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                font: { size: 10 },
                                callback: value => value.toLocaleString('pt-BR')
                            },
                            grid: {
                                color: '#f3f4f6',
                                drawBorder: false
                            },
                            border: { display: false }
                        },
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                font: { size: 10 }
                            },
                            border: { display: false }
                        }
                    }
                }
            });
        }

        criarGraficoMacro('chartKcal', '#34c759', @json(array_column($last7DaysMeals, 'total_calories_kcal')));
        criarGraficoMacro('chartProtein', '#af52de', @json(array_column($last7DaysMeals, 'total_protein_g')));
        criarGraficoMacro('chartFat', '#ffcc00', @json(array_column($last7DaysMeals, 'total_fat_g')));
        criarGraficoMacro('chartCarbs', '#007aff', @json(array_column($last7DaysMeals, 'total_carbohydrate_g')));

        // Sincroniza os "dots" de paginação com o slide visível no carrossel
        const carousel = document.querySelector('.chart-carousel');
        const dots = document.querySelectorAll('.carousel-dots .dot');
        carousel.addEventListener('scroll', function() {
            const indice = Math.round(carousel.scrollLeft / carousel.clientWidth);
            dots.forEach((dot, i) => dot.classList.toggle('active', i === indice));
        });
    });
</script>
@endif
</body>
</html>
