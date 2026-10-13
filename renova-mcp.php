<?php
/**
 * Plugin Name:       Renova MCP Control
 * Plugin URI:        https://github.com/heycarlosluis/renova-mcp
 * Description:       Servidor MCP para control total de WordPress (plugins, temas, contenido, CPT y taxonomías vía ACF, términos, meta, opciones, usuarios, medios, menús, comentarios, utilidades de sitio, Elementor/Elementor Pro y Rank Math SEO) vía la Abilities API + WordPress MCP Adapter.
 * Version:           1.4.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Tested up to:      7.1
 * Author:            heycarlosluis
 * Author URI:        https://heycarlosluis.me
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       renova-mcp
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

define( 'RENOVA_MCP_VERSION', '1.4.0' );
define( 'RENOVA_MCP_DIR', plugin_dir_path( __FILE__ ) );
define( 'RENOVA_MCP_URL', plugin_dir_url( __FILE__ ) );
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
require_once RENOVA_MCP_DIR . 'includes/class-acf-abilities.php';
require_once RENOVA_MCP_DIR . 'includes/class-acf-types-abilities.php';
require_once RENOVA_MCP_DIR . 'includes/class-taxonomy-abilities.php';
require_once RENOVA_MCP_DIR . 'includes/class-elementor-abilities.php';
require_once RENOVA_MCP_DIR . 'includes/class-rankmath-abilities.php';
require_once RENOVA_MCP_DIR . 'includes/class-users-media-abilities.php';
require_once RENOVA_MCP_DIR . 'includes/class-site-abilities.php';
require_once RENOVA_MCP_DIR . 'includes/class-settings.php';
require_once RENOVA_MCP_DIR . 'includes/class-catalog.php';
require_once RENOVA_MCP_DIR . 'includes/class-store.php';
require_once RENOVA_MCP_DIR . 'includes/class-request.php';
require_once RENOVA_MCP_DIR . 'includes/class-tokens.php';
require_once RENOVA_MCP_DIR . 'includes/class-access.php';
require_once RENOVA_MCP_DIR . 'includes/class-rate-limit.php';
require_once RENOVA_MCP_DIR . 'includes/class-audit-log.php';
require_once RENOVA_MCP_DIR . 'includes/class-oauth.php';
require_once RENOVA_MCP_DIR . 'includes/class-admin-screens.php';
require_once RENOVA_MCP_DIR . 'includes/class-admin-page.php';

// Tablas propias, autenticación por token, alcances por herramienta, límite de
// peticiones, registro de actividad y OAuth.
Store::register();
Tokens::register();
Access::register();
Rate_Limit::register();
Audit_Log::register();
OAuth::register();
register_deactivation_hook( __FILE__, array( Store::class, 'unschedule' ) );

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

		// Implementa una interfaz del adaptador: solo puede cargarse si este existe.
		require_once RENOVA_MCP_DIR . 'includes/class-audit-observability-handler.php';

		\WP\MCP\Core\McpAdapter::instance();
	}
);

// Desactiva el servidor MCP por defecto del adaptador. Expone las abilities
// `discover`/`execute`/`get-ability-info` en /wp-json/mcp/mcp-adapter-default-server,
// lo que permitiría ejecutar cualquier ability registrada. Solo queremos exponer
// nuestro servidor controlado (endpoint renova-mcp/v1/mcp).
add_filter( 'mcp_adapter_create_default_server', '__return_false' );

// Registra la categoría y todas las "abilities" (herramientas) en el núcleo.
add_action( 'wp_abilities_api_categories_init', array( Abilities::class, 'register_category' ) );
add_action( 'wp_abilities_api_init', array( Abilities::class, 'register' ) );

// Abilities ACF (valores y grupos de campos).
add_action( 'wp_abilities_api_init', array( Acf_Abilities::class, 'register' ) );

// Abilities ACF para CPT y taxonomías (registro de tipos vía ACF 6.1+).
add_action( 'wp_abilities_api_init', array( Acf_Types_Abilities::class, 'register' ) );

// Abilities de taxonomías y términos nativos de WordPress.
add_action( 'wp_abilities_api_init', array( Taxonomy_Abilities::class, 'register' ) );

// Abilities de Elementor y Elementor Pro (árbol, plantillas, kit global).
add_action( 'wp_abilities_api_init', array( Elementor_Abilities::class, 'register' ) );

// Abilities de Rank Math SEO (meta SEO, schema, ajustes, redirecciones, 404).
add_action( 'wp_abilities_api_init', array( Rankmath_Abilities::class, 'register' ) );

// Abilities de usuarios y biblioteca de medios.
add_action( 'wp_abilities_api_init', array( Users_Media_Abilities::class, 'register' ) );

// Abilities de menús, comentarios y utilidades de sitio (permalinks, caché, updates).
add_action( 'wp_abilities_api_init', array( Site_Abilities::class, 'register' ) );

// Página de administración con la guía de configuración.
Admin_Page::register();

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
			'Control total de WordPress: plugins, temas, contenido (cualquier CPT), meta, opciones, campos ACF, registro de Custom Post Types y taxonomías vía ACF, términos, usuarios, biblioteca de medios, menús, comentarios, utilidades de sitio (permalinks, caché, actualizaciones), control completo de Elementor y Elementor Pro (árbol de elementos, plantillas y kit global) y de Rank Math SEO (meta SEO, schema, ajustes, redirecciones y monitor 404).',
			RENOVA_MCP_VERSION,
			array( \WP\MCP\Transport\HttpTransport::class ),
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
			Audit_Observability_Handler::class,
			// Solo los grupos activados cuyo plugin está presente y, en modo
			// solo lectura, únicamente las herramientas de consulta.
			Catalog::exposed_tool_ids()
		);
	}
);
