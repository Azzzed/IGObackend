<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Iniciativa;
use App\Models\PlanAccion;
use App\Models\User;
use App\Services\IgoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EmpresaSeeder extends Seeder
{
    public function run(): void
    {
        $usuario = User::create([
            'tipo'                 => 'registrado',
            'nombre'               => 'Carlos Empresario',
            'email'                => 'carlos@demo.com',
            'password'             => Hash::make('Demo1234!'),
            'consentimiento'       => true,
            'fecha_consentimiento' => now(),
            'version_politica'     => '1.0',
        ]);

        // Empresa 1: Tech startup
        $empresa1 = Empresa::create([
            'user_id'           => $usuario->id,
            'nombre'            => 'TechSoluciones SAS',
            'sector'            => 'tecnologia',
            'tamano'            => 'micro',
            'genero_empresario' => 'hombre',
            'rango_edad'        => '26-35',
            'pais'              => 'Colombia',
            'ciudad'            => 'Bogotá',
            'activa'            => true,
        ]);

        $iniciativas1 = [
            ['titulo' => 'Implementar sistema de facturación electrónica', 'categoria' => 'tecnologia_informacion',   'importancia' => 5, 'gobernabilidad' => 4],
            ['titulo' => 'Mejorar flujo de caja mensual',                  'categoria' => 'gestion_financiera',       'importancia' => 5, 'gobernabilidad' => 2],
            ['titulo' => 'Contratar desarrollador backend',                 'categoria' => 'talento_humano',           'importancia' => 4, 'gobernabilidad' => 3],
            ['titulo' => 'Crear estrategia de ventas digitales',            'categoria' => 'comercial_ventas',         'importancia' => 3, 'gobernabilidad' => 4],
            ['titulo' => 'Registrar marca ante la SIC',                    'categoria' => 'legal_cumplimiento',       'importancia' => 2, 'gobernabilidad' => 3],
            ['titulo' => 'Optimizar procesos de onboarding',               'categoria' => 'gestion_operativa',        'importancia' => 3, 'gobernabilidad' => 2],
        ];

        foreach ($iniciativas1 as $data) {
            Iniciativa::create(array_merge($data, ['empresa_id' => $empresa1->id, 'cuadrante' => 1]));
        }

        app(IgoService::class)->recalcularTodosLosCuadrantes($empresa1->id);

        // Agregar un plan de acción a la primera iniciativa
        $primera = Iniciativa::where('empresa_id', $empresa1->id)->orderBy('cuadrante')->first();
        if ($primera) {
            PlanAccion::create([
                'iniciativa_id' => $primera->id,
                'deadline'      => now()->addDays(30)->toDateString(),
                'presupuesto'   => 5000000.00,
                'aliados'       => 'Proveedor DIAN, Contador externo',
                'estado'        => 'en_proceso',
                'notas'         => 'Prioridad máxima para cumplimiento fiscal.',
            ]);
        }

        // Empresa 2: Agro
        $empresa2 = Empresa::create([
            'user_id'           => $usuario->id,
            'nombre'            => 'Agrocampo del Norte',
            'sector'            => 'agro',
            'tamano'            => 'pequena',
            'genero_empresario' => 'mujer',
            'rango_edad'        => '36-45',
            'pais'              => 'Colombia',
            'ciudad'            => 'Bucaramanga',
            'activa'            => true,
        ]);

        $iniciativas2 = [
            ['titulo' => 'Certificación de buenas prácticas agrícolas',  'categoria' => 'legal_cumplimiento',   'importancia' => 5, 'gobernabilidad' => 3],
            ['titulo' => 'Sistema de riego automatizado',                 'categoria' => 'tecnologia_informacion', 'importancia' => 4, 'gobernabilidad' => 2],
            ['titulo' => 'Negociar contrato con cadena de supermercados', 'categoria' => 'comercial_ventas',      'importancia' => 5, 'gobernabilidad' => 4],
            ['titulo' => 'Capacitación en manejo de pesticidas',          'categoria' => 'talento_humano',        'importancia' => 3, 'gobernabilidad' => 5],
        ];

        foreach ($iniciativas2 as $data) {
            Iniciativa::create(array_merge($data, ['empresa_id' => $empresa2->id, 'cuadrante' => 1]));
        }

        app(IgoService::class)->recalcularTodosLosCuadrantes($empresa2->id);

        // Usuario invitado de prueba
        User::create([
            'tipo'           => 'invitado',
            'token_invitado' => 'test-guest-token-demo-12345',
            'consentimiento' => false,
        ]);
    }
}
