@extends('layouts.base')

@section('page.title', 'Вход в систему')

@section('main-content')
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg">
                    <div class="card-body p-4">
                        <h1 class="card-title text-center mb-4">Вход в систему</h1>

                        <form method="POST" action="{{ route('login') }}">
                            @csrf
                            {{-- Поле Email --}}
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" name="email" autofocus value="{{ old('email') }}"
                                    class="form-control @error('email') is-invalid @enderror"
                                    placeholder="example@mail.com">
                                @error('email')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            {{-- Поле Пароль --}}
                            <div class="mb-4">
                                <label for="password" class="form-label">Пароль</label>
                                <input type="password" id="password" name="password" autocomplete="current-password"
                                    class="form-control @error('password') is-invalid @enderror" placeholder="Ваш пароль">
                                @error('password')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            {{-- Чекбокс "Запомнить меня" (опционально, но полезно) --}}
                            <div class="mb-4 form-check">
                                <input class="form-check-input" type="checkbox" name="remember" id="remember"
                                    {{ old('remember') ? 'checked' : '' }}>
                                <label class="form-check-label" for="remember">
                                    Запомнить меня
                                </label>
                            </div>

                            {{-- Кнопка Вход --}}
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    Войти
                                </button>
                            </div>

                            {{-- Ссылки на регистрацию и восстановление пароля --}}
                            <div class="mt-3 text-center">
                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="text-decoration-none d-block mb-1">
                                        Забыли пароль?
                                    </a>
                                @endif
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="text-decoration-none">
                                        Нет аккаунта? Зарегистрироваться
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
