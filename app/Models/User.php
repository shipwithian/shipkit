<?php

namespace App\Models;

use App\Domain\AccessControl\Enums\SystemRole;
use App\Domain\AccessControl\Exceptions\LastSuperAdminCannotBeRemoved;
use App\Domain\AccessControl\Models\Role;
use App\Domain\AccessControl\Models\RoleAssignment;
use App\Domain\Settings\Exceptions\ProtectedAccountCannotBeDeleted;
use App\Domain\Settings\Policies\UserPolicy;
use App\Domain\Shared\Eloquent\GuardsInvariants;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property bool $is_protected
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
#[UsePolicy(UserPolicy::class)]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use GuardsInvariants, HasApiTokens, HasFactory, HasRoles {
        HasRoles::roles as spatieRoles;
    }

    use Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The user's roles, attached through {@see RoleAssignment} so removals run the role guards.
     *
     * @return BelongsToMany<Model, Model, RoleAssignment, 'pivot'>
     */
    public function roles(): BelongsToMany
    {
        return $this->spatieRoles()->using(RoleAssignment::class);
    }

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
            'two_factor_confirmed_at' => 'datetime',
            'is_protected' => 'boolean',
        ];
    }

    /**
     * @throws ProtectedAccountCannotBeDeleted
     * @throws LastSuperAdminCannotBeRemoved
     */
    public function ensureCanBeDeleted(): void
    {
        if ($this->getOriginal('is_protected')) {
            throw new ProtectedAccountCannotBeDeleted;
        }

        Role::query()
            ->where('name', SystemRole::SuperAdmin->value)
            ->get()
            ->each(fn (Role $role) => $role->ensureCanLoseHolder($this->getKey()));
    }

    protected function guardDelete(): void
    {
        $this->ensureCanBeDeleted();
    }
}
