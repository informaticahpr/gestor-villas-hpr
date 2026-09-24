# Imagen para desplegar el frontend en Railway (o cualquier host que corra Dockerfiles), junto al
# backend y la base de datos -- ver la seccion "Despliegue" del README para los 3 servicios juntos.
#
# Build en dos etapas: la primera compila el sitio (necesita Node); la segunda solo sirve los
# archivos estaticos ya compilados con Caddy, un servidor liviano (imagen final de pocos MB, sin
# Node ni el codigo fuente adentro).

FROM node:22-alpine AS build
WORKDIR /app

# Dependencias primero (capa cacheable: no se reinstalan si solo cambia el codigo)
COPY package.json package-lock.json ./
RUN npm ci

COPY . .

# Vite incrusta esta URL en el build en tiempo de COMPILACION (no se puede cambiar despues sin
# recompilar). Railway pasa las Variables del servicio como build args solo para los nombres
# declarados con ARG aqui -- hay que definir VITE_API_BASE_URL en Railway > (servicio del
# frontend) > Variables, con la URL publica del backend (ej. https://tu-backend.up.railway.app).
ARG VITE_API_BASE_URL
ENV VITE_API_BASE_URL=$VITE_API_BASE_URL

RUN npm run build

# --- Etapa final: solo Caddy + los archivos ya compilados ---
FROM caddy:2-alpine
COPY --from=build /app/dist /srv
COPY Caddyfile /etc/caddy/Caddyfile

# Railway asigna el puerto en tiempo de arranque (variable $PORT); el Caddyfile lo lee de ahi.
EXPOSE 8080
