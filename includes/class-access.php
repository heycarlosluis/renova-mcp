<?php
/**
 * Control de acceso por herramienta: grupos, modo solo lectura y alcance del token.
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Aplica las restricciones antes del permiso propio de cada ability. Solo
 * restringe: el requisito de capacidad de WordPress de cada herramienta se
 * mantiene intacto.
 */
class Access {

	/** Engancha los filtros. */
	public static function register() {
		add_filter( 'wp_register_ability_args', array( __CLASS__, 'wrap_permission' ), 10, 2 );
		add_filter( 'mcp_adapter_tools_list', array( __CLASS__, 'filter_tools_list' ), 10, 2 );
	}

	/**
	 * Antepone la comprobación de acceso al `permission_callback` de cada ability del plugin.
	 *
	 * @param array<string,mixed> $args Argumentos de registro.
	 * @param string              $name Nombre de la ability.
	 * @return array<string,mixed>
	 */
	public static function wrap_permission( $args, $name ) {
		if ( 0 !== strpos( (string) $name, Abilities::CATEGORY . '/' ) || empty( $args['permission_callback'] ) ) {
			return $args;
		}

		$original = $args['permission_callback'];

		$args['permission_callback'] = static function ( $input = null ) use ( $original, $name ) {
			$allowed = Access::check( $name );
			if ( true !== $allowed ) {
				return $allowed;
			}

			return call_user_func( $original, $input );
		};

		return $args;
	}

	/**
	 * Comprueba si la petición en curso puede usar una ability.
	 *
	 * @param string $ability_id ID de la ability.
	 * @return true|\WP_Error
	 */
	public static function check( $ability_id ) {
		$group  = Catalog::group_of( $ability_id );
		$groups = Catalog::groups();
		$label  = isset( $groups[ $group ] ) ? $groups[ $group ]['label'] : $group;

		if ( ! Catalog::group_enabled( $group ) ) {
			/* translators: %s: nombre del grupo de herramientas. */
			return new \WP_Error( 'renova_mcp_group_disabled', sprintf( __( 'El grupo de herramientas "%s" está desactivado o su plugin no está activo.', 'renova-mcp' ), $label ) );
		}

		$readonly = Catalog::is_readonly( $ability_id );

		if ( ! $readonly && Settings::get( 'read_only' ) ) {
			return new \WP_Error( 'renova_mcp_read_only', __( 'El servidor MCP está en modo solo lectura.', 'renova-mcp' ) );
		}

		$token = Tokens::current();
		if ( $token ) {
			return self::scope_allows( $token['scope'], $ability_id, $group, $label, $readonly );
		}

		return true;
	}

	/**
	 * Comprueba una ability contra el alcance de un token.
	 *
	 * @param array{groups:string[],read_only:bool} $scope      Alcance del token.
	 * @param string                                $ability_id ID de la ability.
	 * @param string                                $group      Grupo de la ability.
	 * @param string                                $label      Nombre del grupo.
	 * @param bool                                  $readonly   Si la ability es de lectura.
	 * @return true|\WP_Error
	 */
	private static function scope_allows( array $scope, $ability_id, $group, $label, $readonly ) {
		if ( ! empty( $scope['read_only'] ) && ! $readonly ) {
			return new \WP_Error( 'renova_mcp_token_read_only', __( 'Este token es de solo lectura.', 'renova-mcp' ) );
		}

		if ( ! empty( $scope['groups'] ) && ! in_array( $group, $scope['groups'], true ) ) {
			/* translators: %s: nombre del grupo de herramientas. */
			return new \WP_Error( 'renova_mcp_token_scope', sprintf( __( 'Este token no tiene acceso al grupo "%s".', 'renova-mcp' ), $label ) );
		}

		return true;
	}

	/**
	 * Oculta en `tools/list` las herramientas que el token no puede usar, para
	 * que el cliente reciba solo lo que le corresponde.
	 *
	 * @param array<int,object>      $tools  Herramientas del servidor.
	 * @param \WP\MCP\Core\McpServer $server Servidor MCP.
	 * @return array<int,object>
	 */
	public static function filter_tools_list( $tools, $server ) {
		if ( RENOVA_MCP_SERVER_ID !== $server->get_server_id() || ! Tokens::current() ) {
			return $tools;
		}

		$allowed = array();
		foreach ( Catalog::exposed_tool_ids() as $ability_id ) {
			if ( true === self::check( $ability_id ) ) {
				$allowed[ Catalog::tool_name( $ability_id ) ] = true;
			}
		}

		return array_values(
			array_filter(
				(array) $tools,
				static function ( $tool ) use ( $allowed ) {
					return isset( $allowed[ $tool->getName() ] );
				}
			)
		);
	}
}
