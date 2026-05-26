<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'nombre',
        'sector',
        'tamano',
        'genero_empresario',
        'rango_edad',
        'pais',
        'ciudad',
        'activa',
    ];

    protected $attributes = [
        'activa' => true,
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function iniciativas(): HasMany
    {
        return $this->hasMany(Iniciativa::class);
    }

    public function informes(): HasMany
    {
        return $this->hasMany(Informe::class);
    }

    public function ultimoInforme(): HasOne
    {
        return $this->hasOne(Informe::class)->latestOfMany();
    }

    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }
}
