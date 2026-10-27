<?php
/**
 * Servidor de autorización OAuth 2.1 para clientes MCP.
 *
 * Implementa lo que pide la especificación MCP: metadatos del recurso protegido
 * (RFC 9728) y del servidor de autorización (RFC 8414), registro dinámico de
 * clientes (RFC 7591) y código de autorización con PKCE S256 para clientes
 * públicos, con rotación del token de refresco.
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Endpoints OAuth y pantalla de consentimiento.
 */
class OAuth {

	const CLIENTS_OPTION = 'renova_mcp_oauth_clients';
	const QUERY_VAR      = 'renova_mcp_oauth';
	const MAX_CLIENTS    = 100;
	const ACCESS_TTL     = HOUR_IN_SECONDS;
	const REFRESH_TTL    = 30 * DAY_IN_SECONDS;
	const CODE_TTL       = 10 * MINUTE_IN_SECONDS;

	/** Engancha los endpoints. */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'serve_well_known' ), 0 );
		add_action( 'init', array( __CLASS__, 'maybe_authorize' ), 1 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'add_www_authenticate' ), 10, 3 );
	}

	/**
	 * Indica si OAuth está activado en los ajustes.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) Settings::get( 'oauth_enabled' );
	}

	/**
	 * Identificador del servidor de autorización.
	 *
	 * @return string
	 */
	public static function issuer() {
		return untrailingslashit( home_url() );
	}

	/**
	 * URL del endpoint MCP (el recurso protegido).
	 *
	 * @return string
	 */
	public static function resource() {
		return rest_url( RENOVA_MCP_ROUTE_NAMESPACE . '/' . RENOVA_MCP_ROUTE );
	}

	/**
	 * URL de los metadatos del recurso protegido.
	 *
	 * @return string
	 */
	public static function resource_metadata_url() {
		$path = (string) wp_parse_url( self::resource(), PHP_URL_PATH );

		return self::origin() . '/.well-known/oauth-protected-resource' . untrailingslashit( $path );
	}

	/**
	 * Origen (esquema y host) del sitio.
	 *
	 * @return string
	 */
	private static function origin() {
		$parts = wp_parse_url( home_url() );
		$port  = isset( $parts['port'] ) ? ':' . $parts['port'] : '';

		return $parts['scheme'] . '://' . $parts['host'] . $port;
	}

	/**
	 * URL del endpoint de autorización (pantalla de consentimiento).
	 *
	 * @return string
	 */
	public static function authorization_endpoint() {
		return add_query_arg( self::QUERY_VAR, 'authorize', home_url( '/' ) );
	}

	/** Registra las rutas REST de token y registro de clientes. */
	public static function register_routes() {
		register_rest_route(
			RENOVA_MCP_ROUTE_NAMESPACE,
			'/oauth/register',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_register' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			RENOVA_MCP_ROUTE_NAMESPACE,
			'/oauth/token',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_token' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Añade `WWW-Authenticate` a las respuestas 401 del endpoint MCP, que es
	 * como los clientes descubren el método de autenticación.
	 *
	 * @param \WP_HTTP_Response $response Respuesta.
	 * @param \WP_REST_Server   $server   Servidor REST.
	 * @param \WP_REST_Request  $request  Petición.
	 * @return \WP_HTTP_Response
	 */
	public static function add_www_authenticate( $response, $server, $request ) {
		if ( ! $response instanceof \WP_HTTP_Response || 401 !== $response->get_status() ) {
			return $response;
		}

		if ( '/' . RENOVA_MCP_ROUTE_NAMESPACE . '/' . RENOVA_MCP_ROUTE !== $request->get_route() ) {
			return $response;
		}

		$challenge = 'Bearer realm="Renova MCP"';
		if ( Tokens::current_error() ) {
			$challenge .= ', error="invalid_token"';
		}
		if ( self::enabled() ) {
			$challenge .= ', resource_metadata="' . self::resource_metadata_url() . '"';
		}

		$response->header( 'WWW-Authenticate', $challenge );

		return $response;
	}

	/**
	 * Sirve los documentos de descubrimiento en `/.well-known/`.
	 */
	public static function serve_well_known() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) wp_parse_url( (string) $uri, PHP_URL_PATH );

		if ( 0 === strpos( $path, '/.well-known/oauth-protected-resource' ) ) {
			$document = array(
				'resource'                 => self::resource(),
				'authorization_servers'    => array( self::issuer() ),
				'bearer_methods_supported' => array( 'header' ),
				'scopes_supported'         => array( 'mcp' ),
				'resource_name'            => 'Renova MCP Control',
			);
		} elseif ( 0 === strpos( $path, '/.well-known/oauth-authorization-server' ) ) {
			$document = array(
				'issuer'                                => self::issuer(),
				'authorization_endpoint'                => self::authorization_endpoint(),
				'token_endpoint'                        => rest_url( RENOVA_MCP_ROUTE_NAMESPACE . '/oauth/token' ),
				'registration_endpoint'                 => rest_url( RENOVA_MCP_ROUTE_NAMESPACE . '/oauth/register' ),
				'response_types_supported'              => array( 'code' ),
				'response_modes_supported'              => array( 'query' ),
				'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
				'code_challenge_methods_supported'      => array( 'S256' ),
				'token_endpoint_auth_methods_supported' => array( 'none' ),
				'scopes_supported'                      => array( 'mcp' ),
				'authorization_response_iss_parameter_supported' => true,
			);
		} else {
			return;
		}

		if ( ! self::enabled() ) {
			return;
		}

		status_header( 200 );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Cache-Control: public, max-age=300' );
		echo wp_json_encode( $document, JSON_UNESCAPED_SLASHES );
		exit;
	}

	// --- Clientes ----------------------------------------------------------------

	/**
	 * Clientes registrados.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function clients() {
		$clients = get_option( self::CLIENTS_OPTION, array() );

		return is_array( $clients ) ? $clients : array();
	}

	/**
	 * Devuelve un cliente registrado.
	 *
	 * @param string $client_id ID del cliente.
	 * @return array<string,mixed>|null
	 */
	public static function client( $client_id ) {
		$clients = self::clients();

		return isset( $clients[ $client_id ] ) ? $clients[ $client_id ] : null;
	}

	/**
	 * Elimina los clientes registrados hace más de un día que no llegaron a
	 * obtener ningún token.
	 */
	public static function prune_clients() {
		global $wpdb;

		$clients = self::clients();
		if ( empty( $clients ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$in_use = (array) $wpdb->get_col( 'SELECT DISTINCT client_id FROM ' . Store::tokens_table() . " WHERE client_id <> ''" );
		$before = count( $clients );

		foreach ( $clients as $client_id => $client ) {
			$age = time() - ( isset( $client['created'] ) ? (int) $client['created'] : 0 );
			if ( $age > DAY_IN_SECONDS && ! in_array( (string) $client_id, $in_use, true ) ) {
				unset( $clients[ $client_id ] );
			}
		}

		if ( count( $clients ) !== $before ) {
			update_option( self::CLIENTS_OPTION, $clients, false );
		}
	}

	/**
	 * Valida una URI de redirección: HTTPS, HTTP solo en loopback, o un esquema
	 * propio de aplicación.
	 *
	 * @param mixed $uri URI a validar.
	 * @return bool
	 */
	public static function is_valid_redirect_uri( $uri ) {
		if ( ! is_string( $uri ) || '' === $uri || strlen( $uri ) > 2000 || preg_match( '/[\s<>"\'\\\\]/', $uri ) ) {
			return false;
		}

		$parts = wp_parse_url( $uri );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || isset( $parts['fragment'] ) ) {
			return false;
		}

		$scheme = strtolower( $parts['scheme'] );

		if ( 'https' === $scheme ) {
			return ! empty( $parts['host'] );
		}

		if ( 'http' === $scheme ) {
			return isset( $parts['host'] ) && self::is_loopback( $parts['host'] );
		}

		$forbidden = array( 'javascript', 'data', 'vbscript', 'file', 'blob', 'about', 'ftp', 'mailto', 'tel', 'ws', 'wss' );

		return (bool) preg_match( '/^[a-z][a-z0-9+.\-]*$/', $scheme ) && ! in_array( $scheme, $forbidden, true );
	}

	/**
	 * Indica si un host es de loopback.
	 *
	 * @param string $host Host.
	 * @return bool
	 */
	private static function is_loopback( $host ) {
		return in_array( strtolower( trim( $host, '[]' ) ), array( 'localhost', '127.0.0.1', '::1' ), true );
	}

	/**
	 * Comprueba que una URI de redirección es una de las registradas por el
	 * cliente. En loopback el puerto puede variar (RFC 8252).
	 *
	 * @param array<string,mixed> $client Cliente registrado.
	 * @param string              $uri    URI recibida.
	 * @return bool
	 */
	public static function redirect_uri_matches( array $client, $uri ) {
		$registered = isset( $client['redirect_uris'] ) ? (array) $client['redirect_uris'] : array();
		if ( in_array( $uri, $registered, true ) ) {
			return true;
		}

		$given = wp_parse_url( (string) $uri );
		if ( ! is_array( $given ) || ! isset( $given['scheme'], $given['host'] ) || 'http' !== strtolower( $given['scheme'] ) || ! self::is_loopback( $given['host'] ) ) {
			return false;
		}

		foreach ( $registered as $candidate ) {
			$parts = wp_parse_url( (string) $candidate );
			if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) ) {
				continue;
			}

			$same_host  = 'http' === strtolower( $parts['scheme'] ) && strtolower( $parts['host'] ) === strtolower( $given['host'] );
			$same_path  = ( isset( $parts['path'] ) ? $parts['path'] : '' ) === ( isset( $given['path'] ) ? $given['path'] : '' );
			$same_query = ( isset( $parts['query'] ) ? $parts['query'] : '' ) === ( isset( $given['query'] ) ? $given['query'] : '' );

			if ( $same_host && $same_path && $same_query ) {
				return true;
			}
		}

		return false;
	}

	// --- Registro dinámico de clientes (RFC 7591) -------------------------------------

	/**
	 * Registra un cliente público.
	 *
	 * @param \WP_REST_Request $request Petición.
	 * @return \WP_REST_Response
	 */
	public static function handle_register( \WP_REST_Request $request ) {
		if ( ! self::enabled() ) {
			return self::error( 'not_found', 'OAuth no está activado en este sitio.', 404 );
		}

		// Como mucho diez registros por IP y hora.
		$throttle = 'renova_mcp_dcr_' . md5( Request::client_ip() . '|' . floor( time() / HOUR_IN_SECONDS ) );
		$attempts = (int) get_transient( $throttle );
		if ( $attempts >= 10 ) {
			return self::error( 'too_many_requests', 'Demasiados registros de cliente. Inténtalo más tarde.', 429 );
		}
		set_transient( $throttle, $attempts + 1, HOUR_IN_SECONDS );

		$body = $request->get_json_params();
		$uris = is_array( $body ) && isset( $body['redirect_uris'] ) ? $body['redirect_uris'] : null;

		if ( ! is_array( $uris ) || empty( $uris ) || count( $uris ) > 10 ) {
			return self::error( 'invalid_redirect_uri', 'redirect_uris debe ser una lista de entre 1 y 10 URIs.' );
		}
		foreach ( $uris as $uri ) {
			if ( ! self::is_valid_redirect_uri( $uri ) ) {
				return self::error( 'invalid_redirect_uri', 'Cada redirect_uri debe usar HTTPS, HTTP en loopback o un esquema propio de aplicación.' );
			}
		}

		$auth_method = isset( $body['token_endpoint_auth_method'] ) ? (string) $body['token_endpoint_auth_method'] : 'none';
		if ( 'none' !== $auth_method ) {
			return self::error( 'invalid_client_metadata', 'Solo se admiten clientes públicos (token_endpoint_auth_method "none").' );
		}

		self::prune_clients();
		$clients = self::clients();
		if ( count( $clients ) >= self::MAX_CLIENTS ) {
			return self::error( 'temporarily_unavailable', 'Se alcanzó el máximo de clientes registrados.', 503 );
		}

		$name = isset( $body['client_name'] ) && is_string( $body['client_name'] ) ? sanitize_text_field( $body['client_name'] ) : '';
		$name = '' === $name ? __( 'Cliente MCP', 'renova-mcp' ) : mb_substr( $name, 0, 100 );

		$client_id             = 'rmcc_' . wp_generate_password( 24, false );
		$clients[ $client_id ] = array(
			'name'          => $name,
			'redirect_uris' => array_values( $uris ),
			'created'       => time(),
		);
		update_option( self::CLIENTS_OPTION, $clients, false );

		return self::json(
			array(
				'client_id'                  => $client_id,
				'client_id_issued_at'        => time(),
				'client_name'                => $name,
				'redirect_uris'              => array_values( $uris ),
				'grant_types'                => array( 'authorization_code', 'refresh_token' ),
				'response_types'             => array( 'code' ),
				'token_endpoint_auth_method' => 'none',
			),
			201
		);
	}

	// --- Endpoint de autorización --------------------------------------------------

	/**
	 * Atiende la petición de autorización: valida, pide inicio de sesión y
	 * consentimiento, y devuelve el código al cliente.
	 */
	public static function maybe_authorize() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Petición iniciada por el cliente OAuth; el consentimiento sí lleva nonce.
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) || 'authorize' !== $_GET[ self::QUERY_VAR ] ) {
			return;
		}

		nocache_headers();
		header( 'X-Frame-Options: DENY' );
		header( "Content-Security-Policy: frame-ancestors 'none'" );
		header( 'Referrer-Policy: no-referrer' );

		if ( ! self::enabled() ) {
			self::page( __( 'OAuth no está activado', 'renova-mcp' ), __( 'Activa OAuth en los ajustes de Renova MCP para autorizar clientes desde aquí.', 'renova-mcp' ), 404 );
		}

		$params = array();
		foreach ( array( 'client_id', 'redirect_uri', 'response_type', 'state', 'code_challenge', 'code_challenge_method', 'scope', 'resource' ) as $key ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Se valida contra el cliente registrado y se escapa al mostrar.
			$params[ $key ] = isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? wp_unslash( $_GET[ $key ] ) : '';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Mientras el cliente y su URI no estén verificados no se redirige a ningún sitio.
		$client = self::client( $params['client_id'] );
		if ( ! $client ) {
			self::page( __( 'Cliente desconocido', 'renova-mcp' ), __( 'Esta aplicación no está registrada en el sitio. Vuelve a iniciar la conexión desde tu cliente MCP.', 'renova-mcp' ), 400 );
		}
		if ( ! self::redirect_uri_matches( $client, $params['redirect_uri'] ) ) {
			self::page( __( 'Redirección no permitida', 'renova-mcp' ), __( 'La dirección de retorno no coincide con la que registró la aplicación.', 'renova-mcp' ), 400 );
		}

		if ( 'code' !== $params['response_type'] ) {
			self::redirect_back( $params, array( 'error' => 'unsupported_response_type' ) );
		}
		if ( 'S256' !== $params['code_challenge_method'] || ! preg_match( '/^[A-Za-z0-9\-._~]{43,128}$/', $params['code_challenge'] ) ) {
			self::redirect_back(
				$params,
				array(
					'error'             => 'invalid_request',
					'error_description' => 'PKCE con code_challenge_method S256 es obligatorio.',
				)
			);
		}

		if ( ! is_user_logged_in() ) {
			self::require_login();
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			self::page( __( 'Permisos insuficientes', 'renova-mcp' ), __( 'Solo un administrador puede autorizar el acceso al servidor MCP.', 'renova-mcp' ), 403 );
		}

		$nonce_action = 'renova_mcp_oauth_' . $params['client_id'];

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
				self::page( __( 'La sesión caducó', 'renova-mcp' ), __( 'Vuelve a iniciar la conexión desde tu cliente MCP.', 'renova-mcp' ), 400 );
			}

			if ( ! isset( $_POST['approve'] ) ) {
				self::redirect_back( $params, array( 'error' => 'access_denied' ) );
			}

			// Sin ningún grupo marcado no hay nada que autorizar.
			$groups = isset( $_POST['groups'] ) ? Catalog::resolve_selection( array_map( 'sanitize_key', (array) wp_unslash( $_POST['groups'] ) ) ) : array();
			if ( empty( $groups ) ) {
				self::redirect_back( $params, array( 'error' => 'access_denied' ) );
			}

			$scope = Tokens::normalize_scope(
				array(
					'groups'    => $groups,
					'read_only' => ! empty( $_POST['read_only'] ),
				)
			);

			$code = wp_generate_password( 48, false );
			set_transient(
				'renova_mcp_code_' . hash( 'sha256', $code ),
				array(
					'client_id'      => $params['client_id'],
					'redirect_uri'   => $params['redirect_uri'],
					'user_id'        => get_current_user_id(),
					'code_challenge' => $params['code_challenge'],
					'scope'          => $scope,
				),
				self::CODE_TTL
			);

			self::redirect_back( $params, array( 'code' => $code ) );
		}

		self::consent_page( $client, $params, $nonce_action );
	}

	/**
	 * Redirige al cliente con el resultado de la autorización.
	 *
	 * @param array<string,string> $params Parámetros de la petición.
	 * @param array<string,string> $result Parámetros de respuesta.
	 */
	private static function redirect_back( array $params, array $result ) {
		if ( '' !== $params['state'] ) {
			$result['state'] = $params['state'];
		}
		$result['iss'] = self::issuer();

		$separator = false === strpos( $params['redirect_uri'], '?' ) ? '?' : '&';

		// La URI ya se verificó contra las registradas por el cliente; puede usar
		// un esquema propio (ej. cursor://), que wp_safe_redirect() rechazaría.
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		wp_redirect( $params['redirect_uri'] . $separator . http_build_query( $result, '', '&', PHP_QUERY_RFC3986 ), 302, 'Renova MCP' );
		exit;
	}

	/**
	 * Lleva al inicio de sesión cuando la URL de acceso es la estándar. Si un
	 * plugin la oculta, no se revela: se pide iniciar sesión por cuenta propia.
	 */
	private static function require_login() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$return      = self::origin() . $request_uri;
		$login       = wp_login_url( $return );

		if ( 'wp-login.php' === basename( (string) wp_parse_url( $login, PHP_URL_PATH ) ) ) {
			wp_safe_redirect( $login );
			exit;
		}

		self::page(
			__( 'Inicia sesión para continuar', 'renova-mcp' ),
			__( 'Inicia sesión como administrador en este sitio desde otra pestaña y después recarga esta página para autorizar la conexión.', 'renova-mcp' ),
			401
		);
	}

	/**
	 * Muestra la pantalla de consentimiento.
	 *
	 * @param array<string,mixed>  $client       Cliente registrado.
	 * @param array<string,string> $params       Parámetros de la petición.
	 * @param string               $nonce_action Acción del nonce.
	 */
	private static function consent_page( array $client, array $params, $nonce_action ) {
		$user   = wp_get_current_user();
		$target = wp_parse_url( $params['redirect_uri'] );
		$host   = isset( $target['host'] ) ? $target['host'] : $target['scheme'] . '://';

		ob_start();
		echo '<p class="lead">';
		/* translators: 1: nombre de la aplicación, 2: nombre del sitio. */
		echo wp_kses( sprintf( __( '<strong>%1$s</strong> quiere conectarse al servidor MCP de <strong>%2$s</strong>.', 'renova-mcp' ), esc_html( $client['name'] ), esc_html( get_bloginfo( 'name' ) ) ), array( 'strong' => array() ) );
		echo '</p>';
		echo '<dl>';
		echo '<div><dt>' . esc_html__( 'Volverá a', 'renova-mcp' ) . '</dt><dd><code>' . esc_html( $host ) . '</code></dd></div>';
		echo '<div><dt>' . esc_html__( 'Actuará como', 'renova-mcp' ) . '</dt><dd>' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_login ) . ')</dd></div>';
		echo '</dl>';
		echo '<form method="post">';
		wp_nonce_field( $nonce_action, '_wpnonce', false );
		echo '<fieldset><legend>' . esc_html__( 'Herramientas a las que tendrá acceso', 'renova-mcp' ) . '</legend>';
		foreach ( Catalog::groups() as $slug => $group ) {
			if ( ! Catalog::group_available( $slug ) ) {
				continue;
			}
			echo '<label class="check"><input type="checkbox" name="groups[]" value="' . esc_attr( $slug ) . '" checked /><span><strong>' . esc_html( $group['label'] ) . '</strong><small>' . esc_html( $group['description'] ) . '</small></span></label>';
		}
		echo '</fieldset>';
		echo '<label class="check standalone"><input type="checkbox" name="read_only" value="1" /><span><strong>' . esc_html__( 'Solo lectura', 'renova-mcp' ) . '</strong><small>' . esc_html__( 'Podrá consultar, pero no crear, modificar ni eliminar nada.', 'renova-mcp' ) . '</small></span></label>';
		echo '<div class="actions">';
		echo '<button type="submit" name="deny" value="1" class="secondary">' . esc_html__( 'Cancelar', 'renova-mcp' ) . '</button>';
		echo '<button type="submit" name="approve" value="1" class="primary">' . esc_html__( 'Autorizar', 'renova-mcp' ) . '</button>';
		echo '</div>';
		echo '</form>';

		self::page( __( 'Autorizar conexión', 'renova-mcp' ), '', 200, (string) ob_get_clean() );
	}

	/**
	 * Muestra una página mínima (consentimiento o error) y termina.
	 *
	 * @param string $title   Título.
	 * @param string $message Mensaje en texto plano.
	 * @param int    $status  Código HTTP.
	 * @param string $html    HTML ya escapado que sustituye al mensaje.
	 */
	private static function page( $title, $message, $status = 200, $html = '' ) {
		status_header( $status );
		header( 'Content-Type: text/html; charset=utf-8' );

		echo '<!DOCTYPE html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8" />';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1" /><meta name="robots" content="noindex, nofollow" />';
		echo '<title>' . esc_html( $title ) . ' · Renova MCP</title>';
		echo '<style>
			*,*::before,*::after{box-sizing:border-box}
			body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f4f6f8;color:#151a23;font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;-webkit-font-smoothing:antialiased}
			main{width:100%;max-width:460px;padding:32px;border-radius:16px;background:#fff;box-shadow:0 0 0 1px rgb(20 31 51/.05),0 1px 2px rgb(20 31 51/.04),0 10px 30px rgb(20 31 51/.035)}
			.kicker{margin:0 0 6px;color:#3157d5;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
			h1{margin:0 0 14px;font-size:22px;line-height:1.25;letter-spacing:-.01em;text-wrap:balance}
			p{margin:0 0 16px;color:#4b5565;text-wrap:pretty}
			p.lead{color:#151a23}
			dl{margin:0 0 22px;padding:14px 16px;border-radius:12px;background:#fafbfc;box-shadow:inset 0 0 0 1px #e9edf2}
			dl div{display:flex;justify-content:space-between;gap:16px}
			dl div+div{margin-top:8px}
			dt{color:#4b5565;font-size:13px}
			dd{margin:0;font-size:13px;font-weight:600;text-align:right;overflow-wrap:anywhere}
			code{font:12px/1.5 ui-monospace,SFMono-Regular,Menlo,monospace}
			fieldset{margin:0 0 14px;padding:0;border:0}
			legend{margin-bottom:10px;padding:0;font-size:13px;font-weight:650}
			.check{display:flex;gap:12px;align-items:flex-start;padding:9px 0;cursor:pointer}
			.check+.check{border-top:1px solid #e9edf2}
			.check.standalone{margin-bottom:24px;padding:14px 16px;border-radius:12px;background:#fafbfc;box-shadow:inset 0 0 0 1px #e9edf2}
			.check input{flex:none;width:18px;height:18px;margin:2px 0 0;accent-color:#3157d5}
			.check strong{display:block;font-size:14px;font-weight:600}
			.check small{display:block;color:#4b5565;font-size:13px;line-height:1.45}
			.actions{display:flex;justify-content:flex-end;gap:10px}
			button{min-height:44px;padding:0 20px;border:0;border-radius:10px;font:inherit;font-size:14px;font-weight:650;cursor:pointer}
			button.primary{background:#3157d5;color:#fff}
			button.primary:hover{background:#2747b5}
			button.secondary{background:#fff;color:#151a23;box-shadow:inset 0 0 0 1px #d8dee7}
			button.secondary:hover{background:#f4f6f8}
			button:focus-visible,.check input:focus-visible{outline:2px solid #3157d5;outline-offset:2px}
			@media (max-width:480px){main{padding:24px}.actions{flex-direction:column-reverse}button{width:100%}}
		</style></head><body><main>';
		echo '<p class="kicker">Renova MCP</p><h1>' . esc_html( $title ) . '</h1>';
		if ( '' !== $html ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML construido y escapado en consent_page().
			echo $html;
		} else {
			echo '<p>' . esc_html( $message ) . '</p>';
		}
		echo '</main></body></html>';
		exit;
	}

	// --- Endpoint de token ---------------------------------------------------------

	/**
	 * Canjea un código de autorización o un token de refresco.
	 *
	 * @param \WP_REST_Request $request Petición.
	 * @return \WP_REST_Response
	 */
	public static function handle_token( \WP_REST_Request $request ) {
		if ( ! self::enabled() ) {
			return self::error( 'not_found', 'OAuth no está activado en este sitio.', 404 );
		}

		$params = $request->get_body_params();
		if ( empty( $params ) ) {
			$params = (array) $request->get_json_params();
		}

		$grant_type = isset( $params['grant_type'] ) ? (string) $params['grant_type'] : '';
		$client_id  = isset( $params['client_id'] ) ? (string) $params['client_id'] : '';
		$client     = self::client( $client_id );

		if ( ! $client ) {
			return self::error( 'invalid_client', 'Cliente desconocido.', 401 );
		}

		if ( 'authorization_code' === $grant_type ) {
			return self::grant_authorization_code( $params, $client_id, $client );
		}

		if ( 'refresh_token' === $grant_type ) {
			return self::grant_refresh_token( $params, $client_id, $client );
		}

		return self::error( 'unsupported_grant_type', 'Solo se admiten authorization_code y refresh_token.' );
	}

	/**
	 * Canjea un código de autorización (de un solo uso) verificando PKCE.
	 *
	 * @param array<string,mixed> $params    Parámetros de la petición.
	 * @param string              $client_id ID del cliente.
	 * @param array<string,mixed> $client    Cliente registrado.
	 * @return \WP_REST_Response
	 */
	private static function grant_authorization_code( array $params, $client_id, array $client ) {
		$code     = isset( $params['code'] ) ? (string) $params['code'] : '';
		$verifier = isset( $params['code_verifier'] ) ? (string) $params['code_verifier'] : '';
		$key      = 'renova_mcp_code_' . hash( 'sha256', $code );
		$data     = '' === $code ? false : get_transient( $key );

		// El código se invalida al primer intento, sea válido o no.
		delete_transient( $key );

		if ( ! is_array( $data ) || $data['client_id'] !== $client_id ) {
			return self::error( 'invalid_grant', 'Código de autorización inválido o caducado.' );
		}

		$redirect_uri = isset( $params['redirect_uri'] ) ? (string) $params['redirect_uri'] : '';
		if ( $redirect_uri !== $data['redirect_uri'] ) {
			return self::error( 'invalid_grant', 'redirect_uri no coincide con la de la autorización.' );
		}

		$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		if ( '' === $verifier || ! hash_equals( (string) $data['code_challenge'], $challenge ) ) {
			return self::error( 'invalid_grant', 'code_verifier no supera la verificación PKCE.' );
		}

		return self::issue( (int) $data['user_id'], $client_id, $client['name'], (array) $data['scope'], wp_generate_password( 24, false ) );
	}

	/**
	 * Renueva los tokens rotando el de refresco.
	 *
	 * @param array<string,mixed> $params    Parámetros de la petición.
	 * @param string              $client_id ID del cliente.
	 * @param array<string,mixed> $client    Cliente registrado.
	 * @return \WP_REST_Response
	 */
	private static function grant_refresh_token( array $params, $client_id, array $client ) {
		$refresh = isset( $params['refresh_token'] ) ? (string) $params['refresh_token'] : '';
		$row     = Tokens::find( $refresh, array( 'oauth_refresh' ) );

		if ( ! $row || $row['client_id'] !== $client_id || ! get_userdata( (int) $row['user_id'] ) ) {
			return self::error( 'invalid_grant', 'Token de refresco inválido, caducado o revocado.' );
		}

		Tokens::revoke_grant( $row['grant_id'] );

		return self::issue( (int) $row['user_id'], $client_id, $client['name'], $row['scope'], $row['grant_id'] );
	}

	/**
	 * Emite el par de tokens de una concesión.
	 *
	 * @param int                 $user_id   Usuario que autorizó.
	 * @param string              $client_id ID del cliente.
	 * @param string              $name      Nombre del cliente.
	 * @param array<string,mixed> $scope     Alcance concedido.
	 * @param string              $grant_id  ID de la concesión.
	 * @return \WP_REST_Response
	 */
	private static function issue( $user_id, $client_id, $name, array $scope, $grant_id ) {
		$common = array(
			'name'      => $name,
			'user_id'   => $user_id,
			'scope'     => $scope,
			'client_id' => $client_id,
			'grant_id'  => $grant_id,
		);

		$access  = Tokens::create(
			array_merge(
				$common,
				array(
					'kind' => 'oauth_access',
					'ttl'  => self::ACCESS_TTL,
				)
			)
		);
		$refresh = Tokens::create(
			array_merge(
				$common,
				array(
					'kind' => 'oauth_refresh',
					'ttl'  => self::REFRESH_TTL,
				)
			)
		);

		if ( is_wp_error( $access ) || is_wp_error( $refresh ) ) {
			Tokens::revoke_grant( $grant_id );
			return self::error( 'server_error', 'No se pudieron emitir los tokens.', 500 );
		}

		return self::json(
			array(
				'access_token'  => $access['token'],
				'token_type'    => 'Bearer',
				'expires_in'    => self::ACCESS_TTL,
				'refresh_token' => $refresh['token'],
				'scope'         => 'mcp',
			)
		);
	}

	// --- Respuestas ---------------------------------------------------------------

	/**
	 * Respuesta JSON sin caché.
	 *
	 * @param array<string,mixed> $data   Cuerpo.
	 * @param int                 $status Código HTTP.
	 * @return \WP_REST_Response
	 */
	private static function json( array $data, $status = 200 ) {
		$response = new \WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store' );
		$response->header( 'Pragma', 'no-cache' );

		return $response;
	}

	/**
	 * Respuesta de error con el formato de OAuth (RFC 6749 §5.2).
	 *
	 * @param string $code        Código de error.
	 * @param string $description Descripción.
	 * @param int    $status      Código HTTP.
	 * @return \WP_REST_Response
	 */
	private static function error( $code, $description, $status = 400 ) {
		return self::json(
			array(
				'error'             => $code,
				'error_description' => $description,
			),
			$status
		);
	}
}
