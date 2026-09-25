<?php

namespace App\Models;

use App\Enums\RolStaff;
use Database\Factories\UsuarioStaffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre
 * @property string $email
 * @property string $password
 * @property RolStaff $rol
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nombre', 'email', 'password', 'rol'])]
#[Hidden(['password', 'remember_token'])]
class UsuarioStaff extends Authenticatable
{
    /** @use HasFactory<UsuarioStaffFactory> */
    use HasFactory;

    protected $table = 'usuarios_staff';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'rol' => RolStaff::class,
        ];
    }

    /**
     * Determine if the staff member has the admin role.
     */
    public function isAdmin(): bool
    {
        return $this->rol === RolStaff::Admin;
    }

    /**
     * Determine if the staff member has the moderator role.
     */
    public function isModerador(): bool
    {
        return $this->rol === RolStaff::Moderador;
    }
}
