# PalmCore CMMS

Ver [`deploy/README.md`](deploy/README.md) para la guía completa de despliegue manual con Docker Compose.

## Despliegue automático con GitHub Actions

Cada push a `master` dispara [`.github/workflows/deploy.yml`](.github/workflows/deploy.yml), que se conecta por SSH al VPS y ejecuta [`deploy/scripts/deploy.sh`](deploy/scripts/deploy.sh). El script actualiza el código, reconstruye los contenedores, corre migraciones, optimiza la app y valida que quedó sana — sin necesidad de entrar manualmente por SSH.

### Qué hace `deploy.sh`

1. Valida que `/opt/palmcore`, el repo git, `deploy/docker-compose.yml` y `.env.production` existen.
2. Valida la configuración con `docker compose --env-file .env.production -f deploy/docker-compose.yml config`.
3. `git fetch origin` + `git reset --hard origin/master` (no usa `git pull`).
4. `docker compose ... pull` (best effort) y `up -d --build`.
5. Espera a que `palmcore-app`, `palmcore-postgres` y `palmcore-redis` estén `running`/`healthy`.
6. `php artisan migrate --force`.
7. `php artisan optimize`.
8. `docker image prune -f`.
9. Healthcheck contra `https://fronda.app/up` (hasta 10 intentos, exige HTTP 200 exacto).

**No hay rollback automático.** Si falla cualquier etapa (validación de compose, build, contenedores no healthy, migración, optimize o healthcheck), el script imprime `docker compose ps` y los últimos 100 logs de `app` y `caddy`, y termina con `exit 1` sin tocar el código ni la base de datos. Motivo: una migración ya aplicada dejaría la app inconsistente si el código se revierte solo — el fix debe ser manual (down migration o fix-forward).

### 1. Generar una llave SSH dedicada para el deploy

En tu máquina local (no la reutilices con tu llave personal):

```bash
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ./palmcore_deploy_key -N ""
```

Esto genera `palmcore_deploy_key` (privada) y `palmcore_deploy_key.pub` (pública).

### 2. Instalar la llave pública en el VPS

Copia la llave pública a la cuenta que usará GitHub Actions para conectarse (por ejemplo `deploy` o el usuario propietario de `/opt/palmcore`):

```bash
ssh-copy-id -i palmcore_deploy_key.pub deploy@TU_IP_O_DOMINIO
```

O manualmente, en el VPS:

```bash
mkdir -p ~/.ssh
cat palmcore_deploy_key.pub >> ~/.ssh/authorized_keys
chmod 700 ~/.ssh
chmod 600 ~/.ssh/authorized_keys
```

Verifica que ese usuario tenga permisos sobre `/opt/palmcore` y sobre el socket de Docker (pertenece al grupo `docker`):

```bash
sudo usermod -aG docker deploy
```

### 3. Crear los Secrets en GitHub

En el repositorio: **Settings → Secrets and variables → Actions → New repository secret**. Crea:

| Secret | Valor |
|---|---|
| `SSH_HOST` | IP o dominio del VPS (ej. `fronda.app` o la IP pública) |
| `SSH_PORT` | Puerto SSH (normalmente `22`) |
| `SSH_USER` | Usuario SSH usado para el deploy (ej. `fronda`) |
| `SSH_PRIVATE_KEY` | Contenido completo de `palmcore_deploy_key` (la llave **privada**) |

```bash
cat palmcore_deploy_key
```

Pega el contenido completo, incluyendo las líneas `-----BEGIN OPENSSH PRIVATE KEY-----` y `-----END OPENSSH PRIVATE KEY-----`.

Borra la llave privada de tu máquina local una vez cargada en GitHub Secrets, o guárdala en un gestor de contraseñas.

### 4. Environment `production` (opcional)

El workflow declara `environment: production`. GitHub la crea automáticamente en el primer run; opcionalmente puedes ir a **Settings → Environments → production** y añadir *required reviewers* o *wait timer* si quieres una aprobación manual antes de desplegar. Los secrets también pueden vivir a nivel de este environment en lugar de a nivel de repositorio.

### 5. Verificar el workflow

Con los secrets configurados, cualquier push a `master` disparará el deploy. Puedes seguir el progreso en la pestaña **Actions** del repositorio. Los logs de cada etapa (`Fetching latest changes`, `Running database migrations`, `Healthcheck OK`, etc.) quedan en la salida del step "Deploy to VPS".

### Notas sobre la infraestructura actual

- El usuario SSH usado en producción es `fronda`, con acceso a `/opt/palmcore` y al grupo `docker`.
- `deploy.sh` asume que `/opt/palmcore` ya es un checkout git de este repositorio con `.env.production` presente — igual que tu flujo manual actual.
- No cambia nada de Caddy, Postgres, Redis ni la arquitectura de contenedores: solo automatiza los comandos que ya ejecutas a mano (`git fetch`/`reset --hard`, `up -d --build`, `migrate --force`, `optimize`), añadiendo validación previa, espera de contenedores, limpieza de imágenes y healthcheck. Si algo falla, se detiene con diagnóstico — no hace rollback.
