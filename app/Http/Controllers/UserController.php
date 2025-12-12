<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class UserController extends Controller

{
    public function store(Request $request)
    {
        // 1. Валидация данных из формы
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Если валидация пройдена, код продолжает выполнение

        // 2. Создание пользователя в базе данных
        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => $validatedData['password'],
        ]);

        // 3. Отправка письма о подтверждении регистрации
        event(new Registered($user));

        // Auth::login($user);

        // 3. (Опционально) Автоматическая авторизация
        // Auth::login($user); 

        // 4. Перенаправление
        return redirect()->route('verification.notice');
    }

    public function dashboard()
    {
        return view('pages.user.dashboard');
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->route('login');
    }

    public function login(Request $request)
    {
        $validatedData = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->has('remember');

        if (Auth::attempt($validatedData, $remember)) {
            $request->session()->regenerate();
            return redirect()->intended(('dashboard'))->with('success', 'Добро пожаловать, ' . Auth::user()->name);
        }

        return back()->withErrors(([
            'email' => 'Неверный логин или пароль',
            'password' => 'Неверный логин или пароль'
        ]));

        // dd($request->all());
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        $customMessage = 'Мы отправили вам на электронную почту ссылку для сброса пароля.';

        return $status === Password::ResetLinkSent
            ? back()->with(['status' => $customMessage])
            : back()->withErrors(['email' => __($status)]);
    }

    public function resetPasswordUpdate(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PasswordReset
            ? redirect()->route('login')->with('success', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    }
}
