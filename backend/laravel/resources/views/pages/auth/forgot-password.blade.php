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
                        <h4 class="mb-2">{{ __('label.forgot_password') }} 🔒</h4>
                        <p class="mb-4">{{ __('label.enter_your_email_to_reset_password') }}</p>

                        @session('status')
                        <div class="alert alert-success alert-dismissible" role="alert">
                            {{ $value }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                            </button>
                        </div>
                        @endsession

                        <form id="formAuthentication" method="POST" class="mb-3" action="{{ route('password.email') }}">
                            @csrf
                            <div class="mb-3">
                                <x-forms.input name="email" :value="null" type="email"/>
                            </div>
                            <button class="btn btn-primary d-grid w-100">{{ __('button.send') }}</button>
                        </form>
                        <div class="text-center">
                            <a href="{{ route('login') }}" class="d-flex align-items-center justify-content-center">
                                <i class="bx bx-chevron-left scaleX-n1-rtl bx-sm"></i>
                                {{ __('label.login') }}
                            </a>
                        </div>
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
