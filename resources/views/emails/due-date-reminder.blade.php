<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Напоминание о сроке</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">

    <h2 style="color: #2c3e50; margin-bottom: 10px;">
        Скоро наступает дата списков
    </h2>

    <p>Здравствуйте, {{ $username }}!</p>

    @if (count($ownLists) > 0)
        <p>Напоминаем о ваших списках в системе <strong>«Piblix»</strong>:</p>

        @foreach ($ownLists as $list)
            <div
                style="
                margin: 0 0 10px;
                padding: 12px 15px;
                background-color: #f4f6f8;
                border-radius: 6px;
            ">
                <p style="margin: 0;">
                    <strong>{{ $list['title'] }}</strong>
                </p>

                <p style="margin: 0; font-size: 0.9em;">
                    {{ $list['date'] }}
                </p>

                <p style="margin: 0; font-size: 0.9em; color: #555;">
                    {{ $list['type'] }}@if ($list['details']) · {{ $list['details'] }}@endif
                </p>
            </div>
        @endforeach

        <p style="margin: 15px 0 25px;">
            <a href="{{ $dashboardUrl }}"
                style="
                display: inline-block;
                padding: 10px 18px;
                background-color: #2c3e50;
                color: #fff;
                text-decoration: none;
                border-radius: 6px;
            ">
                Открыть мои списки
            </a>
        </p>
    @endif

    @if (count($savedLists) > 0)
        <p>
            @if (count($ownLists) > 0)
                Скоро также наступает дата чужих списков, которые вы сохранили:
            @else
                Скоро наступает дата чужих списков, которые вы сохранили
                в системе <strong>«Piblix»</strong>:
            @endif
        </p>

        @foreach ($savedLists as $list)
            <div
                style="
                margin: 0 0 10px;
                padding: 12px 15px;
                background-color: #f4f6f8;
                border-radius: 6px;
            ">
                <p style="margin: 0;">
                    <a href="{{ $list['url'] }}" style="color: #2c3e50;">
                        <strong>{{ $list['title'] }}</strong>
                    </a>
                </p>

                <p style="margin: 0; font-size: 0.9em;">
                    {{ $list['date'] }}
                </p>

                <p style="margin: 0; font-size: 0.9em; color: #555;">
                    {{ $list['type'] }} · {{ $list['owner'] }}@if ($list['details']) · {{ $list['details'] }}@endif
                </p>
            </div>
        @endforeach
    @endif

    <hr style="
        border: none;
        border-top: 1px solid #eee;
        margin: 20px 0;
    " />

    <p style="font-size: 0.85em; color: #777;">
        Напоминания приходят за {{ $reminderDays }} до даты списка, о каждом
        списке — один раз. Изменить срок или отключить напоминания можно
        в настройках аккаунта или
        <a href="{{ $unsubscribeUrl }}" style="color: #777;">по этой ссылке</a>.
    </p>

</body>

</html>
