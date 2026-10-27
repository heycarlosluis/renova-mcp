# Renova MCP Control

Plugin de WordPress que expone herramientas administrativas mediante **MCP (Model Context Protocol)**. Permite consultar y gestionar contenido, usuarios, medios, menús, comentarios, ajustes, plugins y temas desde un cliente MCP, con integraciones para **ACF**, **Elementor** y **Rank Math SEO**.

Versión documentada: **1.4.0**. Construido sobre la Abilities API de WordPress y [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter), incluido en el repositorio.

## Documentación

- [Instalación y conexión](docs/connection.md): endpoint, Bearer, contraseña de aplicación, OAuth y ejemplo HTTP completo.
- [Referencia de las 117 herramientas](docs/tools.md): nombres MCP, parámetros, valores predeterminados y anotaciones.
- [Esquemas JSON del catálogo](docs/tools.json): entradas, salidas y metadatos para automatizaciones.
- [Comportamiento e integraciones](docs/behavior.md): resultados, límites, borrado, copias de Elementor y requisitos de terceros.
- [Seguridad y administración](docs/security.md): permisos, alcances, límites, actividad y mantenimiento.
- [Desarrollo y pruebas](docs/development.md): estructura, dependencias, validación y regeneración de la referencia.
- [Historial de cambios](CHANGELOG.md).

## Requisitos

- **WordPress 6.9+**, con la Abilities API del núcleo. La cabecera del plugin declara compatibilidad probada hasta 7.1; el entorno de validación se detalla en la [guía de desarrollo](docs/development.md).
- **PHP 7.4+**. La emisión de tokens utiliza `mbstring`; instálala si tu entorno no la incluye.
- **HTTPS** para conexiones remotas; se admite HTTP cuando WordPress tiene `WP_ENVIRONMENT_TYPE=local`.
- Para instalaciones por copia, ZIP o Git, incluir **`vendor/`**, versionado en este repositorio. No hace falta Composer en el servidor.
- ACF, Elementor y Rank Math son **opcionales**; sus funciones requieren las APIs o módulos correspondientes.

## Instalación rápida

1. Copia el repositorio completo a `wp-content/plugins/renova-mcp/`, incluyendo `vendor/`.
2. Activa **Renova MCP Control** en **Plugins**.
3. Abre **Renova MCP → Tokens** y crea un token con los grupos, modo de lectura y caducidad que necesite tu cliente. Copia el valor mostrado una sola vez.
4. En **Renova MCP → Conexión**, copia la URL calculada para tu instalación y configura el cliente con transporte HTTP y `Authorization: Bearer TU_TOKEN`.
5. Inicia la conexión y comprueba las herramientas que devuelve `tools/list`.

En una instalación en la raíz del dominio con enlaces permanentes activos, el endpoint es:

```text
https://TU-DOMINIO/wp-json/renova-mcp/v1/mcp
```

Usa la URL mostrada por el plugin si WordPress está en un subdirectorio o utiliza enlaces permanentes simples. El transporte es **Streamable HTTP mediante POST y sesiones**; no hay ruta `/sse`. Abrir la URL con GET puede devolver `405` y no comprueba la conexión MCP.

También se admiten **contraseñas de aplicación de WordPress** mediante Basic y un servidor **OAuth con código de autorización y PKCE S256**, desactivado por defecto. La [guía de conexión](docs/connection.md) explica ambos flujos y sus requisitos.

## Funciones disponibles

El catálogo contiene **117 herramientas**. Sin las tres integraciones, con todos los grupos nativos habilitados y una credencial completa, se exponen **55**. El listado efectivo se reduce según los plugins presentes, los grupos activados y el alcance de la credencial.

| Grupo | Herramientas | Funciones |
| --- | ---: | --- |
| `content` · Contenido | 31 | Entradas, páginas y CPT; metadatos; tipos y taxonomías nativos; términos; medios; menús clásicos; comentarios. |
| `site` · Ajustes del sitio | 10 | Información, opciones, permalinks, reglas de reescritura, personalizador, transients y caché de objetos. |
| `users` · Usuarios | 5 | Listar, obtener, crear, actualizar y eliminar usuarios. |
| `system` · Plugins, temas y actualizaciones | 9 | Listar, instalar, activar, desactivar y eliminar plugins; cambiar de tema; comprobar actualizaciones y actualizar plugins o temas. |
| `acf` · Advanced Custom Fields | 26 | Valores y definiciones de campos, repetidores, grupos, CPT y taxonomías de ACF. |
| `elementor` · Elementor | 23 | Inspeccionar y editar árboles, ajustes de página, copias, caché, plantillas y kit global. |
| `rankmath` · Rank Math SEO | 13 | SEO por contenido, Schema, ajustes, redirecciones y monitor 404. |

Los nombres enviados por MCP incluyen el prefijo **`renova-mcp-`**. Por ejemplo, la ability `renova-mcp/get-post` se invoca como `renova-mcp-get-post`, con `arguments: {"id": 123}`. Consulta todos los nombres y parámetros en la [referencia](docs/tools.md).

## Administración y seguridad

La página **Renova MCP** tiene cuatro pestañas:

- **Conexión**: URL, cabecera de autenticación y plantillas de configuración por cliente.
- **Tokens**: creación y revocación de tokens y conexiones OAuth, con información de uso.
- **Herramientas y seguridad**: grupos expuestos, modo solo lectura, peticiones por minuto, retención de actividad y OAuth.
- **Actividad**: últimas llamadas a herramientas, inicializaciones MCP e intentos con token inválido; permite vaciar el registro.

Todas las herramientas requieren la capacidad **`manage_options`** del usuario autenticado. Los tokens pueden restringir grupos, escrituras y vigencia; esos límites se aplican al listado y a la ejecución. El servidor MCP genérico del adaptador se desactiva y se expone el catálogo de Renova.

Los valores iniciales son: todos los grupos disponibles habilitados, escrituras permitidas, **120 peticiones/minuto** para acceso autenticado, hasta **30/minuto por IP** sin autenticar, actividad durante **30 días** y **OAuth desactivado**. La [guía de seguridad](docs/security.md) explica cómo se cuentan las peticiones y qué datos se guardan.

Antes de automatizar escrituras, revisa las [notas de comportamiento](docs/behavior.md): `create-post` crea una **página publicada** si no indicas tipo y estado, `delete-media` usa borrado **permanente** por defecto y la copia de Elementor conserva **una sola versión del árbol por contenido**.

## Desarrollo

```bash
composer install
bash tests/run.sh
```

La suite crea un WordPress desechable con SQLite y comprueba el protocolo MCP, Bearer y Basic, alcances, OAuth, límites y actividad. Requiere PHP con `sqlite3`, `pdo_sqlite`, `curl` y `mbstring`, WP-CLI, `curl` y `unzip`. La integración continua se ejecuta en pull requests y en pushes a `main`, con PHP 7.4 y 8.3.

Consulta la [guía de desarrollo](docs/development.md) para regenerar el catálogo desde el código y conocer el alcance de las pruebas. Licencia **GPL-2.0-or-later**; véase [LICENSE](LICENSE). Los iconos administrativos se incluyen localmente bajo la [licencia de Lucide](assets/LUCIDE-LICENSE).
