<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Напоминания о сроке — PiblKit</title>
</head>

<body
    style="
    margin: 0;
    padding: 40px 16px;
    font-family: Arial, sans-serif;
    line-height: 1.6;
    color: #333;
    background-color: #f4f6f8;
">
    <div
        style="
        max-width: 440px;
        margin: 0 auto;
        padding: 24px;
        background-color: #fff;
        border-radius: 8px;
    ">
        <h2 style="margin: 0 0 10px; color: #2c3e50;">
            Напоминания о сроке
        </h2>

        @if ($unsubscribed)
            <p>
                Напоминания отключены, письма о сроке списков больше не будут
                приходить. Включить их снова можно в настройках аккаунта.
            </p>
        @else
            <p>
                Отключить письма, которые приходят за день до даты ваших списков
                и сохранённых чужих списков?
            </p>

            <form method="POST" action="{{ $action }}">
                <button type="submit"
                    style="
                    padding: 10px 18px;
                    font-size: 1em;
                    color: #fff;
                    background-color: #2c3e50;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                ">
                    Отключить напоминания
                </button>
            </form>
        @endif

        <p style="margin: 20px 0 0;">
            <a href="{{ url('/dashboard') }}" style="color: #2c3e50;">Перейти в PiblKit</a>
        </p>
    </div>
</body>

</html>
