@extends('layouts.base')

@section('page.title', 'Вход в систему')

@section('main-content')
    <div class="container">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        <h1>Dashboardy</h1>
    </div>
@endsection
