# Renova MCP Control

Servidor **MCP (Model Context Protocol)** para controlar este WordPress de forma remota
desde cualquier cliente MCP (Claude, etc.). Construido sobre la **Abilities API** del núcleo
de WordPress y la librería oficial [WordPress/mcp-adapter](https://github.com/WordPress/mcp-adapter).

## Instalación en cualquier sitio WordPress

Este plugin es **genérico y reutilizable**: funciona en cualquier WordPress, no depende de
un dominio concreto. Para instalarlo en un sitio nuevo:

1. Copia la carpeta `renova-mcp` a `wp-content/plugins/` (por SFTP, ZIP o Git). El directorio
   `vendor/` viaja en el repo, así que **no necesitas ejecutar Composer en el servidor**.
2. Actívalo en *Plugins*.
3. Crea una **Contraseña de aplicación** para un usuario administrador (ver Autenticación).
4. Configura tu cliente MCP con la URL del endpoint y esas credenciales.

Las herramientas de ACF, Elementor y Rank Math se activan solas **solo si** ese plugin está
presente; si no, devuelven un `WP_Error` claro sin romper nada.

## Endpoint

Una vez activado el plugin, el servidor MCP queda disponible en:

```
https://TU-DOMINIO/wp-json/renova-mcp/v1/mcp
```

## Autenticación

Las herramientas exigen un usuario **administrador** (`manage_options`). El endpoint REST
acepta **Contraseñas de aplicación** de WordPress (Basic Auth sobre HTTPS):

1. En el admin de WordPress: **Usuarios → Perfil → Contraseñas de aplicación**.
2. Crea una contraseña de aplicación (ej. nombre "MCP").
3. Configura tu cliente MCP con autenticación Basic: usuario = tu login, contraseña = la
   contraseña de aplicación generada.

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
tras cada cambio. Si Elementor no está activo, cada herramienta devuelve un `WP_Error` claro.

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
