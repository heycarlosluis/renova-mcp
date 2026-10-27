<?php
/**
 * Límite de peticiones por minuto al endpoint MCP.
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Contador por ventana fija de un minuto, por token, usuario o IP.
 */
class Rate_Limit {

	/** Máximo por minuto para peticiones sin autenticar (por IP). */
	const ANONYMOUS_LIMIT = 30;

	/** Engancha la comprobación. */
	public static function register() {
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'check' ), 10, 3 );
	}

	/**
	 * Corta la petición con un 429 si se superó el límite.
	 *
	 * @param mixed            $result  Respuesta previa.
	 * @param \WP_REST_Server  $server  Servidor REST.
	 * @param \WP_REST_Request $request Petición.
	 * @return mixed
	 */
	public static function check( $result, $server, $request ) {
		if ( null !== $result || 0 !== strpos( $request->get_route(), '/' . RENOVA_MCP_ROUTE_NAMESPACE . '/' ) ) {
			return $result;
		}

		$limit = (int) Settings::get( 'rate_limit' );
		if ( $limit <= 0 ) {
			return $result;
		}

		$token = Tokens::current();
		if ( $token ) {
			// Una conexión OAuth renueva su token cada hora: se cuenta por concesión.
			$subject = '' !== $token['grant_id'] ? 'grant:' . $token['grant_id'] : 'token:' . $token['id'];
		} elseif ( is_user_logged_in() ) {
			$subject = 'user:' . get_current_user_id();
		} else {
			$subject = 'ip:' . Request::client_ip();
			$limit   = min( $limit, self::ANONYMOUS_LIMIT );
		}

		$window = (int) floor( time() / MINUTE_IN_SECONDS );
		$key    = 'renova_mcp_rl_' . md5( $subject . '|' . $window );
		$count  = (int) get_transient( $key );

		if ( $count >= $limit ) {
			$server->send_header( 'Retry-After', (string) ( MINUTE_IN_SECONDS - time() % MINUTE_IN_SECONDS ) );

			return new \WP_Error(
				'renova_mcp_rate_limited',
				__( 'Demasiadas peticiones. Espera un momento antes de reintentar.', 'renova-mcp' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $key, $count + 1, 2 * MINUTE_IN_SECONDS );

		return $result;
	}
}
