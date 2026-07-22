@php
    $menuItems = [
        'resumo' => ['label' => 'Resumo do dia', 'url' => '/dashboard/' . $chatId, 'icon' => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>'],
        'week'   => ['label' => 'Últimos 7 dias', 'url' => '/dashboard/' . $chatId . '/week', 'icon' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line>'],
        'meals'  => ['label' => 'Refeições', 'url' => '/dashboard/' . $chatId . '/meals', 'icon' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>'],
        'macros' => ['label' => 'Metas', 'url' => '/dashboard/' . $chatId . '/macros', 'icon' => '<circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path>'],
    ];
@endphp

<div class="dashboard-menu-wrapper">
    <svg class="icon dashboard-menu-toggle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>

    <div class="dashboard-menu-dropdown" id="dashboardMenuDropdown">
        @foreach($menuItems as $key => $item)
            @unless($key === $current)
                <a href="{{ $item['url'] }}" class="dashboard-menu-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $item['icon'] !!}</svg>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endunless
        @endforeach
    </div>
</div>

<style>
    .dashboard-menu-wrapper {
        position: relative;
    }

    .dashboard-menu-toggle {
        cursor: pointer;
    }

    .dashboard-menu-dropdown {
        display: none;
        position: absolute;
        top: calc(100% + 12px);
        left: 0;
        min-width: 200px;
        background-color: #ffffff;
        border: 1px solid #f3f4f6;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        padding: 8px;
        flex-direction: column;
        gap: 2px;
        z-index: 20;
    }

    .dashboard-menu-dropdown.open {
        display: flex;
    }

    .dashboard-menu-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 8px;
        color: #111827;
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 500;
        transition: background-color 0.15s;
    }

    .dashboard-menu-item:active {
        background-color: #f3f4f6;
    }

    .dashboard-menu-item svg {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
        color: #6b7280;
    }
</style>

<script>
    (function () {
        const wrapper = document.querySelector('.dashboard-menu-wrapper');
        const toggle = wrapper.querySelector('.dashboard-menu-toggle');
        const dropdown = document.getElementById('dashboardMenuDropdown');

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            dropdown.classList.toggle('open');
        });

        document.addEventListener('click', function (event) {
            if (!wrapper.contains(event.target)) {
                dropdown.classList.remove('open');
            }
        });
    })();
</script>
