# IGO Manager — Notificaciones Push (Web Push + VAPID)

Infraestructura backend para enviar recordatorios push web a los usuarios:

- **Diario** (07:00 hora Colombia): las acciones que tocan ese día.
- **Semanal** (lunes 08:00 hora Colombia): resumen de iniciativas de la semana.

El frontend (React + service worker en Vercel) consume estos endpoints. Este
documento cubre solo el **backend**.

---

## 1. Variables de entorno

Agrega estas variables a `.env` (local) y al panel de **Railway** (producción):

| Variable | Descripción | Cómo obtenerla |
|----------|-------------|----------------|
| `VAPID_PUBLIC_KEY` | Clave pública VAPID. Segura de exponer; el frontend la usa para suscribir. | `php artisan push:generate-vapid` |
| `VAPID_PRIVATE_KEY` | Clave privada VAPID. **Secreta** — nunca subir a git. | mismo comando |
| `VAPID_SUBJECT` | Contacto del emisor. | `mailto:admin@igomanager.com` |
| `CRON_SECRET` | Token aleatorio que protege los endpoints de cron. | `php -r "echo bin2hex(random_bytes(32));"` (64 hex) |

> **Importante:** si regeneras las claves VAPID, todas las suscripciones
> existentes dejan de funcionar y los usuarios deben volver a suscribirse.

### Generar las claves

```bash
php artisan push:generate-vapid
```

Imprime las 3 variables `VAPID_*` listas para copiar. (En Windows/Laragon, si
falla con *"Unable to create the key"*, exporta primero
`OPENSSL_CONF=<ruta>\extras\ssl\openssl.cnf`. En Linux/Railway no hace falta.)

---

## 2. Extensiones PHP requeridas

Web Push (cifrado ECDH para VAPID) necesita una librería de aritmética de
precisión. Local (Laragon) ya trae `bcmath`. En Railway se agregó `gmp` vía
`nixpacks.toml`:

```toml
nixPkgs = [ ..., "php83Extensions.gmp", ... ]
```

Las demás requeridas (`curl`, `json`, `mbstring`, `openssl`) ya estaban.

---

## 3. Endpoints

### Gestión de suscripciones

| Método | Ruta | Auth | Cuerpo / Notas |
|--------|------|------|----------------|
| `GET` | `/api/v1/push/vapid-public-key` | pública | Devuelve `{ public_key }` |
| `POST` | `/api/v1/push/suscribir` | `auth:sanctum` | `{ endpoint, keys: { p256dh, auth }, contentEncoding }` |
| `DELETE` | `/api/v1/push/desuscribir` | `auth:sanctum` | `{ endpoint }` |

`suscribir` hace `updateOrCreate` por `endpoint`, así que reenviar la misma
suscripción no genera duplicados.

### Cron (protegidos por header, sin sesión)

| Método | Ruta | Protección |
|--------|------|------------|
| `POST` | `/api/v1/cron/recordatorios-diarios` | header `X-Cron-Secret` == `CRON_SECRET` |
| `POST` | `/api/v1/cron/resumen-semanal` | header `X-Cron-Secret` == `CRON_SECRET` |

Si el secret falta o no coincide → **403**. Respuesta exitosa:

```json
{ "success": true, "data": { "usuarios_notificados": 3 }, "message": "..." }
```

---

## 4. Configurar cron-job.org (sin worker en Railway)

Railway no ejecuta el scheduler de Laravel sin un worker continuo (plan extra).
En su lugar, un cron externo gratuito (https://cron-job.org) llama los endpoints.

Crea **dos** cron jobs:

### Job A — Recordatorio diario

- **URL:** `https://igobackend-production.up.railway.app/api/v1/cron/recordatorios-diarios`
- **Método:** `POST`
- **Header personalizado:** `X-Cron-Secret: <valor de CRON_SECRET>`
- **Horario:** todos los días a las **07:00** (zona horaria America/Bogota).
  En cron-job.org elige la zona horaria `America/Bogota` y la hora 07:00.

### Job B — Resumen semanal

- **URL:** `https://igobackend-production.up.railway.app/api/v1/cron/resumen-semanal`
- **Método:** `POST`
- **Header personalizado:** `X-Cron-Secret: <valor de CRON_SECRET>`
- **Horario:** **lunes** a las **08:00** (zona horaria America/Bogota).

> En cron-job.org: sección *"Advanced" → "Custom HTTP headers"* para añadir
> el header `X-Cron-Secret`. Marca el método **POST**.

### Prueba manual con curl

```bash
curl -X POST https://igobackend-production.up.railway.app/api/v1/cron/recordatorios-diarios \
  -H "X-Cron-Secret: TU_CRON_SECRET"
```

---

## 5. Scheduler de Laravel (para el futuro)

Si algún día se activa un worker (`php artisan schedule:work`), los comandos ya
están registrados en `routes/console.php`:

- `push:diario`  → `dailyAt('07:00')`  timezone America/Bogota
- `push:semanal` → `weeklyOn(1, '08:00')` (lunes) timezone America/Bogota

Mientras no haya worker, esos schedules quedan inertes y manda el cron externo.

---

## 6. Pruebas manuales con artisan

```bash
php artisan push:diario     # procesa recordatorios diarios y muestra el conteo
php artisan push:semanal    # procesa el resumen semanal y muestra el conteo
```

Ambos comandos llaman al mismo `NotificacionService` que usan los endpoints de
cron, así que sirven para validar la lógica sin depender del cron externo.

---

## 7. Lógica de distribución de acciones

Cada iniciativa con `plazo` reparte sus N acciones uniformemente entre
`fecha_inicio` y `fecha_fin` (igual que el calendario del frontend):

- N = 1 → la acción cae en `fecha_inicio`.
- N ≥ 2 → acción 0 en `fecha_inicio`, acción N-1 en `fecha_fin`, el resto
  equiespaciado. Ej.: ventana de 14 días con 3 acciones → días 0, 7, 14.

El recordatorio diario notifica solo si **alguna acción cae exactamente hoy**.
El resumen semanal cuenta iniciativas cuya ventana **toca** la semana actual
(lunes–domingo) y sugiere empezar por la de cuadrante más bajo / mayor
importancia. Las iniciativas de **cuadrante 4** (`plazo: null`) se ignoran.

Todo el parseo de `contenido_json` está envuelto en `try/catch`: un informe con
JSON malformado se salta sin tumbar el proceso.

---

## 8. Privacidad

- Las respuestas y los logs **nunca** incluyen email ni datos personales: solo
  `user_id`, ids de suscripción, conteos y códigos de estado HTTP.
- La clave **pública** VAPID es segura de exponer; la **privada** y el
  `CRON_SECRET` son secretos y solo viven en variables de entorno.
- ⚠️ El logger de *queries lentas* (`AppServiceProvider`, solo activo con
  `APP_DEBUG=true`) registra los *bindings* SQL, que pueden incluir email
  (login) o las claves de suscripción. En producción `APP_DEBUG=false`, por lo
  que ese logger está **desactivado**. No habilites debug en producción.
```
