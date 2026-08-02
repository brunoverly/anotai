<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - Histórico da Semana</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-color: #ffffff;
            --text-main: #111827;
            --text-muted: #6b7280;
            --card-border: #e5e7eb;
            --card-shadow: rgba(0, 0, 0, 0.08);

            /* Cores dos Macros e Backgrounds */
            --kcal-color: #22c55e;
            --kcal-bg: #dcfce7;

            --prot-color: #7c3aed;
            --prot-bg: #ede9fe;

            --fat-color: #f59e0b;
            --fat-bg: #fef3c7;

            --carb-color: #3b82f6;
            --carb-bg: #dbeafe;

            --danger-color: #ef4444;
            --danger-bg: #fee2e2;

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
        }

        .mobile-container {
            max-width: 480px;
            margin: 0 auto;
            height: 100vh;
            display: flex;
            flex-direction: column;
            animation: dashboardFadeIn 0.35s ease-out;
        }

        @keyframes dashboardFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Fixed Header */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            background-color: var(--bg-color);
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 1px solid var(--card-border);
        }

        header h1 {
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .title-icon {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            color: var(--text-muted, #6b7280);
        }

        .icon {
            width: 24px;
            height: 24px;
            cursor: pointer;
            color: var(--text-main);
        }

        /* Scrollable Content */
        .scroll-area {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            padding-bottom: 3rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        /* Day Section */
        .day-section {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .day-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-main);
        }

        /* Card Container */
        .macro-card {
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 0.5rem 1rem;
            background-color: #ffffff;
            box-shadow: 0 4px 10px -2px var(--card-shadow);
        }

        /* Macro Row */
        .macro-row {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--card-border);
            gap: 12px;
        }

        .macro-row:last-child {
            border-bottom: none;
        }

        /* Icon Container */
        .icon-box {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .icon-kcal { background-color: var(--kcal-bg); color: var(--kcal-color); }
        .icon-prot { background-color: var(--prot-bg); color: var(--prot-color); }
        .icon-fat { background-color: var(--fat-bg); color: var(--fat-color); }
        .icon-carb { background-color: var(--carb-bg); color: var(--carb-color); }

        /* Macro com meta estourada */
        .macro-row.over-goal .icon-box {
            background-color: var(--danger-bg);
            color: var(--danger-color);
        }

        .macro-row.over-goal .progress-fill {
            background-color: var(--danger-color);
        }

        .macro-row.over-goal .macro-pct {
            color: var(--danger-color);
        }

        .icon-box svg {
            width: 20px;
            height: 20px;
        }

        /* Content Area */
        .content-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .row-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            width: 100%;
        }

        .macro-title {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-main);
        }

        .row-stats {
            display: flex;
            align-items: baseline;
            gap: 12px;
        }

        .macro-values {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .macro-values strong {
            color: var(--text-main);
            font-size: 0.9rem;
            font-weight: 700;
        }

        .macro-pct {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 500;
            width: 32px;
            text-align: right;
        }

        /* Progress Bar */
        .progress-track {
            width: 100%;
            height: 4px;
            background-color: var(--track-bg);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 4px;
        }

        .fill-kcal { background-color: var(--kcal-color); }
        .fill-prot { background-color: var(--prot-color); }
        .fill-fat { background-color: var(--fat-color); }
        .fill-carb { background-color: var(--carb-color); }

    </style>
</head>
<body>

<div class="mobile-container">
    <!-- Header Fixo -->
    <header>
        @include('dashboard.partials.menu', ['current' => 'week'])
        <h1>
            <svg class="title-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
            Últimos 7 dias
        </h1>
    </header>

    <!-- Área de Scroll com os cards renderizados no servidor -->
    <div class="scroll-area" id="cards-container">
        @foreach ($last7DaysMeals as $day => $data)
        <div class="day-section">
            <h2 class="day-title">{{ $data['label'] }}</h2>
        <a href="{{ route('dashboard.day', ['chatId' => $chatId, 'day' => $data['data']]) }}" class="day-section-link" style="text-decoration: none; color: inherit;">
            <div class="macro-card">

                <!-- Kcal -->
                <div class="macro-row @if((int) $data['%_calories'] > 100) over-goal @endif">
                    <div class="icon-box icon-kcal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z"/><path d="M12 18a3.75 3.75 0 0 0 .495-7.468 5.99 5.99 0 0 0-1.925 3.547 5.975 5.975 0 0 1-2.133-1.001A3.75 3.75 0 0 0 12 18Z"/></svg>
                    </div>
                    <div class="content-area">
                        <div class="row-header">
                            <span class="macro-title">Kcal</span>
                            <div class="row-stats">
                                <span class="macro-values">
                                    <strong>{{ $data['total_calories_kcal'] }}</strong>
                                    @if($data['user_calories_goal_kcal'] > 0)
                                        / {{ $data['user_calories_goal_kcal'] }} kcal
                                    @endif
                                </span>
                                @if($data['%_calories'])
                                    <span class="macro-pct">{{ $data['%_calories'] }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill fill-kcal" style="width: {{ min(100, (int) $data['%_calories']) }}%;"></div>
                        </div>
                    </div>
                </div>

                <!-- Proteína -->
                <div class="macro-row @if((int) $data['%_protein'] > 100) over-goal @endif">
                    <div class="icon-box icon-prot">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21c-4.5 0-7-4-7-8.5C5 7.5 8 3 12 3s7 4.5 7 9.5c0 4.5-2.5 8.5-7 8.5Z"/></svg>
                    </div>
                    <div class="content-area">
                        <div class="row-header">
                            <span class="macro-title">Proteína</span>
                            <div class="row-stats">
                                <span class="macro-values">
                                    <strong>{{ $data['total_protein_g'] }}</strong>
                                    @if($data['user_protein_goal_g'] > 0)
                                        / {{ $data['user_protein_goal_g'] }}g
                                    @endif
                                </span>
                                @if($data['%_protein'])
                                    <span class="macro-pct">{{ $data['%_protein'] }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill fill-prot" style="width: {{ min(100, (int) $data['%_protein']) }}%;"></div>
                        </div>
                    </div>
                </div>

                <!-- Gordura -->
                <div class="macro-row @if((int) $data['%_fat'] > 100) over-goal @endif">
                    <div class="icon-box icon-fat">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                    </div>
                    <div class="content-area">
                        <div class="row-header">
                            <span class="macro-title">Gordura</span>
                            <div class="row-stats">
                                <span class="macro-values">
                                    <strong>{{ $data['total_fat_g'] }}</strong>
                                    @if($data['user_fat_goal_g'] > 0)
                                        / {{ $data['user_fat_goal_g'] }}g
                                    @endif
                                </span>
                                @if($data['%_fat'])
                                    <span class="macro-pct">{{ $data['%_fat'] }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill fill-fat" style="width: {{ min(100, (int) $data['%_fat']) }}%;"></div>
                        </div>
                    </div>
                </div>

                <!-- Carboidrato -->
                <div class="macro-row @if((int) $data['%_carbohydrate'] > 100) over-goal @endif">
                    <div class="icon-box icon-carb">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V10a8 8 0 0 1 16 0v11"/><path d="M4 21h16"/><path d="M9 21v-5M15 21v-5"/></svg>
                    </div>
                    <div class="content-area">
                        <div class="row-header">
                            <span class="macro-title">Carboidrato</span>
                            <div class="row-stats">
                                <span class="macro-values">
                                    <strong>{{ $data['total_carbohydrate_g'] }}</strong>
                                    @if($data['user_carbohydrate_goal_g'] > 0)
                                        / {{ $data['user_carbohydrate_goal_g'] }}g
                                    @endif
                                </span>
                                @if($data['%_carbohydrate'])
                                    <span class="macro-pct">{{ $data['%_carbohydrate'] }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill fill-carb" style="width: {{ min(100, (int) $data['%_carbohydrate']) }}%;"></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        </a>
        @endforeach
    </div>
</div>

</body>
</html>
