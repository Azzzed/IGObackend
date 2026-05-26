<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'tipo',
        'nombre',
        'email',
        'password',
        'token_invitado',
        'consentimiento',
        'fecha_consentimiento',
        'version_politica',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'token_invitado',
    ];

    protected function casts(): array
    {
        return [
            'password'            => 'hashed',
            'consentimiento'      => 'boolean',
            'fecha_consentimiento' => 'datetime',
        ];
    }

    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class);
    }

    public function scopeRegistrados($query)
    {
        return $query->where('tipo', 'registrado');
    }

    public function scopeInvitados($query)
    {
        return $query->where('tipo', 'invitado');
    }

    public function isAdmin(): bool
    {
        return $this->email === config('app.admin_email');
    }
}
