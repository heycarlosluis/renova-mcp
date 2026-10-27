# Referencia de herramientas

Catálogo completo de Renova MCP Control 1.4.0: **117 herramientas**.

Generado desde las abilities registradas por [scripts/generate-tool-reference.php](../scripts/generate-tool-reference.php). No editar a mano. Los parámetros, valores predeterminados y anotaciones reflejan el registro; consulta las [notas de comportamiento](behavior.md) para las condiciones adicionales de ejecución.

El nombre usado en `tools/call` lleva el prefijo `renova-mcp-`; el ID de la Abilities API usa `renova-mcp/`. Este catálogo incluye integraciones inactivas: `tools/list` devuelve solo las herramientas disponibles para la configuración y credencial actuales.

[Inicio](../README.md) · [Conexión](connection.md) · [Seguridad](security.md) · [Esquemas completos en JSON](tools.json)

| Grupo | Total | Lectura | Escritura |
| --- | ---: | ---: | ---: |
| [Contenido](#content) | 31 | 13 | 18 |
| [Ajustes del sitio](#site) | 10 | 4 | 6 |
| [Usuarios](#users) | 5 | 2 | 3 |
| [Plugins, temas y actualizaciones](#system) | 9 | 3 | 6 |
| [Advanced Custom Fields](#acf) | 26 | 14 | 12 |
| [Elementor](#elementor) | 23 | 9 | 14 |
| [Rank Math SEO](#rankmath) | 13 | 5 | 8 |

<a id="content"></a>

## Contenido

Entradas, páginas y CPT, metadatos, términos, medios, menús y comentarios.

### `renova-mcp-list-posts`

Lista entradas o páginas con filtros de tipo, estado, búsqueda y cantidad.

Ability: `renova-mcp/list-posts`. [Implementación](../includes/class-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `post_type` | string | No | `"post"` | Tipo de contenido (post, page, etc.). |
| `status` | string | No | `"any"` | Estado (publish, draft, pending, private, any). |
| `search` | string | No | — | Término de búsqueda. |
| `number` | integer | No | `20` | Número máximo de resultados. |

### `renova-mcp-get-post`

Devuelve una entrada/página/CPT completa por su ID: campos del post, meta y términos de todas sus taxonomías.

Ability: `renova-mcp/get-post`. [Implementación](../includes/class-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID del contenido. |

### `renova-mcp-create-post`

Crea una nueva entrada o página con título, contenido y estado.

Ability: `renova-mcp/create-post`. [Implementación](../includes/class-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `title` | string | Sí | — | Título. |
| `content` | string | No | — | Contenido (HTML o bloques). |
| `post_type` | string | No | `"page"` | Tipo: "post" o "page". |
| `status` | string | No | `"publish"` | Estado: publish, draft, pending, private. |
| `excerpt` | string | No | — | Extracto opcional. |
| `slug` | string | No | — | Slug/URL amigable opcional. |

### `renova-mcp-update-post`

Actualiza una entrada o página existente por su ID.

Ability: `renova-mcp/update-post`. [Implementación](../includes/class-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID del contenido a actualizar. |
| `title` | string | No | — |  |
| `content` | string | No | — |  |
| `status` | string | No | — |  |
| `excerpt` | string | No | — |  |
| `slug` | string | No | — |  |

### `renova-mcp-delete-post`

Envía a la papelera o elimina definitivamente una entrada o página.

Ability: `renova-mcp/delete-post`. [Implementación](../includes/class-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID del contenido. |
| `force` | boolean | No | `false` | Si es true, elimina definitivamente (sin papelera). |

### `renova-mcp-get-post-meta`

Devuelve los metadatos de una entrada/CPT. Si se indica "key", devuelve solo esa clave; si no, todas. Envuelve get_post_meta().

Ability: `renova-mcp/get-post-meta`. [Implementación](../includes/class-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID del contenido. |
| `key` | string | No | — | Clave meta concreta (opcional). |

### `renova-mcp-update-post-meta`

Crea o actualiza un metadato de una entrada/CPT. Envuelve update_post_meta(). Para campos ACF usa preferiblemente acf-update-field.

Ability: `renova-mcp/update-post-meta`. [Implementación](../includes/class-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID del contenido. |
| `key` | string | Sí | — | Clave meta. |
| `value` | cualquiera | Sí | — | Valor (texto, número, booleano, array u objeto). |

### `renova-mcp-delete-post-meta`

Elimina un metadato de una entrada/CPT. Acción destructiva. Envuelve delete_post_meta().

Ability: `renova-mcp/delete-post-meta`. [Implementación](../includes/class-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID del contenido. |
| `key` | string | Sí | — | Clave meta a eliminar. |

### `renova-mcp-list-post-types`

Lista todos los post types registrados en WordPress (nativos, de ACF y de otros plugins), con su slug, etiqueta y taxonomías asociadas.

Ability: `renova-mcp/list-post-types`. [Implementación](../includes/class-taxonomy-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `public_only` | boolean | No | `false` | Solo tipos públicos. |

### `renova-mcp-list-taxonomies`

Lista todas las taxonomías registradas en WordPress, con su slug, etiqueta, jerarquía y tipos de contenido asociados.

Ability: `renova-mcp/list-taxonomies`. [Implementación](../includes/class-taxonomy-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `post_type` | string | No | — | Filtrar por las taxonomías de un post type concreto. |
| `public_only` | boolean | No | `false` | Solo taxonomías públicas. |

### `renova-mcp-list-terms`

Lista los términos de una taxonomía con filtros de búsqueda, jerarquía y cantidad. Envuelve get_terms().

Ability: `renova-mcp/list-terms`. [Implementación](../includes/class-taxonomy-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `taxonomy` | string | Sí | — | Slug de la taxonomía (ej. "category", "post_tag" o una propia). |
| `search` | string | No | — | Término de búsqueda. |
| `hide_empty` | boolean | No | `false` | Ocultar términos sin contenido asociado. |
| `parent` | integer | No | — | ID del término padre (para taxonomías jerárquicas). |
| `number` | integer | No | `100` | Número máximo de resultados (máx. 500). |

### `renova-mcp-get-term`

Devuelve un término por su ID (y opcionalmente su taxonomía). Envuelve get_term().

Ability: `renova-mcp/get-term`. [Implementación](../includes/class-taxonomy-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `term_id` | integer | Sí | — | ID del término. |
| `taxonomy` | string | No | — | Slug de la taxonomía (ej. "category", "post_tag" o una propia). |

### `renova-mcp-create-term`

Crea un término (categoría, etiqueta o término propio) en una taxonomía. Envuelve wp_insert_term().

Ability: `renova-mcp/create-term`. [Implementación](../includes/class-taxonomy-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `taxonomy` | string | Sí | — | Slug de la taxonomía (ej. "category", "post_tag" o una propia). |
| `name` | string | Sí | — | Nombre del término. |
| `slug` | string | No | — | Slug opcional. |
| `description` | string | No | — | Descripción opcional. |
| `parent` | integer | No | — | ID del término padre (taxonomías jerárquicas). |

### `renova-mcp-update-term`

Actualiza un término existente por su ID. Envuelve wp_update_term().

Ability: `renova-mcp/update-term`. [Implementación](../includes/class-taxonomy-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `term_id` | integer | Sí | — | ID del término. |
| `taxonomy` | string | Sí | — | Slug de la taxonomía (ej. "category", "post_tag" o una propia). |
| `name` | string | No | — |  |
| `slug` | string | No | — |  |
| `description` | string | No | — |  |
| `parent` | integer | No | — |  |

### `renova-mcp-delete-term`

Elimina un término de una taxonomía. Acción destructiva. Envuelve wp_delete_term().

Ability: `renova-mcp/delete-term`. [Implementación](../includes/class-taxonomy-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `term_id` | integer | Sí | — | ID del término. |
| `taxonomy` | string | Sí | — | Slug de la taxonomía (ej. "category", "post_tag" o una propia). |

### `renova-mcp-set-post-terms`

Asigna (o añade) términos de una taxonomía a una entrada/CPT. Acepta IDs, slugs o nombres. Envuelve wp_set_object_terms().

Ability: `renova-mcp/set-post-terms`. [Implementación](../includes/class-taxonomy-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `post_id` | integer | Sí | — | ID de la entrada/CPT. |
| `taxonomy` | string | Sí | — | Slug de la taxonomía (ej. "category", "post_tag" o una propia). |
| `terms` | array | Sí | — | Lista de términos: IDs (integer), slugs o nombres (string). Si la taxonomía no es jerárquica, los nombres inexistentes se crean. |
| `append` | boolean | No | `false` | Si es true, añade a los términos existentes en lugar de reemplazarlos. |

### `renova-mcp-get-post-terms`

Devuelve los términos asignados a una entrada/CPT en una taxonomía. Envuelve wp_get_object_terms().

Ability: `renova-mcp/get-post-terms`. [Implementación](../includes/class-taxonomy-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `post_id` | integer | Sí | — | ID de la entrada/CPT. |
| `taxonomy` | string / array | Sí | — | Slug de la taxonomía o array de slugs. |

### `renova-mcp-list-media`

Lista archivos de la biblioteca de medios con su URL, tipo MIME y título.

Ability: `renova-mcp/list-media`. [Implementación](../includes/class-users-media-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `search` | string | No | — |  |
| `mime_type` | string | No | — | Filtra por tipo MIME (ej. "image", "image/png", "application/pdf"). |
| `number` | integer | No | `30` | Máximo de resultados. |

### `renova-mcp-get-media`

Devuelve los datos de un adjunto: URL, tipo, metadatos, tamaños y texto alternativo.

Ability: `renova-mcp/get-media`. [Implementación](../includes/class-users-media-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID del adjunto. |

### `renova-mcp-upload-media`

Descarga un archivo desde una URL y lo añade a la biblioteca de medios. Opcionalmente lo adjunta a una entrada y fija título/alt.

Ability: `renova-mcp/upload-media`. [Implementación](../includes/class-users-media-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `url` | string | Sí | — | URL del archivo a importar. |
| `post_id` | integer | No | — | ID de la entrada a la que adjuntar (opcional). |
| `title` | string | No | — |  |
| `alt` | string | No | — | Texto alternativo de la imagen. |
| `featured` | boolean | No | `false` | Si es true y hay post_id, lo fija como imagen destacada. |

### `renova-mcp-delete-media`

Elimina un adjunto de la biblioteca de medios (y su archivo). Acción destructiva.

Ability: `renova-mcp/delete-media`. [Implementación](../includes/class-users-media-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID del adjunto. |
| `force` | boolean | No | `true` | Eliminar definitivamente (sin papelera). |

### `renova-mcp-set-featured-image`

Asigna (o quita) la imagen destacada de una entrada usando el ID de un adjunto existente.

Ability: `renova-mcp/set-featured-image`. [Implementación](../includes/class-users-media-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `post_id` | integer | Sí | — | ID de la entrada. |
| `attachment_id` | integer | Sí | — | ID del adjunto. Usa 0 para quitar la imagen destacada. |

### `renova-mcp-list-menus`

Lista los menús de navegación con su número de elementos y ubicaciones asignadas.

Ability: `renova-mcp/list-menus`. [Implementación](../includes/class-site-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

Sin parámetros; envía `arguments: {}`.

### `renova-mcp-get-menu`

Devuelve los elementos de un menú por su ID, nombre o slug.

Ability: `renova-mcp/get-menu`. [Implementación](../includes/class-site-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `menu` | string | Sí | — | ID, nombre o slug del menú. |

### `renova-mcp-create-menu`

Crea un menú de navegación vacío con el nombre indicado.

Ability: `renova-mcp/create-menu`. [Implementación](../includes/class-site-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `name` | string | Sí | — | Nombre del menú. |

### `renova-mcp-add-menu-item`

Añade un elemento a un menú: enlace a una entrada/página (object_id), una URL personalizada, o un término de taxonomía.

Ability: `renova-mcp/add-menu-item`. [Implementación](../includes/class-site-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `menu` | string | Sí | — | ID, nombre o slug del menú. |
| `title` | string | Sí | — | Texto del enlace. |
| `url` | string | No | — | URL personalizada (para enlaces personalizados). |
| `object_id` | integer | No | — | ID de la entrada/página a enlazar. |
| `object` | string | No | — | Tipo de objeto enlazado (post, page, category, etc.). Por defecto "page" si hay object_id. |
| `parent` | integer | No | — | ID del elemento de menú padre (para submenús). |

### `renova-mcp-delete-menu-item`

Elimina un elemento de un menú por su ID.

Ability: `renova-mcp/delete-menu-item`. [Implementación](../includes/class-site-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `item_id` | integer | Sí | — | ID del elemento del menú. |

### `renova-mcp-assign-menu-location`

Asigna un menú a una ubicación registrada por el tema (ej. "primary", "footer").

Ability: `renova-mcp/assign-menu-location`. [Implementación](../includes/class-site-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `menu` | string | Sí | — | ID, nombre o slug del menú. |
| `location` | string | Sí | — | Slug de la ubicación del tema. |

### `renova-mcp-list-comments`

Lista comentarios con filtros de estado (approve, hold, spam, trash) y entrada.

Ability: `renova-mcp/list-comments`. [Implementación](../includes/class-site-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `status` | string | No | `"all"` | approve, hold, spam, trash o all. |
| `post_id` | integer | No | — | Filtra por entrada. |
| `number` | integer | No | `50` | Máximo de resultados. |

### `renova-mcp-moderate-comment`

Cambia el estado de un comentario: approve, hold, spam o trash.

Ability: `renova-mcp/moderate-comment`. [Implementación](../includes/class-site-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID del comentario. |
| `status` | string | Sí | — | approve, hold, spam o trash. |

### `renova-mcp-delete-comment`

Envía a la papelera o elimina definitivamente un comentario.

Ability: `renova-mcp/delete-comment`. [Implementación](../includes/class-site-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID del comentario. |
| `force` | boolean | No | `false` | Eliminar definitivamente. |

<a id="site"></a>

## Ajustes del sitio

Información del sitio, opciones, permalinks, theme mods, transients y caché.

### `renova-mcp-get-site-info`

Devuelve datos generales: nombre, URL, versión de WordPress/PHP, tema activo y conteos de contenido.

Ability: `renova-mcp/get-site-info`. [Implementación](../includes/class-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

Sin parámetros; envía `arguments: {}`.

### `renova-mcp-get-option`

Devuelve el valor de una opción de WordPress por su nombre.

Ability: `renova-mcp/get-option`. [Implementación](../includes/class-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `name` | string | Sí | — | Nombre de la opción (ej. "blogname", "blogdescription"). |

### `renova-mcp-update-option`

Crea o actualiza una opción de WordPress. Úsalo con cuidado.

Ability: `renova-mcp/update-option`. [Implementación](../includes/class-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `name` | string | Sí | — | Nombre de la opción. |
| `value` | cualquiera | Sí | — | Nuevo valor (texto, número o booleano). |

### `renova-mcp-get-permalink-structure`

Devuelve la estructura de enlaces permanentes (permalinks) actual.

Ability: `renova-mcp/get-permalink-structure`. [Implementación](../includes/class-site-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

Sin parámetros; envía `arguments: {}`.

### `renova-mcp-update-permalink-structure`

Cambia la estructura de permalinks y refresca las reglas de reescritura. Ej.: "/%postname%/" activa los enlaces bonitos y hace que /wp-json/ funcione.

Ability: `renova-mcp/update-permalink-structure`. [Implementación](../includes/class-site-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `structure` | string | Sí | — | Estructura, ej. "/%postname%/". Cadena vacía = enlaces simples (query string). |

### `renova-mcp-flush-rewrite-rules`

Regenera las reglas de reescritura (equivalente a guardar los Enlaces permanentes). Útil tras registrar CPT o cambiar permalinks.

Ability: `renova-mcp/flush-rewrite-rules`. [Implementación](../includes/class-site-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

Sin parámetros; envía `arguments: {}`.

### `renova-mcp-get-theme-mod`

Devuelve un ajuste del personalizador (theme mod) del tema activo, o todos si no se indica clave.

Ability: `renova-mcp/get-theme-mod`. [Implementación](../includes/class-site-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `key` | string | No | — | Clave del theme mod (opcional). |

### `renova-mcp-update-theme-mod`

Crea o actualiza un ajuste del personalizador (theme mod) del tema activo.

Ability: `renova-mcp/update-theme-mod`. [Implementación](../includes/class-site-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `key` | string | Sí | — | Clave del theme mod. |
| `value` | cualquiera | Sí | — | Valor. |

### `renova-mcp-manage-transient`

Lee, fija o elimina un transient (caché temporal). action: get, set o delete.

Ability: `renova-mcp/manage-transient`. [Implementación](../includes/class-site-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `action` | string | Sí | — | get, set o delete. |
| `key` | string | Sí | — | Nombre del transient. |
| `value` | cualquiera | No | — | Valor (para set). |
| `expiration` | integer | No | `0` | Caducidad en segundos (para set). 0 = sin caducidad. |

### `renova-mcp-flush-cache`

Vacía la caché de objetos de WordPress (wp_cache_flush).

Ability: `renova-mcp/flush-cache`. [Implementación](../includes/class-site-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

Sin parámetros; envía `arguments: {}`.

<a id="users"></a>

## Usuarios

Listar, crear, editar y eliminar usuarios.

### `renova-mcp-list-users`

Lista usuarios con su rol, email y login. Permite filtrar por rol y buscar.

Ability: `renova-mcp/list-users`. [Implementación](../includes/class-users-media-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `role` | string | No | — | Filtra por rol (administrator, editor, author, etc.). |
| `search` | string | No | — | Término de búsqueda (login, email, nombre). |
| `number` | integer | No | `50` | Máximo de resultados. |

### `renova-mcp-get-user`

Devuelve los datos de un usuario por su ID, login o email.

Ability: `renova-mcp/get-user`. [Implementación](../includes/class-users-media-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | No | — | ID del usuario. |
| `login` | string | No | — | Login del usuario (alternativa a id). |
| `email` | string | No | — | Email del usuario (alternativa a id). |

### `renova-mcp-create-user`

Crea un usuario con login, email, contraseña y rol.

Ability: `renova-mcp/create-user`. [Implementación](../includes/class-users-media-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `username` | string | Sí | — |  |
| `email` | string | Sí | — |  |
| `password` | string | No | — | Contraseña. Si se omite, se genera una segura. |
| `role` | string | No | `"subscriber"` | Rol (subscriber por defecto). |
| `display_name` | string | No | — |  |
| `first_name` | string | No | — |  |
| `last_name` | string | No | — |  |

### `renova-mcp-update-user`

Actualiza datos de un usuario: email, rol, contraseña, nombre. Acepta cualquier combinación.

Ability: `renova-mcp/update-user`. [Implementación](../includes/class-users-media-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID del usuario. |
| `email` | string | No | — |  |
| `password` | string | No | — |  |
| `role` | string | No | — |  |
| `display_name` | string | No | — |  |
| `first_name` | string | No | — |  |
| `last_name` | string | No | — |  |

### `renova-mcp-delete-user`

Elimina un usuario. Opcionalmente reasigna su contenido a otro usuario. Acción destructiva.

Ability: `renova-mcp/delete-user`. [Implementación](../includes/class-users-media-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID del usuario a eliminar. |
| `reassign` | integer | No | — | ID del usuario al que reasignar el contenido (opcional). |

<a id="system"></a>

## Plugins, temas y actualizaciones

Instalar, activar y eliminar plugins, cambiar de tema y actualizar.

### `renova-mcp-list-plugins`

Lista todos los plugins instalados con su estado (activo/inactivo), versión y descripción.

Ability: `renova-mcp/list-plugins`. [Implementación](../includes/class-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

Sin parámetros; envía `arguments: {}`.

### `renova-mcp-activate-plugin`

Activa un plugin instalado.

Ability: `renova-mcp/activate-plugin`. [Implementación](../includes/class-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `plugin` | string | Sí | — | Ruta del plugin (ej. "akismet/akismet.php") o su carpeta (ej. "akismet"). |

### `renova-mcp-deactivate-plugin`

Desactiva un plugin activo.

Ability: `renova-mcp/deactivate-plugin`. [Implementación](../includes/class-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `plugin` | string | Sí | — | Ruta del plugin (ej. "akismet/akismet.php") o su carpeta (ej. "akismet"). |

### `renova-mcp-install-plugin`

Instala un plugin desde el repositorio de WordPress.org por su slug. Opcionalmente lo activa.

Ability: `renova-mcp/install-plugin`. [Implementación](../includes/class-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `slug` | string | Sí | — | Slug del plugin en WordPress.org (ej. "wordpress-seo"). |
| `activate` | boolean | No | `false` | Activar tras instalar. |

### `renova-mcp-delete-plugin`

Desinstala y elimina por completo un plugin del servidor. Acción destructiva.

Ability: `renova-mcp/delete-plugin`. [Implementación](../includes/class-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `plugin` | string | Sí | — | Ruta del plugin (ej. "akismet/akismet.php") o su carpeta (ej. "akismet"). |

### `renova-mcp-list-themes`

Lista los temas instalados e indica cuál está activo.

Ability: `renova-mcp/list-themes`. [Implementación](../includes/class-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

Sin parámetros; envía `arguments: {}`.

### `renova-mcp-activate-theme`

Cambia el tema activo del sitio.

Ability: `renova-mcp/activate-theme`. [Implementación](../includes/class-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `stylesheet` | string | Sí | — | Carpeta del tema (stylesheet), ej. "twentytwentyfive". |

### `renova-mcp-check-updates`

Comprueba actualizaciones disponibles del núcleo, plugins y temas.

Ability: `renova-mcp/check-updates`. [Implementación](../includes/class-site-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

Sin parámetros; envía `arguments: {}`.

### `renova-mcp-run-update`

Actualiza un plugin o un tema a su última versión. type: plugin o theme; target: ruta del plugin o stylesheet del tema.

Ability: `renova-mcp/run-update`. [Implementación](../includes/class-site-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `type` | string | Sí | — | plugin o theme. |
| `target` | string | Sí | — | Ruta del plugin (ej. "akismet/akismet.php") o stylesheet del tema. |

<a id="acf"></a>

## Advanced Custom Fields

Valores de campos, grupos de campos y registro de CPT y taxonomías.

### `renova-mcp-acf-get-field`

Devuelve el valor de un campo ACF para un contenido. Envuelve get_field().

Ability: `renova-mcp/acf-get-field`. [Implementación](../includes/class-acf-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `selector` | string | Sí | — | Nombre o clave (key) del campo ACF. |
| `post_id` | string / integer | Sí | — | ID del contenido. Acepta un ID numérico, "option" (página de opciones), "user_X" o "term_X". |
| `format_value` | boolean | No | `true` | Aplicar formato de ACF al valor. |

### `renova-mcp-acf-get-field-object`

Devuelve la definición completa de un campo (tipo, etiqueta, opciones) y su valor. Envuelve get_field_object().

Ability: `renova-mcp/acf-get-field-object`. [Implementación](../includes/class-acf-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `selector` | string | Sí | — | Nombre o clave (key) del campo ACF. |
| `post_id` | string / integer | Sí | — | ID del contenido. Acepta un ID numérico, "option" (página de opciones), "user_X" o "term_X". |

### `renova-mcp-acf-get-fields`

Devuelve todos los valores de campos ACF de un contenido. Envuelve get_fields().

Ability: `renova-mcp/acf-get-fields`. [Implementación](../includes/class-acf-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `post_id` | string / integer | Sí | — | ID del contenido. Acepta un ID numérico, "option" (página de opciones), "user_X" o "term_X". |

### `renova-mcp-acf-get-field-objects`

Devuelve las definiciones y valores de todos los campos de un contenido. Envuelve get_field_objects().

Ability: `renova-mcp/acf-get-field-objects`. [Implementación](../includes/class-acf-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `post_id` | string / integer | Sí | — | ID del contenido. Acepta un ID numérico, "option" (página de opciones), "user_X" o "term_X". |

### `renova-mcp-acf-update-field`

Crea o actualiza el valor de un campo ACF para un contenido. Envuelve update_field().

Ability: `renova-mcp/acf-update-field`. [Implementación](../includes/class-acf-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `selector` | string | Sí | — | Nombre o clave (key) del campo ACF. |
| `value` | cualquiera | Sí | — | Nuevo valor (texto, número, booleano, array u objeto). |
| `post_id` | string / integer | Sí | — | ID del contenido. Acepta un ID numérico, "option" (página de opciones), "user_X" o "term_X". |

### `renova-mcp-acf-delete-field`

Elimina el valor de un campo ACF de un contenido. Envuelve delete_field().

Ability: `renova-mcp/acf-delete-field`. [Implementación](../includes/class-acf-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `selector` | string | Sí | — | Nombre o clave (key) del campo ACF. |
| `post_id` | string / integer | Sí | — | ID del contenido. Acepta un ID numérico, "option" (página de opciones), "user_X" o "term_X". |

### `renova-mcp-acf-add-row`

Añade una fila a un campo repeater/flexible. Envuelve add_row().

Ability: `renova-mcp/acf-add-row`. [Implementación](../includes/class-acf-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `selector` | string | Sí | — | Nombre o clave (key) del campo ACF. |
| `row` | object | Sí | — | Objeto con los valores de los subcampos (nombre => valor). |
| `post_id` | string / integer | Sí | — | ID del contenido. Acepta un ID numérico, "option" (página de opciones), "user_X" o "term_X". |

### `renova-mcp-acf-update-row`

Actualiza una fila concreta de un repeater por su índice (base 1). Envuelve update_row().

Ability: `renova-mcp/acf-update-row`. [Implementación](../includes/class-acf-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `selector` | string | Sí | — | Nombre o clave (key) del campo ACF. |
| `row_index` | integer | Sí | — | Índice de la fila (empezando en 1). |
| `row` | object | Sí | — | Valores de los subcampos. |
| `post_id` | string / integer | Sí | — | ID del contenido. Acepta un ID numérico, "option" (página de opciones), "user_X" o "term_X". |

### `renova-mcp-acf-delete-row`

Elimina una fila de un repeater por su índice (base 1). Envuelve delete_row().

Ability: `renova-mcp/acf-delete-row`. [Implementación](../includes/class-acf-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `selector` | string | Sí | — | Nombre o clave (key) del campo ACF. |
| `row_index` | integer | Sí | — | Índice de la fila (empezando en 1). |
| `post_id` | string / integer | Sí | — | ID del contenido. Acepta un ID numérico, "option" (página de opciones), "user_X" o "term_X". |

### `renova-mcp-acf-list-field-groups`

Lista todos los grupos de campos registrados. Envuelve acf_get_field_groups().

Ability: `renova-mcp/acf-list-field-groups`. [Implementación](../includes/class-acf-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

Sin parámetros; envía `arguments: {}`.

### `renova-mcp-acf-get-field-group`

Devuelve un grupo de campos por su clave (key) o ID. Envuelve acf_get_field_group().

Ability: `renova-mcp/acf-get-field-group`. [Implementación](../includes/class-acf-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | string / integer | Sí | — | Clave (ej. "group_abc123") o ID del grupo. |

### `renova-mcp-acf-get-fields-in-group`

Devuelve las definiciones de los campos de un grupo. Envuelve acf_get_fields().

Ability: `renova-mcp/acf-get-fields-in-group`. [Implementación](../includes/class-acf-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `group` | string / integer | Sí | — | Clave o ID del grupo de campos. |

### `renova-mcp-acf-create-field-group`

Crea (importa) un grupo de campos con sus campos y reglas de ubicación. Envuelve acf_import_field_group(). Acepta el mismo formato que la exportación de ACF.

Ability: `renova-mcp/acf-create-field-group`. [Implementación](../includes/class-acf-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `field_group` | object | Sí | — | Definición del grupo: title, fields (array), location (array), etc. |

### `renova-mcp-acf-update-field-group`

Actualiza las propiedades de un grupo de campos existente. Envuelve acf_update_field_group().

Ability: `renova-mcp/acf-update-field-group`. [Implementación](../includes/class-acf-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `field_group` | object | Sí | — | Grupo con su "key" e ID y las propiedades a cambiar. |

### `renova-mcp-acf-delete-field-group`

Elimina un grupo de campos. Acción destructiva. Envuelve acf_delete_field_group().

Ability: `renova-mcp/acf-delete-field-group`. [Implementación](../includes/class-acf-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | string / integer | Sí | — | Clave o ID del grupo. |

### `renova-mcp-acf-export-field-group`

Exporta un grupo de campos a un array portable (apto para crear el mismo grupo en otro sitio). Envuelve acf_export_field_group().

Ability: `renova-mcp/acf-export-field-group`. [Implementación](../includes/class-acf-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | string / integer | Sí | — | Clave o ID del grupo. |

### `renova-mcp-acf-list-post-types`

Lista todos los Custom Post Types creados con ACF (su key, slug, título y estado). Envuelve acf_get_acf_post_types().

Ability: `renova-mcp/acf-list-post-types`. [Implementación](../includes/class-acf-types-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

Sin parámetros; envía `arguments: {}`.

### `renova-mcp-acf-get-post-type`

Devuelve la definición completa de un CPT de ACF por su key (ej. "post_type_abc123"), ID o slug. Envuelve acf_get_post_type().

Ability: `renova-mcp/acf-get-post-type`. [Implementación](../includes/class-acf-types-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | string / integer | Sí | — | Key (post_type_xxx), ID o slug del CPT. |

### `renova-mcp-acf-save-post-type`

Crea un nuevo CPT o actualiza uno existente. Para crear, indica al menos "post_type" (slug) y "title". Para actualizar, incluye también su "key" y/o "ID". Envuelve acf_update_post_type().

Ability: `renova-mcp/acf-save-post-type`. [Implementación](../includes/class-acf-types-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `post_type` | object | Sí | — | Definición del CPT: post_type (slug, máx 20 car.), title, labels, public, hierarchical, supports, has_archive, menu_icon, etc. Mismo formato que la exportación de ACF. |

### `renova-mcp-acf-delete-post-type`

Elimina por completo un CPT de ACF por su key o ID. Acción destructiva (no borra las entradas existentes de ese tipo, solo el registro del CPT). Envuelve acf_delete_post_type().

Ability: `renova-mcp/acf-delete-post-type`. [Implementación](../includes/class-acf-types-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | string / integer | Sí | — | Key o ID del CPT. |

### `renova-mcp-acf-export-post-type`

Devuelve la definición portable de un CPT (apta para recrearlo). Envuelve acf_get_post_type() + acf_prepare_post_type_for_export().

Ability: `renova-mcp/acf-export-post-type`. [Implementación](../includes/class-acf-types-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | string / integer | Sí | — | Key o ID del CPT. |

### `renova-mcp-acf-list-taxonomies`

Lista todas las taxonomías creadas con ACF (key, slug, título, tipos de objeto y estado). Envuelve acf_get_acf_taxonomies().

Ability: `renova-mcp/acf-list-taxonomies`. [Implementación](../includes/class-acf-types-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

Sin parámetros; envía `arguments: {}`.

### `renova-mcp-acf-get-taxonomy`

Devuelve la definición completa de una taxonomía de ACF por su key (ej. "taxonomy_abc123"), ID o slug. Envuelve acf_get_taxonomy().

Ability: `renova-mcp/acf-get-taxonomy`. [Implementación](../includes/class-acf-types-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | string / integer | Sí | — | Key (taxonomy_xxx), ID o slug de la taxonomía. |

### `renova-mcp-acf-save-taxonomy`

Crea una nueva taxonomía o actualiza una existente. Para crear, indica al menos "taxonomy" (slug), "title" y "object_type" (array de post types asociados). Para actualizar, incluye su "key" y/o "ID". Envuelve acf_update_taxonomy().

Ability: `renova-mcp/acf-save-taxonomy`. [Implementación](../includes/class-acf-types-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `taxonomy` | object | Sí | — | Definición: taxonomy (slug, máx 32 car.), title, object_type (array), labels, hierarchical, public, show_in_rest, etc. Mismo formato que la exportación de ACF. |

### `renova-mcp-acf-delete-taxonomy`

Elimina por completo una taxonomía de ACF por su key o ID. Acción destructiva. Envuelve acf_delete_taxonomy().

Ability: `renova-mcp/acf-delete-taxonomy`. [Implementación](../includes/class-acf-types-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | string / integer | Sí | — | Key o ID de la taxonomía. |

### `renova-mcp-acf-export-taxonomy`

Devuelve la definición portable de una taxonomía (apta para recrearla). Envuelve acf_get_taxonomy() + acf_prepare_taxonomy_for_export().

Ability: `renova-mcp/acf-export-taxonomy`. [Implementación](../includes/class-acf-types-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | string / integer | Sí | — | Key o ID de la taxonomía. |

<a id="elementor"></a>

## Elementor

Árbol de elementos, plantillas, ajustes de página y kit global.

### `renova-mcp-elementor-list-content`

Lista entradas/páginas/CPT indicando si están construidas con Elementor (edit_mode "builder").

Ability: `renova-mcp/elementor-list-content`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `post_type` | string | No | `"page"` | Tipo de contenido a listar. |
| `number` | integer | No | `50` | Máximo de resultados. |
| `only_elementor` | boolean | No | `false` | Si es true, solo devuelve los construidos con Elementor. |

### `renova-mcp-elementor-get-data`

Devuelve el árbol completo de `_elementor_data` (JSON decodificado) de una página.

Ability: `renova-mcp/elementor-get-data`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la página/entrada/CPT editada con Elementor. |

### `renova-mcp-elementor-get-structure`

Devuelve un esquema simplificado del árbol (id, tipo, widgetType y una vista previa de texto) para entender la página sin todo el JSON.

Ability: `renova-mcp/elementor-get-structure`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la página/entrada/CPT editada con Elementor. |

### `renova-mcp-elementor-get-element`

Devuelve un único elemento (sección, contenedor, columna o widget) por su id, con todos sus ajustes.

Ability: `renova-mcp/elementor-get-element`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la página/entrada/CPT editada con Elementor. |
| `element_id` | string | Sí | — | ID del elemento dentro del árbol Elementor (7 caracteres). |

### `renova-mcp-elementor-find-elements`

Busca elementos por widgetType (ej. "heading", "button", "image") o por elType (section, container, column, widget).

Ability: `renova-mcp/elementor-find-elements`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la página/entrada/CPT editada con Elementor. |
| `widget_type` | string | No | — | Tipo de widget a buscar (ej. "heading", "button", "text-editor", "image"). |
| `el_type` | string | No | — | Tipo de elemento: section, container, column o widget. |

### `renova-mcp-elementor-get-page-settings`

Devuelve `_elementor_page_settings` (plantilla, fondo, padding, CSS personalizado de página, etc.).

Ability: `renova-mcp/elementor-get-page-settings`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la página/entrada/CPT editada con Elementor. |

### `renova-mcp-elementor-update-data`

Reemplaza por completo `_elementor_data` con un nuevo árbol JSON. Crea una copia de seguridad previa automática y regenera la caché. Acción potente.

Ability: `renova-mcp/elementor-update-data`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la página/entrada/CPT editada con Elementor. |
| `data` | array | Sí | — | Árbol Elementor completo (array de elementos de nivel superior). Acepta array u objeto JSON. |

### `renova-mcp-elementor-update-element`

Fusiona ajustes en un elemento/widget concreto por su id sin recargar toda la página. Ej.: cambiar el texto de un "heading" pasando settings {"title":"Nuevo"}.

Ability: `renova-mcp/elementor-update-element`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la página/entrada/CPT editada con Elementor. |
| `element_id` | string | Sí | — | ID del elemento a modificar. |
| `settings` | object | Sí | — | Ajustes a fusionar en `settings` del elemento. |
| `replace` | boolean | No | `false` | Si es true, reemplaza `settings` por completo en lugar de fusionar. |

### `renova-mcp-elementor-add-element`

Añade una sección, contenedor, columna o widget. Indica elType (y widgetType si es widget) + settings, o pasa un subárbol completo en "element". Los IDs se generan solos. parent_id vacío = nivel superior.

Ability: `renova-mcp/elementor-add-element`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la página/entrada/CPT editada con Elementor. |
| `parent_id` | string | No | — | ID del elemento contenedor. Vacío para añadir al nivel superior de la página. |
| `position` | integer | No | — | Índice donde insertar entre los hijos del padre (0 = primero). Por defecto, al final. |
| `el_type` | string | No | — | section, container, column o widget. |
| `widget_type` | string | No | — | Tipo de widget si el_type es "widget" (ej. "heading", "button", "image", "text-editor"). |
| `settings` | object | No | — | Ajustes iniciales del elemento. |
| `element` | object | No | — | Alternativa: subárbol Elementor completo a insertar tal cual (se le regeneran los IDs). |

### `renova-mcp-elementor-delete-element`

Elimina un elemento (y sus hijos) por su id. Acción destructiva; se hace copia de seguridad previa.

Ability: `renova-mcp/elementor-delete-element`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la página/entrada/CPT editada con Elementor. |
| `element_id` | string | Sí | — | ID del elemento a eliminar. |

### `renova-mcp-elementor-move-element`

Mueve un elemento a otro padre y/o posición.

Ability: `renova-mcp/elementor-move-element`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la página/entrada/CPT editada con Elementor. |
| `element_id` | string | Sí | — | ID del elemento a mover. |
| `new_parent_id` | string | No | — | ID del nuevo padre. Vacío para moverlo al nivel superior. |
| `position` | integer | No | — | Índice destino entre los hijos del nuevo padre. Por defecto, al final. |

### `renova-mcp-elementor-duplicate-element`

Clona un elemento (y sus hijos) con nuevos IDs, insertándolo justo después del original.

Ability: `renova-mcp/elementor-duplicate-element`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la página/entrada/CPT editada con Elementor. |
| `element_id` | string | Sí | — | ID del elemento a duplicar. |

### `renova-mcp-elementor-reorder-elements`

Reordena los hijos directos de un padre según una lista de IDs. parent_id vacío = nivel superior.

Ability: `renova-mcp/elementor-reorder-elements`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la página/entrada/CPT editada con Elementor. |
| `parent_id` | string | No | — | ID del padre cuyos hijos se reordenan. Vacío = nivel superior. |
| `order` | array | Sí | — | Lista de IDs de hijos en el nuevo orden. |

### `renova-mcp-elementor-update-page-settings`

Fusiona ajustes en `_elementor_page_settings` (plantilla, fondo, CSS de página, etc.) y regenera la caché.

Ability: `renova-mcp/elementor-update-page-settings`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la página/entrada/CPT editada con Elementor. |
| `settings` | object | Sí | — | Ajustes a fusionar. |

### `renova-mcp-elementor-backup-data`

Guarda una copia del `_elementor_data` actual en un meta de respaldo y devuelve su marca de tiempo.

Ability: `renova-mcp/elementor-backup-data`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la página/entrada/CPT editada con Elementor. |

### `renova-mcp-elementor-restore-backup`

Restaura el último respaldo guardado de `_elementor_data` y regenera la caché.

Ability: `renova-mcp/elementor-restore-backup`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la página/entrada/CPT editada con Elementor. |

### `renova-mcp-elementor-clear-cache`

Regenera el CSS de Elementor. Sin "id" limpia toda la caché del sitio; con "id" solo la de esa página.

Ability: `renova-mcp/elementor-clear-cache`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | No | — | ID de página para limpiar solo su caché (opcional). |

### `renova-mcp-elementor-list-templates`

Lista las plantillas de la biblioteca (CPT elementor_library): páginas, secciones, contenedores, cabeceras, pies, popups, etc.

Ability: `renova-mcp/elementor-list-templates`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `type` | string | No | — | Filtra por tipo de plantilla (page, section, container, header, footer, popup, etc.). |

### `renova-mcp-elementor-get-template`

Devuelve el árbol JSON de una plantilla de la biblioteca por su ID.

Ability: `renova-mcp/elementor-get-template`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la página/entrada/CPT editada con Elementor. |

### `renova-mcp-elementor-create-template`

Crea una plantilla en la biblioteca a partir de un árbol JSON, o copiando el contenido de una página existente (source_id).

Ability: `renova-mcp/elementor-create-template`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `title` | string | Sí | — | Nombre de la plantilla. |
| `type` | string | No | `"page"` | Tipo de plantilla (page, section, container, header, footer, popup...). |
| `data` | array | No | — | Árbol Elementor de la plantilla. |
| `source_id` | integer | No | — | Alternativa a "data": ID de una página existente de la que copiar el contenido. |

### `renova-mcp-elementor-apply-template`

Inserta el contenido de una plantilla en una página: "replace" sustituye el contenido o "append" lo añade al final. Regenera la caché.

Ability: `renova-mcp/elementor-apply-template`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la página destino. |
| `template_id` | integer | Sí | — | ID de la plantilla a aplicar. |
| `mode` | string | No | `"replace"` | "replace" (por defecto) o "append". |

### `renova-mcp-elementor-get-global-settings`

Devuelve los ajustes del kit activo: colores del sistema y personalizados, tipografías globales y ajustes del sitio.

Ability: `renova-mcp/elementor-get-global-settings`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

Sin parámetros; envía `arguments: {}`.

### `renova-mcp-elementor-update-global-settings`

Fusiona ajustes en el kit activo (colores, tipografías, layout del sitio) y regenera la caché global.

Ability: `renova-mcp/elementor-update-global-settings`. [Implementación](../includes/class-elementor-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `settings` | object | Sí | — | Ajustes del kit a fusionar (ej. system_colors, custom_colors, system_typography, custom_typography). |

<a id="rankmath"></a>

## Rank Math SEO

Meta SEO, schema, ajustes, redirecciones y monitor 404.

### `renova-mcp-rankmath-get-post-seo`

Devuelve los metadatos SEO de Rank Math de una entrada: título, descripción, palabra clave objetivo, robots, canonical, OpenGraph, Twitter y puntuación SEO.

Ability: `renova-mcp/rankmath-get-post-seo`. [Implementación](../includes/class-rankmath-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la entrada/página/CPT. |

### `renova-mcp-rankmath-update-post-seo`

Fija el título SEO, descripción, palabra(s) clave objetivo, robots, canonical, breadcrumb, contenido pilar y campos OpenGraph/Twitter de una entrada.

Ability: `renova-mcp/rankmath-update-post-seo`. [Implementación](../includes/class-rankmath-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la entrada/página/CPT. |
| `title` | string | No | — | Título SEO (admite variables Rank Math como %title%). |
| `description` | string | No | — | Meta descripción. |
| `focus_keyword` | string | No | — | Palabra(s) clave objetivo, separadas por comas. |
| `canonical_url` | string | No | — | URL canónica. |
| `robots` | array | No | — | Directivas robots: index, noindex, nofollow, noarchive, noimageindex, nosnippet. |
| `breadcrumb_title` | string | No | — | Título para las migas de pan. |
| `pillar_content` | boolean | No | — | Marca el contenido como pilar. |
| `facebook_title` | string | No | — |  |
| `facebook_description` | string | No | — |  |
| `facebook_image` | string | No | — |  |
| `twitter_title` | string | No | — |  |
| `twitter_description` | string | No | — |  |
| `twitter_image` | string | No | — |  |

### `renova-mcp-rankmath-get-post-schema`

Devuelve todos los datos de Schema (marcado estructurado) aplicados a una entrada.

Ability: `renova-mcp/rankmath-get-post-schema`. [Implementación](../includes/class-rankmath-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la entrada/página/CPT. |

### `renova-mcp-rankmath-set-post-schema`

Crea o reemplaza un bloque de Schema (ej. Article, FAQPage, Product) en una entrada. "data" es el array de propiedades del schema.

Ability: `renova-mcp/rankmath-set-post-schema`. [Implementación](../includes/class-rankmath-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la entrada/página/CPT. |
| `type` | string | Sí | — | Tipo de Schema (Article, FAQPage, Product, HowTo, Recipe, etc.). |
| `data` | object | Sí | — | Propiedades del schema. Debe incluir al menos @type y metadata. |

### `renova-mcp-rankmath-delete-post-schema`

Elimina un bloque de Schema de una entrada por su tipo.

Ability: `renova-mcp/rankmath-delete-post-schema`. [Implementación](../includes/class-rankmath-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la entrada/página/CPT. |
| `type` | string | Sí | — | Tipo de Schema a eliminar. |

### `renova-mcp-rankmath-get-settings`

Devuelve un grupo de ajustes globales de Rank Math: "general", "titles" (títulos y meta por tipo de contenido) o "sitemap".

Ability: `renova-mcp/rankmath-get-settings`. [Implementación](../includes/class-rankmath-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `group` | string | No | `"general"` | Grupo: general, titles o sitemap. |

### `renova-mcp-rankmath-update-settings`

Fusiona ajustes en un grupo global de Rank Math (general, titles o sitemap) y refresca su caché.

Ability: `renova-mcp/rankmath-update-settings`. [Implementación](../includes/class-rankmath-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `group` | string | Sí | — | Grupo: general, titles o sitemap. |
| `settings` | object | Sí | — | Ajustes a fusionar. |

### `renova-mcp-rankmath-list-redirections`

Lista las redirecciones configuradas en Rank Math con su origen, destino, código y estado.

Ability: `renova-mcp/rankmath-list-redirections`. [Implementación](../includes/class-rankmath-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `number` | integer | No | `100` | Máximo de resultados. |

### `renova-mcp-rankmath-add-redirection`

Crea una redirección de una o varias URL de origen a un destino con el código indicado (301, 302, 307, 410, 451).

Ability: `renova-mcp/rankmath-add-redirection`. [Implementación](../includes/class-rankmath-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `sources` | array | Sí | — | URL(s) o patrones de origen. |
| `url_to` | string | Sí | — | URL de destino. |
| `comparison` | string | No | `"exact"` | Tipo de coincidencia: exact, contains, start, end, regex. |
| `header_code` | string | No | `"301"` | Código HTTP: 301, 302, 307, 410 o 451. |

### `renova-mcp-rankmath-update-redirection`

Actualiza el destino, código o estado (active/inactive) de una redirección por su id.

Ability: `renova-mcp/rankmath-update-redirection`. [Implementación](../includes/class-rankmath-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=false`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la redirección. |
| `url_to` | string | No | — |  |
| `header_code` | string | No | — |  |
| `status` | string | No | — | active o inactive. |

### `renova-mcp-rankmath-delete-redirection`

Elimina una redirección por su id.

Ability: `renova-mcp/rankmath-delete-redirection`. [Implementación](../includes/class-rankmath-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=true`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `id` | integer | Sí | — | ID de la redirección a eliminar. |

### `renova-mcp-rankmath-get-404-log`

Devuelve las entradas del monitor de errores 404 de Rank Math (URI, accesos, fecha).

Ability: `renova-mcp/rankmath-get-404-log`. [Implementación](../includes/class-rankmath-abilities.php).

Anotaciones: `readOnlyHint=true`, `destructiveHint=false`, `idempotentHint=false`.

| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |
| --- | --- | --- | --- | --- |
| `number` | integer | No | `100` | Máximo de resultados. |

### `renova-mcp-rankmath-clear-404-log`

Borra todas las entradas del monitor de errores 404.

Ability: `renova-mcp/rankmath-clear-404-log`. [Implementación](../includes/class-rankmath-abilities.php).

Anotaciones: `readOnlyHint=false`, `destructiveHint=true`, `idempotentHint=true`.

Sin parámetros; envía `arguments: {}`.
