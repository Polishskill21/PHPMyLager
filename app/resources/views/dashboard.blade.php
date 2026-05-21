@extends('layouts.app')

@section('title', 'Dashboard – PhpMyLager')

@push('styles')
<style>
    .container { max-width: 960px; margin: 0 auto; padding: 2.5rem 1.5rem; }

    .alert-success {
        background: #052e16; border: 1px solid #16a34a;
        color: #4ade80; padding: 0.75rem 1rem;
        border-radius: 8px; font-size: 0.875rem; margin-bottom: 2rem;
    }

    h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; }
    .subtitle { color: #64748b; font-size: 0.9rem; margin-bottom: 2rem; }

    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }

    .card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 1.5rem; }
    .card-icon { font-size: 1.8rem; margin-bottom: 0.75rem; }
    .card-title { font-size: 0.8rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem; }
    .card-value { font-size: 1.4rem; font-weight: 700; color: var(--text); }

    .clickable-card { text-decoration: none; display: block; transition: transform 0.2s, border-color 0.2s; }
    .clickable-card:hover { transform: translateY(-3px); border-color: var(--accent-h); }
</style>
@endpush

@section('content')
<div class="container">

    @if(session('success'))
        <div class="alert-success">✓ {{ session('success') }}</div>
    @endif

    @if(Auth::user()->isViewer())
        <p class="viewer-note" style="color: #64748b; margin-bottom: 1rem;">You have read-only access.</p>
    @endif

    <h2>Dashboard</h2>
    <p class="subtitle">Welcome to the Warehouse Management System.</p>

    <div class="grid">
        <a href="{{ route('products') }}" class="card clickable-card">
            <div class="card-icon">📦</div>
            <div class="card-title">Products</div>
            <div class="card-value">Go to Catalog ➔</div>
        </a>
        <a href="{{ route('orders') }}" class="card clickable-card">
            <div class="card-icon">🗂️</div>
            <div class="card-title">Orders</div>
            <div class="card-value">Go to Orders ➔</div>
        </a>
        <a href="{{ route('customers') }}" class="card clickable-card">
            <div class="card-icon">🧙</div>
            <div class="card-title">Customers</div>
            <div class="card-value">API Ready</div>
        </a>
        <div class="card">
            <div class="card-icon">👤</div>
            <div class="card-title">Logged in as</div>
            <div class="card-value">{{ Auth::user()->name }}</div>
        </div>
        <div class="card">
            <div class="card-icon">🟢</div>
            <div class="card-title">System</div>
            <div class="card-value">Operational</div>
        </div>
    </div>

</div>
@endsection
