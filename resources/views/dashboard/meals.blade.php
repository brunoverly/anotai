<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard - Refeições Detalhadas</title>
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

            /* Cores dos Ícones de Refeição */
            --green-icon: #2c854b;
            --green-bg: #e6f9ed;
            --purple-icon: #8b5cf6;
            --purple-bg: #f3e8fc;
            --orange-icon: #d97706;
            --orange-bg: #fef3e6;

            /* Cores de Status e Ação */
            --status-green: #34c759;
            --danger-color: #ef4444;
            --danger-bg: #fef2f2;
            --edit-color: #3b82f6;
            --edit-bg: #eff6ff;
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
            gap: 1.25rem;
        }

        .page-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: -0.25rem;
        }

        /* Meal Card Base */
        .meal-card {
            border: 1px solid var(--card-border);
            border-radius: 12px;
            background-color: #ffffff;
            box-shadow: 0 4px 6px -1px var(--card-shadow);
            display: flex;
            flex-direction: column;
        }

        /* Estrutura Compacta da Refeição */
        .meal-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            background-color: #ffffff;
        }

        .meal-icon-bg {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            flex-shrink: 0;
        }

        .bg-green { background-color: var(--green-bg); }
        .bg-purple { background-color: var(--purple-bg); }
        .bg-orange { background-color: var(--orange-bg); }

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

        .status-icon {
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .dot-green { color: var(--status-green); font-size: 1.2rem; }
        .star-purple { color: var(--purple-icon); font-size: 1rem; }

        /* Lista de Alimentos com Divisor Parcial */
        .food-list {
            position: relative;
            padding: 1rem;
            background-color: #ffffff; /* Volta ao fundo branco */
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        /* Linha sólida que não pega a div inteira */
        .food-list::before {
            content: "";
            position: absolute;
            top: 0;
            left: 1.5rem; /* Margem esquerda */
            right: 1.5rem; /* Margem direita */
            height: 1px;
            background-color: #f3f4f6; /* Cinza bem sutil */
        }

        .food-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .food-name {
            color: #4b5563;
            font-size: 0.8rem;
            font-weight: 400;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Ponto (bullet) cinza antes do alimento */
        .food-name::before {
            content: "";
            display: block;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background-color: #d1d5db;
        }

        .food-qty {
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 500;
            background-color: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
        }

        /* Card Footer / Ações */
        .card-actions {
            display: flex;
            padding: 0.75rem 1rem;
            gap: 12px;
            background-color: #ffffff;
        }

        .btn-action {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 0;
            border-radius: 8px;
            border: none;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
            text-decoration: none;
        }

        .btn-action:active {
            opacity: 0.7;
        }

        .btn-action svg {
            width: 16px;
            height: 16px;
        }

        .btn-edit {
            background-color: var(--edit-bg);
            color: var(--edit-color);
        }

        .btn-delete {
            background-color: var(--danger-bg);
            color: var(--danger-color);
        }

        /* Rodapé / Legenda */
        .footer-legend {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--card-border);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        /* Modal de confirmação */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background-color: rgba(17, 24, 39, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            z-index: 100;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s;
        }

        .modal-overlay.open {
            opacity: 1;
            pointer-events: auto;
        }

        .modal-box {
            background-color: #ffffff;
            border-radius: 16px;
            padding: 1.5rem;
            width: 100%;
            max-width: 320px;
            text-align: center;
            transform: scale(0.95);
            transition: transform 0.2s;
        }

        .modal-overlay.open .modal-box {
            transform: scale(1);
        }

        .modal-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background-color: var(--danger-bg);
            color: var(--danger-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }

        .modal-icon svg {
            width: 24px;
            height: 24px;
        }

        .modal-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .modal-text {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
        }

        .btn-cancel {
            background-color: var(--card-border);
            color: var(--text-main);
        }

        /* Animação de saída do card ao excluir */
        .meal-card.removing {
            opacity: 0;
            transform: scale(0.96);
            transition: opacity 0.25s, transform 0.25s;
        }

    </style>
</head>
<body>

<div class="mobile-container">
    <header>
        @include('dashboard.partials.menu', ['current' => 'meals'])
        <h1>
            <svg class="title-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path><path d="M7 2v20"></path><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path></svg>
            Últimas refeições
        </h1>
    </header>

    <div class="scroll-area" id="meals-container">
    @foreach($dayMeals as $meal)
        @php
            $hora = $meal->consumed_at->hour;
            if ($hora < 12) {
                $periodo = 'Manhã'; $corClasse = 'bg-green';
                $corIcone = 'var(--green-icon)';
                $iconePath = '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M19.07 4.93l-1.41 1.41M6.34 17.66l-1.41 1.41"/>';
            } elseif ($hora < 18) {
                $periodo = 'Tarde'; $corClasse = 'bg-orange';
                $corIcone = 'var(--orange-icon)';
                $iconePath = '<path d="M17.5 19H9a5 5 0 1 1 .5-9.9A5.5 5.5 0 0 1 20 12.5a4.5 4.5 0 0 1-2.5 4.5"/>';
            } else {
                $periodo = 'Noite'; $corClasse = 'bg-purple';
                $corIcone = 'var(--purple-icon)';
                $iconePath = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';
            }
        @endphp
        <div class="meal-card" id="meal-{{ $meal->id }}">
            <div class="meal-item">
                <div class="meal-icon-bg {{ $corClasse }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="{{ $corIcone }}" stroke-width="2">{!! $iconePath !!}</svg>
                </div>
                <div class="meal-info">
                    <div class="meal-name">{{ $periodo }}</div>
                    <div class="meal-time">{{ $meal->consumed_at->format('d/m H:i') }}</div>
                </div>
                <div class="meal-stats">
                    <div class="meal-kcal">{{ round($meal->total_calories_kcal) }} <span>kcal</span></div>
                    <div class="meal-macros">P {{ round($meal->total_protein_g) }}g &nbsp; G {{ round($meal->total_fat_g) }}g &nbsp; C {{ round($meal->total_carbohydrate_g) }}g</div>
                </div>
            </div>

            <div class="food-list">
                @foreach($meal->items as $item)
                    <div class="food-row">
                        <span class="food-name">{{ $item['alimento'] }}</span>
                        <span class="food-qty">{{ $item['quantidade'] }} {{ $item['unidade'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="card-actions">
                <a class="btn-action btn-edit" href="{{ route('dashboard.showMeal', ['chatId' => $chatId, 'mealId' => $meal->id]) }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> Editar
                </a>
                <button class="btn-action btn-delete" onclick="askDeleteMeal({{ $meal->id }})">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg> Excluir
                </button>
            </div>
        </div>
    @endforeach
</div>


</div>

<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
        </div>
        <div class="modal-title">Excluir refeição?</div>
        <div class="modal-text">Essa ação não pode ser desfeita.</div>
        <div class="modal-actions">
            <button class="btn-action btn-cancel" onclick="closeDeleteModal()">Cancelar</button>
            <button class="btn-action btn-delete" onclick="confirmDeleteMeal()">Excluir</button>
        </div>
    </div>
</div>

<script>
    let mealIdToDelete = null;

    function askDeleteMeal(id) {
        mealIdToDelete = id;
        document.getElementById('deleteModal').classList.add('open');
    }

    function closeDeleteModal() {
        mealIdToDelete = null;
        document.getElementById('deleteModal').classList.remove('open');
    }

    function confirmDeleteMeal() {
        const id = mealIdToDelete;
        closeDeleteModal();
        if (id) deleteMeal(id);
    }

    function deleteMeal(id) {
        // UI otimista: remove visualmente na hora, sem esperar o servidor responder.
        const card = document.getElementById(`meal-${id}`);
        card.classList.add('removing');
        card.addEventListener('transitionend', () => card.remove(), { once: true });

        fetch(`/dashboard/{{ $chatId ?? '' }}/meals/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert('Não foi possível excluir a refeição. Atualize a página e tente novamente.');
            }
        })
        .catch(() => {
            alert('Não foi possível excluir a refeição. Atualize a página e tente novamente.');
        });
    }
</script>


</body>
</html>

