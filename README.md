# Inventario App con Docker

Proyecto PHP + MySQL listo para ejecutar con Docker Compose.

## Requisitos

- Docker y Docker Compose instalados (`docker --version` y `docker compose version`)

## Configuracion rapida

1. Copia variables de entorno:

```bash
cp .env.example .env
```

2. Completa `.env` con tus credenciales (o usa valores de desarrollo):

```env
DB_ROOT_PASSWORD=root_secreto
DB_NAME=inventario_app
DB_USER=inventario_user
DB_PASS=inventario_pass
APP_PORT=8080
ADMINER_PORT=8081
DB_PORT_HOST=3306
```

## Levantar servicios

```bash
docker compose up --build -d
```

## URLs

- App: `http://localhost:8080/login.html`
- Adminer: `http://localhost:8081`

## Comandos utiles

```bash
# Ver logs
docker compose logs -f

# Detener servicios
docker compose down

# Reiniciar desde cero (elimina datos de la BD)
docker compose down -v && docker compose up --build -d
```

## Notas de despliegue

- La app usa variables de entorno para conectarse a MySQL (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`).
- Los scripts SQL en `db/migrations` se ejecutan automaticamente solo al crear el volumen por primera vez.
- Si tienes puertos ocupados, cambia `APP_PORT`, `ADMINER_PORT` o `DB_PORT_HOST` en `.env`.
