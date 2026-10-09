<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AppBackground;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['username', 'email', 'password', 'is_active', 'email_verified_at', 'deactivated_at', 'background', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasUlids;

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
        ];
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

    // Адрес фотографии на диске public или null, если фотографии нет
    public function avatarUrl(): ?string
    {
        return $this->avatar_path
            ? Storage::disk('public')->url($this->avatar_path)
            : null;
    }
}
