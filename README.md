# Renova MCP Control

Servidor **MCP (Model Context Protocol)** para controlar este WordPress de forma remota
desde cualquier cliente MCP (Claude, etc.). Construido sobre la **Abilities API** del núcleo
de WordPress y la librería oficial [WordPress/mcp-adapter](https://github.com/WordPress/mcp-adapter).

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
| `list-posts` | Listar entradas o páginas |
| `create-post` / `update-post` / `delete-post` | Crear / editar / borrar contenido |
| `get-option` / `update-option` | Leer / escribir opciones del sitio |

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
