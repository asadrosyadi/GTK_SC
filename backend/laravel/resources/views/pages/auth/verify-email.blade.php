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

                        <h4 class="mb-2">Verifikasi Email ✉️</h4>
                        <p class="mb-4">
                            Terima kasih telah mendaftar. Sebelum mulai, silakan verifikasi alamat email Anda melalui
                            tautan yang kami kirimkan. Jika belum menerima email, kami dapat mengirimkan ulang.
                        </p>

                        @if (session('status') == 'verification-link-sent')
                            <div class="alert alert-success" role="alert">
                                Tautan verifikasi baru telah dikirim ke alamat email Anda.
                            </div>
                        @endif

                        <form method="POST" action="{{ route('verification.send') }}" class="mb-3">
                            @csrf
                            <button type="submit" class="btn btn-primary d-grid w-100">Kirim Ulang Email Verifikasi</button>
                        </form>

                        <form method="POST" action="{{ route('logout') }}" class="text-center">
                            @csrf
                            <button type="submit" class="btn btn-link">Keluar</button>
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
