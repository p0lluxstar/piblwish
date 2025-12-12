@extends('layouts.base')

@section('page.title', 'Регистрация')

@section('main-content')
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg">
                    <div class="card-body p-4">
                        <h1 class="card-title text-center mb-4">Регистрация</h1>

                        <form method="POST" action="{{ route('register') }}">
                            @csrf
                            
                            {{-- Поле Имя --}}
                            <div class="mb-3">
                                <label for="name" class="form-label">Имя</label>
                                <input type="text" id="name" name="name" autofocus value="{{ old('name') }}"
                                    class="form-control @error('name') is-invalid @enderror" placeholder="Ваше имя">
                                @error('name')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            {{-- Поле Email --}}
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" name="email" value="{{ old('email') }}"
                                    class="form-control @error('email') is-invalid @enderror"
                                    placeholder="example@mail.com">
                                @error('email')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            {{-- Поле Пароль --}}
                            <div class="mb-3">
                                <label for="password" class="form-label">Пароль</label>
                                <input type="password" id="password" name="password" autocomplete="new-password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    placeholder="Минимум 8 символов">
                                @error('password')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            {{-- Поле Подтверждение пароля --}}
                            <div class="mb-4">
                                <label for="password_confirmation" class="form-label">Подтверждение пароля</label>
                                <input type="password" id="password_confirmation" name="password_confirmation"
                                    autocomplete="new-password" class="form-control" placeholder="Повторите пароль">
                            </div>

                            {{-- Кнопка Регистрация --}}
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    Зарегистрироваться
                                </button>
                            </div>

                            {{-- Ссылка на вход (опционально) --}}
                            <div class="mt-3 text-center">
                                <a href="{{ route('login') }}" class="text-decoration-none">
                                    Уже зарегистрированы? Войти
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
