<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подтверждение регистрации</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">

    <h2 style="color: #2c3e50; margin-bottom: 10px;">
        Подтверждение регистрации
    </h2>

    <p>Здравствуйте!</p>

    <p>
        Для завершения регистрации в системе
        <strong>«PiblWish»</strong>
        введите следующий код:
    </p>

    <div style="
        font-size: 28px;
        font-weight: bold;
        letter-spacing: 6px;
        text-align: center;
        margin: 20px 0;
        padding: 15px;
        background-color: #f4f6f8;
        border-radius: 6px;
    ">
        {{ $code }}
    </div>

    <p style="font-size: 0.95em;">
        Код действителен в течение <strong>2 минут</strong>.
    </p>

    <hr style="
        border: none;
        border-top: 1px solid #eee;
        margin: 20px 0;
    " />

    <p style="font-size: 0.85em; color: #777;">
        Если вы не регистрировались в системе «PiblWish»,
        просто проигнорируйте это письмо.
    </p>

</body>
</html>