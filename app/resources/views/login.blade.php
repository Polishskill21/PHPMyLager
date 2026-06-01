@extends('layouts.app')

@section('title', 'Login - Storage Management System')

@push('styles')
    @vite('resources/css/pages/login.css')
@endpush

@section('content')
<section class="login-page">
    <div class="login-card">
        <div class="login-brand">
            <h1>Storage Management System</h1>
            <p>Inventory Control Console</p>
        </div>

        @if($errors->any())
            <div class="alert alert-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
            @csrf

            <label class="form-label" for="email">Email Address</label>
            <input
                class="form-input {{ $errors->has('email') ? 'is-invalid' : '' }}"
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
                autocomplete="email"
                required
            >

            <label class="form-label" for="password">Password</label>
            <input
                class="form-input"
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >

            <button class="btn btn-primary login-submit" type="submit">Sign In</button>
        </form>
    </div>
</section>
@endsection

@push('scripts')
<script>
const emailInput = document.getElementById('email');
const form = document.querySelector('form');

const savedEmail = localStorage.getItem('lastEmail');
if (savedEmail && !emailInput.value) {
    emailInput.value = savedEmail;
    emailInput.classList.add('login-input-muted');
}

emailInput.addEventListener('input', function () {
    this.classList.remove('login-input-muted');
});

form.addEventListener('submit', function () {
    if (emailInput.value) {
        localStorage.setItem('lastEmail', emailInput.value);
    }
});
</script>
@endpush
