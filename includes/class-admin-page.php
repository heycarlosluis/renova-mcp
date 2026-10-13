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
		Admin_Screens::register();
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
	 * Pestañas de la página.
	 *
	 * @return array<string,string>
	 */
	public static function tabs() {
		return array(
			'connect'  => __( 'Conexión', 'renova-mcp' ),
			'tokens'   => __( 'Tokens', 'renova-mcp' ),
			'tools'    => __( 'Herramientas y seguridad', 'renova-mcp' ),
			'activity' => __( 'Actividad', 'renova-mcp' ),
		);
	}

	/**
	 * URL de una pestaña.
	 *
	 * @param string              $tab  Clave de la pestaña.
	 * @param array<string,mixed> $args Parámetros adicionales.
	 * @return string
	 */
	public static function tab_url( $tab, array $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => self::PAGE_SLUG,
					'tab'  => $tab,
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Pestaña activa.
	 *
	 * @return string
	 */
	private static function current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'connect';

		return array_key_exists( $tab, self::tabs() ) ? $tab : 'connect';
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
	public static function endpoint_url() {
		return rest_url( RENOVA_MCP_ROUTE_NAMESPACE . '/' . RENOVA_MCP_ROUTE );
	}

	/**
	 * Plantillas de configuración por cliente.
	 *
	 * `__AUTH__` se sustituye por el valor de la cabecera Authorization y
	 * `__TOKEN__` por el token en claro.
	 *
	 * @return array<int,array{key:string,name:string,sub:string,format:string,location:string,icon:string,snippet:string,note?:string}>
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
				'snippet'  => 'claude mcp add renova --transport http "' . $endpoint . '" \\' . "\n" . '  --header "Authorization: __AUTH__"',
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
			array(
				'key'      => 'poke',
				'name'     => 'Poke',
				'sub'      => __( 'URL del servidor y clave de API', 'renova-mcp' ),
				'format'   => 'CLI',
				'location' => __( 'Terminal', 'renova-mcp' ),
				'icon'     => 'network',
				'snippet'  => 'npx poke@latest mcp add "' . $endpoint . '" \\' . "\n" . '  -n "Renova" -k "__TOKEN__"',
				'note'     => __( 'Poke envía la clave como token Bearer: necesita un token de acceso, no una contraseña de aplicación. En su formulario web, pega la URL del endpoint y el token como clave de API.', 'renova-mcp' ),
			),
			array(
				'key'      => 'oauth',
				'name'     => __( 'Conector con OAuth', 'renova-mcp' ),
				'sub'      => __( 'Claude.ai, ChatGPT y similares', 'renova-mcp' ),
				'format'   => 'URL',
				'location' => __( 'Conector personalizado', 'renova-mcp' ),
				'icon'     => 'link',
				'snippet'  => $endpoint,
				'note'     => OAuth::enabled()
					? __( 'Pega solo la URL. El cliente abrirá este sitio para que inicies sesión y autorices la conexión.', 'renova-mcp' )
					: __( 'Pega solo la URL. Antes, activa OAuth en la pestaña Herramientas y seguridad.', 'renova-mcp' ),
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
			"\t\t\t\t\"Authorization\": \"__AUTH__\"\n" .
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
	public static function icon( $icon ) {
		echo '<i data-lucide="' . esc_attr( $icon ) . '" aria-hidden="true"></i>';
	}

	/** Renderiza la página. */
	public static function render() {
		$tab    = self::current_tab();
		$titles = array(
			'connect'  => array( __( 'Centro de conexión', 'renova-mcp' ), __( 'Conecta WordPress con tu asistente', 'renova-mcp' ), __( 'Prepara una conexión MCP segura y copia la configuración exacta para el cliente que utilizas.', 'renova-mcp' ) ),
			'tokens'   => array( __( 'Credenciales', 'renova-mcp' ), __( 'Tokens de acceso', 'renova-mcp' ), __( 'Crea un token por cada cliente, limita lo que puede hacer y revócalo cuando deje de usarse.', 'renova-mcp' ) ),
			'tools'    => array( __( 'Superficie expuesta', 'renova-mcp' ), __( 'Herramientas y seguridad', 'renova-mcp' ), __( 'Decide qué grupos de herramientas ve el asistente y con qué límites trabaja.', 'renova-mcp' ) ),
			'activity' => array( __( 'Auditoría', 'renova-mcp' ), __( 'Actividad', 'renova-mcp' ), __( 'Quién ejecutó qué herramienta, con qué credencial y con qué resultado.', 'renova-mcp' ) ),
		);

		echo '<main class="wrap renova-mcp-wrap" id="renova-mcp-main">';
		echo '<div class="renova-mcp-shell">';

		// Cabecera del producto.
		echo '<header class="renova-mcp-header">';
		echo '<div class="renova-mcp-brand">';
		echo '<span class="renova-mcp-brand-mark">';
		self::icon( 'network' );
		echo '</span>';
		echo '<div>';
		echo '<p class="renova-mcp-kicker">' . esc_html( $titles[ $tab ][0] ) . '</p>';
		echo '<h1 class="renova-mcp-title">' . esc_html( $titles[ $tab ][1] ) . '</h1>';
		echo '<p class="renova-mcp-lead">' . esc_html( $titles[ $tab ][2] ) . '</p>';
		echo '</div>';
		echo '</div>';
		echo '<span class="renova-mcp-version">v' . esc_html( RENOVA_MCP_VERSION ) . '</span>';
		echo '</header>';

		// Navegación entre secciones.
		echo '<nav class="renova-mcp-nav" aria-label="' . esc_attr__( 'Secciones de Renova MCP', 'renova-mcp' ) . '">';
		foreach ( self::tabs() as $key => $label ) {
			echo '<a class="renova-mcp-nav-link' . ( $key === $tab ? ' is-active' : '' ) . '" href="' . esc_url( self::tab_url( $key ) ) . '"' . ( $key === $tab ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		Admin_Screens::notice();

		if ( 'tokens' === $tab ) {
			Admin_Screens::tokens();
		} elseif ( 'tools' === $tab ) {
			Admin_Screens::tools();
		} elseif ( 'activity' === $tab ) {
			Admin_Screens::activity();
		} else {
			self::render_connect();
		}

		echo '<div class="renova-mcp-announcer renova-mcp-sr-only" role="status" aria-live="polite" aria-atomic="true"></div>';
		echo '</div>';
		echo '</main>';
	}

	/** Renderiza la guía de conexión. */
	private static function render_connect() {
		$endpoint = self::endpoint_url();
		$clients  = self::client_configs();
		$user     = wp_get_current_user();
		$profile  = admin_url( 'profile.php#application-passwords-section' );

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
		echo '<span>' . esc_html( OAuth::enabled() ? __( 'Acepta token Bearer, Basic Auth y OAuth 2.1', 'renova-mcp' ) : __( 'Acepta token Bearer y Basic Auth', 'renova-mcp' ) ) . '</span>';
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
		echo '<div><h2 id="renova-mcp-password-title">' . esc_html__( 'Elige una credencial', 'renova-mcp' ) . '</h2>';
		echo '<p>' . esc_html__( 'Una exclusiva para cada cliente que conectes.', 'renova-mcp' ) . '</p></div>';
		echo '</div>';
		echo '<div class="renova-mcp-instruction">';
		echo '<span class="renova-mcp-instruction-number">1</span>';
		echo '<p>' . esc_html__( 'Crea un token de acceso: funciona con cualquier cliente y puedes limitarlo a solo lectura o a ciertos grupos.', 'renova-mcp' ) . '</p>';
		echo '</div>';
		echo '<div class="renova-mcp-instruction">';
		echo '<span class="renova-mcp-instruction-number">2</span>';
		echo '<p>' . esc_html__( 'Si lo prefieres, usa una contraseña de aplicación de tu perfil de WordPress.', 'renova-mcp' ) . '</p>';
		echo '</div>';
		echo '<a class="renova-mcp-button renova-mcp-button-secondary" href="' . esc_url( self::tab_url( 'tokens' ) ) . '">';
		self::icon( 'lock-keyhole' );
		echo '<span>' . esc_html__( 'Crear un token', 'renova-mcp' ) . '</span>';
		self::icon( 'chevron-right' );
		echo '</a>';
		echo '<a class="renova-mcp-button renova-mcp-button-secondary renova-mcp-button-stacked" href="' . esc_url( $profile ) . '">';
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
		echo '<fieldset class="renova-mcp-segmented">';
		echo '<legend class="renova-mcp-sr-only">' . esc_html__( 'Tipo de credencial', 'renova-mcp' ) . '</legend>';
		echo '<label><input type="radio" name="renova_mcp_auth_mode" value="token" checked /><span>' . esc_html__( 'Token de acceso', 'renova-mcp' ) . '</span></label>';
		echo '<label><input type="radio" name="renova_mcp_auth_mode" value="basic" /><span>' . esc_html__( 'Contraseña de aplicación', 'renova-mcp' ) . '</span></label>';
		echo '</fieldset>';
		echo '<div class="renova-mcp-form" data-auth-mode="token">';
		echo '<label class="renova-mcp-field" for="renova-mcp-token">';
		echo '<span>' . esc_html__( 'Token de acceso', 'renova-mcp' ) . '</span>';
		echo '<span class="renova-mcp-password-control">';
		echo '<input type="password" id="renova-mcp-token" name="renova_mcp_token" autocomplete="off" spellcheck="false" placeholder="rmcp_…" aria-describedby="renova-mcp-auth-help" />';
		echo '<button type="button" class="renova-mcp-visibility" data-toggle="#renova-mcp-token" aria-label="' . esc_attr__( 'Mostrar token', 'renova-mcp' ) . '" data-label-show="' . esc_attr__( 'Mostrar token', 'renova-mcp' ) . '" data-label-hide="' . esc_attr__( 'Ocultar token', 'renova-mcp' ) . '" aria-pressed="false">';
		self::icon( 'eye' );
		echo '</button>';
		echo '</span>';
		echo '</label>';
		echo '</div>';
		echo '<div class="renova-mcp-form" data-auth-mode="basic" hidden>';
		echo '<label class="renova-mcp-field" for="renova-mcp-user">';
		echo '<span>' . esc_html__( 'Usuario de WordPress', 'renova-mcp' ) . '</span>';
		echo '<input type="text" id="renova-mcp-user" name="renova_mcp_user" autocomplete="username" value="' . esc_attr( $user->user_login ) . '" aria-describedby="renova-mcp-auth-help" />';
		echo '</label>';
		echo '<label class="renova-mcp-field" for="renova-mcp-pass">';
		echo '<span>' . esc_html__( 'Contraseña de aplicación', 'renova-mcp' ) . '</span>';
		echo '<span class="renova-mcp-password-control">';
		echo '<input type="password" id="renova-mcp-pass" name="renova_mcp_pass" autocomplete="current-password" placeholder="xxxx xxxx xxxx xxxx" aria-describedby="renova-mcp-auth-help" />';
		echo '<button type="button" class="renova-mcp-visibility" data-toggle="#renova-mcp-pass" aria-label="' . esc_attr__( 'Mostrar contraseña', 'renova-mcp' ) . '" data-label-show="' . esc_attr__( 'Mostrar contraseña', 'renova-mcp' ) . '" data-label-hide="' . esc_attr__( 'Ocultar contraseña', 'renova-mcp' ) . '" aria-pressed="false">';
		self::icon( 'eye' );
		echo '</button>';
		echo '</span>';
		echo '</label>';
		echo '</div>';
		echo '<div class="renova-mcp-output">';
		echo '<span class="renova-mcp-output-label">Authorization</span>';
		echo '<div class="renova-mcp-output-row">';
		echo '<code class="renova-mcp-auth" id="renova-mcp-auth">Bearer TU_TOKEN</code>';
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
			echo esc_html( isset( $cfg['note'] ) ? $cfg['note'] : __( 'La autorización se completa automáticamente cuando escribes la credencial.', 'renova-mcp' ) ) . '</p>';
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
		echo '<li><strong>' . esc_html__( 'Da el mínimo acceso', 'renova-mcp' ) . '</strong><span>' . esc_html__( 'Un token por cliente, limitado a los grupos que necesita.', 'renova-mcp' ) . '</span></li>';
		echo '<li><strong>' . esc_html__( 'Revoca el acceso', 'renova-mcp' ) . '</strong><span>' . esc_html__( 'Elimina el token cuando dejes de usar el cliente.', 'renova-mcp' ) . '</span></li>';
		echo '</ul>';
		echo '</aside>';
	}
}
