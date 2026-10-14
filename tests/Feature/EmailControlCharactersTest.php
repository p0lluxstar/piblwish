<?php

namespace Tests\Feature;

use App\Models\User;
use App\Rules\NoControlCharacters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Email с переводом строки внутри кавычек проходит правило 'email',
 * а в заголовке письма превращается в лишнюю строку (например, Bcc со
 * списком чужих адресов). Правило NoControlCharacters отклоняет такие
 * адреса во всех запросах с полем email. С Laravel 13.10 такой адрес отклоняет
 * и само правило 'email' (CVE-2026-48019), поэтому здесь проверяется только
 * отказ по полю email, а работа NoControlCharacters — отдельными тестами ниже.
 */
class EmailControlCharactersTest extends TestCase
{
    use RefreshDatabase;

    private const INJECTED_EMAIL = "\"a\r\nBcc: other@example.org\"@example.com";

    public static function publicEndpointProvider(): array
    {
        return [
            'регистрация' => ['/v1/register', [
                'username' => 'newuser',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]],
            'вход' => ['/v1/login', ['password' => 'password123']],
            'подтверждение регистрации' => ['/v1/verify-registration', ['code' => '123456']],
            'повторная отправка кода' => ['/v1/verify-registration/resend', []],
            'запрос кода восстановления' => ['/v1/forgot-password', []],
            'установка пароля' => ['/v1/reset-password', [
                'code' => '123456',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]],
        ];
    }

    #[DataProvider('publicEndpointProvider')]
    public function test_public_endpoint_rejects_email_with_line_break(string $url, array $data): void
    {
        Mail::fake();

        $this->postJson($url, ['email' => self::INJECTED_EMAIL, ...$data])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email', 'data.errors');

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_email_change_rejects_email_with_line_break(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/user/email', [
                'email' => self::INJECTED_EMAIL,
                'current_password' => 'password',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email', 'data.errors');

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('email_change_requests', 0);
    }

    public static function controlCharacterProvider(): array
    {
        return [
            'CR' => ["a\r@example.com"],
            'LF' => ["\"a\nb\"@example.com"],
            'NUL' => ["\"a\0b\"@example.com"],
            'табуляция' => ["\"a\tb\"@example.com"],
            'DEL' => ["\"a\x7Fb\"@example.com"],
            'NEL (U+0085)' => ["\"a\u{0085}b\"@example.com"],
            'разделитель строк U+2028' => ["\"a\u{2028}b\"@example.com"],
            'некорректный UTF-8' => ["a\xC3@example.com"],
        ];
    }

    #[DataProvider('controlCharacterProvider')]
    public function test_rule_rejects_control_characters(string $email): void
    {
        $validator = Validator::make(['email' => $email], ['email' => [new NoControlCharacters]]);

        $this->assertTrue($validator->fails());
    }

    public static function validEmailProvider(): array
    {
        return [
            'обычный' => ['user@example.com'],
            'с плюсом и точками' => ['first.last+tag@sub.example.com'],
            'кириллический домен' => ['user@пример.рф'],
            'кавычки с пробелом' => ['"first last"@example.com'],
        ];
    }

    #[DataProvider('validEmailProvider')]
    public function test_rule_accepts_regular_emails(string $email): void
    {
        $validator = Validator::make(['email' => $email], ['email' => [new NoControlCharacters]]);

        $this->assertFalse($validator->fails());
    }
}
