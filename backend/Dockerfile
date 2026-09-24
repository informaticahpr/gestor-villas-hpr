# Imagen para desplegar el backend en un host tipo Railway/Render (build por Dockerfile).
# Pensada para la fase de PRUEBAS en linea: un solo proceso (php artisan serve). Para produccion
# real con mas trafico, cambiar a PHP-FPM + Nginx u Octane -- ver la seccion "Despliegue" del README.

FROM php:8.4-cli

# Dependencias del sistema para las extensiones de PHP que usa el proyecto:
#   - gd: imagenes en los PDF/Excel (logo)      - zip: leer/escribir .xlsx (PhpSpreadsheet)
#   - libpq: driver de PostgreSQL (pdo_pgsql)   - freetype/libpng/libzip: requeridas por gd/zip
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libpq-dev sqlite3-dev unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd zip pdo_pgsql pdo_sqlite opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer (no se instala como dependencia de sistema aparte: se copia el binario oficial)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependencias PHP primero (capa cacheable: no se reinstalan si solo cambia el codigo)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

# Al arrancar: cachea config/rutas, aplica migraciones pendientes (no destruye datos) y sirve.
# `php artisan serve` es de un solo proceso -- suficiente para pruebas, no para carga real.
CMD php artisan config:cache \
    && php artisan route:cache \
    && php artisan migrate --force \
    && php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
