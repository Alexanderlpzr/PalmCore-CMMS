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

Construir la imagen

```bash
docker compose -f deploy/docker-compose.yml build
```

Levantar los servicios

```bash
docker compose -f deploy/docker-compose.yml up -d
```

---

# Actualizar la aplicación

```bash
git pull

docker compose -f deploy/docker-compose.yml up -d --build
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
docker compose logs -f
```

Aplicación

```bash
docker compose logs -f app
```

PostgreSQL

```bash
docker compose logs -f postgres
```

Redis

```bash
docker compose logs -f redis
```

---

# Migraciones

```bash
docker compose exec app php artisan migrate --force
```

---

# Seeders

```bash
docker compose exec app php artisan db:seed --force
```

---

# Limpiar caché

```bash
docker compose exec app php artisan optimize:clear
```

---

# Generar APP_KEY

```bash
docker compose exec app php artisan key:generate
```

---

# Backups

Base de datos

```bash
docker compose exec postgres pg_dump \
-U palmcore palmcore > backup.sql
```

Restaurar

```bash
docker compose exec -T postgres psql \
-U palmcore palmcore < backup.sql
```

---

# Actualizar imágenes

```bash
docker compose pull

docker compose up -d
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

- [ ] HTTPS con Let's Encrypt
- [ ] GitHub Actions
- [ ] Renovación automática de certificados
- [ ] Backups automáticos
- [ ] Monitoreo
- [ ] Cloudflare
- [ ] Dominio definitivo

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