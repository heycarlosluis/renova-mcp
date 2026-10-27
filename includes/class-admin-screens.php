<?php
/**
 * Pantallas de administración: tokens, herramientas y seguridad, y actividad.
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Renderiza las pantallas y procesa sus formularios.
 */
class Admin_Screens {

	/** Engancha el procesamiento de formularios. */
	public static function register() {
		add_action( 'admin_post_renova_mcp_create_token', array( __CLASS__, 'handle_create_token' ) );
		add_action( 'admin_post_renova_mcp_revoke_token', array( __CLASS__, 'handle_revoke_token' ) );
		add_action( 'admin_post_renova_mcp_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_renova_mcp_clear_activity', array( __CLASS__, 'handle_clear_activity' ) );
	}

	// --- Formularios ----------------------------------------------------------------

	/**
	 * Comprueba permisos y nonce de un formulario.
	 *
	 * @param string $action Acción del nonce.
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos para gestionar Renova MCP.', 'renova-mcp' ), 403 );
		}
		check_admin_referer( $action );
	}

	/**
	 * Vuelve a una pestaña mostrando un aviso.
	 *
	 * @param string $tab    Pestaña de destino.
	 * @param string $notice Clave del aviso.
	 */
	private static function back( $tab, $notice ) {
		wp_safe_redirect( Admin_Page::tab_url( $tab, array( 'notice' => $notice ) ) );
		exit;
	}

	/**
	 * Clave del transient que guarda un token recién creado para mostrarlo una vez.
	 *
	 * @return string
	 */
	private static function new_token_key() {
		return 'renova_mcp_new_token_' . get_current_user_id();
	}

	/**
	 * Clave de cifrado ligada a la sesión del navegador. El token de sesión solo
	 * vive en la cookie (WordPress guarda su hash), así que lo almacenado no
	 * sirve de nada a quien lea la base de datos.
	 *
	 * @return string
	 */
	private static function session_key() {
		// Sin la extensión sodium de PHP se usa la implementación que trae WordPress.
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			require_once ABSPATH . WPINC . '/sodium_compat/autoload.php';
		}

		return hash( 'sha256', 'renova-mcp-new-token|' . wp_get_session_token(), true );
	}

	/**
	 * Guarda cifrado, durante un minuto, el token recién creado para mostrarlo
	 * tras la redirección.
	 *
	 * @param string $token Token en claro.
	 */
	private static function stash_new_token( $token ) {
		$key   = self::session_key();
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$box   = sodium_crypto_secretbox( $token, $nonce, $key );

		set_transient( self::new_token_key(), base64_encode( $nonce . $box ), MINUTE_IN_SECONDS ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Recupera (una sola vez) el token recién creado.
	 *
	 * @return string Token en claro, o cadena vacía.
	 */
	private static function take_new_token() {
		$stored = get_transient( self::new_token_key() );
		if ( ! is_string( $stored ) || '' === $stored ) {
			return '';
		}
		delete_transient( self::new_token_key() );

		$key = self::session_key();
		$raw = base64_decode( $stored, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}

		$token = sodium_crypto_secretbox_open(
			substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
			substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
			$key
		);

		return is_string( $token ) ? $token : '';
	}

	/** Crea un token de acceso. */
	public static function handle_create_token() {
		self::guard( 'renova_mcp_create_token' );

		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( '' === $name ) {
			self::back( 'tokens', 'token_name' );
		}

		$groups = isset( $_POST['groups'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['groups'] ) ) : array();
		if ( empty( $groups ) ) {
			self::back( 'tokens', 'token_groups' );
		}

		$days   = isset( $_POST['expires'] ) ? absint( $_POST['expires'] ) : 0;
		$result = Tokens::create(
			array(
				'name'    => $name,
				'user_id' => get_current_user_id(),
				'ttl'     => in_array( $days, array( 30, 90, 365 ), true ) ? $days * DAY_IN_SECONDS : 0,
				'scope'   => array(
					'groups'    => Catalog::resolve_selection( $groups ),
					'read_only' => ! empty( $_POST['read_only'] ),
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			self::back( 'tokens', 'token_error' );
		}

		self::stash_new_token( $result['token'] );
		self::back( 'tokens', 'token_created' );
	}

	/** Revoca un token o una conexión OAuth. */
	public static function handle_revoke_token() {
		self::guard( 'renova_mcp_revoke_token' );

		Tokens::revoke( isset( $_POST['token_id'] ) ? absint( $_POST['token_id'] ) : 0 );
		self::back( 'tokens', 'token_revoked' );
	}

	/** Guarda los ajustes de herramientas y seguridad. */
	public static function handle_save_settings() {
		self::guard( 'renova_mcp_save_settings' );

		$enabled = isset( $_POST['groups'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['groups'] ) ) : array();

		// Los grupos cuyo plugin no está activo no se envían (casilla desactivada):
		// conservan su estado para cuando el plugin vuelva.
		$disabled = array();
		foreach ( Catalog::group_slugs() as $group ) {
			$was_disabled = in_array( $group, (array) Settings::get( 'disabled_groups' ), true );
			if ( Catalog::group_available( $group ) ? ! in_array( $group, $enabled, true ) : $was_disabled ) {
				$disabled[] = $group;
			}
		}

		Settings::update(
			array(
				'disabled_groups' => $disabled,
				'read_only'       => ! empty( $_POST['read_only'] ),
				'rate_limit'      => isset( $_POST['rate_limit'] ) ? absint( $_POST['rate_limit'] ) : 0,
				'audit_retention' => isset( $_POST['audit_retention'] ) ? absint( $_POST['audit_retention'] ) : 30,
				'oauth_enabled'   => ! empty( $_POST['oauth_enabled'] ),
			)
		);

		self::back( 'tools', 'settings_saved' );
	}

	/** Vacía el registro de actividad. */
	public static function handle_clear_activity() {
		self::guard( 'renova_mcp_clear_activity' );

		Audit_Log::clear();
		self::back( 'activity', 'activity_cleared' );
	}

	// --- Avisos ---------------------------------------------------------------------

	/** Muestra el aviso que corresponda tras procesar un formulario. */
	public static function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';

		$messages = array(
			'token_revoked'    => array( 'success', __( 'Acceso revocado. El cliente dejará de poder conectarse.', 'renova-mcp' ) ),
			'token_name'       => array( 'error', __( 'Ponle un nombre al token para reconocerlo después.', 'renova-mcp' ) ),
			'token_groups'     => array( 'error', __( 'Marca al menos un grupo de herramientas.', 'renova-mcp' ) ),
			'token_error'      => array( 'error', __( 'No se pudo guardar el token. Inténtalo de nuevo.', 'renova-mcp' ) ),
			'settings_saved'   => array( 'success', __( 'Ajustes guardados.', 'renova-mcp' ) ),
			'activity_cleared' => array( 'success', __( 'Registro de actividad vaciado.', 'renova-mcp' ) ),
		);

		if ( ! isset( $messages[ $key ] ) ) {
			return;
		}

		echo '<div class="renova-mcp-notice is-' . esc_attr( $messages[ $key ][0] ) . '" role="' . ( 'error' === $messages[ $key ][0] ? 'alert' : 'status' ) . '">';
		Admin_Page::icon( 'error' === $messages[ $key ][0] ? 'triangle-alert' : 'circle-check' );
		echo '<span>' . esc_html( $messages[ $key ][1] ) . '</span>';
		echo '</div>';
	}

	// --- Utilidades de presentación -------------------------------------------------

	/**
	 * Fecha legible a partir de una fecha UTC de la base de datos.
	 *
	 * @param string|null $utc Fecha en formato MySQL (UTC).
	 * @return string
	 */
	private static function date( $utc ) {
		if ( empty( $utc ) ) {
			return '';
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $utc . ' UTC' ) );
	}

	/**
	 * Tiempo transcurrido ("hace 5 minutos").
	 *
	 * @param string|null $utc Fecha en formato MySQL (UTC).
	 * @return string
	 */
	private static function ago( $utc ) {
		if ( empty( $utc ) ) {
			return __( 'Nunca', 'renova-mcp' );
		}

		/* translators: %s: tiempo transcurrido, ej. "5 minutos". */
		return sprintf( __( 'hace %s', 'renova-mcp' ), human_time_diff( strtotime( $utc . ' UTC' ), time() ) );
	}

	/**
	 * Describe el alcance de un token.
	 *
	 * @param array{groups:string[],read_only:bool} $scope Alcance.
	 * @return string
	 */
	private static function scope_label( array $scope ) {
		$groups = Catalog::groups();

		if ( empty( $scope['groups'] ) ) {
			$label = __( 'Todos los grupos', 'renova-mcp' );
		} else {
			$names = array();
			foreach ( $scope['groups'] as $slug ) {
				$names[] = isset( $groups[ $slug ] ) ? $groups[ $slug ]['label'] : $slug;
			}
			$label = implode( ', ', $names );
		}

		return $label;
	}

	/**
	 * Casillas de grupos de herramientas.
	 *
	 * @param string[] $checked Grupos marcados.
	 * @param bool     $lock    Si los grupos no disponibles se muestran bloqueados.
	 */
	private static function group_checkboxes( array $checked, $lock ) {
		$counts = Catalog::counts();

		echo '<div class="renova-mcp-checks">';
		foreach ( Catalog::groups() as $slug => $group ) {
			$available = Catalog::group_available( $slug );
			if ( ! $available && ! $lock ) {
				continue;
			}

			/* translators: 1: número de herramientas, 2: cuántas son de lectura. */
			$meta = sprintf( __( '%1$d herramientas · %2$d de lectura', 'renova-mcp' ), $counts[ $slug ]['total'], $counts[ $slug ]['readonly'] );

			echo '<label class="renova-mcp-check' . ( $available ? '' : ' is-disabled' ) . '">';
			echo '<input type="checkbox" name="groups[]" value="' . esc_attr( $slug ) . '"' . checked( $available && in_array( $slug, $checked, true ), true, false ) . disabled( $available, false, false ) . ' />';
			echo '<span class="renova-mcp-check-copy"><strong>' . esc_html( $group['label'] ) . '</strong><small>' . esc_html( $group['description'] ) . '</small></span>';
			echo '<span class="renova-mcp-check-meta">' . esc_html( $available ? $meta : __( 'Plugin no activo', 'renova-mcp' ) ) . '</span>';
			echo '</label>';
		}
		echo '</div>';
	}

	/**
	 * Cabecera de un panel.
	 *
	 * @param string $icon  Icono de Lucide.
	 * @param string $id    ID del título.
	 * @param string $title Título.
	 * @param string $lead  Texto de apoyo.
	 */
	private static function panel_heading( $icon, $id, $title, $lead ) {
		echo '<div class="renova-mcp-panel-heading">';
		echo '<span class="renova-mcp-panel-icon">';
		Admin_Page::icon( $icon );
		echo '</span>';
		echo '<div><h2 id="' . esc_attr( $id ) . '">' . esc_html( $title ) . '</h2>';
		echo '<p>' . esc_html( $lead ) . '</p></div>';
		echo '</div>';
	}

	// --- Tokens ---------------------------------------------------------------------

	/** Pantalla de tokens. */
	public static function tokens() {
		$new_token = self::take_new_token();
		if ( '' !== $new_token ) {
			echo '<section class="renova-mcp-reveal" aria-labelledby="renova-mcp-reveal-title">';
			echo '<div><h2 id="renova-mcp-reveal-title">' . esc_html__( 'Copia tu token ahora', 'renova-mcp' ) . '</h2>';
			echo '<p>' . esc_html__( 'Solo se guarda su huella: al salir de esta página no podrá volver a mostrarse.', 'renova-mcp' ) . '</p></div>';
			echo '<div class="renova-mcp-reveal-row">';
			echo '<code class="renova-mcp-reveal-token" id="renova-mcp-new-token"><bdi>' . esc_html( $new_token ) . '</bdi></code>';
			echo '<button type="button" class="renova-mcp-button renova-mcp-button-primary renova-mcp-copy" data-copy="#renova-mcp-new-token">';
			Admin_Page::icon( 'copy' );
			echo '<span class="renova-mcp-copy-label">' . esc_html__( 'Copiar token', 'renova-mcp' ) . '</span>';
			echo '</button>';
			echo '</div>';
			echo '<p class="renova-mcp-reveal-next"><a href="' . esc_url( Admin_Page::tab_url( 'connect' ) ) . '">' . esc_html__( 'Ver la configuración para tu cliente', 'renova-mcp' ) . '</a></p>';
			echo '</section>';
		}

		echo '<div class="renova-mcp-split">';

		// Crear token.
		echo '<section class="renova-mcp-panel" aria-labelledby="renova-mcp-create-title">';
		self::panel_heading( 'lock-keyhole', 'renova-mcp-create-title', __( 'Nuevo token', 'renova-mcp' ), __( 'Actuará en tu nombre, con los límites que marques aquí.', 'renova-mcp' ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="renova-mcp-form">';
		echo '<input type="hidden" name="action" value="renova_mcp_create_token" />';
		wp_nonce_field( 'renova_mcp_create_token' );
		echo '<label class="renova-mcp-field" for="renova-mcp-token-name">';
		echo '<span>' . esc_html__( 'Nombre', 'renova-mcp' ) . '</span>';
		echo '<input type="text" id="renova-mcp-token-name" name="name" maxlength="100" required placeholder="' . esc_attr__( 'Ej. Poke, Cursor del portátil…', 'renova-mcp' ) . '" />';
		echo '</label>';
		echo '<fieldset class="renova-mcp-fieldset"><legend>' . esc_html__( 'Grupos de herramientas', 'renova-mcp' ) . '</legend>';
		self::group_checkboxes( Catalog::group_slugs(), false );
		echo '</fieldset>';
		echo '<label class="renova-mcp-check is-standalone">';
		echo '<input type="checkbox" name="read_only" value="1" />';
		echo '<span class="renova-mcp-check-copy"><strong>' . esc_html__( 'Solo lectura', 'renova-mcp' ) . '</strong><small>' . esc_html__( 'Podrá consultar, pero no crear, modificar ni eliminar nada.', 'renova-mcp' ) . '</small></span>';
		echo '</label>';
		echo '<label class="renova-mcp-field" for="renova-mcp-token-expires">';
		echo '<span>' . esc_html__( 'Caducidad', 'renova-mcp' ) . '</span>';
		echo '<select id="renova-mcp-token-expires" name="expires">';
		echo '<option value="0">' . esc_html__( 'No caduca', 'renova-mcp' ) . '</option>';
		echo '<option value="30">' . esc_html__( '30 días', 'renova-mcp' ) . '</option>';
		echo '<option value="90">' . esc_html__( '90 días', 'renova-mcp' ) . '</option>';
		echo '<option value="365">' . esc_html__( '1 año', 'renova-mcp' ) . '</option>';
		echo '</select>';
		echo '</label>';
		echo '<button type="submit" class="renova-mcp-button renova-mcp-button-primary renova-mcp-button-block">' . esc_html__( 'Crear token', 'renova-mcp' ) . '</button>';
		echo '</form>';
		echo '</section>';

		// Listado.
		$rows = Tokens::listing();
		echo '<section class="renova-mcp-panel renova-mcp-panel-wide" aria-labelledby="renova-mcp-list-title">';
		self::panel_heading( 'shield-check', 'renova-mcp-list-title', __( 'Accesos activos', 'renova-mcp' ), __( 'Tokens creados aquí y clientes conectados por OAuth.', 'renova-mcp' ) );

		if ( empty( $rows ) ) {
			echo '<p class="renova-mcp-empty">' . esc_html__( 'Aún no hay ningún token. Crea el primero para conectar un cliente.', 'renova-mcp' ) . '</p>';
		} else {
			echo '<div class="renova-mcp-table-scroll" tabindex="0" role="region" aria-labelledby="renova-mcp-list-title">';
			echo '<table class="renova-mcp-table">';
			echo '<thead><tr>';
			echo '<th scope="col">' . esc_html__( 'Nombre', 'renova-mcp' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Alcance', 'renova-mcp' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Último uso', 'renova-mcp' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Caduca', 'renova-mcp' ) . '</th>';
			echo '<th scope="col"><span class="renova-mcp-sr-only">' . esc_html__( 'Acciones', 'renova-mcp' ) . '</span></th>';
			echo '</tr></thead><tbody>';

			foreach ( $rows as $row ) {
				$owner    = get_userdata( (int) $row['user_id'] );
				$is_oauth = 'oauth_refresh' === $row['kind'];

				echo '<tr>';
				echo '<td><strong>' . esc_html( $row['name'] ) . '</strong>';
				echo '<small>' . esc_html( $is_oauth ? __( 'OAuth', 'renova-mcp' ) : __( 'Token', 'renova-mcp' ) ) . ' · ' . esc_html( $owner ? $owner->user_login : __( 'usuario eliminado', 'renova-mcp' ) ) . ' · ' . esc_html( self::date( $row['created_at'] ) ) . '</small></td>';
				echo '<td>' . esc_html( self::scope_label( $row['scope'] ) );
				if ( ! empty( $row['scope']['read_only'] ) ) {
					echo ' <span class="renova-mcp-badge">' . esc_html__( 'Solo lectura', 'renova-mcp' ) . '</span>';
				}
				echo '</td>';
				echo '<td>' . esc_html( self::ago( $row['last_used_at'] ) );
				if ( '' !== $row['last_ip'] ) {
					echo '<small>' . esc_html( $row['last_ip'] ) . '</small>';
				}
				echo '</td>';
				// Una conexión OAuth se renueva sola mientras se use: no tiene caducidad fija.
				echo '<td>' . esc_html( $is_oauth || empty( $row['expires_at'] ) ? '—' : self::date( $row['expires_at'] ) ) . '</td>';
				echo '<td class="renova-mcp-table-action">';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				echo '<input type="hidden" name="action" value="renova_mcp_revoke_token" />';
				echo '<input type="hidden" name="token_id" value="' . esc_attr( $row['id'] ) . '" />';
				wp_nonce_field( 'renova_mcp_revoke_token' );
				/* translators: %s: nombre del token. */
				echo '<button type="submit" class="renova-mcp-button renova-mcp-button-danger" data-confirm="' . esc_attr( sprintf( __( '¿Revocar "%s"? El cliente dejará de poder conectarse.', 'renova-mcp' ), $row['name'] ) ) . '">' . esc_html__( 'Revocar', 'renova-mcp' ) . '<span class="renova-mcp-sr-only"> ' . esc_html( $row['name'] ) . '</span></button>';
				echo '</form>';
				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
			echo '</div>';
		}
		echo '</section>';

		echo '</div>';
	}

	// --- Herramientas y seguridad -----------------------------------------------------

	/** Pantalla de herramientas y seguridad. */
	public static function tools() {
		$settings = Settings::all();
		$exposed  = count( Catalog::exposed_tool_ids() );
		$total    = count( Catalog::all_tool_ids() );
		$enabled  = array_values( array_diff( Catalog::group_slugs(), $settings['disabled_groups'] ) );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="renova-mcp-split">';
		echo '<input type="hidden" name="action" value="renova_mcp_save_settings" />';
		wp_nonce_field( 'renova_mcp_save_settings' );

		// Grupos expuestos.
		echo '<section class="renova-mcp-panel renova-mcp-panel-wide" aria-labelledby="renova-mcp-groups-title">';
		self::panel_heading( 'shield', 'renova-mcp-groups-title', __( 'Grupos expuestos', 'renova-mcp' ), __( 'Lo que no esté marcado no aparece en el cliente ni puede ejecutarse.', 'renova-mcp' ) );
		/* translators: 1: herramientas expuestas, 2: herramientas totales. */
		echo '<p class="renova-mcp-summary">' . esc_html( sprintf( __( 'Ahora mismo el servidor expone %1$d de %2$d herramientas.', 'renova-mcp' ), $exposed, $total ) ) . '</p>';
		self::group_checkboxes( $enabled, true );
		echo '<label class="renova-mcp-check is-standalone">';
		echo '<input type="checkbox" name="read_only" value="1"' . checked( $settings['read_only'], true, false ) . ' />';
		echo '<span class="renova-mcp-check-copy"><strong>' . esc_html__( 'Modo solo lectura', 'renova-mcp' ) . '</strong><small>' . esc_html__( 'Oculta y bloquea todas las herramientas que crean, modifican o eliminan, para cualquier credencial.', 'renova-mcp' ) . '</small></span>';
		echo '</label>';
		echo '</section>';

		// Límites y OAuth.
		echo '<section class="renova-mcp-panel" aria-labelledby="renova-mcp-limits-title">';
		self::panel_heading( 'lock-keyhole', 'renova-mcp-limits-title', __( 'Límites y acceso', 'renova-mcp' ), __( 'Protecciones que se aplican a todas las conexiones.', 'renova-mcp' ) );
		echo '<div class="renova-mcp-form">';
		echo '<label class="renova-mcp-field" for="renova-mcp-rate-limit">';
		echo '<span>' . esc_html__( 'Peticiones por minuto y credencial', 'renova-mcp' ) . '</span>';
		echo '<input type="number" id="renova-mcp-rate-limit" name="rate_limit" min="0" max="6000" step="1" inputmode="numeric" value="' . esc_attr( $settings['rate_limit'] ) . '" aria-describedby="renova-mcp-rate-limit-help" />';
		echo '<small id="renova-mcp-rate-limit-help">' . esc_html__( 'Al superarlo, el servidor responde 429 hasta el minuto siguiente. Con 0 no hay límite.', 'renova-mcp' ) . '</small>';
		echo '</label>';
		echo '<label class="renova-mcp-field" for="renova-mcp-retention">';
		echo '<span>' . esc_html__( 'Días que se conserva la actividad', 'renova-mcp' ) . '</span>';
		echo '<input type="number" id="renova-mcp-retention" name="audit_retention" min="1" max="365" step="1" inputmode="numeric" value="' . esc_attr( $settings['audit_retention'] ) . '" />';
		echo '</label>';
		echo '<label class="renova-mcp-check is-standalone">';
		echo '<input type="checkbox" name="oauth_enabled" value="1"' . checked( $settings['oauth_enabled'], true, false ) . ' aria-describedby="renova-mcp-oauth-help" />';
		echo '<span class="renova-mcp-check-copy"><strong>' . esc_html__( 'Permitir conexión por OAuth', 'renova-mcp' ) . '</strong><small id="renova-mcp-oauth-help">' . esc_html__( 'Para clientes que solo piden la URL, como los conectores de Claude.ai o ChatGPT. Cualquier aplicación puede registrarse, pero solo un administrador con sesión iniciada puede autorizarla.', 'renova-mcp' ) . '</small></span>';
		echo '</label>';
		if ( $settings['oauth_enabled'] && '' === (string) get_option( 'permalink_structure' ) ) {
			echo '<p class="renova-mcp-inline-warning">';
			Admin_Page::icon( 'triangle-alert' );
			echo '<span>' . esc_html__( 'OAuth necesita enlaces permanentes distintos de "Simple" para publicar sus direcciones de descubrimiento.', 'renova-mcp' ) . '</span></p>';
		}
		echo '<button type="submit" class="renova-mcp-button renova-mcp-button-primary renova-mcp-button-block">' . esc_html__( 'Guardar ajustes', 'renova-mcp' ) . '</button>';
		echo '</div>';
		echo '</section>';

		echo '</form>';
	}

	// --- Actividad ------------------------------------------------------------------

	/** Pantalla de actividad. */
	public static function activity() {
		$rows   = Audit_Log::recent( 100 );
		$tokens = array();
		foreach ( Tokens::listing() as $token ) {
			$tokens[ (int) $token['id'] ] = $token['name'];
		}

		$auth_labels = array(
			'token'   => __( 'Token', 'renova-mcp' ),
			'oauth'   => __( 'OAuth', 'renova-mcp' ),
			'basic'   => __( 'Contraseña de aplicación', 'renova-mcp' ),
			'session' => __( 'Sesión', 'renova-mcp' ),
		);
		$status_labels = array(
			'success' => __( 'Correcto', 'renova-mcp' ),
			'error'   => __( 'Error', 'renova-mcp' ),
			'denied'  => __( 'Denegado', 'renova-mcp' ),
		);

		echo '<section class="renova-mcp-panel" aria-labelledby="renova-mcp-activity-title">';
		echo '<div class="renova-mcp-clients-heading">';
		echo '<div><h2 id="renova-mcp-activity-title">' . esc_html__( 'Últimas 100 operaciones', 'renova-mcp' ) . '</h2>';
		/* translators: %d: días de retención. */
		echo '<p>' . esc_html( sprintf( __( 'Se registran las llamadas a herramientas, los inicios de sesión MCP y los tokens rechazados. Se conservan %d días.', 'renova-mcp' ), (int) Settings::get( 'audit_retention' ) ) ) . '</p></div>';
		if ( ! empty( $rows ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="renova_mcp_clear_activity" />';
			wp_nonce_field( 'renova_mcp_clear_activity' );
			echo '<button type="submit" class="renova-mcp-button renova-mcp-button-danger" data-confirm="' . esc_attr__( '¿Vaciar todo el registro de actividad? No se puede deshacer.', 'renova-mcp' ) . '">' . esc_html__( 'Vaciar registro', 'renova-mcp' ) . '</button>';
			echo '</form>';
		}
		echo '</div>';

		if ( empty( $rows ) ) {
			echo '<p class="renova-mcp-empty">' . esc_html__( 'Todavía no hay actividad. Aparecerá aquí cuando un cliente se conecte.', 'renova-mcp' ) . '</p>';
			echo '</section>';
			return;
		}

		echo '<div class="renova-mcp-table-scroll" tabindex="0" role="region" aria-labelledby="renova-mcp-activity-title">';
		echo '<table class="renova-mcp-table">';
		echo '<thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Fecha', 'renova-mcp' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Operación', 'renova-mcp' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Resultado', 'renova-mcp' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Credencial', 'renova-mcp' ) . '</th>';
		echo '<th scope="col" class="renova-mcp-table-number">' . esc_html__( 'Duración', 'renova-mcp' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$owner     = (int) $row['user_id'] > 0 ? get_userdata( (int) $row['user_id'] ) : null;
			$operation = '' !== $row['tool'] ? preg_replace( '/^' . preg_quote( Abilities::CATEGORY, '/' ) . '-/', '', $row['tool'] ) : $row['method'];
			$status    = isset( $status_labels[ $row['status'] ] ) ? $status_labels[ $row['status'] ] : $row['status'];
			$auth      = isset( $auth_labels[ $row['auth'] ] ) ? $auth_labels[ $row['auth'] ] : $row['auth'];
			if ( (int) $row['token_id'] > 0 && isset( $tokens[ (int) $row['token_id'] ] ) ) {
				$auth = $tokens[ (int) $row['token_id'] ];
			}

			echo '<tr>';
			echo '<td class="renova-mcp-table-nowrap">' . esc_html( self::date( $row['created_at'] ) ) . '</td>';
			echo '<td><strong><code>' . esc_html( $operation ) . '</code></strong>';
			if ( '' !== $row['detail'] ) {
				echo '<small>' . esc_html( $row['detail'] ) . '</small>';
			}
			echo '</td>';
			echo '<td><span class="renova-mcp-badge is-' . esc_attr( sanitize_html_class( $row['status'] ) ) . '">' . esc_html( $status ) . '</span></td>';
			echo '<td>' . esc_html( $auth ) . '<small>' . esc_html( trim( ( $owner ? $owner->user_login : '' ) . ' · ' . $row['ip'], ' ·' ) ) . '</small></td>';
			echo '<td class="renova-mcp-table-number">' . esc_html( number_format_i18n( (int) $row['duration_ms'] ) ) . ' ms</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
		echo '</section>';
	}
}
