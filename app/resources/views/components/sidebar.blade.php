@php
    $navItems = [
        ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard.png', 'match' => 'dashboard'],
        ['route' => 'products', 'label' => 'Products', 'icon' => 'drill.png', 'match' => 'products'],
        ['route' => 'orders', 'label' => 'Orders', 'icon' => 'shopping-cart.png', 'match' => 'orders'],
        ['route' => 'customers', 'label' => 'Customers', 'icon' => 'users-round.png', 'match' => 'customers'],
        ['route' => 'warehouse', 'label' => 'Product Groups', 'icon' => 'boxes.png', 'match' => 'warehouse'],
        ['route' => 'purchase-orders', 'label' => 'Purchase Orders', 'icon' => 'package.png', 'match' => 'purchase-orders'],
        ['route' => 'suppliers', 'label' => 'Suppliers', 'icon' => 'truck.png', 'match' => 'suppliers'],
    ];
@endphp

<aside class="app-sidebar">
    <div>
        <a href="{{ route('dashboard') }}" class="app-brand">
            <span class="app-brand-mark">
                <img class="app-icon" src="{{ asset('icons/lucide/layout-grid.png') }}" alt="">
            </span>
            <span class="app-brand-name">Storage Management System</span>
        </a>

        <nav class="app-nav" aria-label="Primary navigation">
            @foreach($navItems as $item)
                <a href="{{ route($item['route']) }}" class="app-nav-link {{ request()->routeIs($item['match']) ? 'active' : '' }}">
                    <img class="app-icon" src="{{ asset('icons/lucide/' . $item['icon']) }}" alt="">
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    <div class="app-sidebar-bottom">
        {{-- Settings is intentionally hidden until a real settings page exists. Keep the
             bottom slot available so this action can return without restructuring the sidebar. --}}

        <div class="app-user-card">
            <div class="app-user-meta">
                <span class="app-user-avatar">
                    <img class="app-icon" src="{{ asset('icons/lucide/circle-user-round.png') }}" alt="">
                </span>
                <div class="app-user-copy">
                    <div class="app-user-name">{{ Auth::user()->name }}</div>
                    <div class="app-user-role">{{ ucfirst(Auth::user()->role) }}</div>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="app-logout-btn" aria-label="Logout">
                    <img class="app-icon" src="{{ asset('icons/lucide/log-out.png') }}" alt="">
                </button>
            </form>
        </div>
    </div>
</aside>
