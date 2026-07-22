<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - Histórico da Semana</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-color: #ffffff;
            --text-main: #111827;
            --text-muted: #6b7280;
            --card-border: #f3f4f6;
            --card-shadow: rgba(0, 0, 0, 0.03);

            /* Cores dos Macros e Backgrounds */
            --kcal-color: #22c55e;
            --kcal-bg: #dcfce7;

            --prot-color: #7c3aed;
            --prot-bg: #ede9fe;

            --fat-color: #f59e0b;
            --fat-bg: #fef3c7;

            --carb-color: #3b82f6;
            --carb-bg: #dbeafe;

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
            box-shadow: 0 4px 6px -1px var(--card-shadow);
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
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        <h1>Dashboard</h1>
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
    </header>

    <!-- Área de Scroll onde os cards serão renderizados dinamicamente -->
    <div class="scroll-area" id="cards-container">
        <!-- O conteúdo será injetado aqui via JavaScript -->
    </div>
</div>

<script>
    // SVGs dos Ícones
    const icons = {
        kcal: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.66 11.2c-.23-.3-.51-.56-.77-.82-.67-.6-1.43-1.03-2.07-1.66C13.33 7.26 13 4.85 13.95 3c-.95.23-1.78.75-2.49 1.32-2.59 2.08-3.61 5.75-2.39 8.9.04.1.08.2.08.33 0 .22-.15.42-.35.5-.22.1-.46.04-.64-.12a.83.83 0 0 1-.22-.38c-.27-.87-.19-1.77.05-2.6.43-1.48 1.25-2.73 2.22-3.79-.37-.16-.76-.23-1.15-.23-2.72 0-5.18 2.14-5.55 4.96-.13.97.1 1.97.35 2.92.56 2.05 1.74 3.84 3.39 4.98 1.48 1.03 3.3 1.54 5.12 1.33 2.76-.32 5.06-2.32 5.76-4.96.22-.84.22-1.74-.04-2.58zM14 19.5c-1.35 0-2.54-.76-3.15-1.92-.06-.11-.03-.26.06-.34.1-.09.25-.09.36-.02.66.42 1.47.63 2.3.56.95-.08 1.83-.56 2.45-1.25.09-.1.24-.12.35-.05.11.07.13.22.06.33a4.01 4.01 0 0 1-2.43 2.69z"/></svg>`,
        prot: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.97 3.35c-1.43.32-2.8 1.1-3.8 2.19l-4.5 4.88c-.96 1.05-1.39 2.53-1.17 3.97.22 1.44 1.12 2.68 2.44 3.35l.93.47c1.32.67 2.87.65 4.18-.04l2.1-1.11c1.31-.69 2.19-1.97 2.37-3.45.18-1.48-.36-2.94-1.45-3.92l-1.1-1zM9.5 15.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>`,
        fat: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>`,
        carb: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M2 21.36L20.5 2.86l.66.66c1.13 1.13 1.13 2.97 0 4.1l-1.32 1.32c-.8.8-2.02 1.02-3.05.57l-1.89-1.89-1.41 1.41 1.89 1.89c.45 1.03.23 2.25-.57 3.05l-1.32 1.32c-1.13 1.13-2.97 1.13-4.1 0l-.66-.66L2 21.36zM15.5 12c.83 0 1.5-.67 1.5-1.5s-.67-1.5-1.5-1.5-1.5.67-1.5 1.5.67 1.5 1.5 1.5z"/></svg>`
    };

    // Dados Simulados para os 7 dias
    const weekData = [
        { day: "Hoje", data: { kcal: [850, 2000], prot: [68, 120], fat: [26, 65], carb: [102, 250] } },
        { day: "Ontem", data: { kcal: [1950, 2000], prot: [115, 120], fat: [62, 65], carb: [240, 250] } },
        { day: "Sábado", data: { kcal: [2100, 2000], prot: [125, 120], fat: [70, 65], carb: [260, 250] } },
        { day: "Sexta-feira", data: { kcal: [1800, 2000], prot: [110, 120], fat: [55, 65], carb: [220, 250] } },
        { day: "Quinta-feira", data: { kcal: [1980, 2000], prot: [118, 120], fat: [60, 65], carb: [245, 250] } },
        { day: "Quarta-feira", data: { kcal: [2050, 2000], prot: [122, 120], fat: [66, 65], carb: [255, 250] } },
        { day: "Terça-feira", data: { kcal: [1900, 2000], prot: [112, 120], fat: [58, 65], carb: [230, 250] } }
    ];

    function renderCards() {
        const container = document.getElementById('cards-container');
        let html = '';

        weekData.forEach(item => {
            // Cálculo das porcentagens garantindo que não ultrapasse 100% visualmente na barra
            const pctKcal = Math.min(Math.round((item.data.kcal[0] / item.data.kcal[1]) * 100), 100);
            const pctProt = Math.min(Math.round((item.data.prot[0] / item.data.prot[1]) * 100), 100);
            const pctFat = Math.min(Math.round((item.data.fat[0] / item.data.fat[1]) * 100), 100);
            const pctCarb = Math.min(Math.round((item.data.carb[0] / item.data.carb[1]) * 100), 100);

            // Porcentagem real para o texto (pode passar de 100%)
            const txtKcal = Math.round((item.data.kcal[0] / item.data.kcal[1]) * 100);
            const txtProt = Math.round((item.data.prot[0] / item.data.prot[1]) * 100);
            const txtFat = Math.round((item.data.fat[0] / item.data.fat[1]) * 100);
            const txtCarb = Math.round((item.data.carb[0] / item.data.carb[1]) * 100);

            html += `
                <div class="day-section">
                    <h2 class="day-title">${item.day}</h2>
                    <div class="macro-card">

                        <!-- Kcal -->
                        <div class="macro-row">
                            <div class="icon-box icon-kcal">${icons.kcal}</div>
                            <div class="content-area">
                                <div class="row-header">
                                    <span class="macro-title">Kcal</span>
                                    <div class="row-stats">
                                        <span class="macro-values"><strong>${item.data.kcal[0]}</strong> / ${item.data.kcal[1]} kcal</span>
                                        <span class="macro-pct">${txtKcal}%</span>
                                    </div>
                                </div>
                                <div class="progress-track">
                                    <div class="progress-fill fill-kcal" style="width: ${pctKcal}%;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Proteína -->
                        <div class="macro-row">
                            <div class="icon-box icon-prot">${icons.prot}</div>
                            <div class="content-area">
                                <div class="row-header">
                                    <span class="macro-title">Proteína</span>
                                    <div class="row-stats">
                                        <span class="macro-values"><strong>${item.data.prot[0]}</strong> / ${item.data.prot[1]}g</span>
                                        <span class="macro-pct">${txtProt}%</span>
                                    </div>
                                </div>
                                <div class="progress-track">
                                    <div class="progress-fill fill-prot" style="width: ${pctProt}%;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Gordura -->
                        <div class="macro-row">
                            <div class="icon-box icon-fat">${icons.fat}</div>
                            <div class="content-area">
                                <div class="row-header">
                                    <span class="macro-title">Gordura</span>
                                    <div class="row-stats">
                                        <span class="macro-values"><strong>${item.data.fat[0]}</strong> / ${item.data.fat[1]}g</span>
                                        <span class="macro-pct">${txtFat}%</span>
                                    </div>
                                </div>
                                <div class="progress-track">
                                    <div class="progress-fill fill-fat" style="width: ${pctFat}%;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Carboidrato -->
                        <div class="macro-row">
                            <div class="icon-box icon-carb">${icons.carb}</div>
                            <div class="content-area">
                                <div class="row-header">
                                    <span class="macro-title">Carboidrato</span>
                                    <div class="row-stats">
                                        <span class="macro-values"><strong>${item.data.carb[0]}</strong> / ${item.data.carb[1]}g</span>
                                        <span class="macro-pct">${txtCarb}%</span>
                                    </div>
                                </div>
                                <div class="progress-track">
                                    <div class="progress-fill fill-carb" style="width: ${pctCarb}%;"></div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    // Inicializa a renderização
    document.addEventListener("DOMContentLoaded", renderCards);
</script>

</body>
</html>
