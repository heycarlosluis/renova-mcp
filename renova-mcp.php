<?php
/**
 * Plugin Name:       Renova MCP Control
 * Plugin URI:        https://github.com/heycarlosluis/renova
 * Description:       Servidor MCP para control total de WordPress (plugins, temas, páginas, entradas y opciones) vía la Abilities API + WordPress MCP Adapter.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            renova
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       renova-mcp
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

define( 'RENOVA_MCP_VERSION', '1.0.0' );
define( 'RENOVA_MCP_DIR', plugin_dir_path( __FILE__ ) );
define( 'RENOVA_MCP_SERVER_ID', 'renova-mcp-server' );
define( 'RENOVA_MCP_ROUTE_NAMESPACE', 'renova-mcp/v1' );
define( 'RENOVA_MCP_ROUTE', 'mcp' );

/*
 * Carga la librería MCP Adapter (Jetpack Autoloader) y las clases del plugin.
 * El vendor/ viaja en el repositorio para que el despliegue por Git en Hostinger
 * disponga de las dependencias sin necesidad de ejecutar Composer en el servidor.
 */
$renova_mcp_autoload = RENOVA_MCP_DIR . 'vendor/autoload_packages.php';
if ( is_readable( $renova_mcp_autoload ) ) {
	require_once $renova_mcp_autoload;
}
require_once RENOVA_MCP_DIR . 'includes/class-abilities.php';

/**
 * Inicializa el MCP Adapter. Su instancia engancha la creación de servidores
 * en el hook `mcp_adapter_init` durante `rest_api_init` (o `init` en WP-CLI).
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p><strong>Renova MCP:</strong> ';
					esc_html_e( 'no se pudo cargar la librería MCP Adapter. Falta el directorio vendor/. Ejecuta "composer install" en wp-content/plugins/renova-mcp.', 'renova-mcp' );
					echo '</p></div>';
				}
			);
			return;
		}

		\WP\MCP\Core\McpAdapter::instance();
	}
);

// Registra la categoría y todas las "abilities" (herramientas) en el núcleo.
add_action( 'wp_abilities_api_categories_init', array( Abilities::class, 'register_category' ) );
add_action( 'wp_abilities_api_init', array( Abilities::class, 'register' ) );

/**
 * Crea el servidor MCP exponiendo las abilities como herramientas (tools).
 *
 * Endpoint resultante: /wp-json/renova-mcp/v1/mcp
 */
add_action(
	'mcp_adapter_init',
	static function ( $adapter ) {
		$adapter->create_server(
			RENOVA_MCP_SERVER_ID,
			RENOVA_MCP_ROUTE_NAMESPACE,
			RENOVA_MCP_ROUTE,
			'Renova MCP Control',
			'Control total de WordPress: gestión de plugins, temas, páginas, entradas y opciones del sitio.',
			RENOVA_MCP_VERSION,
			array( \WP\MCP\Transport\HttpTransport::class ),
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
			\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class,
			Abilities::tool_ids()
		);
	}
);
