# GymTrack - entorno Docker

Entorno reproducible para ejecutar GymTrack con WordPress y MySQL mediante Docker Compose.

## Arquitectura

- `gymtrack-wordpress`: WordPress + Apache + PHP + plugin GymTrack.
- `gymtrack-mysql`: base de datos MySQL 8.
- `gymtrack_wordpress`: volumen persistente de WordPress.
- `gymtrack_db`: volumen persistente de MySQL.

## Arranque

```bash
docker compose up -d --build
```

Después, abrir:

```text
http://localhost:8082
```

## Estado

```bash
docker compose ps
```

## Logs

```bash
docker compose logs -f wordpress
```

## Parada

```bash
docker compose down
```

Los datos permanecen en los volúmenes Docker. Para eliminar también los datos:

```bash
docker compose down -v
```

> En una primera ejecución WordPress mostrará su asistente de instalación. Para el proyecto real, la configuración final de credenciales y persistencia se documentará antes del despliegue definitivo.
