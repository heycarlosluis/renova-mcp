# Changelog

Todas las versiones notables de **Renova MCP Control**.

## 1.4.0

- **Tokens de acceso**: nueva autenticación `Authorization: Bearer` con tokens creados
  desde la administración, guardados solo como hash y revocables. Permite conectar
  clientes que no aceptan Basic Auth (Poke, entre otros). Las contraseñas de aplicación
  siguen funcionando.
- **Alcances por token**: cada token puede limitarse a grupos de herramientas, a solo
  lectura y tener caducidad. Un token limitado solo ve en `tools/list` lo que puede usar.
- **Menos herramientas expuestas**: las de ACF, Elementor y Rank Math ya no se publican si
  su plugin no está activo, y cada grupo puede desactivarse. Nuevo modo solo lectura global.
- **Anotaciones completas**: las 117 herramientas declaran `readOnlyHint` y, las de
  escritura, `destructiveHint` e `idempotentHint`.
- **Registro de actividad**: sustituye al manejador nulo del adaptador; anota llamadas,
  sesiones y tokens rechazados sin volcar el contenido de los argumentos.
- **Límite de peticiones** por credencial, con respuesta `429` y `Retry-After`.
- **OAuth 2.1 opcional**: metadatos de descubrimiento, registro dinámico de clientes, PKCE
  S256, pantalla de consentimiento y rotación del token de refresco. Desactivado por defecto.
- **Descubrimiento de autenticación**: las respuestas `401` del endpoint incluyen
  `WWW-Authenticate: Bearer`.
- **Administración**: nuevas secciones Tokens, Herramientas y seguridad, y Actividad; la
  guía de conexión añade Poke y conectores con OAuth.
- **Pruebas**: `tests/run.sh` recorre el protocolo de extremo a extremo sobre un WordPress
  desechable, y se ejecuta en GitHub Actions.
- **Base de datos**: dos tablas nuevas (`renova_mcp_tokens`, `renova_mcp_audit`) que se
  crean al cargar el plugin y se eliminan al desinstalarlo.

## 1.3.1

- **Guía de conexión rediseñada**: nueva composición visual inspirada en herramientas
  para desarrolladores, con endpoint destacado, selector de clientes y panel de código.
- **Mejor accesibilidad**: navegación de clientes con teclado, estados de copia anunciados,
  foco visible, controles táctiles más amplios y mejor adaptación móvil.
- **Iconografía consistente**: Lucide se incluye localmente con un paquete reducido de iconos.
- **Carga de assets corregida**: los estilos y scripts administrativos usan la URL pública
  del plugin en vez de la ruta interna del servidor.
- **Flujo más claro**: acceso directo a las contraseñas de aplicación, usuario actual
  precargado y credencial generada solo cuando ambos campos están completos.

## 1.3.0

- **Página de administración**: nueva entrada "Renova MCP" en el menú con una guía
  de configuración visual (endpoint con copiar, generación en vivo del header Basic,
  y configuraciones listas para Claude Desktop, Claude Code, Cursor y Codex/ChatGPT).

## 1.2.0

- **Dependencias**: `wordpress/mcp-adapter` sube a `^0.6` (v0.6.1) y `wordpress/php-mcp-schema` a v0.1.3.
- **Seguridad**: se desactiva el servidor MCP por defecto del adaptador
  (`/wp-json/mcp/mcp-adapter-default-server`), que exponía abilities `discover`/`execute`
  sobre cualquier ability registrada; solo queda el endpoint controlado de Renova.
- **Seguridad**: `delete-user` impide eliminar tu propio usuario y al último administrador.
- **Robustez**: se acotan los límites de resultados (`number`) en listados de contenido,
  términos, usuarios, medios, comentarios, plantillas y registros de Rank Math.
- **Correcciones**: `upload-media` genera un nombre de archivo válido cuando la URL no
  aporta uno; `create-post`/`update-post` validan el `post_status`; `delete-plugin`
  comprueba `WP_Filesystem()`; `elementor-list-content` evita consultas N+1 al filtrar.
- **Mantenimiento**: cabecera actualizada (`Tested up to: 7.1`, URI del repo corregida)
  y se añade `uninstall.php` para limpiar las copias de seguridad de Elementor.

## 1.1.0

- **Rank Math SEO** (13 herramientas): meta SEO por entrada (título, descripción,
  keyword, robots, canonical, OpenGraph, Twitter), Schema, ajustes globales
  (general/titles/sitemap), redirecciones y monitor 404.
- **Usuarios** (5): listar, obtener, crear, actualizar (roles, contraseña) y eliminar.
- **Medios** (5): listar, obtener, subir desde URL, eliminar y fijar imagen destacada.
- **Menús** (6): listar/obtener/crear menús, añadir/eliminar elementos y asignar ubicación.
- **Comentarios** (3): listar, moderar (approve/hold/spam/trash) y eliminar.
- **Utilidades de sitio** (10): estructura de permalinks, flush de reglas de reescritura,
  theme mods, transients, comprobación y ejecución de actualizaciones, y flush de caché.
- Todas las herramientas de terceros (ACF, Elementor, Rank Math) degradan a `WP_Error`
  cuando el plugin correspondiente no está activo.

## 1.0.0

- Control base de WordPress: información del sitio, plugins, temas, contenido (cualquier
  CPT), post-meta, opciones, taxonomías y términos.
- **ACF** (16): valores, grupos de campos, y registro de CPT/taxonomías vía ACF 6.1+.
- **Elementor / Elementor Pro** (23): árbol de elementos (`_elementor_data`), ajustes de
  página, copias de seguridad, caché, plantillas de biblioteca y kit global.
- Construido sobre la Abilities API del núcleo de WordPress + WordPress MCP Adapter.
