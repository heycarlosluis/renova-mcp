# Comportamiento e integraciones

[Inicio](../README.md) · [Referencia completa](tools.md) · [Esquemas JSON](tools.json)

Estas notas complementan los parámetros registrados de las 117 herramientas en la versión **1.4.0**. El catálogo se registra completo en la Abilities API, pero el servidor MCP publica únicamente los grupos disponibles y habilitados, filtrados por el modo de lectura y la credencial.

## Nombres, resultados y errores

- Ability de WordPress: `renova-mcp/get-post`. Nombre MCP: **`renova-mcp-get-post`**.
- Los argumentos son objetos JSON. El tipo `cualquiera` en la referencia significa que el esquema no fija un tipo para ese valor; los esquemas completos están en [tools.json](tools.json).
- Una herramienta puede devolver datos, un indicador `success` o un `WP_Error`. Los listados suelen incluir `count` y una colección con nombre propio; `count` es el número devuelto, no un total paginado.
- El adaptador entrega los resultados de herramientas como contenido MCP y, cuando corresponde, `structuredContent`. Un fallo de ejecución puede llegar como `result.isError=true`; un método o herramienta inexistente puede producir `error` JSON-RPC. No basta con mirar el estado HTTP.
- Muchas herramientas declaran únicamente una salida de tipo `object`. Esto no garantiza un contrato detallado de campos de salida ni que `success=true` implique una modificación efectiva: algunas funciones consideran correcta una actualización sin cambios.
- Las anotaciones `readOnlyHint`, `destructiveHint` e `idempotentHint` son las declaradas por el registro. La política de confirmación depende del cliente; el plugin no abre un diálogo para cada escritura remota.

## Contenido, tipos y taxonomías de WordPress

`list-posts` consulta `post` por defecto; **`create-post` crea `page` con estado `publish` por defecto**. Para trabajar en borrador, indica siempre `status: "draft"`. Ambos aceptan tipos de contenido registrados, incluidos CPT. `update-post` modifica los campos recibidos; no cambia el tipo de contenido. Los estados de creación y actualización se validan contra los estados de publicación admitidos por WordPress, incluido `future`.

```json
{"name":"renova-mcp-create-post","arguments":{"title":"Nueva entrada","post_type":"post","status":"draft","content":"Contenido de prueba"}}
```

`get-post` devuelve campos del contenido, metadatos y términos de sus taxonomías. `get-post-meta` lee una clave o todas; los valores leídos sin una clave conservan la estructura de WordPress. `update-post-meta` y `delete-post-meta` operan sobre claves del contenido indicado.

`delete-post` pasa `force=false` por defecto a WordPress; el uso de papelera depende del tipo de contenido y de la configuración del sitio. Con `force=true` solicita el borrado definitivo.

`list-post-types` y `list-taxonomies` inspeccionan registros ya existentes. La creación y modificación de definiciones de CPT y taxonomías se realiza mediante las herramientas de ACF; no existe una herramienta nativa equivalente para registrar arbitrariamente un tipo nuevo.

`set-post-terms` acepta IDs numéricos o nombres; normaliza los valores numéricos a enteros. Con `append=false`, el valor inicial, reemplaza las asignaciones de esa taxonomía; con `append=true` añade términos. WordPress puede crear términos cuando se pasan nombres. `get-post-terms` acepta una taxonomía o una lista. No puede eliminarse el término predeterminado protegido de una taxonomía.

## Usuarios y medios

`get-user` busca por `id`, después por `login` y después por `email`, según los campos presentes. Devuelve los datos públicos administrativos y nombre/apellido, sin devolver contraseñas. `create-user` usa el rol `subscriber` si se omite y genera una contraseña si no se proporciona; la respuesta no devuelve esa contraseña generada. `update-user` admite contraseña y rol.

`delete-user` bloquea la eliminación del usuario autenticado y la del último usuario con rol `administrator`. Con `reassign` puedes reasignar su contenido; sin reasignación se aplica el comportamiento de eliminación de WordPress. Estas protecciones corresponden a `delete-user`, no a cualquier cambio posible de rol o metadatos.

`upload-media` importa un archivo desde una **URL** mediante las funciones de descarga e importación de WordPress; no recibe un archivo binario o base64. Admite título, texto alternativo, contenido asociado y uso como imagen destacada. Los tipos de archivo y permisos del servidor siguen dependiendo de WordPress.

**`delete-media` usa `force=true` por defecto**: solicita borrar el adjunto y sus archivos definitivamente. Si envías `force=false`, la posibilidad de papelera depende de la configuración de medios de WordPress. `set-featured-image` recibe un adjunto existente; `attachment_id: 0` quita la imagen destacada sin eliminar el medio.

## Menús, comentarios y utilidades

Las seis herramientas de menús trabajan con los **menús clásicos de WordPress** (`wp_nav_menu`), sus elementos y ubicaciones del tema. No implementan la edición de los bloques de navegación del editor del sitio. Los menús se resuelven por ID, nombre o slug. `add-menu-item` admite enlaces personalizados o `object_id`; la implementación reconoce `category` y `post_tag` como objetos de taxonomía y trata otros valores de `object` como tipos de contenido.

`moderate-comment` acepta `approve`, `hold`, `spam` y `trash`. `list-comments` devuelve una vista recortada del contenido a 30 palabras. `delete-comment` usa `force=false` salvo que se indique lo contrario y delega la eliminación en WordPress.

`get-option` y `update-option` trabajan con opciones por nombre, sin una lista específica de opciones permitidas. Los theme mods pertenecen al tema activo. `update-permalink-structure` y `flush-rewrite-rules` refrescan las reglas con escritura de reglas del servidor cuando WordPress lo permite.

`manage-transient` ofrece `get`, `set` y `delete`, con expiración en segundos para `set`. Toda la herramienta se clasifica como **escritura**, por lo que también se oculta y bloquea su operación `get` en modo solo lectura.

`flush-cache` llama a `wp_cache_flush()`: vacía la caché de objetos de WordPress. No equivale a purgar todas las cachés de página, hosting o CDN.

`check-updates` consulta actualizaciones del núcleo, plugins y temas y refresca los datos de comprobación de WordPress. **`run-update` solo actualiza plugins o temas**, con `type: "plugin"` o `"theme"`; no actualiza el núcleo. Para plugins utiliza la ruta completa obtenida de `list-plugins`; para temas, su `stylesheet`.

`install-plugin` descarga desde WordPress.org por slug y admite `activate`; no instala un ZIP arbitrario. Activar, desactivar y eliminar plugins acepta la ruta o una carpeta que el plugin pueda resolver. `delete-plugin` desactiva primero el plugin si está activo y comprueba el sistema de archivos. No existen herramientas específicas para instalar o borrar temas.

## Límites de listados

Estos son los valores por defecto y los topes superiores aplicados por las implementaciones a los parámetros `number` habituales. Usa enteros positivos; los esquemas no expresan en todos los casos un mínimo y los valores especiales de WordPress no deben usarse como garantía de paginación o límite.

| Listado | Predeterminado | Tope superior aplicado |
| --- | ---: | ---: |
| `list-posts` | 20 | 100 |
| `list-users` | 50 | 100 |
| `list-media` | 30 | 100 |
| `list-comments` | 50 | 100 |
| `list-terms` | 100 | 500 |
| `elementor-list-content` | 50 | 100 |
| `elementor-list-templates` | 100 | 100, fijo; no admite `number` |
| `rankmath-list-redirections` | 100 | 500 |
| `rankmath-get-404-log` | 100 | 500 |

No hay una interfaz general de paginación por página/cursor en estas herramientas. Consulta los parámetros de cada herramienta antes de asumir que acepta `offset` o `page`.

## Advanced Custom Fields: 26 herramientas

El grupo `acf` se expone cuando están disponibles `get_field()` y `acf_get_field_groups()`. Incluye **16** herramientas de valores, filas y grupos y **10** de definiciones de CPT/taxonomías.

- Los selectores de campo aceptan nombre o clave ACF. `post_id` puede ser numérico o un identificador ACF como `option`, `user_X` o `term_X`, sujeto a la API instalada.
- `acf-get-field` aplica formato por defecto (`format_value=true`); su indicador `exists` es falso si ACF devuelve `false` o `null`, por lo que no distingue por sí solo un valor booleano falso de un campo ausente.
- Las filas de repetidores se gestionan con `acf-add-row`, `acf-update-row` y `acf-delete-row`; el índice sigue la convención de ACF, normalmente desde **1**. El tipo de campo y su edición/licencia deben admitir repetidores.
- `acf-create-field-group` importa una definición de grupo; `acf-update-field-group` llama a la actualización de la definición del grupo. No debe asumirse que actualizar el grupo importa recursivamente todas sus definiciones de campos.
- La exportación de grupos exige que la instalación ofrezca `acf_export_field_group()`; si no está disponible devuelve un error de función no soportada. Que ACF esté activo no garantiza todas sus APIs.
- Las definiciones de CPT y taxonomías requieren la API de **ACF 6.1+**. El catálogo del grupo puede aparecer con ACF activo aunque esa API concreta no exista; las llamadas devuelven `renova_mcp_acf_types_inactive` cuando falta.
- `acf-save-post-type` y `acf-save-taxonomy` reciben un objeto de definición. Para crear indican el slug (`post_type` o `taxonomy`); para actualizar identifican la definición mediante `key` o `ID`. Los exportadores de esas definiciones usan la función de preparación de ACF cuando existe y devuelven la definición sin preparar en caso contrario.

Las definiciones se guardan mediante ACF; su registro y disponibilidad efectiva siguen el ciclo de carga de ese plugin. No se crean herramientas adicionales por cada campo, CPT o taxonomía.

## Elementor: 23 herramientas

El grupo se publica cuando se ha ejecutado `elementor/loaded` o existe `Elementor\Plugin`. El código edita el árbol guardado en **`_elementor_data`** y los ajustes en **`_elementor_page_settings`**. No reproduce el editor visual ni valida todos los controles, widgets o relaciones que puede imponer una versión de Elementor.

### Inspección y edición

`elementor-get-data` devuelve el árbol completo; `elementor-get-structure` produce un resumen con IDs, tipos, widgets y una vista previa de texto. `elementor-find-elements` filtra por `widget_type` o `el_type`; sin ninguno no devuelve coincidencias. Los IDs de elementos son cadenas y son distintos del ID numérico del contenido.

`elementor-update-data` reemplaza el árbol completo. `elementor-update-element` modifica los ajustes de un nodo: fusiona con `array_merge` por defecto y los reemplaza con `replace=true`. Las fusiones son superficiales; un valor anidado recibido puede reemplazar el anterior completo.

Añadir y duplicar elementos regenera sus IDs recursivamente. Mover conserva sus IDs. `position` cuenta desde **0**; un valor negativo u omitido añade al final. Un padre vacío representa la raíz. Reordenar conserva al final los hijos que no se mencionen en `order`.

Cada escritura del árbol marca el modo de edición si estaba vacío, guarda la versión de Elementor cuando está disponible y limpia/regenera el CSS de la página. Los ajustes de página también refrescan su CSS. El hosting o CDN puede tener una caché adicional.

### Copias de seguridad

Se conserva **una sola copia por contenido** en `_renova_elementor_backup`; cada copia nueva sobrescribe la anterior. Guarda el árbol y una fecha, no todos los datos de la página, sus medios ni el kit global.

Crean copia automáticamente **`elementor-update-data`**, **`elementor-delete-element`** y **`elementor-apply-template`**. Las demás ediciones no crean una copia automática: utiliza `elementor-backup-data` antes si necesitas poder restaurar el árbol. `elementor-restore-backup` restaura la última copia y refresca CSS; no crea una copia del estado que reemplaza.

### Plantillas y kit

Las plantillas se guardan en `elementor_library`. Pueden crearse desde `data` o copiando `source_id`; si se proporcionan ambos, se utiliza el origen. Se crean publicadas. Aplicar una plantilla usa `replace` por defecto o `append`, regenera los IDs del árbol importado y hace una copia previa del destino.

El kit se resuelve desde `elementor_active_kit`. Las dos herramientas globales requieren un kit activo; **el código no exige Elementor Pro por separado**. Las opciones y widgets disponibles dependen de Elementor y de Pro si está instalado. Actualizar los ajustes globales hace una fusión superficial y limpia la caché global de Elementor.

## Rank Math SEO: 13 herramientas

El grupo requiere `rank_math()`, `RANK_MATH_VERSION` o `RankMath\Helper`. La implementación opera sobre los metadatos `rank_math_*`, las opciones globales y las tablas de Rank Math.

- `rankmath-get-post-seo` devuelve las claves de metadatos con prefijo `rank_math_`. Su actualización recibe campos concretos como título, descripción, palabra clave, canonical, robots, Open Graph/Facebook, Twitter y contenido pilar; los nombres de escritura no son necesariamente las claves completas devueltas por la lectura.
- Schema se guarda por tipo en `rank_math_schema_TIPO`. `rankmath-set-post-schema` crea o reemplaza ese bloque y añade `@type` si falta; el borrado afecta solo al tipo indicado.
- Los ajustes globales se organizan en `general`, `titles` y `sitemap`. Actualizarlos fusiona superficialmente el objeto recibido con el existente.
- Redirecciones y monitor 404 requieren sus **módulos y tablas** de Rank Math. Si faltan, sus listados devuelven un error específico; las escrituras también necesitan que exista el almacenamiento correspondiente.
- Crear una redirección recibe `sources` como lista de patrones, con una comparación común y código `301` por defecto. Se usa la API interna de Rank Math si está disponible o una inserción directa como alternativa.
- `rankmath-update-redirection` cambia destino, código y estado; no edita patrones de origen. La actualización y el borrado operan directamente en la tabla.
- `rankmath-clear-404-log` vacía por completo la tabla del monitor; no es un borrado selectivo ni una operación reversible.

La compatibilidad funcional de estas integraciones requiere pruebas con las versiones instaladas de terceros. La suite incluida verifica el núcleo MCP en un WordPress sin ACF, Elementor ni Rank Math; no ejecuta estas 62 herramientas contra esos plugins.
