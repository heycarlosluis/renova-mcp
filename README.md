# Renova MCP Control

Servidor **MCP (Model Context Protocol)** para controlar este WordPress de forma remota
desde cualquier cliente MCP (Claude, etc.). Construido sobre la **Abilities API** del núcleo
de WordPress y la librería oficial [WordPress/mcp-adapter](https://github.com/WordPress/mcp-adapter).

## Requisitos

- WordPress **6.9+** (la Abilities API está integrada en el núcleo desde 6.9). Probado hasta 7.1.
- PHP **7.4+**.
- `wordpress/mcp-adapter` `^0.6` (viaja versionado en `vendor/`, no requiere Composer en el servidor).

## Instalación en cualquier sitio WordPress

Este plugin es **genérico y reutilizable**: funciona en cualquier WordPress, no depende de
un dominio concreto. Para instalarlo en un sitio nuevo:

1. Copia la carpeta `renova-mcp` a `wp-content/plugins/` (por SFTP, ZIP o Git). El directorio
   `vendor/` viaja en el repo, así que **no necesitas ejecutar Composer en el servidor**.
2. Actívalo en *Plugins*.
3. En **Renova MCP → Tokens**, crea un token de acceso para tu cliente (ver Autenticación).
4. Configura tu cliente MCP con la URL del endpoint y ese token.

Las herramientas de ACF, Elementor y Rank Math **solo se exponen** si ese plugin está
activo: un sitio sin ninguno de los tres publica 55 herramientas en lugar de 117.

Tras activarlo, verás una entrada **Renova MCP** en el menú de administración con cuatro
secciones:

- **Conexión**: endpoint con botón de copiar y configuraciones listas para Claude Desktop,
  Claude Code, Cursor, Codex/ChatGPT, Poke y conectores con OAuth.
- **Tokens**: crear y revocar tokens de acceso y ver los clientes conectados por OAuth.
- **Herramientas y seguridad**: grupos expuestos, modo solo lectura, límite de peticiones
  y OAuth.
- **Actividad**: registro de quién ejecutó qué herramienta y con qué resultado.

## Endpoint

Una vez activado el plugin, el servidor MCP queda disponible en:

```
https://TU-DOMINIO/wp-json/renova-mcp/v1/mcp
```

> El servidor por defecto del adaptador (`/wp-json/mcp/mcp-adapter-default-server`) se
> desactiva automáticamente: expondría abilities `discover`/`execute` sobre cualquier
> ability registrada. Solo queda expuesto el endpoint controlado de Renova.

## Transporte

El endpoint habla **Streamable HTTP**, el transporte estándar de MCP (versiones de protocolo
2025-11-25, 2025-06-18 y 2024-11-05). No existe una ruta `/sse`: ese es el transporte
anterior, ya retirado de la especificación. Un cliente que pida "URL del servidor MCP"
funciona con la URL de arriba tal cual.

## Autenticación

Las herramientas exigen un usuario **administrador** (`manage_options`). Hay tres formas de
identificarse; todas requieren HTTPS (o un entorno marcado como `local`).

### Token de acceso (recomendado)

En **Renova MCP → Tokens** crea un token por cliente. Se envía como
`Authorization: Bearer rmcp_…` y funciona con cualquier cliente que acepte una clave de API,
incluidos los que no permiten Basic Auth (por ejemplo, Poke).

- El token se muestra **una sola vez**; en la base de datos solo se guarda su hash SHA-256.
- Puedes limitarlo a ciertos **grupos de herramientas**, marcarlo como **solo lectura** y
  darle **caducidad**. Un token limitado ni siquiera ve en `tools/list` lo que no puede usar.
- El alcance solo restringe: nunca da más permisos que los del usuario que creó el token.

### Contraseña de aplicación

Sigue funcionando la autenticación Basic con una contraseña de aplicación de WordPress
(**Usuarios → Perfil → Contraseñas de aplicación**): usuario = tu login, contraseña = la
contraseña de aplicación.

### OAuth 2.1 (opcional)

Para clientes que solo piden la URL y abren el navegador para autorizar (conectores de
Claude.ai, ChatGPT…). Se activa en **Herramientas y seguridad**; viene **desactivado**.

- Descubrimiento en `/.well-known/oauth-protected-resource` y
  `/.well-known/oauth-authorization-server`, registro dinámico de clientes, código de
  autorización con **PKCE S256** obligatorio y rotación del token de refresco.
- Solo un administrador con sesión iniciada puede aprobar una conexión, eligiendo grupos y
  solo lectura en la pantalla de consentimiento. Se revoca desde **Tokens**.
- Requiere enlaces permanentes distintos de "Simple" y que WordPress esté en la raíz del
  dominio (las direcciones `/.well-known/` se sirven desde ahí).

## Seguridad

- **Grupos de herramientas**: Contenido, Ajustes del sitio, Usuarios, Plugins/temas/
  actualizaciones, ACF, Elementor y Rank Math. Cada uno se puede desactivar entero.
- **Modo solo lectura**: oculta y bloquea todo lo que crea, modifica o elimina.
- **Anotaciones**: cada herramienta declara si es de lectura, destructiva e idempotente
  (`readOnlyHint`, `destructiveHint`, `idempotentHint`) para que el cliente sepa cuándo
  pedir confirmación.
- **Límite de peticiones**: 120 por minuto y credencial por defecto (30 por IP sin
  autenticar); al superarlo responde `429` con `Retry-After`.
- **Registro de actividad**: llamadas a herramientas, inicios de sesión MCP y tokens
  rechazados, con usuario, credencial, IP, resultado y duración. De los argumentos solo se
  guardan los identificadores (id, slug, clave…); del resto, el tipo y el tamaño. Se
  conserva 30 días por defecto.

Filtros disponibles: `renova_mcp_is_secure_request` (sitios detrás de un proxy que termina
TLS) y `renova_mcp_client_ip` (IP real detrás de un proxy de confianza).

## Pruebas

```bash
bash tests/run.sh
```

Monta un WordPress desechable sobre SQLite (sin Docker ni MySQL), activa el plugin y
recorre el protocolo de extremo a extremo: autenticación, transporte, listado y ejecución
de herramientas, alcance de los tokens, flujo OAuth completo, límite de peticiones y
registro de actividad. Requiere `php` (con `sqlite3` y `curl`), `wp-cli`, `curl` y `unzip`.

## Herramientas disponibles

| Herramienta | Acción |
|-------------|--------|
| `get-site-info` | Datos generales del sitio |
| `list-plugins` | Lista plugins y su estado |
| `activate-plugin` / `deactivate-plugin` | Activar / desactivar un plugin |
| `install-plugin` | Instalar desde WordPress.org (por slug) |
| `delete-plugin` | Eliminar un plugin del servidor |
| `list-themes` / `activate-theme` | Listar / cambiar el tema activo |
| `list-posts` | Listar entradas, páginas o CPT |
| `get-post` | Obtener un contenido completo (campos, meta y términos) |
| `create-post` / `update-post` / `delete-post` | Crear / editar / borrar contenido (cualquier `post_type`) |
| `get-post-meta` / `update-post-meta` / `delete-post-meta` | Leer / escribir / borrar metadatos de contenido |
| `get-option` / `update-option` | Leer / escribir opciones del sitio |

### Usuarios y medios

| Herramienta | Acción |
|-------------|--------|
| `list-users` / `get-user` | Listar / obtener usuarios (filtro por rol, búsqueda) |
| `create-user` / `update-user` / `delete-user` | Crear / editar (rol, contraseña) / eliminar usuarios |
| `list-media` / `get-media` | Listar / obtener adjuntos de la biblioteca |
| `upload-media` | Importar un archivo desde una URL a la biblioteca |
| `delete-media` | Eliminar un adjunto |
| `set-featured-image` | Fijar / quitar la imagen destacada de una entrada |

### Menús, comentarios y utilidades de sitio

| Herramienta | Acción |
|-------------|--------|
| `list-menus` / `get-menu` / `create-menu` | Gestión de menús de navegación |
| `add-menu-item` / `delete-menu-item` / `assign-menu-location` | Elementos y ubicaciones de menú |
| `list-comments` / `moderate-comment` / `delete-comment` | Moderar comentarios (approve/hold/spam/trash) |
| `get-permalink-structure` / `update-permalink-structure` | Leer / cambiar la estructura de enlaces permanentes |
| `flush-rewrite-rules` | Refrescar reglas de reescritura |
| `get-theme-mod` / `update-theme-mod` | Ajustes del personalizador del tema |
| `manage-transient` | Leer / fijar / borrar transients |
| `check-updates` / `run-update` | Comprobar y ejecutar actualizaciones de plugins/temas |
| `flush-cache` | Vaciar la caché de objetos |

### Tipos de contenido y taxonomías (nativo de WordPress)

| Herramienta | Acción |
|-------------|--------|
| `list-post-types` | Listar todos los post types registrados |
| `list-taxonomies` | Listar todas las taxonomías registradas |
| `list-terms` / `get-term` | Listar / obtener términos de una taxonomía |
| `create-term` / `update-term` / `delete-term` | Crear / editar / borrar términos |
| `set-post-terms` / `get-post-terms` | Asignar / leer términos de una entrada o CPT |

### Herramientas ACF (solo si Advanced Custom Fields está activo)

| Herramienta | Función ACF |
|-------------|-------------|
| `acf-get-field` / `acf-update-field` / `acf-delete-field` | `get_field` / `update_field` / `delete_field` |
| `acf-get-field-object` / `acf-get-field-objects` | `get_field_object` / `get_field_objects` |
| `acf-get-fields` | `get_fields` |
| `acf-add-row` / `acf-update-row` / `acf-delete-row` | `add_row` / `update_row` / `delete_row` (repeater) |
| `acf-list-field-groups` / `acf-get-field-group` | `acf_get_field_groups` / `acf_get_field_group` |
| `acf-get-fields-in-group` | `acf_get_fields` |
| `acf-create-field-group` / `acf-update-field-group` / `acf-delete-field-group` | `acf_import_field_group` / `acf_update_field_group` / `acf_delete_field_group` |
| `acf-export-field-group` | `acf_export_field_group` |

#### Custom Post Types y taxonomías vía ACF (ACF 6.1+)

| Herramienta | Función ACF |
|-------------|-------------|
| `acf-list-post-types` / `acf-get-post-type` | `acf_get_acf_post_types` / `acf_get_post_type` |
| `acf-save-post-type` / `acf-delete-post-type` | `acf_update_post_type` / `acf_delete_post_type` |
| `acf-export-post-type` | `acf_prepare_post_type_for_export` |
| `acf-list-taxonomies` / `acf-get-taxonomy` | `acf_get_acf_taxonomies` / `acf_get_taxonomy` |
| `acf-save-taxonomy` / `acf-delete-taxonomy` | `acf_update_taxonomy` / `acf_delete_taxonomy` |
| `acf-export-taxonomy` | `acf_prepare_taxonomy_for_export` |

### Herramientas Elementor / Elementor Pro (solo si Elementor está activo)

Operan directamente sobre el árbol JSON de `_elementor_data`, regenerando la caché de CSS
tras cada cambio. Si Elementor no está activo, estas herramientas no se exponen.

| Herramienta | Acción |
|-------------|--------|
| `elementor-list-content` | Listar páginas/CPT indicando cuáles usan Elementor |
| `elementor-get-data` | Árbol JSON completo de una página |
| `elementor-get-structure` | Esquema simplificado (id, tipo, widgetType, vista previa de texto) |
| `elementor-get-element` | Un elemento concreto por su id |
| `elementor-find-elements` | Buscar elementos por `widgetType` o `elType` |
| `elementor-get-page-settings` | Ajustes de página (`_elementor_page_settings`) |
| `elementor-update-data` | Reemplazar todo el árbol (con copia de seguridad automática) |
| `elementor-update-element` | Fusionar/reemplazar ajustes de un elemento o widget |
| `elementor-add-element` | Añadir sección, contenedor, columna o widget |
| `elementor-delete-element` | Eliminar un elemento (con copia de seguridad) |
| `elementor-move-element` | Mover un elemento a otro padre/posición |
| `elementor-duplicate-element` | Clonar un elemento con nuevos IDs |
| `elementor-reorder-elements` | Reordenar los hijos de un padre |
| `elementor-update-page-settings` | Fusionar ajustes de página |
| `elementor-backup-data` / `elementor-restore-backup` | Copia de seguridad / restauración del árbol |
| `elementor-clear-cache` | Regenerar el CSS (global o por página) |
| `elementor-list-templates` / `elementor-get-template` | Listar / obtener plantillas de la biblioteca |
| `elementor-create-template` | Crear plantilla (desde JSON o copiando una página) |
| `elementor-apply-template` | Aplicar una plantilla a una página (`replace` o `append`) |
| `elementor-get-global-settings` / `elementor-update-global-settings` | Kit global: colores y tipografías globales (Pro) |

### Herramientas Rank Math SEO (solo si Rank Math está activo)

Controlan los datos de Rank Math directamente (post-meta `rank_math_*`, opciones y tablas
de redirecciones / monitor 404). Si Rank Math no está activo, cada herramienta devuelve un
`WP_Error` claro.

| Herramienta | Acción |
|-------------|--------|
| `rankmath-get-post-seo` / `rankmath-update-post-seo` | Leer / fijar título, descripción, keyword, robots, canonical, OG, Twitter |
| `rankmath-get-post-schema` / `rankmath-set-post-schema` / `rankmath-delete-post-schema` | Marcado estructurado (Schema) por entrada |
| `rankmath-get-settings` / `rankmath-update-settings` | Ajustes globales (general, titles, sitemap) |
| `rankmath-list-redirections` / `rankmath-add-redirection` | Listar / crear redirecciones |
| `rankmath-update-redirection` / `rankmath-delete-redirection` | Editar / borrar redirecciones |
| `rankmath-get-404-log` / `rankmath-clear-404-log` | Leer / vaciar el monitor de errores 404 |

## Desarrollo

Las dependencias se gestionan con Composer y **se versionan en `vendor/`** para que el
despliegue por Git en Hostinger no requiera ejecutar Composer en el servidor.

```bash
composer install   # dentro de wp-content/plugins/renova-mcp
```

## Seguridad

Este plugin otorga control administrativo total del sitio a quien tenga credenciales válidas.
Usa siempre HTTPS, limita las contraseñas de aplicación a usuarios de confianza y revócalas
cuando dejes de usarlas.

Medidas de seguridad integradas:

- El servidor por defecto del adaptador MCP queda desactivado (solo se expone el endpoint de Renova).
- `delete-user` impide eliminar tu propio usuario o al último administrador del sitio.
- Los listados tienen un límite máximo de resultados para evitar consultas excesivas.
- Todos los endpoints exigen `manage_options` (administrador).
