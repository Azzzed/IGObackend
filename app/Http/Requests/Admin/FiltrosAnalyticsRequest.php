<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrosAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // AdminMiddleware ya verifica el rol
    }

    public function rules(): array
    {
        return [
            // ─── Filtros de empresa ───────────────────────────────────────────
            'sector'    => ['nullable', 'string', Rule::in([
                'agro','calzado_moda','tecnologia','servicios','comercio',
                'salud','turismo','educacion','manufactura','otro',
            ])],
            'genero'    => ['nullable', 'string', Rule::in(['hombre','mujer','otro'])],
            'edad'      => ['nullable', 'string', Rule::in(['18-25','26-35','36-45','46-55','56+'])],
            'tamano'    => ['nullable', 'string', Rule::in(['idea','micro','pequena','mediana','grande'])],
            'ciudad'    => ['nullable', 'string', 'max:100'],
            'pais'      => ['nullable', 'string', 'max:100'],

            // ─── Filtros de iniciativa ────────────────────────────────────────
            'cuadrante' => ['nullable', 'integer', Rule::in([1,2,3,4])],
            'categoria' => ['nullable', 'string', Rule::in([
                'gestion_operativa','tecnologia_informacion','gestion_financiera',
                'cadena_suministro','talento_humano','comercial_ventas',
                'legal_cumplimiento','otro',
            ])],

            // ─── Filtro de período (rango de fecha) ───────────────────────────
            'periodo'   => ['nullable', 'string', Rule::in(['last_30','last_90','last_180','last_365','all'])],

            // ─── Paginación y orden (para /metricas/iniciativas) ──────────────
            'page'      => ['nullable', 'integer', 'min:1'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort'      => ['nullable', 'string', Rule::in(['fecha','importancia','gobernabilidad','cuadrante','sector'])],
            'dir'       => ['nullable', 'string', Rule::in(['asc','desc'])],
        ];
    }

    /**
     * Devuelve solo los filtros presentes (sin nulls ni vacíos).
     * Se pasa directamente a MetricasService::aplicarFiltros().
     */
    public function filtros(): array
    {
        return array_filter(
            $this->only([
                'sector','genero','edad','tamano','ciudad','pais',
                'cuadrante','categoria','periodo',
                'page','per_page','sort','dir',
            ]),
            fn ($v) => $v !== null && $v !== ''
        );
    }
}
