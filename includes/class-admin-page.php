<?php
/**
 * Página de administración con la guía de configuración de Renova MCP.
 *
 * Registra una página en el menú de WordPress que, en lugar de ajustes,
 * muestra una guía paso a paso para conectar el servidor MCP a cualquier
 * cliente (Claude, Cursor, etc.).
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Página de administración "Renova MCP".
 */
class Admin_Page {

	const PAGE_SLUG = 'renova-mcp-guide';

	/**
	 * Hook de la página (para encolar assets solo en ella).
	 *
	 * @var string
	 */
	private static $hook = '';

	/**
	 * Registra la página en el menú de administración.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Añade la página al menú.
	 */
	public static function add_menu() {
		self::$hook = add_menu_page(
			__( 'Renova MCP', 'renova-mcp' ),
			__( 'Renova MCP', 'renova-mcp' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' ),
			'dashicons-rest-api',
			81
		);
	}

	/**
	 * Encola estilos y scripts solo en la página de la guía.
	 *
	 * @param string $hook Hook de la página actual.
	 */
	public static function enqueue( $hook ) {
		if ( $hook !== self::$hook ) {
			return;
		}

		wp_enqueue_style(
			'renova-mcp-admin',
			RENOVA_MCP_DIR . 'assets/admin-page.css',
			array(),
			RENOVA_MCP_VERSION
		);

		wp_enqueue_script(
			'renova-mcp-admin',
			RENOVA_MCP_DIR . 'assets/admin-page.js',
			array(),
			RENOVA_MCP_VERSION,
			true
		);
	}

	/**
	 * URL pública del endpoint MCP.
	 *
	 * @return string
	 */
	private static function endpoint_url() {
		return rest_url( RENOVA_MCP_ROUTE_NAMESPACE . '/' . RENOVA_MCP_ROUTE );
	}

	/**
	 * Plantillas de configuración por cliente.
	 *
	 * @return array<int,array{name:string,sub:string,format:string,snippet:string}>
	 */
	private static function client_configs() {
		$endpoint = self::endpoint_url();

		return array(
			array(
				'name'    => 'Claude Desktop',
				'sub'     => __( 'Configuración de la app de escritorio de Claude.', 'renova-mcp' ),
				'format'  => 'json',
				'snippet' => self::json_snippet( 'claude', $endpoint, true ),
			),
			array(
				'name'    => 'Claude Code',
				'sub'     => __( 'Línea de comandos para añadir el servidor.', 'renova-mcp' ),
				'format'  => 'shell',
				'snippet' => 'claude mcp add renova --transport http "' . $endpoint . '" \\' . "\n" . '  --header "Authorization: Basic __AUTH__"',
			),
			array(
				'name'    => 'Cursor',
				'sub'     => __( 'Archivo .cursor/mcp.json del proyecto.', 'renova-mcp' ),
				'format'  => 'json',
				'snippet' => self::json_snippet( 'cursor', $endpoint, false ),
			),
			array(
				'name'    => 'Codex / ChatGPT',
				'sub'     => __( 'Cliente OpenAI compatible con MCP HTTP.', 'renova-mcp' ),
				'format'  => 'json',
				'snippet' => self::json_snippet( 'openai', $endpoint, false ),
			),
		);
	}

	/**
	 * Construye un bloque JSON de configuración de servidor MCP.
	 *
	 * @param string $client  Clave del cliente.
	 * @param string $endpoint URL del endpoint.
	 * @param bool   $with_type Incluye "type": "http" (Claude Desktop lo requiere).
	 * @return string
	 */
	private static function json_snippet( $client, $endpoint, $with_type ) {
		$type_line = $with_type ? "\t\t\t\"type\": \"http\",\n" : '';

		return "{\n" .
			"\t\"mcpServers\": {\n" .
			"\t\t\"" . $client . "\": {\n" .
			$type_line .
			"\t\t\t\"url\": \"" . $endpoint . "\",\n" .
			"\t\t\t\"headers\": {\n" .
			"\t\t\t\t\"Authorization\": \"Basic __AUTH__\"\n" .
			"\t\t\t}\n" .
			"\t\t}\n" .
			"\t}\n" .
			"}";
	}

	/**
	 * Renderiza la página.
	 */
	public static function render() {
		$endpoint = self::endpoint_url();

		echo '<div class="wrap renova-mcp-wrap">';

		// Cabecera.
		echo '<div class="renova-mcp-header">';
		echo '<div>';
		echo '<h1 class="renova-mcp-title">' . esc_html__( 'Renova MCP', 'renova-mcp' ) . '</h1>';
		echo '<p class="renova-mcp-lead">' . esc_html__( 'Conecta este WordPress a cualquier cliente MCP (Claude, Cursor, Codex…) y controla todo el sitio de forma remota.', 'renova-mcp' ) . '</p>';
		echo '</div>';
		echo '<span class="renova-mcp-badge">v' . esc_html( RENOVA_MCP_VERSION ) . '</span>';
		echo '</div>';

		// Endpoint (destacado).
		echo '<section class="renova-mcp-card renova-mcp-endpoint">';
		echo '<div class="renova-mcp-endpoint-head">';
		echo '<span class="renova-mcp-overline">' . esc_html__( 'Endpoint MCP', 'renova-mcp' ) . '</span>';
		echo '<span class="renova-mcp-pill">Streamable HTTP</span>';
		echo '</div>';
		echo '<div class="renova-mcp-endpoint-row">';
		echo '<code class="renova-mcp-endpoint-url" id="renova-mcp-endpoint">' . esc_html( $endpoint ) . '</code>';
		echo '<button type="button" class="button renova-mcp-copy" data-copy="#renova-mcp-endpoint">' . esc_html__( 'Copiar URL', 'renova-mcp' ) . '</button>';
		echo '</div>';
		echo '<p class="renova-mcp-hint">' . esc_html__( 'Autenticación Basic Auth: tu usuario de WordPress + una contraseña de aplicación.', 'renova-mcp' ) . '</p>';
		echo '</section>';

		echo '<div class="renova-mcp-grid">';

		// Columna izquierda: pasos 1 y 2.
		echo '<div class="renova-mcp-col">';

		// Paso 1: contraseña de aplicación.
		echo '<section class="renova-mcp-card">';
		echo '<h2 class="renova-mcp-card-title"><span class="renova-mcp-step">1</span>' . esc_html__( 'Crea una contraseña de aplicación', 'renova-mcp' ) . '</h2>';
		echo '<ol class="renova-mcp-steps">';
		echo '<li>' . esc_html__( 'Ve a Usuarios → Tu perfil.', 'renova-mcp' ) . '</li>';
		echo '<li>' . esc_html__( 'Baja hasta "Contraseñas de aplicación" y escribe un nombre (ej. "MCP").', 'renova-mcp' ) . '</li>';
		echo '<li>' . esc_html__( 'Pulsa "Añadir nueva contraseña de aplicación" y copia la contraseña generada (solo se muestra una vez).', 'renova-mcp' ) . '</li>';
		echo '<li>' . esc_html__( 'Guárdala en un lugar seguro: la usarás como contraseña en el paso siguiente.', 'renova-mcp' ) . '</li>';
		echo '</ol>';
		echo '</section>';

		// Paso 2: header de autorización.
		echo '<section class="renova-mcp-card">';
		echo '<h2 class="renova-mcp-card-title"><span class="renova-mcp-step">2</span>' . esc_html__( 'Genera tu header de autorización', 'renova-mcp' ) . '</h2>';
		echo '<p class="renova-mcp-hint">' . esc_html__( 'Introduce tus credenciales y copia el valor que se genera (se calcula en tu navegador, no se envía a ningún servidor).', 'renova-mcp' ) . '</p>';
		echo '<div class="renova-mcp-form">';
		echo '<label class="renova-mcp-field">';
		echo '<span>' . esc_html__( 'Usuario', 'renova-mcp' ) . '</span>';
		echo '<input type="text" id="renova-mcp-user" autocomplete="username" placeholder="tu_usuario" />';
		echo '</label>';
		echo '<label class="renova-mcp-field">';
		echo '<span>' . esc_html__( 'Contraseña de aplicación', 'renova-mcp' ) . '</span>';
		echo '<input type="password" id="renova-mcp-pass" autocomplete="current-password" placeholder="xxxx xxxx xxxx xxxx" />';
		echo '</label>';
		echo '</div>';
		echo '<div class="renova-mcp-auth-row">';
		echo '<code class="renova-mcp-auth" id="renova-mcp-auth">Basic __AUTH__</code>';
		echo '<button type="button" class="button renova-mcp-copy" data-copy="#renova-mcp-auth">' . esc_html__( 'Copiar', 'renova-mcp' ) . '</button>';
		echo '</div>';
		echo '</section>';

		echo '</div>';

		// Columna derecha: paso 3 (clientes).
		echo '<div class="renova-mcp-col">';
		echo '<section class="renova-mcp-card renova-mcp-clients">';
		echo '<h2 class="renova-mcp-card-title"><span class="renova-mcp-step">3</span>' . esc_html__( 'Conecta tu cliente', 'renova-mcp' ) . '</h2>';
		echo '<p class="renova-mcp-hint">' . esc_html__( 'Pega el endpoint y el header de autorización en la configuración MCP de tu cliente preferido.', 'renova-mcp' ) . '</p>';

		foreach ( self::client_configs() as $cfg ) {
			echo '<div class="renova-mcp-client">';
			echo '<div class="renova-mcp-client-head">';
			echo '<div>';
			echo '<strong class="renova-mcp-client-name">' . esc_html( $cfg['name'] ) . '</strong>';
			echo '<span class="renova-mcp-client-sub">' . esc_html( $cfg['sub'] ) . '</span>';
			echo '</div>';
			echo '<button type="button" class="button button-small renova-mcp-copy" data-copy="#snippet-' . esc_attr( sanitize_title( $cfg['name'] ) ) . '">' . esc_html__( 'Copiar', 'renova-mcp' ) . '</button>';
			echo '</div>';
			echo '<pre class="renova-mcp-pre"><code class="renova-mcp-snippet" id="snippet-' . esc_attr( sanitize_title( $cfg['name'] ) ) . '" data-template="' . esc_attr( base64_encode( $cfg['snippet'] ) ) . '">' . esc_html( $cfg['snippet'] ) . '</code></pre>';
			echo '</div>';
		}

		echo '</section>';

		// Nota de seguridad.
		echo '<section class="renova-mcp-card renova-mcp-security">';
		echo '<h2 class="renova-mcp-card-title">' . esc_html__( 'Seguridad', 'renova-mcp' ) . '</h2>';
		echo '<ul class="renova-mcp-bullets">';
		echo '<li>' . esc_html__( 'Usa siempre HTTPS para el endpoint.', 'renova-mcp' ) . '</li>';
		echo '<li>' . esc_html__( 'La contraseña de aplicación solo debe estar en manos de confianza; revócala cuando dejes de usarla.', 'renova-mcp' ) . '</li>';
		echo '<li>' . esc_html__( 'Solo los usuarios administradores pueden ejecutar las herramientas.', 'renova-mcp' ) . '</li>';
		echo '</ul>';
		echo '</section>';

		echo '</div>';
		echo '</div>';

		echo '</div>';
	}
}
