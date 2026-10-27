# Instalación y conexión

[Inicio](../README.md) · [Herramientas](tools.md) · [Seguridad](security.md)

## Instalar o actualizar

Copia la carpeta completa a `wp-content/plugins/renova-mcp/`, incluido `vendor/`, y activa el plugin. Puede instalarse por ZIP, SFTP o Git. No depende de un dominio concreto. Las dependencias están versionadas; si recibes una copia sin ellas, ejecuta `composer install` en la carpeta del plugin antes de desplegar.

Al actualizar por Git no hace falta desactivar y reactivar: el plugin comprueba su versión de esquema en `plugins_loaded` y crea las tablas de tokens y actividad cuando corresponde. La [guía de seguridad](security.md#almacenamiento-y-ciclo-de-vida) detalla la conservación y eliminación de datos.

Abre **Renova MCP → Conexión** y copia el endpoint mostrado. Se construye con `rest_url()`: respeta la ubicación del sitio y su configuración REST. La URL habitual con WordPress en la raíz y enlaces permanentes activos es:

```text
https://TU-DOMINIO/wp-json/renova-mcp/v1/mcp
```

Con enlaces permanentes simples puede usar `?rest_route=/renova-mcp/v1/mcp`. No construyas la URL a mano cuando el panel ya ofrece la correcta.

## Elegir autenticación

| Método | Configuración | Alcance |
| --- | --- | --- |
| Token Bearer | Crear en **Tokens**; enviar `Authorization: Bearer rmcp_…`. | Grupos y solo lectura por token; caducidad opcional. |
| Contraseña de aplicación | Crear en el perfil de WordPress; enviar Basic con login y contraseña de aplicación. | Capacidades del usuario y restricciones globales del plugin. |
| OAuth | Activar en **Herramientas y seguridad**; conectar con la URL y aprobar en el navegador. | Grupos y solo lectura elegidos en el consentimiento. |

Las herramientas exigen `manage_options` incluso cuando la credencial es válida. Para acceso remoto usa HTTPS. Bearer admite también un entorno declarado `local`; las contraseñas de aplicación se rigen por la disponibilidad que determine WordPress.

### Token Bearer

1. En **Tokens**, indica un nombre que identifique al cliente.
2. Marca al menos un grupo y, si corresponde, **Solo lectura**.
3. Elige **No caduca**, **30 días**, **90 días** o **1 año**.
4. Crea el token y guarda el valor: se muestra una sola vez.
5. En el cliente, utiliza ese valor como clave de API si el cliente la envía como Bearer, o configura la cabecera completa `Authorization: Bearer TU_TOKEN`.

Un campo que pida solo la clave recibe `rmcp_…`, sin añadir `Bearer`. Un campo de cabecera `Authorization` recibe `Bearer rmcp_…`. Puedes revocar el acceso desde **Tokens**; para cambiar los permisos de un token manual, crea otro y revoca el anterior.

La pestaña **Conexión** permite pegar la credencial y copiar plantillas por cliente. Las sustituciones de las plantillas se realizan en JavaScript en el navegador. El formato que admite cada cliente debe comprobarse en su propia configuración; la compatibilidad del servidor se define por HTTP, las cabeceras y el flujo MCP que se describen aquí.

### Contraseña de aplicación

En **Usuarios → Perfil → Contraseñas de aplicación**, genera una contraseña para el cliente. Utiliza tu **login de WordPress** y esa contraseña mediante Basic; no uses la contraseña normal de inicio de sesión.

```text
Authorization: Basic BASE64_DE_LOGIN_DOS_PUNTOS_CONTRASEÑA_DE_APLICACIÓN
```

Base64 es una codificación, por lo que esta cabecera también requiere HTTPS. Las contraseñas de aplicación no tienen los alcances por token de Renova; siguen sometidas a los grupos y al modo solo lectura globales.

### OAuth con PKCE

Activa **OAuth** en **Herramientas y seguridad** y proporciona el endpoint MCP al cliente. El cliente debe implementar descubrimiento, registro dinámico de cliente público y autorización con PKCE S256. El flujo es:

1. Una respuesta `401` del endpoint anuncia `WWW-Authenticate: Bearer` y la URL `resource_metadata` cuando OAuth está habilitado.
2. El cliente descubre el servidor y se registra con sus URI de retorno.
3. El navegador abre WordPress; un usuario con `manage_options` inicia sesión, selecciona grupos y aprueba o cancela.
4. El cliente canjea el código y el verificador PKCE por tokens de acceso y refresco.
5. Envía el token de acceso como Bearer al endpoint MCP y renueva cuando corresponda.

Para este descubrimiento, WordPress debe poder servir las rutas `/.well-known/` desde la raíz del dominio y utilizar enlaces permanentes distintos de **Simple**. La implementación actual se documenta para un sitio cuya URL pública esté en la raíz; las instalaciones en subdirectorios requieren revisar la resolución del emisor y de las rutas de descubrimiento.

| Ruta habitual | Método | Uso |
| --- | --- | --- |
| `/.well-known/oauth-protected-resource/wp-json/renova-mcp/v1/mcp` | GET | Metadatos del recurso; URL anunciada para el endpoint habitual. |
| `/.well-known/oauth-protected-resource` | GET | También devuelve los metadatos del recurso. |
| `/.well-known/oauth-authorization-server` | GET | Emisor, endpoints y métodos admitidos. |
| `/?renova_mcp_oauth=authorize` | GET / POST | Inicio de sesión y consentimiento. |
| `/wp-json/renova-mcp/v1/oauth/register` | POST JSON | Registro dinámico de cliente público. |
| `/wp-json/renova-mcp/v1/oauth/token` | POST formulario o JSON | Canje de código o renovación. |

El servidor ofrece el scope OAuth `mcp`; la selección de grupos se guarda en la concesión y se aplica al catálogo. Los endpoints de registro y token son públicos por diseño: las herramientas siguen requiriendo autenticación y autorización. Cuando OAuth está desactivado, registro y token responden `404` y no se sirven los documentos de descubrimiento del plugin.

El código dura **10 minutos** y se consume en el primer intento de canje. El acceso dura **1 hora**; el refresco, **30 días**. Cada renovación revoca los tokens anteriores de la concesión y emite un par nuevo. Desde **Tokens** se revoca la conexión completa. Para las restricciones de registro y retorno, consulta [Seguridad](security.md#oauth).

## Transporte y sesiones

El adaptador incluido negocia `2025-11-25`, `2025-06-18` y `2024-11-05`. Usa **POST** con JSON-RPC 2.0 y `Content-Type: application/json`. No existe un endpoint `/sse`; GET devuelve `405` porque este transporte no implementa un flujo SSE de escucha.

1. Envía `initialize` con la credencial y los datos del cliente.
2. Conserva la cabecera de respuesta **`Mcp-Session-Id`** y la versión negociada.
3. Envía `notifications/initialized`; responde `202` sin cuerpo.
4. Usa esa sesión y la credencial en `tools/list`, `tools/call` y las demás peticiones. Envía `MCP-Protocol-Version` con la versión negociada.
5. Puedes cerrar la sesión mediante DELETE al mismo endpoint, con autenticación y `Mcp-Session-Id`.

### Ejemplo HTTP

Sustituye los valores de ejemplo por el endpoint y token de tu sitio. Primero inicializa:

```bash
curl -i 'https://TU-DOMINIO/wp-json/renova-mcp/v1/mcp' \
  -H 'Authorization: Bearer TU_TOKEN' \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  --data '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"mi-cliente","version":"1.0.0"}}}'
```

Copia el valor de `Mcp-Session-Id` devuelto y envía la notificación:

```bash
curl -i 'https://TU-DOMINIO/wp-json/renova-mcp/v1/mcp' \
  -H 'Authorization: Bearer TU_TOKEN' \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -H 'Mcp-Session-Id: TU_SESION' \
  -H 'MCP-Protocol-Version: 2025-06-18' \
  --data '{"jsonrpc":"2.0","method":"notifications/initialized"}'
```

Lista las herramientas autorizadas:

```bash
curl -sS 'https://TU-DOMINIO/wp-json/renova-mcp/v1/mcp' \
  -H 'Authorization: Bearer TU_TOKEN' \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -H 'Mcp-Session-Id: TU_SESION' \
  -H 'MCP-Protocol-Version: 2025-06-18' \
  --data '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}'
```

Para ejecutar una lectura, conserva las mismas cabeceras y cambia el cuerpo por:

```json
{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"renova-mcp-get-site-info","arguments":{}}}
```

La credencial necesita acceso al grupo `site` para ese ejemplo. Las herramientas sin parámetros reciben `{}`, no `[]`. Los clientes MCP suelen gestionar automáticamente esta secuencia.

## Resolver problemas

| Síntoma | Qué comprobar |
| --- | --- |
| `401`, token inválido o conexión insegura | Token completo, vigente y sin revocar; HTTPS reconocido por WordPress; cabecera `Authorization` preservada por el servidor o proxy. |
| Credencial válida pero herramienta rechazada | Capacidad `manage_options`, grupo activo, permisos del token y modo solo lectura global. |
| Error de sesión ausente o caducada | Repetir `initialize` y conservar el nuevo `Mcp-Session-Id`. |
| `405` al abrir el endpoint en el navegador | Usar POST e inicializar MCP; GET no sirve como prueba. |
| Herramienta de ACF, Elementor o Rank Math ausente | Plugin requerido presente, grupo habilitado y alcance de la credencial. Reconectar para refrescar el listado. |
| `429` | Respetar `Retry-After` cuando se incluya y reducir la frecuencia; consultar el límite configurado. |
| `404` de REST o descubrimiento OAuth | URL copiada del panel, plugin activo, reglas del servidor, enlaces permanentes y OAuth habilitado. |
| Aviso de MCP Adapter no disponible | Restaurar `vendor/` o instalar las dependencias con Composer antes del despliegue. |
| Cambios de Elementor sin reflejarse visualmente | Revisar el árbol y CSS de la página; la caché de objetos, la de Elementor y la del hosting/CDN son distintas. |

Las peticiones de ejemplo y la suite automatizada no requieren un cliente MCP comercial. El plugin no garantiza que una plantilla concreta sea aceptada por todas las versiones de cada cliente.
