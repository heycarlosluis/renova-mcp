<?php
/**
 * Ajustes del servidor MCP (grupos expuestos, solo lectura, límites y OAuth).
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Lectura y escritura de los ajustes del plugin.
 */
class Settings {

	const OPTION = 'renova_mcp_settings';

	/**
	 * Valores por defecto.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			// Grupos de herramientas desactivados a mano (por defecto, ninguno).
			'disabled_groups' => array(),
			// Si está activo, solo se exponen y ejecutan herramientas de lectura.
			'read_only'       => false,
			// Peticiones por minuto y credencial. 0 desactiva el límite.
			'rate_limit'      => 120,
			// Días que se conserva el registro de actividad.
			'audit_retention' => 30,
			// Servidor de autorización OAuth 2.1 (registro dinámico + PKCE).
			'oauth_enabled'   => false,
		);
	}

	/**
	 * Devuelve todos los ajustes ya normalizados.
	 *
	 * @return array<string,mixed>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );

		return self::sanitize( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Devuelve un ajuste concreto.
	 *
	 * @param string $key Clave del ajuste.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();

		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Guarda los ajustes (mezclados con los actuales).
	 *
	 * @param array<string,mixed> $values Valores a guardar.
	 */
	public static function update( array $values ) {
		update_option( self::OPTION, self::sanitize( array_merge( self::all(), $values ) ), false );
	}

	/**
	 * Normaliza un conjunto de ajustes.
	 *
	 * @param array<string,mixed> $values Valores sin validar.
	 * @return array<string,mixed>
	 */
	private static function sanitize( array $values ) {
		$values = array_merge( self::defaults(), array_intersect_key( $values, self::defaults() ) );
		$groups = Catalog::group_slugs();

		return array(
			'disabled_groups' => array_values( array_intersect( $groups, array_map( 'sanitize_key', (array) $values['disabled_groups'] ) ) ),
			'read_only'       => (bool) $values['read_only'],
			'rate_limit'      => max( 0, min( 6000, (int) $values['rate_limit'] ) ),
			'audit_retention' => max( 1, min( 365, (int) $values['audit_retention'] ) ),
			'oauth_enabled'   => (bool) $values['oauth_enabled'],
		);
	}
}
