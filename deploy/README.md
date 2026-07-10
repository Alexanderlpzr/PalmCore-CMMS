# PalmCore CMMS - Production Deployment

Este directorio contiene toda la configuración necesaria para desplegar PalmCore CMMS en un servidor Linux utilizando Docker.

---

# Infraestructura

```
Internet
        │
        ▼
+------------------------+
|      Nginx             |
+------------------------+
           │
           ▼
+------------------------+
|    PalmCore App        |
| PHP 8.4 + Laravel      |
| Supervisor             |
+------------------------+
      │            │
      ▼            ▼
 PostgreSQL      Redis
```

---

# Requisitos

- Ubuntu 24.04 LTS
- Docker Engine
- Docker Compose Plugin
- Git
- Dominio (opcional durante desarrollo)

---

# Configurar dominio fronda.app

Antes del despliegue, apunta el dominio a tu servidor:

- Registro DNS - Tipo A: `fronda.app` -> `IP_PUBLICA_SERVIDOR`
- Registro DNS - Tipo A: `www.fronda.app` -> `IP_PUBLICA_SERVIDOR` (opcional)
- TTL recomendado: 300 segundos

En `deploy/.env.production.example` ya está preparado para este dominio:

- `APP_URL=https://fronda.app`
- `CORS_ALLOWED_ORIGINS=https://fronda.app,https://www.fronda.app`
- `SESSION_DOMAIN=fronda.app`

Para el entorno real, copia esos valores a `.env.production`.

---

# Estructura

```
deploy/
├── docker-compose.yml
├── .env.production.example
└── README.md
```

---

# Primer despliegue

Clonar el proyecto

```bash
git clone https://github.com/Alexanderlpzr/PalmCore-CMMS.git
```

Entrar al proyecto

```bash
cd PalmCore-CMMS
```

Crear el archivo de entorno

```bash
cp deploy/.env.production.example .env.production
```

Editar las variables necesarias

```bash
nano .env.production
```

Variables mínimas recomendadas para dominio y seguridad:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://fronda.app
CORS_ALLOWED_ORIGINS=https://fronda.app,https://www.fronda.app
SESSION_DOMAIN=fronda.app
```

Construir la imagen

```bash
docker compose --env-file .env.production -f deploy/docker-compose.yml build
```

Levantar los servicios

```bash
docker compose --env-file .env.production -f deploy/docker-compose.yml up -d
```

---

# Actualizar la aplicación

```bash
git pull

docker compose --env-file .env.production -f deploy/docker-compose.yml up -d --build
```

---

# Reiniciar

```bash
docker compose restart
```

---

# Detener

```bash
docker compose down
```

---

# Ver logs

Todos los servicios

```bash
docker compose --env-file .env.production -f deploy/docker-compose.yml logs -f
```

Aplicación

```bash
docker compose --env-file .env.production -f deploy/docker-compose.yml logs -f app
```

PostgreSQL

```bash
docker compose --env-file .env.production -f deploy/docker-compose.yml logs -f postgres
```

Redis

```bash
docker compose --env-file .env.production -f deploy/docker-compose.yml logs -f redis
```

---

# Migraciones

```bash
docker compose --env-file .env.production -f deploy/docker-compose.yml exec app php artisan migrate --force
```

---

# Seeders

```bash
docker compose --env-file .env.production -f deploy/docker-compose.yml exec app php artisan db:seed --force
```

---

# Limpiar caché

```bash
docker compose --env-file .env.production -f deploy/docker-compose.yml exec app php artisan optimize:clear
```

---

# Generar APP_KEY

```bash
docker compose --env-file .env.production -f deploy/docker-compose.yml exec app php artisan key:generate
```

---

# Backups

Base de datos

```bash
docker compose --env-file .env.production -f deploy/docker-compose.yml exec postgres pg_dump \
-U palmcore palmcore > backup.sql
```

Restaurar

```bash
docker compose --env-file .env.production -f deploy/docker-compose.yml exec -T postgres psql \
-U palmcore palmcore < backup.sql
```

---

# Actualizar imágenes

```bash
docker compose --env-file .env.production -f deploy/docker-compose.yml pull

docker compose --env-file .env.production -f deploy/docker-compose.yml up -d
```

---

# Variables importantes

| Variable | Descripción |
|----------|-------------|
| APP_ENV | production |
| APP_DEBUG | false |
| DB_HOST | postgres |
| REDIS_HOST | redis |
| APP_URL | Dominio o IP pública |

---

# Pendientes

- [ ] HTTPS con Let's Encrypt (obligatorio para dominio en producción)
- [ ] GitHub Actions
- [ ] Renovación automática de certificados
- [ ] Backups automáticos
- [ ] Monitoreo
- [ ] Cloudflare
- [x] Dominio definitivo: fronda.app

---

# Validaciones post-deploy

Verificar salud de la aplicación:

```bash
curl -I https://fronda.app/up
```

Resultado esperado: respuesta `200`.

Verificar cabeceras de seguridad:

```bash
curl -I https://fronda.app
```

Debe incluir `strict-transport-security` cuando la conexión sea HTTPS.

---

# Mantenimiento

Actualizar el servidor

```bash
sudo apt update
sudo apt upgrade -y
```

Actualizar Docker

```bash
sudo apt update
sudo apt install docker-ce docker-ce-cli
```

---

Desarrollado por Alexander López.