<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email изменён</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">

    <h2 style="color: #2c3e50; margin-bottom: 10px;">
        Email изменён
    </h2>

    <p>Здравствуйте, {{ $username }}!</p>

    <p>
        Email вашего аккаунта в системе
        <strong>«PiblWish»</strong>
        изменён. Письма, в том числе коды восстановления пароля, теперь
        приходят на новый адрес.
    </p>

    <div
        style="
        margin: 20px 0;
        padding: 15px;
        background-color: #f4f6f8;
        border-radius: 6px;
    ">
        <p style="margin: 0;">
            Новый email: <strong>{{ $newEmail }}</strong>
        </p>

        <p style="margin: 0;">
            Дата и время: <strong>{{ $changedAt }}</strong>
        </p>

        @if ($ipAddress)
            <p style="margin: 0;">
                IP-адрес: <strong>{{ $ipAddress }}</strong>
            </p>
        @endif
    </div>

    <hr style="
        border: none;
        border-top: 1px solid #eee;
        margin: 20px 0;
    " />

    <p style="font-size: 0.85em; color: #777;">
        Если вы не меняли email, срочно свяжитесь с поддержкой
        «PiblWish»: доступ к вашему аккаунту мог получить кто-то другой.
    </p>

</body>

</html>
