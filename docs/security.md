# Seguridad y administración

[Inicio](../README.md) · [Conexión](connection.md) · [Comportamiento](behavior.md)

## Autorización y exposición

Todas las herramientas de Renova exigen **`current_user_can('manage_options')`**. La autorización efectiva combina la capacidad del usuario, disponibilidad de la integración, grupo habilitado, modo solo lectura global y alcance de la credencial. Los filtros se aplican antes del callback de permisos original de la ability.

El servidor MCP por defecto del adaptador se desactiva con `mcp_adapter_create_default_server=false`: no se ofrece su catálogo genérico de descubrimiento/ejecución. El servidor de Renova se identifica como `renova-mcp-server` y usa el espacio REST `renova-mcp/v1`.

Los grupos se gestionan en **Herramientas y seguridad**. Los de ACF, Elementor y Rank Math permanecen fuera de MCP cuando falta su dependencia; las abilities siguen registradas internamente. Un token limitado tampoco ve en `tools/list` lo que no puede ejecutar. Tras cambiar plugins o ajustes, vuelve a conectar o refresca las herramientas del cliente.

**Los grupos limitan herramientas, no objetos o claves concretos de WordPress.** Por ejemplo, `get-option` puede leer cualquier opción por nombre, `update-option` puede escribirlas, y los metadatos pueden afectar el comportamiento de otros plugins. El modo solo lectura permite lecturas administrativas que pueden contener datos sensibles. No constituye un aislamiento por página, usuario, opción o integración.

## Ajustes

Se guardan en la opción `renova_mcp_settings`.

| Clave | Predeterminado | Valores admitidos |
| --- | --- | --- |
| `disabled_groups` | `[]` | Grupos excluidos: `content`, `site`, `users`, `system`, `acf`, `elementor`, `rankmath`. |
| `read_only` | `false` | Oculta y bloquea herramientas cuya anotación `readonly` no sea `true`. |
| `rate_limit` | `120` | De 0 a 6000 peticiones/minuto; 0 desactiva el límite general. |
| `audit_retention` | `30` | De 1 a 365 días de actividad. |
| `oauth_enabled` | `false` | Habilita descubrimiento, registro y autorización OAuth. |

Al guardar grupos, el formulario conserva la selección previa de integraciones ausentes. La etiqueta de lectura se evalúa para la herramienta completa: `manage-transient` se bloquea incluso si se solicita `action: "get"`; `check-updates` se admite como lectura aunque refresque las comprobaciones de WordPress.

## Tokens Bearer

Los tokens de acceso manuales usan el prefijo `rmcp_` y 40 caracteres aleatorios; su tabla guarda el **hash SHA-256**, usuario, nombre, tipo, alcance, fechas e IP de último uso. El valor se muestra una sola vez. Para atravesar la redirección del formulario, se conserva temporalmente una copia cifrada con una clave ligada a la sesión del navegador, durante un minuto, y se elimina al leerla.

Desde el panel se elige caducidad de 30, 90 o 365 días, o sin caducidad. Los tokens no elevan capacidades: si el usuario pierde `manage_options`, la herramienta deja de autorizarse. Al eliminar un usuario se revocan sus tokens.

El alcance contiene `groups` y `read_only`. En la API interna, **`groups: []` significa sin restricción de grupo**, no ausencia de acceso. La interfaz exige al menos un grupo; seleccionar todos los grupos disponibles se normaliza como acceso sin restricción y también cubre integraciones que se activen después. La configuración global puede seguir restringiendo ese acceso.

Los tokens manuales se revocan por ID. Los tokens OAuth comparten `grant_id`: revocar la conexión elimina toda la concesión. El último uso y su IP se actualizan como máximo una vez cada cinco minutos por token o concesión, por lo que el panel no es un contador en tiempo real de cada solicitud.

## OAuth

El servidor implementa autorización para **clientes públicos** (`token_endpoint_auth_method: "none"`), registro dinámico, código con **PKCE S256** y renovación con rotación. No emite secretos de cliente ni ofrece `client_credentials` o flujo de contraseña. El scope OAuth anunciado es `mcp`; los grupos y la lectura se eligen en el consentimiento.

- Registro: de **1 a 10 URI de retorno**, máximo **10 intentos por IP y hora** y **100 clientes registrados**. Estos límites de registro son adicionales al límite REST general.
- Retorno: HTTPS, HTTP en loopback (`localhost`, `127.0.0.1`, `::1`) o esquemas propios de aplicación admitidos. Se rechazan esquemas peligrosos y fragmentos. La URI debe coincidir con una registrada; en retornos HTTP de loopback se permite variar el puerto.
- Consentimiento: sesión de WordPress y `manage_options`, formulario con nonce, protección contra iframes y posibilidad de cancelar. Un cliente o retorno inválido no provoca una redirección a una URL arbitraria.
- Código: **10 minutos**, de un solo intento de canje, vinculado al cliente, URI y desafío PKCE.
- Acceso: **1 hora**. Refresco: **30 días**, renovado con cada par nuevo. Al renovar se revocan tanto el acceso como el refresco anteriores de esa concesión.
- Los tokens se entregan con cabeceras `Cache-Control: no-store` y `Pragma: no-cache`. Los metadatos de descubrimiento pueden cachearse 5 minutos.

**Desactivar OAuth impide nuevas autorizaciones y renovaciones, pero no revoca los tokens de acceso ya emitidos.** Revoca también las conexiones en **Tokens** si necesitas cortar su acceso de inmediato. No hay una ruta OAuth específica de revocación pública; la administración gestiona la revocación.

## Límite de peticiones

El filtro REST cubre las rutas bajo **`/renova-mcp/v1/`**, incluidos MCP, registro y token OAuth. Usa una ventana fija de un minuto y transients:

| Autenticación | Unidad de conteo |
| --- | --- |
| Token manual | ID del token. |
| Token OAuth | Concesión `grant_id`, compartida aunque roten los tokens. |
| Contraseña de aplicación u otra sesión de WordPress | ID del usuario; varias contraseñas del mismo usuario comparten contador. |
| Sin autenticar | IP; máximo `min(rate_limit, 30)` por minuto. |

La respuesta al exceder este límite es `429`, código `renova_mcp_rate_limited` y cabecera `Retry-After` con los segundos restantes de la ventana. Con `rate_limit=0` se desactiva este control general, incluido el anónimo; el límite propio de registro OAuth sigue existiendo. El control no cubre las rutas de descubrimiento o consentimiento servidas fuera de REST ni sustituye al control de tráfico del servidor.

## Registro de actividad

La pestaña **Actividad** muestra las últimas **100 entradas**. Se almacenan las inicializaciones MCP y las llamadas `tools/call` observadas por el adaptador, así como intentos con tokens reconocidos como Renova pero inválidos, caducados o revocados. No es un registro exhaustivo de todo el tráfico REST, inicios de sesión de WordPress o métodos MCP.

Cada fila incluye fecha UTC, usuario, token, tipo de autenticación, método, herramienta, estado, duración, IP y detalle resumido. Para resumir argumentos:

- Se conservan números y booleanos.
- Cadenas identificadoras como `id`, `slug`, `name`, `key`, `option`, `selector`, tipo o estado pueden guardarse hasta 120 caracteres.
- De otras cadenas se registra su longitud; de arrays, la cantidad de elementos.
- El resumen de argumentos se limita a 600 caracteres; un fallo puede añadir una descripción acotada de su motivo.

No se vuelcan sistemáticamente cuerpos de contenido ni valores completos, pero nombres, identificadores, mensajes de error e IP sí pueden incluir información del sitio. Los rechazos de token se limitan a cinco registros por IP y minuto para reducir ruido. El registro puede vaciarse manualmente; la retención no lo convierte en un archivo inmutable.

## HTTPS y proxies

Bearer se acepta si WordPress reconoce HTTPS o si `wp_get_environment_type()` devuelve `local`. La IP inicial es `REMOTE_ADDR` y se limita a 45 caracteres. Hay dos filtros propios:

| Filtro | Argumento y retorno | Uso |
| --- | --- | --- |
| `renova_mcp_is_secure_request` | Booleano. | Ajustar la detección de conexión segura detrás de un proxy que termina TLS. |
| `renova_mcp_client_ip` | Cadena de IP. | Resolver la IP real cuando existe un proxy de confianza. |

Configura estos filtros conforme a la topología real. No fuerces siempre `true` ni confíes en cabeceras de IP que un cliente pueda enviar directamente. Las contraseñas de aplicación siguen las reglas de autenticación de WordPress.

## Almacenamiento y ciclo de vida

El prefijo de tabla es el de la instalación, no necesariamente `wp_`.

| Recurso | Contenido |
| --- | --- |
| `{prefix}renova_mcp_tokens` | Hashes y metadatos de tokens manuales y OAuth. |
| `{prefix}renova_mcp_audit` | Actividad del servidor. |
| `renova_mcp_settings` | Configuración. |
| `renova_mcp_oauth_clients` | Clientes y URI de retorno registrados. |
| `renova_mcp_db_version` | Versión del esquema propio. |
| `_renova_elementor_backup` | Última copia del árbol por contenido, en post-meta. |
| Transients `renova_mcp_*` | Códigos OAuth, límites, protección contra ruido y visualización temporal del token. |

El evento **`renova_mcp_prune`** se programa diariamente, con primera ejecución prevista una hora después. Elimina tokens vencidos, actividad anterior a la retención y clientes de más de un día sin tokens asociados. Depende de que WP-Cron se ejecute; no asegura eliminación al segundo exacto de caducidad. La validación del token comprueba su vigencia aunque todavía no haya pasado la limpieza.

**Desactivar** conserva los datos y cancela el evento de limpieza. **Desinstalar** mediante WordPress elimina las dos tablas, las tres opciones propias, todas las copias `_renova_elementor_backup` y el evento. No borra el contenido creado, las modificaciones hechas mediante herramientas ni las contraseñas de aplicación de WordPress. El desinstalador no recorre los transients ni el almacenamiento de sesiones del adaptador; no debe describirse como una eliminación inmediata de todo rastro temporal.
