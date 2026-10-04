<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Пароль изменён</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">

    <h2 style="color: #2c3e50; margin-bottom: 10px;">
        Пароль изменён
    </h2>

    <p>Здравствуйте, {{ $username }}!</p>

    <p>
        Пароль от вашего аккаунта в системе
        <strong>«PiblWish»</strong>
        был изменён.
    </p>

    <div
        style="
        margin: 20px 0;
        padding: 15px;
        background-color: #f4f6f8;
        border-radius: 6px;
    ">
        <p style="margin: 0;">
            Дата и время: <strong>{{ $changedAt }}</strong>
        </p>

        @if ($ipAddress)
            <p style="margin: 0;">
                IP-адрес: <strong>{{ $ipAddress }}</strong>
            </p>
        @endif
    </div>

    <p style="font-size: 0.95em;">
        Все сеансы на других устройствах завершены.
    </p>

    <hr style="
        border: none;
        border-top: 1px solid #eee;
        margin: 20px 0;
    " />

    <p style="font-size: 0.85em; color: #777;">
        Если вы не меняли пароль, срочно свяжитесь с поддержкой
        «PiblWish»: доступ к вашему аккаунту мог получить кто-то другой.
    </p>

</body>

</html>
