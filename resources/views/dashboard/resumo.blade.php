<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard Nutricional</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-color: #ffffff;
            --text-main: #1a1a1a;
            --text-muted: #6b7280;
            --card-border: #f3f4f6;

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
        }

        .icon {
            width: 24px;
            height: 24px;
            cursor: pointer;
        }

        /* Títulos de Seção */
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            margin-top: 1.5rem;
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
            box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.02);
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
            padding: 1rem;
            box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.02);
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

    </style>
</head>
<body>

<div class="mobile-container">
    <!-- Cabeçalho -->
    <header>
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        <h1>Olá, {{ $userName }}</h1>
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
    </header>

    <!-- Resumo de Hoje -->
    <h2 class="section-title">Resumo de hoje</h2>


    <div class="macros-grid">
        <!-- Calorias -->
        <div class="macro-card card-kcal @if((int) $resumo['%_calories'] > 100) over-goal @endif">
            <div class="macro-values">
                <span class="macro-current">{{ $resumo['total_calories_kcal'] }}</span>
                @if($resumo['user_calories_goal_kcal'] > 0)
                    <span class="macro-target">/ {{ $resumo['user_calories_goal_kcal'] }}</span>
                @endif
            </div>
            <span class="macro-name">kcal</span>
            <div class="progress-container">
                <div class="progress-track"><div class="progress-fill" style="width: {{ $resumo['%_calories'] ?: '100%' }}"></div></div>
                @if($resumo['%_calories'])
                    <span class="progress-pct">{{ $resumo['%_calories'] }}</span>
                @endif
            </div>
        </div>

        <!-- Proteína -->
        <div class="macro-card card-protein @if((int) $resumo['%_protein'] > 100) over-goal @endif">
            <div class="macro-values">
                <span class="macro-current">{{ $resumo['total_protein_g'] }}</span>
                @if ($resumo['user_protein_goal_g'] > 0)
                    <span class="macro-target">/ {{ $resumo['user_protein_goal_g'] }}g</span>
                @endif
            </div>
            <span class="macro-name">Proteína</span>
            <div class="progress-container">
                <div class="progress-track"><div class="progress-fill" style="width: {{ $resumo['%_protein'] ?: '100%' }}"></div></div>
                @if($resumo['%_protein'])
                    <span class="progress-pct">{{ $resumo['%_protein'] }}</span>
                @endif
            </div>
        </div>

        <!-- Gordura -->
        <div class="macro-card card-fat @if((int) $resumo['%_fat'] > 100) over-goal @endif">
            <div class="macro-values">
                <span class="macro-current">{{ $resumo['total_fat_g'] }}</span>
                @if ($resumo['user_fat_goal_g'] > 0)
                    <span class="macro-target">/ {{ $resumo['user_fat_goal_g'] }}g</span>
                @endif
            </div>
            <span class="macro-name">Gordura</span>
            <div class="progress-container">
                <div class="progress-track"><div class="progress-fill" style="width: {{ $resumo['%_fat'] ?: '100%' }}"></div></div>
                @if($resumo['%_fat'])
                    <span class="progress-pct">{{ $resumo['%_fat'] }}</span>
                @endif
            </div>
        </div>

        <!-- Carboidrato -->
        <div class="macro-card card-carbs @if((int) $resumo['%_carbohydrate'] > 100) over-goal @endif">
            <div class="macro-values">
                <span class="macro-current">{{ $resumo['total_carbohydrate_g'] }}</span>
                @if ($resumo['user_carbohydrate_goal_g'] > 0)
                    <span class="macro-target">/ {{ $resumo['user_carbohydrate_goal_g'] }}g</span>
                @endif
            </div>
            <span class="macro-name">Carboidrato</span>
            <div class="progress-container">
                <div class="progress-track"><div class="progress-fill" style="width: {{ $resumo['%_carbohydrate'] ?: '100%' }}"></div></div>
                @if($resumo['%_carbohydrate'])
                    <span class="progress-pct">{{ $resumo['%_carbohydrate'] }}</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Evolução da Semana -->
    <h2 class="section-title">Evolução da semana</h2>
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

    <!-- Últimas Refeições -->
    <h2 class="section-title">Últimas refeições</h2>
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
                    <div class="meal-macros">P {{ round($meal->total_protein_g) }}g &nbsp; G {{ round($meal->total_fat_g) }}g &nbsp; C {{ round($meal->total_carbohydrate_g) }}g</div>
                </div>
            </div>
        @empty
            <p style="color: var(--text-muted); font-size: 0.85rem; text-align: center; padding: 1rem 0;">Nenhuma refeição registrada hoje.</p>
        @endforelse
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Configuração de estilo global do Chart.js
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = '#6b7280';

        const labels = @json(array_keys($last7DaysMeals));

        function criarGraficoMacro(canvasId, cor, dados) {
            const ctx = document.getElementById(canvasId).getContext('2d');
            return new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        data: dados,
                        borderColor: cor,
                        backgroundColor: cor,
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

</body>
</html>
