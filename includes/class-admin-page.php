<?php
/**
 * Página de administración con la guía de configuración de Renova MCP.
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

	/** Registra la página en el menú de administración. */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/** Añade la página al menú. */
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
			RENOVA_MCP_URL . 'assets/admin-page.css',
			array(),
			RENOVA_MCP_VERSION
		);

		wp_enqueue_script(
			'renova-mcp-lucide',
			RENOVA_MCP_URL . 'assets/lucide-icons.min.js',
			array(),
			RENOVA_MCP_VERSION,
			true
		);

		wp_enqueue_script(
			'renova-mcp-admin',
			RENOVA_MCP_URL . 'assets/admin-page.js',
			array( 'renova-mcp-lucide' ),
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
	 * @return array<int,array{key:string,name:string,sub:string,format:string,location:string,icon:string,snippet:string}>
	 */
	private static function client_configs() {
		$endpoint = self::endpoint_url();

		return array(
			array(
				'key'      => 'claude-desktop',
				'name'     => 'Claude Desktop',
				'sub'      => __( 'Aplicación de escritorio', 'renova-mcp' ),
				'format'   => 'JSON',
				'location' => 'claude_desktop_config.json',
				'icon'     => 'monitor',
				'snippet'  => self::json_snippet( 'claude', $endpoint, true ),
			),
			array(
				'key'      => 'claude-code',
				'name'     => 'Claude Code',
				'sub'      => __( 'Comando para la terminal', 'renova-mcp' ),
				'format'   => 'CLI',
				'location' => __( 'Terminal', 'renova-mcp' ),
				'icon'     => 'square-terminal',
				'snippet'  => 'claude mcp add renova --transport http "' . $endpoint . '" \\' . "\n" . '  --header "Authorization: Basic __AUTH__"',
			),
			array(
				'key'      => 'cursor',
				'name'     => 'Cursor',
				'sub'      => __( 'Configuración del proyecto', 'renova-mcp' ),
				'format'   => 'JSON',
				'location' => '.cursor/mcp.json',
				'icon'     => 'mouse-pointer-2',
				'snippet'  => self::json_snippet( 'cursor', $endpoint, false ),
			),
			array(
				'key'      => 'codex-chatgpt',
				'name'     => 'Codex / ChatGPT',
				'sub'      => __( 'Cliente OpenAI con MCP HTTP', 'renova-mcp' ),
				'format'   => 'JSON',
				'location' => __( 'Configuración MCP', 'renova-mcp' ),
				'icon'     => 'bot',
				'snippet'  => self::json_snippet( 'openai', $endpoint, false ),
			),
		);
	}

	/**
	 * Construye un bloque JSON de configuración de servidor MCP.
	 *
	 * @param string $client    Clave del cliente.
	 * @param string $endpoint  URL del endpoint.
	 * @param bool   $with_type Incluye "type": "http".
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
	 * Renderiza un icono decorativo de Lucide.
	 *
	 * @param string $icon Clase del icono.
	 */
	private static function icon( $icon ) {
		echo '<i data-lucide="' . esc_attr( $icon ) . '" aria-hidden="true"></i>';
	}

	/** Renderiza la página. */
	public static function render() {
		$endpoint = self::endpoint_url();
		$clients  = self::client_configs();
		$user     = wp_get_current_user();
		$profile  = admin_url( 'profile.php#application-passwords-section' );

		echo '<main class="wrap renova-mcp-wrap" id="renova-mcp-main">';
		echo '<div class="renova-mcp-shell">';

		// Cabecera del producto.
		echo '<header class="renova-mcp-header">';
		echo '<div class="renova-mcp-brand">';
		echo '<span class="renova-mcp-brand-mark">';
		self::icon( 'network' );
		echo '</span>';
		echo '<div>';
		echo '<p class="renova-mcp-kicker">' . esc_html__( 'Centro de conexión', 'renova-mcp' ) . '</p>';
		echo '<h1 class="renova-mcp-title">' . esc_html__( 'Conecta WordPress con tu asistente', 'renova-mcp' ) . '</h1>';
		echo '<p class="renova-mcp-lead">' . esc_html__( 'Prepara una conexión MCP segura y copia la configuración exacta para el cliente que utilizas.', 'renova-mcp' ) . '</p>';
		echo '</div>';
		echo '</div>';
		echo '<span class="renova-mcp-version">v' . esc_html( RENOVA_MCP_VERSION ) . '</span>';
		echo '</header>';

		// Endpoint destacado.
		echo '<section class="renova-mcp-endpoint" aria-labelledby="renova-mcp-endpoint-title">';
		echo '<div class="renova-mcp-endpoint-intro">';
		echo '<span class="renova-mcp-endpoint-icon">';
		self::icon( 'link' );
		echo '</span>';
		echo '<div>';
		echo '<h2 id="renova-mcp-endpoint-title">' . esc_html__( 'Endpoint MCP', 'renova-mcp' ) . '</h2>';
		echo '<p>' . esc_html__( 'Usa esta URL en cualquiera de los clientes compatibles.', 'renova-mcp' ) . '</p>';
		echo '</div>';
		echo '</div>';
		echo '<div class="renova-mcp-endpoint-main">';
		echo '<code class="renova-mcp-endpoint-url" id="renova-mcp-endpoint"><bdi>' . esc_html( $endpoint ) . '</bdi></code>';
		echo '<button type="button" class="renova-mcp-button renova-mcp-button-primary renova-mcp-copy" data-copy="#renova-mcp-endpoint">';
		self::icon( 'copy' );
		echo '<span class="renova-mcp-copy-label">' . esc_html__( 'Copiar URL', 'renova-mcp' ) . '</span>';
		echo '</button>';
		echo '</div>';
		echo '<div class="renova-mcp-endpoint-meta">';
		echo '<span class="renova-mcp-status">';
		self::icon( 'circle-check' );
		echo esc_html__( 'Streamable HTTP', 'renova-mcp' ) . '</span>';
		echo '<span>' . esc_html__( 'Protegido con Basic Auth', 'renova-mcp' ) . '</span>';
		echo '</div>';
		echo '</section>';

		echo '<div class="renova-mcp-workspace">';

		// Columna izquierda: preparación.
		echo '<div class="renova-mcp-setup">';
		echo '<section class="renova-mcp-panel renova-mcp-password-panel" aria-labelledby="renova-mcp-password-title">';
		echo '<div class="renova-mcp-panel-heading">';
		echo '<span class="renova-mcp-panel-icon">';
		self::icon( 'lock-keyhole' );
		echo '</span>';
		echo '<div><h2 id="renova-mcp-password-title">' . esc_html__( 'Contraseña de aplicación', 'renova-mcp' ) . '</h2>';
		echo '<p>' . esc_html__( 'Crea una credencial exclusiva para esta conexión.', 'renova-mcp' ) . '</p></div>';
		echo '</div>';
		echo '<div class="renova-mcp-instruction">';
		echo '<span class="renova-mcp-instruction-number">1</span>';
		echo '<p>' . esc_html__( 'Abre tu perfil y busca la sección Contraseñas de aplicación.', 'renova-mcp' ) . '</p>';
		echo '</div>';
		echo '<div class="renova-mcp-instruction">';
		echo '<span class="renova-mcp-instruction-number">2</span>';
		echo '<p>' . esc_html__( 'Crea una con el nombre “MCP” y copia el valor generado.', 'renova-mcp' ) . '</p>';
		echo '</div>';
		echo '<a class="renova-mcp-button renova-mcp-button-secondary" href="' . esc_url( $profile ) . '">';
		self::icon( 'user-round' );
		echo '<span>' . esc_html__( 'Abrir mi perfil', 'renova-mcp' ) . '</span>';
		self::icon( 'external-link' );
		echo '</a>';
		echo '</section>';

		echo '<section class="renova-mcp-panel renova-mcp-auth-panel" aria-labelledby="renova-mcp-auth-title">';
		echo '<div class="renova-mcp-panel-heading">';
		echo '<span class="renova-mcp-panel-icon">';
		self::icon( 'shield-check' );
		echo '</span>';
		echo '<div><h2 id="renova-mcp-auth-title">' . esc_html__( 'Genera la autorización', 'renova-mcp' ) . '</h2>';
		echo '<p id="renova-mcp-auth-help">' . esc_html__( 'Los datos se procesan solo en este navegador.', 'renova-mcp' ) . '</p></div>';
		echo '</div>';
		echo '<div class="renova-mcp-form">';
		echo '<label class="renova-mcp-field" for="renova-mcp-user">';
		echo '<span>' . esc_html__( 'Usuario de WordPress', 'renova-mcp' ) . '</span>';
		echo '<input type="text" id="renova-mcp-user" name="renova_mcp_user" autocomplete="username" value="' . esc_attr( $user->user_login ) . '" aria-describedby="renova-mcp-auth-help" />';
		echo '</label>';
		echo '<label class="renova-mcp-field" for="renova-mcp-pass">';
		echo '<span>' . esc_html__( 'Contraseña de aplicación', 'renova-mcp' ) . '</span>';
		echo '<span class="renova-mcp-password-control">';
		echo '<input type="password" id="renova-mcp-pass" name="renova_mcp_pass" autocomplete="current-password" placeholder="xxxx xxxx xxxx xxxx" aria-describedby="renova-mcp-auth-help" />';
		echo '<button type="button" class="renova-mcp-visibility" aria-label="' . esc_attr__( 'Mostrar contraseña', 'renova-mcp' ) . '" aria-pressed="false">';
		self::icon( 'eye' );
		echo '</button>';
		echo '</span>';
		echo '</label>';
		echo '</div>';
		echo '<div class="renova-mcp-output">';
		echo '<span class="renova-mcp-output-label">Authorization</span>';
		echo '<div class="renova-mcp-output-row">';
		echo '<code class="renova-mcp-auth" id="renova-mcp-auth">Basic __AUTH__</code>';
		echo '<button type="button" class="renova-mcp-icon-button renova-mcp-copy" data-copy="#renova-mcp-auth" aria-label="' . esc_attr__( 'Copiar autorización', 'renova-mcp' ) . '">';
		self::icon( 'copy' );
		echo '<span class="renova-mcp-copy-label renova-mcp-sr-only">' . esc_html__( 'Copiar autorización', 'renova-mcp' ) . '</span>';
		echo '</button>';
		echo '</div>';
		echo '</div>';
		echo '</section>';
		echo '</div>';

		// Columna derecha: selector y configuración por cliente.
		echo '<section class="renova-mcp-panel renova-mcp-clients" aria-labelledby="renova-mcp-clients-title">';
		echo '<div class="renova-mcp-clients-heading">';
		echo '<div><h2 id="renova-mcp-clients-title">' . esc_html__( 'Conecta tu cliente', 'renova-mcp' ) . '</h2>';
		echo '<p>' . esc_html__( 'Selecciona una aplicación y copia su configuración.', 'renova-mcp' ) . '</p></div>';
		echo '<span class="renova-mcp-client-count">' . esc_html( count( $clients ) ) . ' ' . esc_html__( 'clientes', 'renova-mcp' ) . '</span>';
		echo '</div>';

		echo '<div class="renova-mcp-client-layout">';
		echo '<div class="renova-mcp-client-tabs" role="tablist" aria-label="' . esc_attr__( 'Clientes MCP', 'renova-mcp' ) . '">';
		foreach ( $clients as $index => $cfg ) {
			$selected = 0 === $index;
			echo '<button type="button" class="renova-mcp-client-tab' . ( $selected ? ' is-active' : '' ) . '" id="renova-mcp-tab-' . esc_attr( $cfg['key'] ) . '" role="tab" aria-selected="' . ( $selected ? 'true' : 'false' ) . '" aria-controls="renova-mcp-panel-' . esc_attr( $cfg['key'] ) . '" tabindex="' . ( $selected ? '0' : '-1' ) . '" data-client="' . esc_attr( $cfg['key'] ) . '">';
			echo '<span class="renova-mcp-client-icon">';
			self::icon( $cfg['icon'] );
			echo '</span>';
			echo '<span class="renova-mcp-client-copy"><strong>' . esc_html( $cfg['name'] ) . '</strong><small>' . esc_html( $cfg['sub'] ) . '</small></span>';
			self::icon( 'chevron-right' );
			echo '</button>';
		}
		echo '</div>';

		echo '<div class="renova-mcp-code-area">';
		foreach ( $clients as $index => $cfg ) {
			$selected = 0 === $index;
			echo '<div class="renova-mcp-code-panel' . ( $selected ? ' is-active' : '' ) . '" id="renova-mcp-panel-' . esc_attr( $cfg['key'] ) . '" role="tabpanel" aria-labelledby="renova-mcp-tab-' . esc_attr( $cfg['key'] ) . '"' . ( $selected ? '' : ' hidden' ) . '>';
			echo '<div class="renova-mcp-code-toolbar">';
			echo '<div><span class="renova-mcp-code-format">' . esc_html( $cfg['format'] ) . '</span><strong>' . esc_html( $cfg['location'] ) . '</strong></div>';
			echo '<button type="button" class="renova-mcp-button renova-mcp-button-code renova-mcp-copy" data-copy="#snippet-' . esc_attr( $cfg['key'] ) . '">';
			self::icon( 'copy' );
			echo '<span class="renova-mcp-copy-label">' . esc_html__( 'Copiar configuración', 'renova-mcp' ) . '</span>';
			echo '</button>';
			echo '</div>';
			echo '<pre class="renova-mcp-pre" tabindex="0"><code class="renova-mcp-snippet" id="snippet-' . esc_attr( $cfg['key'] ) . '" data-template="' . esc_attr( base64_encode( $cfg['snippet'] ) ) . '">' . esc_html( $cfg['snippet'] ) . '</code></pre>';
			echo '<p class="renova-mcp-code-note">';
			self::icon( 'info' );
			echo esc_html__( 'La autorización se completa automáticamente cuando escribes la contraseña.', 'renova-mcp' ) . '</p>';
			echo '</div>';
		}
		echo '</div>';
		echo '</div>';
		echo '</section>';
		echo '</div>';

		// Recomendaciones de seguridad.
		echo '<aside class="renova-mcp-security" aria-labelledby="renova-mcp-security-title">';
		echo '<div class="renova-mcp-security-heading">';
		echo '<span class="renova-mcp-security-icon">';
		self::icon( 'shield' );
		echo '</span>';
		echo '<div><h2 id="renova-mcp-security-title">' . esc_html__( 'Conecta con seguridad', 'renova-mcp' ) . '</h2>';
		echo '<p>' . esc_html__( 'Tres prácticas para mantener el acceso bajo control.', 'renova-mcp' ) . '</p></div>';
		echo '</div>';
		echo '<ul class="renova-mcp-security-list">';
		echo '<li><strong>' . esc_html__( 'Usa HTTPS', 'renova-mcp' ) . '</strong><span>' . esc_html__( 'No envíes credenciales por una conexión sin cifrar.', 'renova-mcp' ) . '</span></li>';
		echo '<li><strong>' . esc_html__( 'Crea una clave exclusiva', 'renova-mcp' ) . '</strong><span>' . esc_html__( 'No reutilices tu contraseña principal de WordPress.', 'renova-mcp' ) . '</span></li>';
		echo '<li><strong>' . esc_html__( 'Revoca el acceso', 'renova-mcp' ) . '</strong><span>' . esc_html__( 'Elimina la contraseña cuando dejes de usar el cliente.', 'renova-mcp' ) . '</span></li>';
		echo '</ul>';
		echo '</aside>';

		echo '<div class="renova-mcp-announcer renova-mcp-sr-only" role="status" aria-live="polite" aria-atomic="true"></div>';
		echo '</div>';
		echo '</main>';
	}
}
