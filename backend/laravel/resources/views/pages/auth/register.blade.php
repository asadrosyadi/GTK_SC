@extends('layouts.app')

@section('body')
    @include('components.auth-background')
    <div class="container-xxl">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner">
                <div class="card">
                    <div class="card-body">
                        <div class="app-brand justify-content-center">
                            <a href="{{ route('login') }}" class="app-brand-link gap-2">
                                <span class="app-brand-logo demo">
                                    <img src="{{ asset('plant.png') }}" alt="404 Not Found Indonesia" width="30"
                                         style="border-radius: 150px" srcset="">
                                </span>
                                <span class="app-brand-text text-body fw-bold fs-3">{{ config('app.name') }}</span>
                            </a>
                        </div>

                        <h4 class="mb-2">{{ __('label.adventure_start_here') }} </h4>
                        <p class="mb-4">{{ __('label.lets_create_an_account') }}</p>

                        <form id="formAuthentication" class="mb-3" action="{{ route('register') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <x-forms.input name="name" :value="null"/>
                            </div>
                            <div class="mb-3">
                                <x-forms.input name="email" :value="null" type="email"/>
                            </div>
                            <div class="mb-3">
                                <x-forms.input-password name="password"/>
                            </div>
                            <div class="mb-3">
                                <x-forms.input-password name="password_confirmation"/>
                            </div>

                            <button class="btn mt-4 btn-primary d-grid w-100" type="submit">
                                {{ __('button.register') }}
                            </button>
                        </form>

                        <p class="text-center">
                            <span>{{ __('label.already_have_an_account') }}</span>
                            <a href="{{ route('login') }}">
                                <span>{{ __('label.login') }}</span>
                            </a>
                        </p>
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
