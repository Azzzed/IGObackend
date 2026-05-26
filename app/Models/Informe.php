<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Informe extends Model
{
    use HasFactory;

    protected $fillable = [
        'empresa_id',
        'contenido_json',
        'asintotas_json',
    ];

    protected function casts(): array
    {
        return [
            'contenido_json'  => 'array',
            'asintotas_json'  => 'array',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
