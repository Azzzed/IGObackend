<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Iniciativa extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'empresa_id',
        'titulo',
        'categoria',
        'importancia',
        'gobernabilidad',
        'cuadrante',
    ];

    protected function casts(): array
    {
        return [
            'importancia'    => 'integer',
            'gobernabilidad' => 'integer',
            'cuadrante'      => 'integer',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function planAccion(): HasOne
    {
        return $this->hasOne(PlanAccion::class);
    }

    public function scopePorCuadrante($query, int $cuadrante)
    {
        return $query->where('cuadrante', $cuadrante);
    }
}
