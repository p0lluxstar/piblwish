<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AppBackground;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['username', 'email', 'password', 'is_active', 'email_verified_at', 'deactivated_at', 'background', 'show_friends_events', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
            'background' => AppBackground::class,
            'show_friends_events' => 'boolean',
        ];
    }

    // Аккаунты, которые зарегистрированы, но ещё не подтвердили email
    // кодом из письма (и не удалены)
    #[Scope]
    protected function pendingVerification(Builder $query): void
    {
        $query->where('is_active', false)
            ->whereNull('email_verified_at')
            ->whereNull('deactivated_at');
    }

    public function verificationRegistrationCodes(): HasMany
    {
        return $this->hasMany(VerificationRegistrationCode::class);
    }

    // Незавершённый запрос на смену email (не больше одного)
    public function emailChangeRequest(): HasOne
    {
        return $this->hasOne(EmailChangeRequest::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    // Чужие списки, добавленные к себе с общей страницы
    public function savedWishlists(): HasMany
    {
        return $this->hasMany(SavedWishlist::class);
    }

    // Адрес фотографии на диске public или null, если фотографии нет
    public function avatarUrl(): ?string
    {
        return $this->avatar_path
            ? Storage::disk('public')->url($this->avatar_path)
            : null;
    }
}
