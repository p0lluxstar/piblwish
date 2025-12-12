@extends('layouts.base')

@section('page.title', 'Home')

@section('main-content')
    <div class="container py-4">
        @auth
            <p>Здесь вы можете воспользоваться основной функцией приложения.</p>
            <form method="GET" action="{{ route('home') }}" class="mb-4 d-flex">
                <input type="text" name="url" class="form-control me-2" placeholder="Введите URL-адрес API"
                    value="{{ $url ?? '' }}">
                <button type="submit" class="btn btn-primary">Загрузить</button>
            </form>

            @if (empty($url))
                <p class="text-center">Введите URL-адрес и нажмите "Загрузить", чтобы получить данные.</p>
            @elseif(isset($data['error']))
                <div class="alert alert-danger text-center">{{ $data['error'] }}</div>
            @elseif(empty($data))
                <div class="text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                    <p>Загрузка...</p>
                </div>
            @else
                <ol>
                    @foreach ($data as $item)
                        <li><strong>{{ $item['id'] }}</strong></li>
                    @endforeach
                </ol>
            @endif
        @else
            <div class="alert alert-info text-center py-5">
                <h4 class="alert-heading">Доступ ограничен</h4>
                <p>Зарегистрируйтесь или войдите, чтобы воспользоваться функцией приложения (загрузка данных API).</p>
                <hr>
                <a href="{{ route('login') }}" class="btn btn-outline-primary btn-lg">Войти</a>
                <a href="{{ route('register') }}" class="btn btn-primary btn-lg me-2">Регистрация</a>
            </div>
        @endauth

    </div>
@endsection
