@extends('layouts.app')

@section('body')
    @include('components.auth-background')
    <div class="container-xxl">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner py-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-center mb-4">
                            <img src="{{ asset('img/static/agroguard.png') }}" alt="Logo AgroGuard" width="150">
                        </div>

                        <h4 class="mb-2">Konfirmasi Kata Sandi 🔒</h4>
                        <p class="mb-4">Ini adalah area aman aplikasi. Silakan konfirmasi kata sandi Anda sebelum melanjutkan.</p>

                        <form method="POST" action="{{ route('password.confirm') }}" class="mb-3">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="password">{{ __('field.password') }}</label>
                                <input type="password" id="password" name="password" required autocomplete="current-password"
                                       class="form-control @error('password') is-invalid @enderror">
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-primary d-grid w-100">Konfirmasi</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}"/>
    <link rel="stylesheet" href="{{ asset('assets/css/auth-agro.css') }}"/>
@endpush
