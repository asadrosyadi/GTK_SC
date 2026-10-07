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
                        <h4 class="mb-2">{{ __('label.reset_password') }} 🔒</h4>
                        <p class="mb-4">{{ __('label.please_enter_your_new_password') }}</p>

                        <form id="formAuthentication" class="mb-3" action="{{ route('password.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="token" value="{{ $request->route('token') }}">
                            <div class="mb-3">
                                <x-forms.input name="email" :value="$request->email" type="email" />
                            </div>
                            <div class="mb-3">
                                <x-forms.input-password name="password"/>
                            </div>
                            <div class="mb-3">
                                <x-forms.input-password name="password_confirmation"/>
                            </div>

                            <button class="btn mt-4 btn-primary d-grid w-100"
                                    type="submit">{{ __('button.reset') }}</button>
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
