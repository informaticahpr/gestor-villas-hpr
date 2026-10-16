# Gestor de Villas HPR

Sistema web para la gestión de saldos de las villas: cargos, abonos, cuotas de mantenimiento,
estados de cuenta, recibos y reportes.

```
Gestor de Villas HPR/
├── backend/    ← API en Laravel 13 (PHP 8.4) + Sanctum
└── frontend/   ← Vue 3 + TypeScript + Vite + Pinia + Vue Router + Tailwind CSS v4
```

## Funcionalidad

- **Villas:** alta y edición de villas con su propietario y encargado, historial de propietarios
  anteriores y estado de cuenta por villa.
- **Cargos y créditos:** captura individual o a todas las villas. La fecha del movimiento siempre es
  la del servidor; el usuario solo elige el vencimiento (obligatorio en los cargos).
- **Cuota de mantenimiento:** monto mensual configurable y cuotas especiales por villa.
- **Reportes:** estado de cuenta, saldos generales, antigüedad de saldos y reporte por concepto, con
  exportación a PDF y Excel.
- **Reimpresión:** cualquier recibo se puede volver a generar; los movimientos se anulan (no se borran).
- **Configuración:** usuarios, permisos por cargo, conceptos, cuotas, formas de pago y bitácora de
  cambios.

Las fechas se muestran como `DD/MM/AAAA` y la zona horaria es `America/Tegucigalpa`.

## Desarrollo local

Requisitos: PHP 8.4, Composer, Node.js 24.

**Backend**
```
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```
Queda en `http://127.0.0.1:8000`. En local usa SQLite.

**Frontend**
```
cd frontend
npm install
cp .env.example .env
npm run dev
```
Queda en `http://localhost:5173`.

El seeder crea usuarios de prueba **solo para desarrollo local**. Nunca se deben usar esas
credenciales en un entorno publicado.

`php artisan db:seed --class=SimulacionSeeder` agrega villas y meses de historial ficticios para
revisar reportes y exportaciones.

## Despliegue

El backend y el frontend tienen cada uno su `Dockerfile`. Las variables necesarias están documentadas
en `backend/.env.example` y `frontend/.env.example`. Antes de publicar:

- `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=error`.
- Servir por HTTPS con `SESSION_SECURE_COOKIE=true`.
- `APP_URL`, `FRONTEND_URL` y `SANCTUM_STATEFUL_DOMAINS` con los dominios reales.
- Crear usuarios con contraseñas propias y fuertes; no ejecutar el seeder de usuarios de prueba.
