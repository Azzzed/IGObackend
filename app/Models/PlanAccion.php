<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanAccion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'planes_accion';

    protected $fillable = [
        'iniciativa_id',
        'deadline',
        'presupuesto',
        'aliados',
        'estado',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'deadline'    => 'date',
            'presupuesto' => 'decimal:2',
        ];
    }

    public function iniciativa(): BelongsTo
    {
        return $this->belongsTo(Iniciativa::class);
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeEnProceso($query)
    {
        return $query->where('estado', 'en_proceso');
    }
}
