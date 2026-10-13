<?php
/**
 * Utilidades sobre la petición HTTP en curso.
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Lectura de ruta, cabecera de autorización e IP del cliente.
 */
class Request {

	/**
	 * Indica si la petición va dirigida al espacio de rutas REST del plugin.
	 *
	 * Se resuelve leyendo la URL porque `determine_current_user` se ejecuta
	 * antes de que el servidor REST conozca la ruta.
	 *
	 * @return bool
	 */
	public static function is_mcp_route() {
		$namespace = '/' . RENOVA_MCP_ROUTE_NAMESPACE . '/';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rest_route'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$route = sanitize_text_field( wp_unslash( $_GET['rest_route'] ) );
			if ( 0 === strpos( $route, $namespace ) ) {
				return true;
			}
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) wp_parse_url( (string) $uri, PHP_URL_PATH );

		return false !== strpos( $path, '/' . rest_get_url_prefix() . $namespace );
	}

	/**
	 * Token enviado como `Authorization: Bearer …`, o cadena vacía.
	 *
	 * @return string
	 */
	public static function bearer_token() {
		$header = '';

		foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$header = trim( wp_unslash( $_SERVER[ $key ] ) );
				break;
			}
		}

		// Algunos servidores (CGI/FastCGI) no pasan la cabecera a $_SERVER.
		if ( '' === $header && function_exists( 'getallheaders' ) ) {
			foreach ( (array) getallheaders() as $name => $value ) {
				if ( 0 === strcasecmp( $name, 'Authorization' ) ) {
					$header = trim( (string) $value );
					break;
				}
			}
		}

		if ( 0 !== stripos( $header, 'Bearer ' ) ) {
			return '';
		}

		return trim( substr( $header, 7 ) );
	}

	/**
	 * Indica si es seguro aceptar un token por esta conexión. Sigue el mismo
	 * criterio que las contraseñas de aplicación: HTTPS o entorno local.
	 *
	 * @return bool
	 */
	public static function is_secure() {
		$secure = is_ssl() || 'local' === wp_get_environment_type();

		/**
		 * Filtra si la conexión se considera segura para aceptar tokens
		 * (útil detrás de un proxy que termina TLS sin informar a WordPress).
		 *
		 * @param bool $secure Si la conexión es segura.
		 */
		return (bool) apply_filters( 'renova_mcp_is_secure_request', $secure );
	}

	/**
	 * IP del cliente.
	 *
	 * @return string
	 */
	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Filtra la IP del cliente. Por defecto se usa REMOTE_ADDR; si el sitio
		 * está detrás de un proxy de confianza, aquí puede leerse su cabecera.
		 *
		 * @param string $ip IP detectada.
		 */
		$ip = (string) apply_filters( 'renova_mcp_client_ip', $ip );

		return substr( $ip, 0, 45 );
	}
}
