# IGO Manager — Endpoints de Analytics (Admin)

> **Base URL local:** `http://127.0.0.1:8000/api/v1`  
> **Base URL producción:** `https://igobackend-production.up.railway.app/api/v1`  
> **Autenticación:** todos los endpoints requieren `Authorization: Bearer <token>` de un usuario admin.  
> **Privacidad:** ninguna respuesta incluye `email` ni `user_id`.

---

## Tabla de endpoints

| # | Método | Path | Descripción | Caché |
|---|--------|------|-------------|-------|
| 1 | GET | `/admin/metricas/kpis` | KPIs globales + delta + sparkline | 5 min |
| 2 | GET | `/admin/metricas/usuarios` | Serie temporal de crecimiento | 1 h |
| 3 | GET | `/admin/metricas/demograficos` | Distribuciones demográficas | 1 h |
| 4 | GET | `/admin/metricas/palabras-clave` | Frecuencia de palabras en títulos | 1 h |
| 5 | GET | `/admin/metricas/matriz-agregada` | Matriz IGO agregada por segmento | 1 h |
| 6 | GET | `/admin/metricas/iniciativas` | Tabla paginada de iniciativas | 5 min |
| 7 | GET | `/admin/registros` | Tabla unificada con plan incluido | 5 min |
| 8 | GET | `/admin/registros/{id}` | Detalle de una iniciativa | 5 min |
| 9 | GET | `/admin/exportar` | Descarga CSV o Excel | — |

---

## Filtros comunes (query params opcionales)

Todos los endpoints marcados con ✦ aceptan estos parámetros. Si se omite un parámetro, no se aplica ese filtro.

| Parámetro | Tipo | Valores válidos | Aplica a |
|-----------|------|-----------------|----------|
| `sector` | string | `agro` `calzado_moda` `tecnologia` `servicios` `comercio` `salud` `turismo` `educacion` `manufactura` `otro` | empresas |
| `genero` | string | `hombre` `mujer` `otro` | empresas |
| `edad` | string | `18-25` `26-35` `36-45` `46-55` `56+` | empresas |
| `tamano` | string | `idea` `micro` `pequena` `mediana` `grande` | empresas |
| `ciudad` | string | texto libre | empresas |
| `pais` | string | texto libre | empresas |
| `cuadrante` | integer | `1` `2` `3` `4` | iniciativas |
| `categoria` | string | `gestion_operativa` `tecnologia_informacion` `gestion_financiera` `cadena_suministro` `talento_humano` `comercial_ventas` `legal_cumplimiento` `otro` | iniciativas |
| `periodo` | string | `last_30` `last_90` `last_180` `last_365` `all` | fecha creación |

**Filtros de paginación** (solo endpoints de tabla):

| Parámetro | Tipo | Default | Valores |
|-----------|------|---------|---------|
| `page` | integer | `1` | ≥ 1 |
| `per_page` | integer | `20` | 1–100 |
| `sort` | string | `fecha` | `fecha` `importancia` `gobernabilidad` `cuadrante` `sector` `titulo` `estado_plan` |
| `dir` | string | `desc` | `asc` `desc` |

---

## 1. GET `/admin/metricas/kpis`

**Descripción:** Cuatro KPIs globales (usuarios, empresas, iniciativas, informes) con delta porcentual respecto al mes anterior y sparkline de las últimas 7 semanas. Sin filtros — siempre sobre el universo completo.

**Ejemplo de respuesta:**
```json
{
  "success": true,
  "data": {
    "usuarios": {
      "total": 30,
      "delta_pct": 100.0,
      "sparkline": [0, 0, 0, 0, 0, 0, 30]
    },
    "empresas": {
      "total": 12,
      "delta_pct": 100.0,
      "sparkline": [0, 0, 0, 0, 0, 0, 12]
    },
    "iniciativas": {
      "total": 24,
      "delta_pct": 100.0,
      "sparkline": [0, 0, 0, 0, 0, 0, 24]
    },
    "informes": {
      "total": 4,
      "delta_pct": 100.0,
      "sparkline": [0, 0, 0, 0, 0, 0, 4]
    }
  },
  "message": "KPIs obtenidos correctamente."
}
```

**Nota:** `delta_pct` es `null` cuando no hay datos del mes anterior y no hay datos este mes. `100.0` indica que todos los registros son nuevos (primer mes con datos).

---

## 2. GET `/admin/metricas/usuarios`

**Parámetros propios:**

| Parámetro | Tipo | Default | Valores |
|-----------|------|---------|---------|
| `periodo` | string | `mensual` | `diario` `semanal` `mensual` |

**Descripción:** Serie temporal combinada de crecimiento de usuarios, empresas e iniciativas. Formato array ordenado descendente (últimos 12 períodos).

**Ejemplo de respuesta:**
```json
{
  "success": true,
  "data": {
    "totales": {
      "registrados": 30,
      "invitados": 2
    },
    "serie": [
      { "fecha": "2026-05", "usuarios": 30, "empresas": 12, "iniciativas": 24 },
      { "fecha": "2026-04", "usuarios": 0,  "empresas": 0,  "iniciativas": 0  }
    ]
  },
  "message": "Métricas de crecimiento obtenidas correctamente."
}
```

---

## 3. GET `/admin/metricas/demograficos` ✦

**Filtros aceptados:** todos excepto `cuadrante` y `categoria` (se aplican via `whereHas` sobre las iniciativas de la empresa).

**Descripción:** Distribuciones de empresas por sector, tamaño, género, rango de edad y país. Si se combinan filtros, todas las distribuciones reflejan el segmento filtrado.

**Ejemplo:** `?sector=tecnologia&genero=mujer`

**Ejemplo de respuesta:**
```json
{
  "success": true,
  "data": {
    "por_sector":  { "comercio": 2, "educacion": 2, "tecnologia": 1 },
    "por_tamano":  { "micro": 5, "pequena": 4, "mediana": 2, "grande": 1 },
    "por_genero":  { "hombre": 7, "mujer": 4, "otro": 1 },
    "por_edad":    { "26-35": 5, "36-45": 4, "18-25": 2, "46-55": 1 },
    "por_pais":    { "Colombia": 10, "Mexico": 2 }
  },
  "message": "Datos demográficos obtenidos correctamente."
}
```

---

## 4. GET `/admin/metricas/palabras-clave` ✦

**Filtros aceptados:** todos, incluyendo `cuadrante` (ya **no** está hardcodeado a 1).

**Descripción:** Top 50 palabras más frecuentes en títulos de iniciativas del segmento filtrado. Si no se pasa `cuadrante`, analiza todos los cuadrantes.

**Ejemplo:** `?sector=comercio&cuadrante=1`

**Ejemplo de respuesta:**
```json
{
  "success": true,
  "data": {
    "frecuencias": {
      "ventas": 8,
      "digital": 5,
      "flujo": 4,
      "cliente": 3
    }
  },
  "message": "Palabras clave obtenidas correctamente."
}
```

---

## 5. GET `/admin/metricas/matriz-agregada` ✦

**Filtros aceptados:** todos.

**Descripción:** Promedios globales de importancia/gobernabilidad, distribución por cuadrante y distribución sector × cuadrante. El `promedio_global` sirve como las asíntotas del segmento filtrado.

**Ejemplo:** `?sector=tecnologia`

**Ejemplo de respuesta:**
```json
{
  "success": true,
  "data": {
    "promedio_global": {
      "importancia": 3.42,
      "gobernabilidad": 2.87,
      "total": 24
    },
    "distribucion_por_cuadrante": {
      "1": { "total": 10, "avg_importancia": 4.2, "avg_gobernabilidad": 3.8 },
      "2": { "total": 6,  "avg_importancia": 4.0, "avg_gobernabilidad": 2.1 },
      "3": { "total": 4,  "avg_importancia": 2.5, "avg_gobernabilidad": 3.5 },
      "4": { "total": 4,  "avg_importancia": 2.0, "avg_gobernabilidad": 1.8 }
    },
    "distribucion_sector_cuadrante": [
      { "sector": "comercio",   "cuadrante": 1, "total": 3 },
      { "sector": "tecnologia", "cuadrante": 1, "total": 2 }
    ]
  },
  "message": "Matriz IGO agregada obtenida correctamente."
}
```

---

## 6. GET `/admin/metricas/iniciativas` ✦

**Filtros aceptados:** todos + paginación.

**Descripción:** Tabla paginada de iniciativas con datos de su empresa. Útil para gráficas de dispersión IGO con filtros. `empresa_nombre` se incluye (no es dato sensible). Sin email ni user_id.

**Ejemplo:** `?sector=comercio&cuadrante=1&sort=importancia&dir=desc`

**Ejemplo de respuesta:**
```json
{
  "success": true,
  "data": {
    "rows": [
      {
        "id": 17,
        "fecha": "2026-05-26",
        "empresa_nombre": "Tienda Central",
        "ciudad": "Bogotá",
        "sector": "comercio",
        "categoria": "comercial_ventas",
        "importancia": 5,
        "gobernabilidad": 3,
        "cuadrante": 1
      }
    ],
    "meta": {
      "total": 3,
      "page": 1,
      "per_page": 20,
      "last_page": 1
    }
  },
  "message": "Iniciativas obtenidas correctamente."
}
```

---

## 7. GET `/admin/registros` ✦

**Filtros aceptados:** todos + paginación.

**Descripción:** Tabla unificada que incluye el título de la iniciativa y el estado del plan de acción (LEFT JOIN). Ideal para la tabla de registros históricos detallados del dashboard. Sin email ni user_id.

**Campos `sort` adicionales:** `titulo`, `estado_plan`.

**Ejemplo:** `?sector=comercio&genero=mujer&cuadrante=1&sort=titulo&dir=asc`

**Ejemplo de respuesta:**
```json
{
  "success": true,
  "data": {
    "rows": [
      {
        "id": 25,
        "fecha_creacion": "2026-05-27",
        "empresa_nombre": "moda s.a.s",
        "sector": "calzado_moda",
        "tamano": "grande",
        "ciudad": "Medellín",
        "pais": "Colombia",
        "genero_empresario": "mujer",
        "rango_edad": "36-45",
        "titulo": "Coordinar la campaña de marketing",
        "categoria": "comercial_ventas",
        "importancia": 3,
        "gobernabilidad": 2,
        "cuadrante": 4,
        "tiene_plan": false,
        "estado_plan": null
      }
    ],
    "meta": {
      "total": 24,
      "page": 1,
      "per_page": 20,
      "last_page": 2
    }
  },
  "message": "Registros obtenidos correctamente."
}
```

---

## 8. GET `/admin/registros/{iniciativa_id}`

**Sin filtros.** Devuelve el detalle completo de una iniciativa específica, su empresa y su plan de acción (si existe). Sin email ni user_id.

**Ejemplo:** `GET /api/v1/admin/registros/25`

**Ejemplo de respuesta (con plan):**
```json
{
  "success": true,
  "data": {
    "iniciativa": {
      "id": 25,
      "titulo": "Coordinar la campaña de marketing",
      "categoria": "comercial_ventas",
      "importancia": 3,
      "gobernabilidad": 2,
      "cuadrante": 4,
      "fecha_creacion": "2026-05-27",
      "fecha_actualizacion": "2026-05-27"
    },
    "empresa": {
      "id": 8,
      "nombre": "moda s.a.s",
      "sector": "calzado_moda",
      "tamano": "grande",
      "ciudad": "Medellín",
      "pais": "Colombia",
      "genero_empresario": "mujer",
      "rango_edad": "36-45"
    },
    "plan": {
      "estado": "en_proceso",
      "presupuesto": "5000000.00",
      "aliados": "Agencia digital XYZ",
      "notas": "Campaña trimestral Q3",
      "deadline": "2026-08-31"
    }
  },
  "message": "Detalle de registro obtenido correctamente."
}
```

**Cuando no tiene plan:** `"plan": null`

**Error 404:**
```json
{
  "success": false,
  "message": "Iniciativa no encontrada.",
  "errors": []
}
```

---

## 9. GET `/admin/exportar`

**Parámetros propios:**

| Parámetro | Tipo | Default | Valores |
|-----------|------|---------|---------|
| `formato` | string | `csv` | `csv` `excel` |

**Filtros aceptados:** todos excepto paginación (`page`, `per_page`, `sort`, `dir` se ignoran).

**Descripción:** Descarga el segmento filtrado como archivo. `formato=csv` devuelve `text/csv`; `formato=excel` devuelve un `.xlsx` con estilos.

**Columnas exportadas:** `id`, `categoria`, `importancia`, `gobernabilidad`, `cuadrante`, `sector`, `tamano`, `genero_empresario`, `rango_edad`, `pais`, `ciudad`, `fecha`.

**Ejemplo CSV:** `GET /api/v1/admin/exportar?formato=csv&sector=comercio`  
**Ejemplo Excel:** `GET /api/v1/admin/exportar?formato=excel&periodo=last_90`

**Respuesta:** archivo descargable (no JSON).

---

## Invalidación de caché

El caché de analytics se invalida automáticamente cada vez que se crea, edita o elimina una iniciativa. El mecanismo usa un contador `admin:cache:bust` en el driver de caché (file). Los TTLs son:

- Endpoints pesados (demográficos, palabras-clave, matriz, usuarios): **1 hora**
- Endpoints de tabla (kpis, iniciativas, registros): **5 minutos**
- Exportación (CSV/Excel): **sin caché** (generado en tiempo real)

---

## Seguridad

- Todos los endpoints requieren `AdminMiddleware` (verifica `email == ADMIN_EMAIL`).
- **Ninguna respuesta incluye `email` ni `user_id`** — verificado en pruebas automatizadas.
- `empresa_nombre` sí se incluye (nombre comercial, no dato personal).
