# Rick and Morty API

Prueba técnica backend en Laravel 13. La aplicación consume la API pública de
[Rick and Morty](https://rickandmortyapi.com), sincroniza personajes, episodios y
localizaciones en una base de datos propia y expone una API REST con registro de
usuarios, autenticación y gestión de personajes favoritos.

## Requisitos

- Docker (Docker Desktop en macOS/Windows o Docker Engine en Linux).
- No hace falta PHP, Composer ni MySQL instalados en la máquina: todo corre en
  contenedores a través de Laravel Sail.

## Instalación

```bash
git clone <url-del-repositorio> rick-and-morty-api
cd rick-and-morty-api

# Instalar dependencias de Composer usando un contenedor temporal
docker run --rm \
    -v "$(pwd)":/opt -w /opt \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs

# Variables de entorno (ya vienen preparadas para Sail)
cp .env.example .env

# Levantar contenedores (aplicación PHP + MySQL)
./vendor/bin/sail up -d

# Clave de la aplicación y migraciones
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

La aplicación queda disponible en `http://localhost:8000`.

> La aplicación se publica en el puerto 8000 (`APP_PORT`) y MySQL en el 3306 (`FORWARD_DB_PORT`). Si alguno está ocupado, cámbialos
> en el fichero `.env` antes de hacer `sail up`.

## Uso

### Sincronizar los datos de Rick and Morty

```bash
./vendor/bin/sail artisan rickandmorty:sync
```

Descarga localizaciones, episodios y personajes (en ese orden, porque los
personajes referencian a los otros dos) y los guarda en la base de datos. Se puede
ejecutar tantas veces como se quiera: los registros se actualizan por su
identificador externo, nunca se duplican.

Opciones:

```bash
# Solo algunas entidades
./vendor/bin/sail artisan rickandmorty:sync --only=locations --only=episodes

# Volver a procesar el JSON crudo guardado en la última descarga, sin tocar la red
./vendor/bin/sail artisan rickandmorty:sync --from-raw
```

Si alguna página falla (red, límite de peticiones, respuesta inesperada) el comando
continúa con las siguientes, muestra el detalle al final y termina con código de
salida 1. Basta con volver a ejecutarlo para completar lo que faltó.

### Otros comandos

```bash
# Ejecutar los tests
./vendor/bin/sail test

# Parar los contenedores
./vendor/bin/sail down
```

Para no escribir `./vendor/bin/sail` cada vez se puede definir un alias:

```bash
alias sail='./vendor/bin/sail'
```

## Decisiones de diseño

Se irán documentando en este apartado a medida que avance el desarrollo.
