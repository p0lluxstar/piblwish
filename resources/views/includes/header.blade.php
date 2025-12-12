<header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/">
                <strong>PiblWish</strong>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
                            Главная
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('about') ? 'active' : '' }}"
                            href="{{ route('about') }}">
                            О нас
                        </a>
                    </li>
                    @auth
                        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                            href="{{ route('dashboard') }}">
                            Dashboard
                        </a>
                    @endauth
                </ul>
                <div class="navbar-nav">
                    <ul class="navbar-nav me-auto">
                        @auth
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                @csrf
                            </form>

                            <li class="d-flex align-items-center justify-content-center text-white">
                                <span>
                                    Привет, {{ auth()->user()->name }}!
                                </span>
                            </li>
                            <li>
                                <a class="btn btn-outline-light ms-2" href="#"
                                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    Выйти
                                </a>
                            </li>
                        @endauth
                        @guest
                            <li>
                                <a class="nav-link {{ request()->routeIs('login') ? 'active' : '' }}"
                                    href="{{ route('login') }}">
                                    Войти
                                </a>
                            </li>
                            <li>
                                <a class="btn btn-outline-light ms-2" href="{{ route('register') }}">Регистрация</a>
                            </li>
                        @endguest

                    </ul>
                </div>
            </div>
        </div>
    </nav>
</header>
