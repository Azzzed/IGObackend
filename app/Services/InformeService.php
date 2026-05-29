<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Informe;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class InformeService
{
    public function __construct(private readonly IgoService $igoService) {}

    /* ─────────────────────────────────────────────
     |  Punto de entrada principal
     ───────────────────────────────────────────── */
    public function generarInforme(Empresa $empresa): Informe
    {
        Log::info('Iniciando generación de informe', ['empresa_id' => $empresa->id]);

        $iniciativas = $empresa->iniciativas()
            ->orderBy('cuadrante')
            ->orderByDesc('importancia')
            ->get();

        Log::info('Iniciativas encontradas', ['count' => $iniciativas->count()]);

        $asintotas = $this->igoService->calcularAsintotas($empresa->id);

        Log::info('Asíntotas calculadas', $asintotas);

        $prompt    = $this->construirPrompt($empresa, $iniciativas, $asintotas);

        Log::info('Llamando a Groq API...');

        // Intento 1 — temperature 0.4
        $rawContent = $this->llamarGroq($prompt, 0.4);
        $contenido  = $this->parsearJson($rawContent);

        // Intento 2 — JSON malformado: reintenta con temperature 0.2
        if ($contenido === null) {
            $rawContent = $this->llamarGroq($prompt, 0.2);
            $contenido  = $this->parsearJson($rawContent);

            if ($contenido === null) {
                throw new RuntimeException(
                    'La IA devolvió JSON inválido en dos intentos consecutivos.'
                );
            }
        }

        // Guarda o actualiza el informe de la empresa (1:1 por empresa)
        return Informe::updateOrCreate(
            ['empresa_id' => $empresa->id],
            [
                'contenido_json' => $contenido,
                'asintotas_json' => $asintotas,
            ]
        );
    }

    /* ─────────────────────────────────────────────
     |  Llamada a Groq API
     ───────────────────────────────────────────── */
    private function llamarGroq(string $prompt, float $temperature): string
    {
        $url = 'https://api.groq.com/openai/v1/chat/completions';
        $key = config('services.groq.key');

        Log::info('llamarGroq', [
            'url'         => $url,
            'temperature' => $temperature,
            'key_present' => !empty($key),
            'key_prefix'  => $key ? substr($key, 0, 10) . '...' : 'NULL',
        ]);

        $response = Http::timeout(45)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ])
            ->post($url, [
                'model'       => config('services.groq.model', 'llama-3.3-70b-versatile'),
                'temperature' => $temperature,
                'max_tokens'  => 3000,
                'messages'    => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user',   'content' => $prompt],
                ],
            ]);

        Log::info('Respuesta Groq', [
            'status'       => $response->status(),
            'raw_excerpt'  => substr($response->body(), 0, 500),
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Error al comunicarse con Groq API: HTTP ' . $response->status()
                . ' — ' . substr($response->body(), 0, 300)
            );
        }

        return $response->json('choices.0.message.content') ?? '';
    }

    /* ─────────────────────────────────────────────
     |  Parseo de JSON — limpia posibles code fences
     ───────────────────────────────────────────── */
    private function parsearJson(string $raw): ?array
    {
        $cleaned = preg_replace('/^```json\s*|\s*```$/s', '', trim($raw));
        $data    = json_decode($cleaned, true);

        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }

    /* ─────────────────────────────────────────────
     |  Prompt de usuario construido dinámicamente
     ───────────────────────────────────────────── */
    private function construirPrompt(Empresa $empresa, $iniciativas, array $asintotas): string
    {
        $hoy = now()->format('Y-m-d');

        $listaIniciativas = $iniciativas->map(function ($ini) {
            return "- ID {$ini->id}: \"{$ini->titulo}\" | Categoría: {$ini->categoria} | Imp: {$ini->importancia} | Gob: {$ini->gobernabilidad} | Cuadrante: {$ini->cuadrante}";
        })->join("\n");

        return <<<PROMPT
Fecha de hoy: {$hoy}
Empresa: {$empresa->nombre}
Sector: {$empresa->sector}
Tamaño: {$empresa->tamano}
Ciudad: {$empresa->ciudad}

Asíntota importancia (promedio): {$asintotas['importancia']}
Asíntota gobernabilidad (promedio): {$asintotas['gobernabilidad']}

Iniciativas evaluadas:
{$listaIniciativas}

Genera el informe ejecutivo IGO para esta empresa. Usa la fecha de hoy como punto de partida para calcular todas las fechas del campo "plazo".
PROMPT;
    }

    /* ─────────────────────────────────────────────
     |  System prompt — motor de análisis IGO
     ───────────────────────────────────────────── */
    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Eres el motor de análisis estratégico de IGO Manager, una herramienta
de consultoría empresarial. Tu trabajo es analizar las iniciativas de
una empresa usando la metodología IGO (Importancia vs Gobernabilidad)
y generar un informe ejecutivo claro, directo y accionable.

TONO:
- Habla de tú al empresario. Coloquial pero profesional.
- No uses frases genéricas tipo "es fundamental" o "se recomienda".
- Sé directo: "Arranca esta semana con X porque tienes todo para hacerlo".
- El informe debe sentirse como un consultor que conoce el negocio,
  no como un texto genérico de IA.
- Adapta el lenguaje al sector de la empresa.

REGLAS DE NEGOCIO IGO:
- Las asíntotas (puntos de corte) son los promedios de cada eje,
  no valores fijos. Vienen en el contexto del prompt.
- Cuadrante 1 (Alta Imp / Alta Gob): HACER YA — tiene impacto y
  capacidad de ejecutar ahora mismo.
- Cuadrante 2 (Alta Imp / Baja Gob): ESTRATÉGICO — muy importante
  pero necesita aliados, recursos o capacitación primero.
- Cuadrante 3 (Baja Imp / Alta Gob): RUTINA — puede ejecutarse
  pero no es urgente. Delegar o sistematizar.
- Cuadrante 4 (Baja Imp / Baja Gob): DESCARTAR — no vale la pena
  invertir tiempo ni dinero ahora.
- La Importancia tiene prioridad sobre la Gobernabilidad.

CHECKLIST:
- Cada iniciativa tiene entre 2 y 5 acciones concretas.
- Cada acción tiene un título (qué hacer) y una descripción
  (cómo hacerlo, específico al sector de la empresa).
- Las acciones del cuadrante 4 solo tienen 1 acción: no hacer nada.
- El lenguaje de las acciones es activo: "Abre", "Calcula", "Define",
  "Cotiza", "Designa" — verbos en imperativo.

PLAZOS (campo "plazo" de cada iniciativa):
El plazo se calcula combinando el cuadrante y la complejidad de las acciones.
Usa la "Fecha de hoy" que viene en el prompt del usuario como punto de partida.

Ventanas base por cuadrante:
- Cuadrante 1 (Hacer ya):    fecha_inicio = hoy,            fecha_fin = hoy + 3 a 7 días
- Cuadrante 2 (Estratégico): fecha_inicio = hoy + 7 días,  fecha_fin = hoy + 14 a 28 días
- Cuadrante 3 (Rutina):      fecha_inicio = hoy + 28 días, fecha_fin = hoy + 30 a 60 días
- Cuadrante 4 (Descartar):   plazo = null (no se asigna fecha)

Ajuste por complejidad dentro de la ventana:
- Pocas acciones (2-3) o simples → usa el extremo corto de la ventana
- Muchas acciones (4-5) o complejas → usa el extremo largo de la ventana
- Evalúa la complejidad según las acciones que tú mismo generaste

Valores para descripcion_plazo:
- Cuadrante 1 simple:   "Urgente – esta semana"
- Cuadrante 1 complejo: "Urgente – esta semana, complejidad alta"
- Cuadrante 2 simple:   "Corto plazo – 2 semanas"
- Cuadrante 2 complejo: "Corto plazo – 4 semanas"
- Cuadrante 3 simple:   "Medio plazo – 1 mes"
- Cuadrante 3 complejo: "Medio plazo – 2 meses"
- Cuadrante 4:          plazo es null

FORMATO DE RESPUESTA:
Responde ÚNICAMENTE con JSON válido. Sin texto adicional,
sin markdown, sin explicaciones fuera del JSON.

{
  "empresa": "Nombre de la empresa",
  "sector": "sector_economico",
  "fecha": "DD mes YYYY",
  "resumen": "2-3 oraciones coloquiales sobre el estado general del negocio.",
  "asintotas": {
    "importancia": 3.0,
    "gobernabilidad": 3.0
  },
  "totales": {
    "evaluadas": 5,
    "hacer_ya": 2,
    "estrategico": 1,
    "rutina": 1,
    "descartar": 1
  },
  "iniciativas": [
    {
      "id": 1,
      "titulo": "Nombre de la iniciativa",
      "categoria": "gestion_financiera",
      "importancia": 4,
      "gobernabilidad": 5,
      "cuadrante": 1,
      "etiqueta": "Hacer ya",
      "plazo": {
        "fecha_inicio": "YYYY-MM-DD",
        "fecha_fin": "YYYY-MM-DD",
        "duracion_dias": 5,
        "descripcion_plazo": "Urgente – esta semana"
      },
      "acciones": [
        {
          "titulo": "Título concreto en imperativo",
          "descripcion": "Descripción específica al sector, 1-2 oraciones directas."
        }
      ]
    },
    {
      "id": 2,
      "titulo": "Iniciativa de cuadrante 4 (ejemplo)",
      "categoria": "otro",
      "importancia": 2,
      "gobernabilidad": 1,
      "cuadrante": 4,
      "etiqueta": "Descartar",
      "plazo": null,
      "acciones": [
        {
          "titulo": "No actuar ahora",
          "descripcion": "Esta iniciativa no justifica inversión de tiempo ni dinero en este momento."
        }
      ]
    }
  ]
}

ORDEN de las iniciativas en el JSON:
1. Primero las del cuadrante 1 (ordenadas por importancia desc)
2. Luego cuadrante 2
3. Luego cuadrante 3
4. Al final cuadrante 4
PROMPT;
    }
}
