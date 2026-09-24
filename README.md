# Gestor de Villas HPR

Sistema de gestión de saldos de Villas HPR. Ver `informacion/Sistema de Gestión de Saldos Villas HPR.docx`
para los requerimientos originales y `docs/` para el análisis de la base de datos existente y el diseño del esquema nuevo.

```
Gestor de Villas HPR/
├── backend/    ← Laravel 13 (PHP 8.4) + Sanctum, corriendo sobre SQLite local con datos de ejemplo
├── frontend/   ← Vue 3 + TypeScript + Vite + Pinia + Vue Router + Tailwind CSS v4
├── docs/       ← análisis de TPVADMIN y diseño del esquema nuevo
├── db_ejemplo/ ← script de esquema (solo estructura) exportado de TPVADMIN
└── informacion/← documento de requerimientos, contexto y datos de conexión
```

## Estado actual: pantallas completas, datos de ejemplo (SQLite), no conectado a TPVADMIN

El sistema tiene todas las pantallas del `.docx` funcionando de extremo a extremo (login, buscar
villa, crear/editar villa con estado de cuenta, aplicar cargo/crédito, reportes, configuración de
usuarios), pero corriendo contra **SQLite local con datos de ejemplo** (`backend/database/database.sqlite`),
no contra `TPVADMIN`. Esto fue una decisión deliberada: el esquema real sobre `TPVADMIN` sigue
pendiente de confirmación (ver `docs/new-schema-design.md` y `docs/pending-verification.sql`).

Los modelos (`CLIE1`, `CONC1`, `CUEN1`) usan a propósito los mismos nombres de tabla/columna
propuestos para `TPVADMIN`, para que migrar de SQLite a la base real sea, en principio, solo un
cambio de conexión — no un rediseño.

### Usuarios de prueba (contraseña `passwd` para los tres)

El login es por **nombre de usuario** (no distingue mayúsculas), no por correo.

| Rol | Usuario |
|---|---|
| Director | `Director` |
| Admin | `Administrador` |
| Supervisor | `Supervisor` |

Supervisor puede aplicar cargos/abonos y ver reportes, pero no puede entrar a Configuración
(gestión de usuarios) — tal como pide el `.docx`.

**Usuarios:** solo el **Administrador** puede crearlos, editarlos, habilitarlos/deshabilitarlos, eliminarlos
y cambiar contraseñas. El **Director** puede ver la lista (solo lectura); el resto de Configuración
(conceptos, cuotas, formas de pago, bitácora) sí la gestiona.

### Cuota de mantenimiento y cuotas especiales

- **Configuración → Cuotas** tiene dos submenús: *Cuota de mantenimiento* (el monto mensual, que solo
  define Director/Admin) y *Cuotas especiales* (el monto de las villas marcadas con cuota especial).
- La cuota se **aplica desde Cargo / Crédito** y nadie puede escribir su monto ahí: se cobra la cuota
  mensual, salvo a las villas con cuota especial, que pagan el monto asignado a cada una. La cuota
  especial solo afecta a este concepto (`CONC1.ES_MANTENIMIENTO`), no a los demás.
- Mientras la cuota mensual no esté definida, el cargo de mantenimiento no se puede aplicar.
- En los **cargos** la fecha de vencimiento es obligatoria (individuales o a todas las villas); en los
  créditos/abonos no.
- **La fecha de un movimiento no se puede elegir ni manipular:** siempre es la de hoy del servidor
  (zona `America/Tegucigalpa`), sin importar lo que envíe el cliente (`MovimientoController`). Lo único
  que elige el usuario es el vencimiento, que no puede ser anterior a hoy. Así ningún cargo o abono
  queda con fecha equivocada en los estados de cuenta.

### Datos de simulación (para revisar reportes y exportaciones)

`php artisan db:seed --class=SimulacionSeeder` agrega 24 villas con nombres y datos de relleno (D-1…F-8) y
siete meses de historial de cargos y abonos para **todas** las villas, con escenarios variados: al día,
pagos tardíos con mora, atrasos en los cuatro tramos de antigüedad, pagos parciales, trimestrales y
bimestrales, saldos a favor, cuotas especiales, cargos extraordinarios, observaciones largas y vacías, y las
seis formas de pago. Es determinista y **no** forma parte de `DatabaseSeeder`. Respeta los movimientos que
ya existan (conserva el cargo del mes actual) y no se ejecuta si ya hay historial anterior al mes actual.

### Formato de fechas

Todas las fechas del sistema se muestran y se escriben como **DD/MM/AAAA** (con hora, `DD/MM/AAAA HH:MM`
en 24 h, hora de Tegucigalpa): pantallas, PDF, Excel y recibos. Los campos de fecha son el componente
`frontend/src/components/FechaInput.vue`, que no depende del idioma del navegador (el
`<input type="date">` nativo mostraría MM/DD/AAAA en uno en inglés). Entre el frontend y la API las fechas
viajan como `AAAA-MM-DD`; las funciones de formato están en `frontend/src/lib/fechaFormato.ts`.

### Nombre de los archivos exportados

`titulodocumento_villa_fecha.ext` (`App\Support\NombreArchivo`). La fecha va como `DD-MM-AAAA`, porque la barra
de `DD/MM/AAAA` no se admite en un nombre de archivo; el segmento de la villa se omite si el documento no es de
una villa. Ejemplos: `estado-de-cuenta_B-1_01-02-2026-al-21-09-2026.pdf`,
`estado-de-cuenta_todas_01-02-2026-al-21-09-2026.xlsx`, `recibo-CR0000141_B-2_08-06-2026.pdf`,
`saldos-generales_21-09-2026.pdf`, `antiguedad-de-saldos_21-09-2026.xlsx`, `bitacora_21-09-2026.pdf`.

### Reimpresión de recibos

- El menú **Reimpresión** (todos los roles) lista los cargos y abonos aplicados, paginados (10/20/50/100),
  con filtros por villa, tipo, folio y rango de fechas, y un botón **Ver recibo** en cada uno.
- En el estado de cuenta (Reportes → Estado de Cuenta, y la pestaña Reporte de una villa) al hacer clic en un
  movimiento se abre su detalle (folio, fecha, monto y observación) con el botón **Ver recibo**.
- Cualquier recibo se puede abrir cuantas veces se quiera (`GET /api/movimientos/{id}/recibo`). El encabezado
  siempre muestra cuándo se generó y quién lo sacó; el pie conserva quién y cuándo registró el movimiento.

### Villas de ejemplo (`database/seeders/VillaSeeder.php`)

Incluye escenarios variados a propósito: villas al día, con 1-2 meses de mora, con 6 meses de mora,
una con **deuda histórica continua desde enero 2020** (para probar el caso que menciona
`docs/historical-debt.md`), una con saldo a favor, una con pago parcial de un mes, y una con
cuota mensual automática desactivada.

## ⚠️ Antes de conectar contra TPVADMIN real

`backend/.env` tiene la conexión real a `TPVADMIN` (`sqlsrv`) comentada, lista para reactivar, con
`DB_PASSWORD=TU_PASSWORD` como placeholder. **No activarla ni correr ningún comando que escriba en
esa base hasta que:**

1. Se reemplace `DB_PASSWORD` por la contraseña real de un usuario con permisos de escritura
   (hoy `dev` es `db_datareader`, solo lectura).
2. Se hayan corrido y confirmado las consultas de `docs/pending-verification.sql`.
3. Se haya revisado `docs/new-schema-design.md` con el resultado de esa verificación y aplicado el
   DDL propuesto ahí (agrega `ID_MOV`/`ANIO`/`MES` a `CUEN1`, campos de contacto a `CLIE1`, etc.)
   contra la base real.

`SESSION_DRIVER`, `CACHE_STORE` y `QUEUE_CONNECTION` están en `file`/`file`/`sync` (no `database`)
a propósito, para que la infraestructura interna de Laravel nunca necesite escribir en la base de
datos configurada — así, aunque en algún momento `DB_CONNECTION` apunte a `sqlsrv`, un simple
`php artisan serve` no intentará crear tablas ahí.

## Seguridad

- **Login:** el usuario se compara sin distinguir mayúsculas contra `users.name`, con contraseña
  con hash bcrypt. El mensaje de error es igual si el usuario no existe o si la contraseña es
  incorrecta (no delata cuáles nombres de usuario son válidos), y `AuthController::login()` siempre
  calcula un hash (real o señuelo) para que tampoco lo delate el tiempo de respuesta.
- **Límite de intentos:** `POST /api/login` tiene `throttle:login` (`AppServiceProvider`): 5 intentos
  por minuto por usuario+IP y 20 por minuto por IP en total. Pasado el límite responde 422 →429.
- **Sesión:** por cookie (Sanctum, con CSRF), sin "recordarme" — dura `SESSION_LIFETIME` (2 h
  inactiva) y no sobrevive a cerrar el navegador más allá de eso. Se regenera al iniciar sesión
  (evita fijación de sesión) y se invalida al cerrarla.
- **Antes de salir a internet:**
  - `APP_ENV=production`, `APP_DEBUG=false` (con `true` cualquier error muestra la traza completa
    del código a quien sea).
  - Servir todo por **HTTPS** y fijar `SESSION_SECURE_COOKIE=true` — si no, la cookie de sesión y
    la contraseña viajan sin cifrar.
  - `APP_URL`, `FRONTEND_URL` y `SANCTUM_STATEFUL_DOMAINS` con el dominio real, no `localhost`.
  - Cambiar las contraseñas de los tres usuarios de prueba (`passwd` — ver más abajo), débil y
    compartida a propósito solo para desarrollo.
  - Sin doble factor ni bloqueo de cuenta tras muchos intentos fallidos (el límite de intentos
    frena la automatización, pero no bloquea la cuenta); valorar para una siguiente fase.

## Despliegue en la nube (pruebas)

Todo el sistema (frontend, backend y base de datos) se despliega como **3 servicios dentro de un
mismo proyecto de Railway** (un proyecto admite hasta 5). Railway no sirve estáticos solo-con-subir
la carpeta como Netlify, por eso el frontend también lleva su propio `Dockerfile` (con
[Caddy](https://caddyserver.com/), un servidor liviano) además del del backend.

1. **Base de datos:** agregar el plugin de **Postgres** de Railway (un clic) — no usar SQLite en la
   nube: un contenedor no tiene disco persistente entre despliegues y el archivo `.sqlite` se
   perdería en el siguiente deploy. `config/database.php` ya trae la conexión `pgsql`; solo hay que
   poner `DB_CONNECTION=pgsql` en las variables del servicio del backend. Railway entrega la cadena
   de conexión en `DATABASE_URL`, que `config/database.php` ya sabe leer (no hace falta copiar
   host/usuario/contraseña a mano).
2. **Backend:** nuevo servicio → *Deploy from GitHub repo* → *root directory* `backend/` (Railway
   detecta el `Dockerfile` solo). `backend/Dockerfile` instala las extensiones que usa el proyecto
   (`gd`, `zip`, `pdo_pgsql`), cachea config/rutas, aplica migraciones pendientes al arrancar y
   sirve con `php artisan serve`. Variables a definir ahí (ver `backend/.env.example`): `APP_KEY`
   (generarla local con `php artisan key:generate --show` y pegar el resultado), `APP_ENV=production`,
   `APP_DEBUG=false`, `DB_CONNECTION=pgsql`, y las de los pasos 4-5 más abajo. Primera vez: correr
   `php artisan db:seed` una vez desde la consola del servicio (pestaña *Shell* de Railway) para
   tener los 3 usuarios de prueba. Activar *Networking → Public Domain* para que el navegador pueda
   llegar a la API.
3. **Frontend:** otro servicio nuevo → mismo repo → *root directory* `frontend/` (detecta el
   `Dockerfile` del frontend). Antes de que arranque el build hay que definir la variable
   `VITE_API_BASE_URL` en ese servicio (Variables), con la URL pública del backend del paso 2 (ej.
   `https://tu-backend.up.railway.app`) — Vite la incrusta en el archivo compilado, así que
   cambiarla después exige un *redeploy*, no solo reiniciar. Activar también su *Public Domain*.
4. **Conectar ambos:** en el backend, `FRONTEND_URL` y `SANCTUM_STATEFUL_DOMAINS` con el dominio
   público que Railway le dio al frontend (sin `https://` en `SANCTUM_STATEFUL_DOMAINS`, ej.
   `tu-frontend.up.railway.app`).
5. **Cookie entre servicios:** cada servicio de Railway recibe su propio subdominio de
   `up.railway.app`, y ese dominio está registrado como sitio público independiente (igual que
   `netlify.app` o `vercel.app`) — así que, aunque los tres estén en el mismo proyecto de Railway,
   el navegador sigue tratando al frontend y al backend como **sitios distintos**. Por eso hace
   falta `SESSION_SAME_SITE=none` junto con `SESSION_SECURE_COOKIE=true` en el backend (ambos ya
   comentados en `.env.example`). **Limitación real de este esquema:** Safari y algunos
   navegadores/extensiones más estrictos con la privacidad pueden bloquear esa cookie igual. La
   solución de fondo es tener **un dominio propio** con dos subdominios (ej. `app.tudominio.com`
   para el frontend y `api.tudominio.com` para el backend, ambos apuntados por DNS a su servicio de
   Railway) y `SESSION_DOMAIN=.tudominio.com`: al ser subdominios del mismo dominio, la cookie ya
   no es "entre sitios" y se puede dejar en `SESSION_SAME_SITE=lax` (más seguro), sin ese riesgo.
6. **Alternativa para el frontend:** si en algún momento se prefiere no tener el frontend como
   contenedor propio, Netlify sirve archivos estáticos "gratis" de forma más directa (sin
   Dockerfile: conectar el repo con *base directory* `frontend`, ya trae `netlify.toml` listo con
   el build y la redirección que necesita el router de Vue). El backend y la base de datos seguirían
   igual, en Railway.
7. **Para conectar contra el `TPVADMIN` real más adelante** (SQL Server en la red del hotel, ver
   sección de abajo): Railway no tiene forma de alcanzar un servidor dentro de esa red privada. Esa
   fase necesita una VPN hacia el hotel, lo que apunta más bien a un servidor propio (VPS) en vez de
   esta plataforma — no es necesario resolverlo ahora, solo para cuando ya no sean datos de prueba.

## Requisitos instalados en esta máquina

- PHP 8.4.24 (ZTS, x64) — `winget install PHP.PHP.8.4`
- Composer 2.10.2 — instalado manualmente en `%LOCALAPPDATA%\ComposerBin` (no está en winget)
- Node.js 24.19.0 / npm 11.17.0 — `winget install OpenJS.NodeJS.LTS`
- ODBC Driver 18 for SQL Server — `winget install Microsoft.msodbcsql.18` (para cuando se conecte a TPVADMIN)
- Extensiones PHP `sqlsrv` / `pdo_sqlsrv` (Microsoft, v5.13.3, build `_84_ts_x64`) copiadas al
  directorio `ext` de PHP y habilitadas en `php.ini`

Si se abre una terminal nueva y `php`/`composer`/`node` no se reconocen, es por caché de PATH de la
sesión — abrir una terminal nueva basta (las variables ya quedaron en el PATH de usuario/máquina).

## Arranque

Con las dependencias ya instaladas (`vendor/` y `node_modules/` presentes), para encender el
sistema completo en dos terminales:

**Terminal 1 — Backend**
```
cd backend
php artisan serve
```
Queda escuchando en `http://127.0.0.1:8000`.

**Terminal 2 — Frontend**
```
cd frontend
npm run dev
```
Queda escuchando en `http://localhost:5173`.

Luego abrir el navegador en `http://localhost:5173` y entrar con uno de los
[usuarios de prueba](#usuarios-de-prueba-contraseña-password-para-los-tres) (contraseña `password`).

Para apagar, `Ctrl+C` en cada terminal. Si es la primera vez o faltan dependencias, ver los pasos
de instalación en las secciones [Backend (Laravel)](#backend-laravel) y [Frontend (Vue)](#frontend-vue)
más abajo.

## Backend (Laravel)

```
cd backend
composer install
php artisan migrate:fresh --seed   # recrea SQLite con los datos de ejemplo
php artisan serve
```

- Sanctum con autenticación por cookie (SPA), `statefulApi()` + `redirectGuestsTo(null)` en
  `bootstrap/app.php` para que rutas sin sesión respondan 401 JSON en vez de intentar redirigir a
  una vista de login que no existe (este backend es API-only).
- CORS (`config/cors.php`) acepta al frontend (`FRONTEND_URL`, por defecto `http://localhost:5173`)
  con `supports_credentials = true`.
- `app/Services/SaldoService.php` centraliza el cálculo de saldo, estado de cuenta (con saldo
  corrido) y el reporte de antigüedad de saldos (FIFO: los abonos cubren primero los cargos más
  antiguos; el saldo pendiente completo de cada villa se clasifica en el bucket de 30/60/90/+90
  días según la fecha del cargo impago más viejo).
- Endpoints principales bajo `/api`: `login`, `logout`, `user`, `villas` (CRUD + estado de cuenta),
  `conceptos`, `movimientos` (aplicar cargo/abono), `reportes/estado-cuenta`,
  `reportes/saldos-generales` (+ `/exportar` en CSV), `usuarios` (solo Director/Admin).

## Frontend (Vue)

```
cd frontend
npm install
npm run dev
```

- `src/stores/auth.ts` — sesión actual, login/logout. `src/stores/ui.ts` — estado de los modales
  globales (Crear Villa, Cargo/Crédito), para poder abrirlos desde la barra de navegación o desde
  los accesos rápidos de Home.
- `src/router/index.ts` — guard de navegación: exige sesión, oculta Configuración a quien no sea
  Director/Admin.
- `src/components/VillaModal.vue` — mismo modal para crear y editar (pestañas Datos / Reporte del
  último año exacto, de hoy a la misma fecha del año anterior; el `.docx` pedía 6 meses).
- `src/views/ReportesView.vue` — Estado de Cuenta (individual o todas las villas, por rango de
  fechas) y Saldo Villas General (con filtros "omitir al día"/"omitir a favor", tabla de antigüedad
  con porcentajes, exportar a CSV/Excel e imprimir).

## Explícitamente fuera de alcance de esta ronda

- Exportación a PDF con librería dedicada — el botón "Imprimir" (con CSS de impresión) cubre ese
  caso vía "Guardar como PDF" del navegador.
- Permisos finos más allá de lo que el `.docx` ya especifica.
- Cambio de contraseña / recuperación de contraseña.
- Conexión real a `TPVADMIN` — ver la sección de arriba.
