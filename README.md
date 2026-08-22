# Rick and Morty API

Prueba técnica backend en **Laravel 13**. La aplicación consume la API pública de
[Rick and Morty](https://rickandmortyapi.com), sincroniza personajes, episodios y
localizaciones en una base de datos propia y expone una API REST con registro de
usuarios, autenticación por tokens y gestión de personajes favoritos.

- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Sincronizar los datos](#sincronizar-los-datos)
- [Documentación de la API](#documentación-de-la-api)
- [Endpoints](#endpoints)
- [Autenticación](#autenticación)
- [Formato de errores](#formato-de-errores)
- [Tests](#tests)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Decisiones de diseño](#decisiones-de-diseño)
- [Limitaciones conocidas y posibles mejoras](#limitaciones-conocidas-y-posibles-mejoras)
- [Configuración](#configuración)

## Requisitos

- Docker (Docker Desktop en macOS/Windows o Docker Engine en Linux).
- No hace falta PHP, Composer, MySQL ni Node en la máquina: todo corre en
  contenedores a través de Laravel Sail. El proyecto no tiene frontend ni
  necesita compilar assets.

## Instalación

```bash
git clone <url-del-repositorio> rick-and-morty-api
cd rick-and-morty-api

# 1. Dependencias de Composer, usando un contenedor temporal con PHP y Composer
docker run --rm \
    -v "$(pwd)":/opt -w /opt \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs

# 2. Variables de entorno (vienen preparadas para Sail)
cp .env.example .env

# 3. Levantar los contenedores: aplicación PHP 8.5 + MySQL 8.4
./vendor/bin/sail up -d

# 4. Clave de la aplicación y tablas
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate

# 5. Descargar los datos de Rick and Morty (unos 20 segundos)
./vendor/bin/sail artisan rickandmorty:sync
```

La aplicación queda en `http://localhost:8000`. Esa misma URL muestra la
documentación interactiva de la API.

> La aplicación se publica en el puerto 8000 (`APP_PORT`) y MySQL en el 3306
> (`FORWARD_DB_PORT`). Si alguno está ocupado, cámbialos en `.env` antes de
> `sail up`. La primera vez que se arranca, Docker construye la imagen de PHP y
> tarda unos minutos.

Para no escribir `./vendor/bin/sail` cada vez:

```bash
alias sail='./vendor/bin/sail'
```

Comandos habituales:

```bash
./vendor/bin/sail up -d      # arrancar
./vendor/bin/sail down       # parar (los datos de MySQL se conservan)
./vendor/bin/sail test       # tests
./vendor/bin/sail shell      # terminal dentro del contenedor
./vendor/bin/sail mysql      # cliente MySQL (base "laravel", usuario "sail")
```

## Sincronizar los datos

```bash
./vendor/bin/sail artisan rickandmorty:sync
```

Descarga localizaciones, episodios y personajes (en ese orden, porque los
personajes referencian a los otros dos) y los guarda en la base de datos. Al
terminar muestra un resumen:

```
+------------+------------+------------------+-----------+-------------+
| Entidad    | Páginas OK | Páginas fallidas | Registros | Descartados |
+------------+------------+------------------+-----------+-------------+
| locations  | 7          | 0                | 126       | 0           |
| episodes   | 3          | 0                | 51        | 0           |
| characters | 42         | 0                | 826       | 0           |
+------------+------------+------------------+-----------+-------------+
```

Se puede ejecutar tantas veces como se quiera: los registros se crean o
actualizan por su identificador externo y nunca se duplican. Entre página y
página se espera 250 ms para respetar el límite de peticiones de la fuente.

Opciones:

```bash
# Solo algunas entidades (separadas por comas o repitiendo la opción)
./vendor/bin/sail artisan rickandmorty:sync --only=locations,episodes

# Volver a procesar el JSON crudo guardado en la última descarga, sin tocar la red
./vendor/bin/sail artisan rickandmorty:sync --from-raw
```

Si alguna página falla (red, límite de peticiones, respuesta inesperada) el
comando continúa con las siguientes, muestra el detalle al final y termina con
código de salida 1. Basta con volver a ejecutarlo para completar lo que faltó.
Un registro con formato incorrecto se descarta con un aviso sin afectar al resto
de su página.

## Documentación de la API

- `http://localhost:8000` — documentación interactiva (Swagger UI) desde la que
  se pueden probar todos los endpoints. El botón **Authorize** acepta el token
  devuelto por el registro o el inicio de sesión.
- `http://localhost:8000/api/docs` — la especificación OpenAPI 3.1 en YAML
  (`docs/openapi.yaml`).

Un test comprueba que las rutas documentadas coinciden exactamente con las
registradas en la aplicación, así que la documentación no puede quedarse
desactualizada sin que la suite falle.

## Endpoints

Todas las rutas cuelgan de `/api`. Las marcadas con 🔒 requieren el token.

| Método | Ruta | Descripción |
|---|---|---|
| `POST` | `/auth/register` | Crea un usuario y devuelve un token |
| `POST` | `/auth/login` | Devuelve un token nuevo (10 intentos por minuto e IP) |
| `GET` 🔒 | `/auth/me` | Usuario autenticado |
| `POST` 🔒 | `/auth/logout` | Invalida el token usado |
| `GET` | `/characters` | Listado. Filtros: `name`, `status`, `species`, `gender` |
| `GET` | `/characters/{id}` | Detalle con origen, ubicación actual y episodios |
| `GET` | `/episodes` | Listado. Filtros: `name`, `code`, `season` |
| `GET` | `/episodes/{id}` | Detalle con personajes |
| `GET` | `/locations` | Listado. Filtros: `name`, `type`, `dimension` |
| `GET` | `/locations/{id}` | Detalle con residentes (personajes cuya ubicación actual es esta) |
| `GET` 🔒 | `/favorites` | Favoritos del usuario, los más recientes primero |
| `POST` 🔒 | `/favorites` | Añade un favorito (`{"character_id": 2}`) → 201, 409 si ya lo era |
| `DELETE` 🔒 | `/favorites/{id}` | Quita un favorito → 204, 404 si no lo era |

Los listados se paginan con `page` y `per_page` (1–100, por defecto 20) y
devuelven `data`, `links` y `meta`. Los filtros se combinan con AND; `name` busca
coincidencias parciales, el resto son exactas. Un filtro con valor no permitido
(por ejemplo `status=Zombie`) responde 422.

```bash
curl 'http://localhost:8000/api/characters?status=Alive&species=Human&per_page=2'
curl 'http://localhost:8000/api/episodes?season=3'
curl 'http://localhost:8000/api/locations/35'
```

## Autenticación

Sistema propio de tokens, sin paquetes externos (ver [decisiones](#decisiones-de-diseño)).

```bash
# Registro (o POST /api/auth/login con email y password)
curl -X POST http://localhost:8000/api/auth/register \
     -H 'Content-Type: application/json' \
     -d '{"name":"Rick","email":"rick@example.com","password":"wubba-lubba-dub-dub"}'
```

```json
{
  "token": "91ujwUPE7NAy...",
  "token_type": "Bearer",
  "expires_at": "2026-09-21T09:16:08+00:00",
  "user": { "id": 1, "name": "Rick", "email": "rick@example.com", "created_at": "..." }
}
```

El token se devuelve **una sola vez**; en base de datos solo se guarda su hash.
Se envía en cada petición protegida:

```bash
curl http://localhost:8000/api/favorites -H 'Authorization: Bearer 91ujwUPE7NAy...'
curl -X POST http://localhost:8000/api/favorites \
     -H 'Authorization: Bearer 91ujwUPE7NAy...' \
     -H 'Content-Type: application/json' \
     -d '{"character_id": 2}'
```

Los tokens caducan a los 30 días (`API_TOKEN_LIFETIME_DAYS`; `0` = no caducan).
Cada inicio de sesión emite un token distinto y cerrar sesión solo invalida el
token usado, de modo que otros dispositivos no se ven afectados.

## Formato de errores

Todas las respuestas de error de la API tienen la misma forma, tanto si
`APP_DEBUG` está activo como si no (la traza va al log, nunca a la respuesta):

```json
{ "message": "Not Found." }
```

Los errores de validación (422) añaden los fallos por campo:

```json
{
  "message": "The email field is required. (and 1 more error)",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password field must be at least 8 characters."]
  }
}
```

| Código | Cuándo |
|---|---|
| 401 | Falta el token, no existe, ha caducado o se ha revocado; credenciales incorrectas |
| 404 | Registro o ruta inexistente; quitar un favorito que no lo era |
| 405 | Método no permitido |
| 409 | Añadir un favorito que ya lo era |
| 422 | Datos de entrada o filtros no válidos |
| 429 | Límite de peticiones superado (60/min por usuario o IP; 10/min en el login) |
| 500 | Error interno (mensaje genérico, detalle en `storage/logs/laravel.log`) |

## Tests

```bash
./vendor/bin/sail test
```

Los tests corren contra MySQL (base `testing`, que Sail crea automáticamente),
así que hace falta tener los contenedores levantados. Son 126 tests agrupados
por capa:

| Carpeta | Qué cubre |
|---|---|
| `tests/Unit/RickAndMorty` | Conversión de valores externos, DTOs y parser de páginas: campos ausentes, tipos incorrectos, fechas no interpretables, URLs mal formadas, registros descartados sin perder la página |
| `tests/Feature/RickAndMorty` | Cliente HTTP con `Http::fake()`: reintentos ante 5xx y 429, sin reintento en 404, error de conexión, cuerpo que no es JSON, estructura inesperada |
| `tests/Feature/Sync` | Comando de sincronización: orden de entidades, idempotencia, actualización de cambios, paginación, fallo parcial de una página, JSON crudo y reproceso sin red |
| `tests/Feature/Models` | Relaciones y claves foráneas |
| `tests/Feature/Auth`, `tests/Unit/Auth` | Registro, login, tokens (emisión, caducidad, revocación), límite de intentos |
| `tests/Feature/Catalog` | Listados, filtros, paginación, detalles, 404, filtros inválidos |
| `tests/Feature/Favorites` | Añadir, listar, eliminar, duplicados, aislamiento entre usuarios |
| `tests/Feature/Api` | Formato de errores y coherencia de la documentación OpenAPI con las rutas |

Ningún test toca la red: `tests/TestCase.php` activa
`Http::preventStrayRequests()`, por lo que cualquier petición real no simulada
hace fallar el test. Las respuestas de la API externa se simulan con fixtures
reales recortadas (`tests/Fixtures/rickandmorty/`), que incluyen los casos
especiales de la fuente (origen desconocido, campos vacíos).

El estilo de código se comprueba con Pint (incluido en Laravel):

```bash
./vendor/bin/sail php vendor/bin/pint --test
```

## Estructura del proyecto

```
app/
├── Console/Commands/SyncRickAndMortyCommand.php   Comando rickandmorty:sync (solo opciones y salida)
├── Exceptions/ApiExceptionRenderer.php            Formato único de error para /api/*
├── Http/
│   ├── Controllers/Api/                           Auth, Character, Episode, Location, Favorite
│   ├── Requests/                                  Validación de entrada (auth, filtros de listado, favoritos)
│   └── Resources/                                 Forma del JSON de salida
├── Models/                                        Character, Episode, Location, User, ApiToken, RawApiPage
├── Services/
│   ├── RickAndMorty/                              Integración con la API externa
│   │   ├── RickAndMortyClient.php                 HTTP: timeout, reintentos, errores controlados
│   │   ├── ResponseParser.php                     JSON de una página → DTOs
│   │   ├── ExternalValueParser.php                "" → null, URL → id, texto → fecha
│   │   ├── DTO/                                   CharacterData, EpisodeData, LocationData, PageData
│   │   └── Exceptions/                            RickAndMortyApiException, InvalidExternalDataException
│   ├── Sync/SyncService.php                       Recorre páginas, guarda JSON crudo, upsert, relaciones
│   └── Auth/ApiTokenService.php                   Emisión, validación y revocación de tokens
database/migrations/                               locations, episodes, characters, character_episode,
                                                   raw_api_pages, api_tokens, favorites
docs/openapi.yaml                                  Especificación OpenAPI 3.1
routes/api.php                                     Rutas de la API
tests/                                             Ver sección Tests
```

Flujo de una sincronización: `SyncService` pide una página a
`RickAndMortyClient`, que hace la petición HTTP y entrega el JSON a
`ResponseParser`; este valida la estructura y convierte cada registro en un DTO
(`CharacterData`, …). `SyncService` guarda el JSON crudo, abre una transacción y
hace `upsert` de los registros por `external_id`; para los personajes traduce los
identificadores externos de localizaciones y episodios a claves propias y
sincroniza la tabla pivote.

## Decisiones de diseño

**Identificador externo separado de la clave primaria.** Cada entidad tiene su
`id` autoincremental y una columna `external_id` única con el identificador de la
API de Rick and Morty. La aplicación funciona con sus propias claves (los
favoritos apuntan a `characters.id`), de forma que un cambio en la fuente no rompe
nada, y `external_id` es la clave de idempotencia de la sincronización: se hace
`upsert` por esa columna y el índice único garantiza físicamente que no haya
duplicados.

**Campos ausentes o inconsistentes de la fuente.** La API usa la cadena vacía
cuando no tiene un dato (`type` en 401 de los 826 personajes) y el literal
`unknown` cuando el dato es desconocido a propósito (`status`, `gender`, la
`dimension` de 29 localizaciones). Se normaliza `""` a `NULL` y se conserva
`unknown` tal cual, porque es un valor con significado en el dominio. Las
localizaciones de origen o actuales que vienen como `unknown` sin identificador
(300 y 21 personajes) se guardan como claves foráneas nulas. Las fechas que no se
pueden interpretar quedan a `NULL` sin descartar el episodio; en cambio un código
de episodio que no cumpla el formato `S01E01` descarta el registro, porque es una
clave única.

**Validación antes de procesar.** Cada registro de la fuente pasa por el
validador de Laravel con reglas declarativas (`'id' => ['required', 'integer']`,
…) antes de construir el DTO. Un registro inválido se descarta con su motivo y
la página se procesa igualmente; una página sin `info` y `results` se da por
fallida y la sincronización continúa con la siguiente. El modelo interno nunca
ve el formato del proveedor: las relaciones llegan como URLs y se convierten en
identificadores, y el campo `episode` de la fuente se llama `code` en la
aplicación.

**Tolerancia a fallos del servicio remoto.** El cliente aplica un tiempo de
espera de 10 segundos y reintenta hasta 3 veces con espera creciente ante errores
de red, 429 y 5xx; un 404 no se reintenta. Un cuerpo que no sea JSON (la fuente
responde `error code: 1015` en texto plano cuando se supera su límite de
peticiones) produce una excepción controlada. La sincronización espera 250 ms
entre páginas para no alcanzar ese límite, guarda cada página dentro de una
transacción y, si una falla, sigue con las demás y lo informa al final.

**Relaciones.** La tabla pivote `character_episode` tiene clave primaria
compuesta, así que no puede contener duplicados. Se construye desde el lado de
los personajes (`character.episode`) y se sincroniza con `sync()` para que
refleje la fuente, porque la API es inconsistente entre sus dos lados: hay tres
episodios cuyo listado de personajes no coincide con el de los personajes. Los
residentes de una localización no se guardan: se derivan de la ubicación actual
de los personajes, como pide el enunciado, lo que evita mantener el mismo dato en
dos sitios (la fuente también tiene una discrepancia ahí).

**JSON crudo.** Cada página descargada se guarda tal cual en `raw_api_pages`
antes de procesarla. Permite reprocesar con `--from-raw` sin volver a descargar
(por ejemplo, tras cambiar la lógica de transformación) y ver qué envió
exactamente la fuente si algo no cuadra.

**Autenticación propia.** Al registrarse o iniciar sesión se genera un token
aleatorio de 64 caracteres; en base de datos se guarda solo su hash SHA-256,
igual que hacen Sanctum o GitHub, de modo que una fuga de la tabla no sirve para
nada. SHA-256 y no bcrypt porque el token ya es imposible de adivinar y así se
puede buscar por índice. El guard se registra con `Auth::viaRequest()`, una
función del propio framework, por lo que las rutas usan `auth:api` y
`$request->user()` como con cualquier otro guard. Un inicio de sesión fallido
responde 401 con el mismo mensaje exista o no el email, y está limitado a 10
intentos por minuto.

**Economía de dependencias.** No se ha añadido ningún paquete: el cliente HTTP,
la validación, los límites de peticiones, el guard de autenticación y las
factories son del framework, y la documentación OpenAPI está escrita a mano con
un test que la mantiene alineada con las rutas. La única dependencia añadida es
`laravel/sail` en desarrollo, requerida por el enunciado. La página de
documentación carga Swagger UI desde un CDN; es opcional y no forma parte de la
API.

**Formato de error único.** Una clase (`ApiExceptionRenderer`) traduce cualquier
excepción de `/api/*` a `{"message": …}`, con `errors` en validación, y oculta
siempre la traza y los nombres de clases internas (Laravel incluía el nombre del
modelo en los 404).

**Separación de responsabilidades.** Los controladores tienen dos o tres líneas:
validan con un FormRequest, delegan en un scope del modelo o en un servicio y
devuelven un Resource. El comando de consola solo lee opciones y pinta el
resumen; la lógica está en `SyncService`, que es un objeto normal sin estado y
fácil de testear.

## Limitaciones conocidas y posibles mejoras

- Los registros que desaparezcan de la fuente no se eliminan de la base de datos.
  Es deliberado (borrar podría dejar favoritos huérfanos); un paso final que
  compare los `external_id` descargados con los existentes lo resolvería.
- Si falla la primera página de una entidad no se conoce cuántas páginas hay, así
  que esa entidad se detiene; el comando lo informa y basta con relanzarlo.
- La sincronización es completa en cada ejecución (52 peticiones, unos 20
  segundos). Para volúmenes mayores se podría paralelizar con colas o
  sincronizar solo los cambios.
- Los tokens no se limpian automáticamente al caducar; un comando programado
  podría borrarlos.

## Configuración

Variables de `.env` propias del proyecto (todas con valor por defecto):

| Variable | Por defecto | Descripción |
|---|---|---|
| `APP_PORT` | `8000` | Puerto en el que Sail publica la aplicación |
| `FORWARD_DB_PORT` | `3306` | Puerto en el que Sail publica MySQL |
| `RICKANDMORTY_BASE_URL` | `https://rickandmortyapi.com/api` | URL base de la API externa |
| `RICKANDMORTY_TIMEOUT` | `10` | Segundos de espera máxima por petición |
| `RICKANDMORTY_MAX_ATTEMPTS` | `3` | Intentos por petición ante errores de red, 429 o 5xx |
| `RICKANDMORTY_RETRY_DELAY_MS` | `500` | Espera base entre intentos (se multiplica por el número de intento) |
| `RICKANDMORTY_DELAY_BETWEEN_PAGES_MS` | `250` | Pausa entre páginas durante la sincronización |
| `API_TOKEN_LIFETIME_DAYS` | `30` | Días de validez de los tokens (`0` = no caducan) |
