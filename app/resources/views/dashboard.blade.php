@extends('layouts.app')

@section('title', 'Dashboard – PhpMyLager')
@section('page-title', 'Dashboard')

@push('styles')
    @vite('resources/css/pages/dashboard.css')
@endpush

@section('content')
<section class="dashboard-page">
    @if(session('success'))
        <div class="alert alert-success">✓ {{ session('success') }}</div>
    @endif

    @if(Auth::user()->isViewer())
        <p class="dashboard-viewer-note">You have read-only access.</p>
    @endif

    <header class="page-header">
        <h1 class="page-title">
            <img class="page-title-icon" src="{{ asset('icons/lucide/layout-dashboard.png') }}" alt="">
            <span>Dashboard</span>
        </h1>
        <p class="page-subtitle">Welcome to the Warehouse Management System.</p>
    </header>

    <div class="dashboard-grid">
        <a href="{{ route('products') }}" class="dashboard-card dashboard-card-link">
            <img class="dashboard-card-icon" src="{{ asset('icons/lucide/drill.png') }}" alt="">
            <div class="dashboard-card-title">Products</div>
            <div class="dashboard-card-value">Go to Catalog</div>
        </a>
        <a href="{{ route('orders') }}" class="dashboard-card dashboard-card-link">
            <img class="dashboard-card-icon" src="{{ asset('icons/lucide/shopping-cart.png') }}" alt="">
            <div class="dashboard-card-title">Orders</div>
            <div class="dashboard-card-value">Go to Orders</div>
        </a>
        <a href="{{ route('customers') }}" class="dashboard-card dashboard-card-link">
            <img class="dashboard-card-icon" src="{{ asset('icons/lucide/users-round.png') }}" alt="">
            <div class="dashboard-card-title">Customers</div>
            <div class="dashboard-card-value">Go to Customers</div>
        </a>
        <a href="{{ route('warehouse') }}" class="dashboard-card dashboard-card-link">
            <img class="dashboard-card-icon" src="{{ asset('icons/lucide/boxes.png') }}" alt="">
            <div class="dashboard-card-title">Product Groups</div>
            <div class="dashboard-card-value">Go to Product Groups</div>
        </a>
        <a href="{{ route('purchase-orders') }}" class="dashboard-card dashboard-card-link">
            <img class="dashboard-card-icon" src="{{ asset('icons/lucide/package.png') }}" alt="">
            <div class="dashboard-card-title">Purchase Orders</div>
            <div class="dashboard-card-value">Go to Purchasing</div>
        </a>
        <a href="{{ route('suppliers') }}" class="dashboard-card dashboard-card-link">
            <img class="dashboard-card-icon" src="{{ asset('icons/lucide/truck.png') }}" alt="">
            <div class="dashboard-card-title">Suppliers</div>
            <div class="dashboard-card-value">Go to Suppliers</div>
        </a>
    </div>
</section>
@endsection
