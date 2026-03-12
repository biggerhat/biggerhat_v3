<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @mixin IdeHelperUser
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    use HasRoles;
    use UsesSlugName;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [
        'id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
        ];
    }

    /**
     * @return BelongsToMany<Miniature, $this>
     */
    public function collectionMiniatures(): BelongsToMany
    {
        return $this->belongsToMany(Miniature::class, 'user_miniatures')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Package, $this>
     */
    public function collectionPackages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'user_packages')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Wishlist, $this>
     */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }
}
