@php
    // Мета-теги превью ссылки. Общая страница списка передаёт $meta
    // (SharedWishlistPageController), остальные страницы получают общие теги сайта
    $siteName = 'PiblWish';
    $pageTitle = isset($meta) ? $meta['title'].' — '.$siteName : $siteName.' - Твои желания в одном месте';
    $ogTitle = $meta['title'] ?? $siteName.' — твои желания в одном месте';
    $ogDescription = $meta['description']
        ?? 'Списки желаний, дела, заметки и сборы. Делитесь ссылкой с друзьями — они выберут подарок, а сюрприз сохранится.';
    // Адреса строятся от APP_URL, как и адрес фото пользователя: за прокси
    // url() может вернуть http вместо https
    $appUrl = rtrim(config('app.url'), '/');
    $ogImage = $appUrl.'/og-image.jpg';
    $ogUrl = $appUrl.'/'.ltrim(request()->path(), '/');
@endphp
<!doctype html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" href="/favicon.ico">
    <meta name="description" content="{{ $ogDescription }}">
    @if (! empty($noindex))
        <meta name="robots" content="noindex">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:locale" content="ru_RU">
    <meta property="og:url" content="{{ $ogUrl }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $siteName }} — твои желания в одном месте">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    {{-- Тема ставится до загрузки стилей и приложения, чтобы страница не мигала
         светлой. Ключ и значения совпадают с composables/useTheme.ts --}}
    <script>
        (function () {
            var preference = 'system';
            try {
                preference = localStorage.getItem('app-theme') || 'system';
            } catch (e) {}
            var dark = preference === 'dark'
                || (preference !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.dataset.theme = dark ? 'dark' : 'light';
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    <title>{{ $pageTitle }}</title>
</head>

<body>
    <div id="app"></div>
</body>

</html>
