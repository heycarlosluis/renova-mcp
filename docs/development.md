# Desarrollo y pruebas

[Inicio](../README.md) · [Referencia](tools.md) · [Seguridad](security.md) · [Changelog](../CHANGELOG.md)

## Estructura

| Archivo o directorio | Responsabilidad |
| --- | --- |
| `renova-mcp.php` | Cabecera y constantes 1.4.0, carga de dependencias, hooks de abilities y creación del servidor MCP. |
| `includes/class-abilities.php` | Información, plugins, temas, contenido, metadatos y opciones: 18 herramientas. |
| `includes/class-taxonomy-abilities.php` | Tipos, taxonomías y términos nativos: 9. |
| `includes/class-users-media-abilities.php` | Usuarios y medios: 10. |
| `includes/class-site-abilities.php` | Menús, comentarios, permalinks, personalizador, transients, actualizaciones y caché: 18. |
| `includes/class-acf-abilities.php` | Valores, filas y grupos ACF: 16. |
| `includes/class-acf-types-abilities.php` | Definiciones de CPT y taxonomías ACF: 10. |
| `includes/class-elementor-abilities.php` | Árbol, ajustes, copias, caché, plantillas y kit: 23. |
| `includes/class-rankmath-abilities.php` | SEO, Schema, ajustes, redirecciones y 404: 13. |
| `includes/class-catalog.php` | Inventario, grupos, disponibilidad, nombres MCP y selección del catálogo expuesto. |
| `includes/class-access.php` | Restricciones globales y por token en permisos y listado de herramientas. |
| `includes/class-request.php` | Detección de ruta REST, Bearer, HTTPS e IP. |
| `includes/class-tokens.php` | Emisión, hash, autenticación, alcance, uso y revocación de tokens. |
| `includes/class-oauth.php` | Descubrimiento, registro público, consentimiento, códigos PKCE y renovación. |
| `includes/class-settings.php` | Valores predeterminados y normalización de ajustes. |
| `includes/class-store.php` | Tablas propias, versión de esquema y limpieza diaria. |
| `includes/class-rate-limit.php` | Contadores por minuto y rechazo REST con `429`. |
| `includes/class-audit-log.php` y `class-audit-observability-handler.php` | Resumen de argumentos y persistencia de eventos observados por el adaptador. |
| `includes/class-admin-page.php` y `class-admin-screens.php` | Guía de conexión, tokens, ajustes, actividad y procesamiento de formularios. |
| `assets/` | CSS y JavaScript administrativos e iconos Lucide locales. |
| `scripts/generate-tool-reference.php` | Generación del catálogo Markdown y JSON desde WordPress. |
| `tests/` | Instalación desechable y pruebas HTTP del protocolo. |
| `uninstall.php` | Limpieza explícita al desinstalar mediante WordPress. |
| `vendor/` | Dependencias incluidas y versionadas para despliegues sin Composer. |

Los totales por archivo y por grupo son clasificaciones diferentes: por ejemplo, las herramientas de `class-site-abilities.php` se reparten entre `content`, `site` y `system`.

## Dependencias

```bash
composer install
composer validate --strict
```

`composer.json` declara PHP `>=7.4`, `wordpress/mcp-adapter ^0.6` y Jetpack Autoloader `^5.0 || ^4.0 || ^3.0`. El archivo de bloqueo incluye MCP Adapter **0.6.1**, PHP MCP Schema **0.1.3** y Jetpack Autoloader **5.0.20**. Para reproducir el despliegue usa `install`, que respeta ese bloqueo; una actualización de dependencias debe revisar también los cambios en `vendor/`.

El plugin intenta cargar `vendor/autoload_packages.php` y utiliza Jetpack Autoloader. Si la clase del adaptador no está disponible, muestra un aviso administrativo y no crea el servidor. No edites documentación o código de dependencias como si fueran parte propia del plugin.

## Pruebas de extremo a extremo

```bash
bash tests/run.sh
```

Requisitos locales: PHP con `sqlite3`, `pdo_sqlite`, `curl` y `mbstring`, WP-CLI, `curl`, `unzip` y acceso a las descargas de WordPress y SQLite Database Integration. No utiliza Docker ni necesita MySQL. El servidor PHP se abre en loopback y se cierra al terminar.

La suite:

1. Comprueba sintaxis de PHP propio, sin recorrer `vendor/`.
2. Descarga o reutiliza un ZIP de WordPress y del plugin de integración SQLite.
3. Crea una instalación desechable con entorno `local`, activa Renova y configura permalinks.
4. Crea tokens y una contraseña de aplicación para las pruebas; habilita OAuth en ese sitio de pruebas.
5. Prueba autenticación, negociación y sesiones, notificaciones, `ping`, listado de las 55 herramientas nativas y ejecución de lectura, creación y eliminación de contenido.
6. Comprueba alcances por token, ocultación de integraciones ausentes, modo solo lectura global y desactivación de grupos.
7. Recorre registro, consentimiento, PKCE, canje, restricciones y rotación OAuth.
8. Verifica `429` y `Retry-After`, registros de llamadas y rechazos, y que no se vuelque el contenido de los argumentos del ejemplo.
9. Falla si encuentra avisos de PHP en el registro de WordPress, excluyendo los del propio PHAR de WP-CLI.

El recorrido actual reúne **69 comprobaciones**: 58 principales, 5 de restricciones globales, 3 de límite y 3 de actividad. No ejecuta las 117 herramientas ni instala ACF, Elementor o Rank Math; estas integraciones necesitan validación adicional con sus versiones y módulos reales. Tampoco sustituye una prueba de interfaz administrativa, multisite o despliegue en el hosting final.

### Directorio, puerto y caché

```bash
RENOVA_TEST_DIR=/tmp/renova-mcp-doc-check RENOVA_TEST_PORT=8898 bash tests/run.sh
```

| Variable | Valor inicial | Efecto |
| --- | --- | --- |
| `RENOVA_TEST_DIR` | `${TMPDIR:-/tmp}/renova-mcp-test` | Carpeta de trabajo, caché, sitio y logs. |
| `RENOVA_TEST_PORT` | `8899` | Puerto de loopback para el servidor PHP. |

Utiliza un directorio dedicado: cada ejecución **borra su subcarpeta `wp/` y `wp-cli.log`**, recrea la instalación y conserva los ZIP en `cache/`. No apuntes al directorio de un WordPress que quieras conservar. Para probar otra descarga de WordPress, elimina el ZIP correspondiente de la caché de pruebas antes de repetir.

Los registros `wp-cli.log`, `server.log` y `wp/wp-content/debug.log` quedan en la carpeta de trabajo. No publiques esa carpeta: la instalación contiene credenciales desechables y una base de datos de pruebas. El runner establece las variables de autenticación que consume `tests/smoke.php`; no necesitas copiar credenciales de producción.

### Integración continua y validación registrada

[GitHub Actions](../.github/workflows/tests.yml) ejecuta el recorrido en **PHP 7.4 y 8.3**, en pull requests y pushes a `main`. El ZIP de WordPress se obtiene de `latest.zip` si no existe en la caché; no hay una matriz fijada de versiones de WordPress ni una prueba específica de WordPress 6.9.

La revisión documental de 1.4.0 pasó las **69 comprobaciones** en **WordPress 7.1.3 y PHP 8.5.12**. Esto documenta la ejecución local observada; no significa que se hayan ejecutado aquí las dos versiones PHP de la matriz de Actions. La cabecera del plugin conserva `Requires at least: 6.9`, `Requires PHP: 7.4` y `Tested up to: 7.1`.

## Regenerar la referencia

Con Renova activo en un WordPress de desarrollo:

```bash
wp --path=/ruta/wordpress eval-file scripts/generate-tool-reference.php
```

Desde la raíz del repositorio, después de ejecutar las pruebas con su directorio predeterminado:

```bash
wp --path="${TMPDIR:-/tmp}/renova-mcp-test/wp" eval-file scripts/generate-tool-reference.php
```

El script lee **todas las abilities del catálogo**, incluidas las integraciones ausentes, sin ejecutar sus callbacks ni cambiar ajustes. Escribe `docs/tools.md` y `docs/tools.json` en este repositorio. No necesitas instalar plugins de terceros para generar sus definiciones. Los textos siguen las traducciones disponibles en la instalación; utiliza el mismo idioma al comparar la referencia.

El JSON contiene versión, ID de ability, nombre MCP, grupo, etiqueta, descripción, anotaciones del registro, esquemas de entrada/salida y archivo de origen. Las anotaciones internas se llaman `readonly`, `destructive` e `idempotent`; el Markdown las presenta con sus nombres MCP `readOnlyHint`, `destructiveHint` e `idempotentHint`.

La generación conserva el orden del catálogo y no incluye fechas, credenciales ni datos de contenido. Actions regenera la referencia después del recorrido y comprueba que coincide con los archivos versionados. Si el esquema no existe o falla la escritura, el script devuelve un error.

## Mantener código y documentación alineados

Al añadir o cambiar una herramienta:

1. Actualiza su registro, esquema, callback, anotaciones y lista `tool_ids()`.
2. Revisa su grupo en `Catalog`; los prefijos de integración se asignan automáticamente y una herramienta nativa no clasificada cae en `system`.
3. Ejecuta las pruebas pertinentes y vuelve a generar la referencia.
4. Actualiza los totales del README y las notas de comportamiento, permisos o dependencias que hayan cambiado.
5. Añade la entrada de changelog correspondiente y verifica los enlaces relativos.

Los ejemplos de conexión describen el protocolo que sirve este código. Si cambia un cliente externo, revisa sus plantillas por separado y comprueba la compatibilidad de su formato antes de anunciarlas como verificadas.
