<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Отписка от напоминаний о сроке по подписанной ссылке из письма; вход не нужен.
 *
 * GET только показывает страницу с кнопкой: почтовые сервисы заранее открывают
 * ссылки из писем, и отписка по GET срабатывала бы без ведома пользователя.
 * POST отключает напоминания; его же отправляет почтовый клиент по заголовку
 * List-Unsubscribe-Post, поэтому маршрут исключён из проверки CSRF.
 */
class EmailReminderController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    public function showUnsubscribe(Request $request, User $user): View
    {
        return view('email-reminders.unsubscribe', [
            'action' => $request->fullUrl(),
            'unsubscribed' => ! $user->email_reminders,
        ]);
    }

    public function unsubscribe(User $user): View
    {
        $this->userService->updateSettings($user, null, emailReminders: false);

        return view('email-reminders.unsubscribe', [
            'action' => null,
            'unsubscribed' => true,
        ]);
    }
}
