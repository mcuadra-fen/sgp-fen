<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['keycloak_id', 'name', 'username', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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

    /** Acceso completo (config sgp.admins); el resto de las personas solo ve «Mis cobros». */
    public function esAdmin(): bool
    {
        return in_array(strtolower((string) $this->email), config('sgp.admins'), true);
    }

    /** @return string[] centros de costo que puede ver en «Mis cobros» */
    public function centrosCosto(): array
    {
        return AccesoArea::centrosDe($this->email);
    }

    public function puedeIngresar(): bool
    {
        return $this->esAdmin() || $this->centrosCosto() !== [];
    }
}
