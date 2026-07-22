<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - {{ isset($meal) ? 'Editar Refeição' : 'Editar Metas' }}</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-color: #ffffff;
            --text-main: #111827;
            --text-muted: #6b7280;
            --card-border: #f3f4f6;
            --card-shadow: rgba(0, 0, 0, 0.03);

            /* Cores dos Ícones e Backgrounds */
            --kcal-color: #2c854b;
            --kcal-bg: #e6f9ed;

            --prot-color: #8b5cf6;
            --prot-bg: #f3e8fc;

            --fat-color: #d97706;
            --fat-bg: #fef3e6;

            --carb-color: #3b82f6;
            --carb-bg: #dbeafe;

            /* Cores de Ação (Estilo Editar) */
            --edit-color: #3b82f6;
            --edit-bg: #eff6ff;

            /* Cores de Sucesso (Salvo) */
            --success-color: #2c854b;
            --success-bg: #e6f9ed;
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

        .page-header {
            margin-bottom: 0.5rem;
        }

        .page-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .page-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        /* Form Card Base */
        .edit-card {
            border: 1px solid var(--card-border);
            border-radius: 12px;
            background-color: #ffffff;
            box-shadow: 0 4px 6px -1px var(--card-shadow);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .edit-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1rem;
            border-bottom: 1px solid var(--card-border);
        }

        .edit-row:last-child {
            border-bottom: none;
        }

        /* Lado esquerdo: Ícone + Info */
        .macro-label-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .icon-box {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .icon-box svg { width: 20px; height: 20px; }

        .bg-kcal { background-color: var(--kcal-bg); color: var(--kcal-color); }
        .bg-prot { background-color: var(--prot-bg); color: var(--prot-color); }
        .bg-fat { background-color: var(--fat-bg); color: var(--fat-color); }
        .bg-carb { background-color: var(--carb-bg); color: var(--carb-color); }

        .macro-info {
            display: flex;
            flex-direction: column;
        }

        .macro-name {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--text-main);
        }

        .macro-desc {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Lado direito: Input Wrapper */
        .input-wrapper {
            display: flex;
            align-items: baseline;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px 12px;
            transition: all 0.2s ease;
        }

        .input-wrapper:focus-within {
            border-color: var(--edit-color);
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .input-wrapper input {
            width: 70px;
            border: none;
            background: transparent;
            text-align: right;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-main);
            outline: none;
            font-family: 'Inter', sans-serif;
            padding: 0;
        }

        .input-kcal input {
            width: 80px;
        }

        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type=number] {
            -moz-appearance: textfield;
        }

        .unit {
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 500;
            margin-left: 4px;
        }

        /* Botão de Salvar (Estilo Editar: Fundo azul claro, texto azul) */
        .btn-save {
            width: 100%;
            background-color: var(--edit-bg);
            color: var(--edit-color);
            padding: 16px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.1s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 0.5rem;
        }

        .btn-save:active {
            opacity: 0.7;
            transform: scale(0.98);
        }

        .btn-save svg {
            width: 20px;
            height: 20px;
        }

    </style>
</head>
<body>

<div class="mobile-container">
    <header>
        @if(isset($meal))
            <a href="{{ '/dashboard/' . $chatId . '/meals' }}">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </a>
        @else
            @include('dashboard.partials.menu', ['current' => 'macros'])
        @endif
        <h1>{{ isset($meal) ? 'Editar Refeição' : 'Metas Pessoais' }}</h1>
        <div style="width: 24px;"></div>
    </header>

    <div class="scroll-area">

        @if(session('success'))
            <div style="background-color: var(--success-bg); color: var(--success-color); padding: 1rem; border-radius: 12px; font-size: 0.9rem; font-weight: 600;">
                {{ session('success') }}
            </div>
        @endif

        <div class="page-header">
            @if(isset($meal))
                <h2 class="page-title">Ajustar Refeição</h2>
                <p class="page-desc">Corrija os macros desta refeição. Os totais e resumos do painel serão recalculados com base nestes valores.</p>
            @else
                <h2 class="page-title">Ajustar Macros</h2>
                <p class="page-desc">Defina seus objetivos diários. Os gráficos de progresso e resumos do painel serão atualizados com base nestes valores.</p>
            @endif
        </div>

        <form method="POST" action="{{ isset($meal) ? '/dashboard/' . $chatId . '/meal/' . $meal->id : '/dashboard/' . $chatId . '/macros' }}">
            @csrf

            <div class="edit-card">

                <div class="edit-row">
                    <div class="macro-label-group">
                        <div class="icon-box bg-kcal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z"/><path d="M12 18a3.75 3.75 0 0 0 .495-7.468 5.99 5.99 0 0 0-1.925 3.547 5.975 5.975 0 0 1-2.133-1.001A3.75 3.75 0 0 0 12 18Z"/></svg>
                        </div>
                        <div class="macro-info">
                            <span class="macro-name">Calorias</span>
                            <span class="macro-desc">Energia diária</span>
                        </div>
                    </div>
                    <div class="input-wrapper input-kcal">
                        @if(isset($meal))
                            <input type="number" name="total_calories_kcal" value="{{ $meal->total_calories_kcal }}" />
                        @else
                            <input type="number" name="calories_kcal" value="{{ $user->calories_kcal }}" />
                        @endif
                        <span class="unit">kcal</span>
                    </div>
                </div>

                <div class="edit-row">
                    <div class="macro-label-group">
                        <div class="icon-box bg-prot">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21c-4.5 0-7-4-7-8.5C5 7.5 8 3 12 3s7 4.5 7 9.5c0 4.5-2.5 8.5-7 8.5Z"/></svg>
                        </div>
                        <div class="macro-info">
                            <span class="macro-name">Proteína</span>
                            <span class="macro-desc">Construção muscular</span>
                        </div>
                    </div>
                    <div class="input-wrapper">
                        @if(isset($meal))
                            <input type="number" name="total_protein_g" value="{{ $meal->total_protein_g }}" />
                        @else
                            <input type="number" name="protein_g" value="{{ $user->protein_g }}" />
                        @endif
                        <span class="unit">g</span>
                    </div>
                </div>

                <div class="edit-row">
                    <div class="macro-label-group">
                        <div class="icon-box bg-fat">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                        </div>
                        <div class="macro-info">
                            <span class="macro-name">Gordura</span>
                            <span class="macro-desc">Regulação hormonal</span>
                        </div>
                    </div>
                    <div class="input-wrapper">
                        @if(isset($meal))
                            <input type="number" name="total_fat_g" value="{{ $meal->total_fat_g }}" />
                        @else
                            <input type="number" name="fat_g" value="{{ $user->fat_g }}" />
                        @endif
                        <span class="unit">g</span>
                    </div>
                </div>

                <div class="edit-row">
                    <div class="macro-label-group">
                        <div class="icon-box bg-carb">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V10a8 8 0 0 1 16 0v11"/><path d="M4 21h16"/><path d="M9 21v-5M15 21v-5"/></svg>
                        </div>
                        <div class="macro-info">
                            <span class="macro-name">Carboidrato</span>
                            <span class="macro-desc">Reserva de energia</span>
                        </div>
                    </div>
                    <div class="input-wrapper">
                        @if(isset($meal))
                            <input type="number" name="total_carbohydrate_g" value="{{ $meal->total_carbohydrate_g }}" />
                        @else
                            <input type="number" name="carbohydrate_g" value="{{ $user->carbohydrate_g }}" />
                        @endif
                        <span class="unit">g</span>
                    </div>
                </div>

            </div>

            <button class="btn-save" type="submit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                Salvar Alterações
            </button>
        </form>

    </div>
</div>

</body>
</html>
