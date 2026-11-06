<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Traits\HasRoles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'email_verified_at', 'password', 'profile_photo_path', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable, SoftDeletes;

    /** @var array{profile_photo_path: string, role: string} */
    protected $attributes = [
        'profile_photo_path' => '/assets/img/profile/user.png',
        'role' => 'engineering',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /** @return HasMany<Layer, $this> */
    public function layers(): HasMany
    {
        return $this->hasMany(Layer::class);
    }

    /** @return Attribute<string, never> */
    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::make(get: fn (): string => asset($this->profile_photo_path ?: '/assets/img/profile/user.png'));
    }
}
