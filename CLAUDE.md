# IGO Manager — Master Prompt para Claude Code (Backend Laravel)

## Contexto del proyecto

Estás desarrollando el backend de **IGO Manager**, una aplicación web para empresarios y emprendedores que digitaliza la metodología de consultoría IGO (Importancia vs. Gobernabilidad). El backend es una **REST API pura en Laravel** — nunca genera HTML, nunca usa Blade, solo responde JSON.

El frontend (React) se desarrolla por separado y consume esta API. El documento de requerimientos funcionales completo se adjunta en este proyecto.

---

## Stack técnico

- **Framework:** Laravel (versión instalada en el proyecto)
- **Base de datos:** MySQL
- **Autenticación:** Laravel Sanctum (tokens Bearer)
- **IA / Reportes:** Groq API (llamadas HTTP desde Laravel)
- **PDF:** Generado desde el frontend (no es responsabilidad del backend)
- **Hosting destino:** Hostinger Shared Hosting (PHP)

---

## Reglas absolutas — NUNCA las rompas

1. **Esta es una API REST pura.** Nunca uses Blade, nunca retornes HTML, nunca uses `return view()`. Todo es `return response()->json()`.
2. **Todos los endpoints van bajo el prefijo `/api/v1/`.**
3. **Nunca hagas lógica de negocio en los controladores.** Usa Services o Actions para eso.
4. **Siempre valida los requests con Form Requests de Laravel.**
5. **Nunca expongas datos del usuario administrador en respuestas públicas.**
6. **Los datos del panel admin siempre deben ser anónimos** — ninguna consulta devuelve información que identifique a un usuario individual.
7. **CORS debe estar configurado desde el inicio** con el origen del frontend (`http://localhost:5173` en desarrollo).
8. **Toda respuesta exitosa sigue esta estructura:**
```json
{
  "success": true,
  "data": {},
  "message": "Mensaje descriptivo"
}
```
9. **Toda respuesta de error sigue esta estructura:**
```json
{
  "success": false,
  "message": "Descripción del error",
  "errors": {}
}
```
10. **Usa soft deletes** en todas las tablas principales (usuarios, empresas, iniciativas, planes).

---

## Arquitectura de carpetas

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── V1/
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── EmpresaController.php
│   │   │   │   ├── IniciativaController.php
│   │   │   │   ├── PlanAccionController.php
│   │   │   │   ├── InformeController.php
│   │   │   │   └── Admin/
│   │   │   │       └── MetricasController.php
│   ├── Requests/
│   │   ├── Auth/
│   │   ├── Empresa/
│   │   ├── Iniciativa/
│   │   └── PlanAccion/
│   ├── Resources/
│   │   ├── UserResource.php
│   │   ├── EmpresaResource.php
│   │   ├── IniciativaResource.php
│   │   └── PlanAccionResource.php
│   └── Middleware/
│       └── AdminMiddleware.php
├── Services/
│   ├── IgoService.php          ← lógica de cálculo IGO y asíntotas
│   ├── InformeService.php      ← comunicación con Groq API
│   └── MetricasService.php     ← consultas anonimizadas para admin
├── Models/
│   ├── User.php
│   ├── Empresa.php
│   ├── Iniciativa.php
│   ├── PlanAccion.php
│   └── Informe.php
database/
├── migrations/
└── seeders/
```

---

## Base de datos — Estructura completa

### Tabla: users
```
id                  → bigint, PK
tipo                → enum('invitado', 'registrado') default 'registrado'
nombre              → string, nullable
email               → string, unique, nullable (null para invitados)
password            → string, nullable (null para invitados)
token_invitado      → string, unique, nullable (identificador de sesión invitado)
consentimiento      → boolean, default false
fecha_consentimiento→ timestamp, nullable
version_politica    → string, nullable
remember_token      → string, nullable
created_at / updated_at / deleted_at
```

### Tabla: empresas
```
id
user_id             → FK users
nombre              → string
sector              → enum('agro','calzado_moda','tecnologia','servicios',
                          'comercio','salud','turismo','educacion',
                          'manufactura','otro')
tamano              → enum('idea','micro','pequena','mediana','grande')
genero_empresario   → enum('hombre','mujer','otro')
rango_edad          → enum('18-25','26-35','36-45','46-55','56+')
pais                → string
ciudad              → string
activa              → boolean, default true
created_at / updated_at / deleted_at
```

### Tabla: iniciativas
```
id
empresa_id          → FK empresas
titulo              → string
categoria           → enum('gestion_operativa','tecnologia_informacion',
                          'gestion_financiera','cadena_suministro',
                          'talento_humano','comercial_ventas',
                          'legal_cumplimiento','otro')
importancia         → tinyint (1-5)
gobernabilidad      → tinyint (1-5)
cuadrante           → tinyint (1-4) ← calculado automáticamente
created_at / updated_at / deleted_at
```

### Tabla: planes_accion
```
id
iniciativa_id       → FK iniciativas
deadline            → date, nullable
presupuesto         → decimal(12,2), nullable
aliados             → text, nullable
estado              → enum('pendiente','en_proceso','terminado','abortado')
                      default 'pendiente'
notas               → text, nullable
created_at / updated_at / deleted_at
```

### Tabla: informes
```
id
empresa_id          → FK empresas
contenido_json      → json  ← respuesta completa de Groq
asintotas_json      → json  ← { imp: x, gob: y } al momento de generar
created_at / updated_at
```

---

## Lógica IGO — IgoService.php

Esta es la lógica central del sistema. Implementa exactamente esto:

### Cálculo de asíntotas dinámicas
```php
// Las asíntotas se calculan como el PROMEDIO de todas las calificaciones
// de cada eje para las iniciativas de una empresa específica

public function calcularAsintotas(int $empresaId): array
{
    $iniciativas = Iniciativa::where('empresa_id', $empresaId)
        ->whereNull('deleted_at')
        ->get();

    if ($iniciativas->isEmpty()) {
        return ['importancia' => 3, 'gobernabilidad' => 3]; // default
    }

    return [
        'importancia'    => round($iniciativas->avg('importancia'), 2),
        'gobernabilidad' => round($iniciativas->avg('gobernabilidad'), 2),
    ];
}
```

### Clasificación por cuadrante
```php
// REGLA: Importancia tiene prioridad sobre Gobernabilidad
// El cuadrante se determina comparando contra las asíntotas (no valores fijos)

public function calcularCuadrante(
    float $importancia,
    float $gobernabilidad,
    float $asintotaImp,
    float $asintotaGob
): int {
    $altaImp = $importancia >= $asintotaImp;
    $altaGob = $gobernabilidad >= $asintotaGob;

    return match(true) {
        $altaImp && $altaGob   => 1, // ¡Hacer ya!
        $altaImp && !$altaGob  => 2, // Estratégico — mejorar gobernabilidad
        !$altaImp && $altaGob  => 3, // Rutina — delegar
        default                => 4, // Descartar
    };
}
```

### Recalcular cuadrantes de toda la empresa
```php
// Llamar esto SIEMPRE que se crea o edita una iniciativa
// porque las asíntotas cambian y todos los cuadrantes se recalculan

public function recalcularTodosLosCuadrantes(int $empresaId): void
{
    $asintotas = $this->calcularAsintotas($empresaId);
    $iniciativas = Iniciativa::where('empresa_id', $empresaId)->get();

    foreach ($iniciativas as $iniciativa) {
        $iniciativa->cuadrante = $this->calcularCuadrante(
            $iniciativa->importancia,
            $iniciativa->gobernabilidad,
            $asintotas['importancia'],
            $asintotas['gobernabilidad']
        );
        $iniciativa->save();
    }
}
```

---

## Endpoints requeridos

### Autenticación — AuthController
```
POST   /api/v1/auth/registro          → crear cuenta (registrado)
POST   /api/v1/auth/login             → login con email/password
POST   /api/v1/auth/logout            → invalidar token (auth requerida)
GET    /api/v1/auth/me                → usuario autenticado (auth requerida)
POST   /api/v1/auth/invitado          → crear sesión de invitado
POST   /api/v1/auth/invitado/migrar   → migrar invitado a cuenta registrada
```

### Empresas — EmpresaController
```
GET    /api/v1/empresas               → listar empresas del usuario autenticado
POST   /api/v1/empresas               → crear empresa
GET    /api/v1/empresas/{id}          → detalle de empresa
PUT    /api/v1/empresas/{id}          → editar empresa
DELETE /api/v1/empresas/{id}          → eliminar empresa (soft delete)
```

### Iniciativas — IniciativaController
```
GET    /api/v1/empresas/{id}/iniciativas      → listar iniciativas de una empresa
POST   /api/v1/empresas/{id}/iniciativas      → crear iniciativa (recalcula asíntotas)
GET    /api/v1/iniciativas/{id}               → detalle de iniciativa
PUT    /api/v1/iniciativas/{id}               → editar iniciativa (recalcula asíntotas)
DELETE /api/v1/iniciativas/{id}               → eliminar (soft delete, recalcula asíntotas)
GET    /api/v1/empresas/{id}/matriz           → matriz completa con asíntotas y cuadrantes
```

### Informe IA — InformeController
```
POST   /api/v1/empresas/{id}/informe          → generar informe con Groq API
GET    /api/v1/empresas/{id}/informe/ultimo   → último informe generado
```

### Planes de acción — PlanAccionController
```
POST   /api/v1/iniciativas/{id}/plan          → crear plan de acción
GET    /api/v1/iniciativas/{id}/plan          → ver plan de acción
PUT    /api/v1/planes/{id}                    → editar plan
DELETE /api/v1/planes/{id}                    → eliminar plan
```

### Admin — MetricasController (rol admin únicamente)
```
GET    /api/v1/admin/metricas/usuarios        → total usuarios por período
GET    /api/v1/admin/metricas/demograficos    → distribución por sector, tamaño, género, edad
GET    /api/v1/admin/metricas/palabras-clave  → títulos frecuentes en cuadrante 1 (anónimo)
GET    /api/v1/admin/metricas/matriz-agregada → promedio IGO de todos los usuarios
GET    /api/v1/admin/exportar                 → exportar datos en CSV/Excel
```

---

## InformeService — Integración con Groq API

```php
public function generarInforme(Empresa $empresa): array
{
    $iniciativas = Iniciativa::where('empresa_id', $empresa->id)
        ->orderBy('cuadrante')
        ->orderByDesc('importancia')
        ->get();

    $asintotas = app(IgoService::class)->calcularAsintotas($empresa->id);

    $prompt = $this->construirPrompt($empresa, $iniciativas, $asintotas);

    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . config('services.groq.key'),
        'Content-Type'  => 'application/json',
    ])->post('https://api.groq.com/openai/v1/chat/completions', [
        'model'       => 'llama3-8b-8192',
        'temperature' => 0.4,
        'max_tokens'  => 2000,
        'messages'    => [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user',   'content' => $prompt],
        ],
    ]);

    return json_decode($response->json('choices.0.message.content'), true);
}

private function systemPrompt(): string
{
    return <<<PROMPT
    Eres un consultor empresarial experto en la metodología IGO (Importancia vs Gobernabilidad).
    Recibes las iniciativas de una empresa clasificadas por cuadrante IGO y generas un informe
    ejecutivo con checklist de acciones concretas adaptadas al sector de la empresa.

    REGLAS:
    - Responde ÚNICAMENTE en JSON válido, sin texto adicional ni markdown.
    - Para cada iniciativa genera entre 3 y 6 acciones concretas.
    - Cada acción tiene: "titulo" (qué hacer) y "descripcion" (cómo hacerlo, específico al sector).
    - Adapta el lenguaje y las recomendaciones al sector de la empresa.
    - Ordena las iniciativas por cuadrante (1 primero, 4 último).
    - El tono es profesional pero directo, sin rodeos.

    FORMATO DE RESPUESTA:
    {
      "resumen": "2-3 oraciones sobre el estado general de la empresa",
      "asintotas": { "importancia": X, "gobernabilidad": X },
      "iniciativas": [
        {
          "id": 1,
          "titulo": "Nombre de la iniciativa",
          "cuadrante": 1,
          "etiqueta": "¡Hacer ya!",
          "categoria": "gestion_financiera",
          "importancia": 4,
          "gobernabilidad": 5,
          "acciones": [
            {
              "titulo": "Título concreto de la acción",
              "descripcion": "Descripción específica de cómo ejecutarla en el sector X"
            }
          ]
        }
      ]
    }
    PROMPT;
}
```

---

## Manejo de usuarios invitados

```php
// Un invitado se identifica por un token_invitado único (UUID)
// Se guarda en la BD igual que un usuario registrado
// Al migrar, se transfieren TODAS sus empresas e iniciativas

public function migrarInvitado(Request $request): JsonResponse
{
    $invitado = User::where('token_invitado', $request->token_invitado)
        ->where('tipo', 'invitado')
        ->firstOrFail();

    // Crear cuenta registrada
    $registrado = User::create([
        'tipo'     => 'registrado',
        'nombre'   => $request->nombre,
        'email'    => $request->email,
        'password' => Hash::make($request->password),
        'consentimiento' => true,
        'fecha_consentimiento' => now(),
    ]);

    // Transferir todas las empresas del invitado
    Empresa::where('user_id', $invitado->id)
        ->update(['user_id' => $registrado->id]);

    // Eliminar el invitado
    $invitado->delete();

    $token = $registrado->createToken('auth_token')->plainTextToken;

    return response()->json([
        'success' => true,
        'data'    => ['token' => $token, 'user' => new UserResource($registrado)],
        'message' => 'Cuenta creada exitosamente. Tus datos han sido transferidos.',
    ]);
}
```

---

## Configuración de CORS

En `config/cors.php`:
```php
'paths' => ['api/*'],
'allowed_methods' => ['*'],
'allowed_origins' => [
    'http://localhost:5173',   // React en desarrollo
    'http://localhost:3000',   // alternativo
    'https://app.tudominio.com' // producción (cambiar cuando se tenga)
],
'allowed_headers' => ['*'],
'exposed_headers' => [],
'max_age' => 0,
'supports_credentials' => true,
```

---

## Variables de entorno requeridas (.env)

```env
APP_NAME=IGOManager
APP_ENV=local
APP_URL=http://igo-backend.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=igo_manager
DB_USERNAME=root
DB_PASSWORD=

GROQ_API_KEY=tu_api_key_aqui
GROQ_MODEL=llama3-8b-8192

SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:3000

FRONTEND_URL=http://localhost:5173
```

En `config/services.php` agregar:
```php
'groq' => [
    'key'   => env('GROQ_API_KEY'),
    'model' => env('GROQ_MODEL', 'llama3-8b-8192'),
],
```

---

## Orden de desarrollo — sigue este orden estrictamente

```
FASE 1 — Base
□ 1. Migraciones de todas las tablas
□ 2. Modelos con relaciones y fillable
□ 3. Seeders (usuario admin, datos de prueba)
□ 4. Configurar Sanctum + CORS
□ 5. Configurar rutas en routes/api.php

FASE 2 — Autenticación
□ 6. AuthController completo (registro, login, logout, me)
□ 7. Manejo de usuarios invitados
□ 8. Migración de invitado a registrado
□ 9. AdminMiddleware (verificar rol admin)

FASE 3 — Lógica de negocio
□ 10. IgoService (asíntotas + cuadrantes)
□ 11. EmpresaController CRUD
□ 12. IniciativaController CRUD + recálculo automático
□ 13. Endpoint de matriz completa con asíntotas

FASE 4 — IA y reportes
□ 14. InformeService (integración Groq API)
□ 15. InformeController

FASE 5 — Planes y admin
□ 16. PlanAccionController CRUD
□ 17. MetricasController (panel admin, datos anónimos)
□ 18. Endpoint de exportación CSV/Excel

FASE 6 — Calidad
□ 19. Revisar que NINGÚN endpoint expone datos sensibles en el admin
□ 20. Probar todos los endpoints con Thunder Client
□ 21. Documentar colección de Postman/Thunder Client
```

---

## Notas finales para Claude Code

- Cuando crees un endpoint, crea también su Form Request de validación.
- Cuando crees un modelo, define siempre: `$fillable`, `$casts`, relaciones y `SoftDeletes`.
- Cuando modifiques la lógica IGO, recuerda que las asíntotas cambian con cada iniciativa nueva o editada — siempre llama `recalcularTodosLosCuadrantes()`.
- Nunca hardcodees el valor 3 o 2.5 como punto de corte — siempre usa el promedio calculado.
- El campo `cuadrante` en la tabla `iniciativas` se guarda en BD pero siempre se recalcula al modificar datos.
- Los datos del panel admin **nunca** incluyen `user_id`, `email`, ni ningún campo que identifique un usuario. Solo agregados estadísticos.
