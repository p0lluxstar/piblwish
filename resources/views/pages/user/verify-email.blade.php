@extends('layouts.base')

@section('page.title', 'Подтверждение регистрации')

@section('main-content')
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-8">
                
                {{-- Блок уведомления о подтверждении почты --}}
                <div class="alert alert-info" role="alert">
                    <h4 class="alert-heading">Подтвердите вашу почту!</h4>
                    <p>
                        Для завершения регистрации и получения полного доступа к приложению, пожалуйста, перейдите по ссылке, отправленной на ваш адрес электронной почты. 
                        Если вы не видите письмо, проверьте папку "Спам" или нажмите кнопку ниже, чтобы отправить его повторно.
                    </p>
                    <hr>
                    
                    {{-- Форма для повторной отправки письма (стандартный функционал Laravel) --}}
                    <form method="POST" action="{{ route('verification.send') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            Отправить письмо повторно
                        </button>
                    </form>
                    
                    {{-- Сообщение об успешной повторной отправке --}}
                    @if (session('status') == 'verification-link-sent')
                        <span class="text-success ms-3">
                            Новая ссылка подтверждения была отправлена на ваш адрес электронной почты.
                        </span>
                    @endif
                    
                </div>

            </div>
        </div>
    </div>
@endsection