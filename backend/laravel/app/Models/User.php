<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'image',
        'iot_id',
        'iot_token',
        'locale',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'iot_token',
    ];

    protected $appends = [
        'avatar_url',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function avatarUrl(): Attribute
    {
        return new Attribute(
            get: fn () => 'https://ui-avatars.com/api/?name='.$this->name,
        );
    }

    public function scopeSearch(Builder $query, ?string $search)
    {
        return $query->when($search, function (Builder $query, string $search) {
            return $query->where('name', 'LIKE', $search.'%');
        });
    }

    public function scopeRender(Builder $query, ?int $page): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $query->paginate($page ?? 50)->withQueryString();
    }
}
