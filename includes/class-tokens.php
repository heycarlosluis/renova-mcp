<?php
/**
 * Tokens de acceso (`Authorization: Bearer …`) para el endpoint MCP.
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Emite, valida y revoca tokens. Solo se guarda su hash SHA-256.
 */
class Tokens {

	const PREFIX         = 'rmcp_';
	const REFRESH_PREFIX = 'rmcr_';

	/**
	 * Token con el que se autenticó la petición en curso.
	 *
	 * @var array<string,mixed>|null
	 */
	private static $current = null;

	/**
	 * Error de autenticación de la petición en curso.
	 *
	 * @var \WP_Error|null
	 */
	private static $error = null;

	/**
	 * Si la cabecera de la petición ya se evaluó.
	 *
	 * @var bool
	 */
	private static $resolved = false;

	/** Engancha la autenticación. */
	public static function register() {
		// Después de las cookies (10) y las contraseñas de aplicación (20).
		add_filter( 'determine_current_user', array( __CLASS__, 'authenticate' ), 30 );
		add_filter( 'rest_authentication_errors', array( __CLASS__, 'authentication_errors' ), 20 );
		add_action( 'deleted_user', array( __CLASS__, 'revoke_user' ) );
	}

	/**
	 * Identifica al usuario a partir del token Bearer en peticiones al endpoint MCP.
	 *
	 * @param int|false $user_id Usuario ya identificado, si lo hay.
	 * @return int|false
	 */
	public static function authenticate( $user_id ) {
		if ( ! empty( $user_id ) || ! Request::is_mcp_route() ) {
			return $user_id;
		}

		if ( self::$resolved ) {
			return self::$current ? (int) self::$current['user_id'] : $user_id;
		}
		self::$resolved = true;

		// Un Bearer con otro formato pertenece a otro sistema de autenticación.
		$token = Request::bearer_token();
		if ( 0 !== strpos( $token, self::PREFIX ) ) {
			return $user_id;
		}

		if ( ! Request::is_secure() ) {
			self::$error = new \WP_Error( 'renova_mcp_insecure_connection', __( 'Los tokens de acceso solo se aceptan por HTTPS.', 'renova-mcp' ), array( 'status' => 401 ) );
			return $user_id;
		}

		$row = self::find( $token, array( 'access', 'oauth_access' ) );
		if ( ! $row || ! get_userdata( (int) $row['user_id'] ) ) {
			self::$error = new \WP_Error( 'renova_mcp_invalid_token', __( 'Token de acceso inválido, caducado o revocado.', 'renova-mcp' ), array( 'status' => 401 ) );
			self::note_failure();
			return $user_id;
		}

		self::$current = $row;
		self::touch( $row );

		return (int) $row['user_id'];
	}

	/**
	 * Anota un intento con token inválido. Se limita a cinco entradas por IP y
	 * minuto para que una ráfaga de intentos no llene el registro.
	 */
	private static function note_failure() {
		$key   = 'renova_mcp_af_' . md5( Request::client_ip() . '|' . floor( time() / MINUTE_IN_SECONDS ) );
		$count = (int) get_transient( $key );
		if ( $count >= 5 ) {
			return;
		}

		set_transient( $key, $count + 1, 2 * MINUTE_IN_SECONDS );
		Audit_Log::write(
			array(
				'auth'   => 'token',
				'method' => 'auth',
				'status' => 'denied',
				'detail' => __( 'Token inválido, caducado o revocado.', 'renova-mcp' ),
			)
		);
	}

	/**
	 * Devuelve el error de token, si lo hubo, como error de autenticación REST.
	 *
	 * @param \WP_Error|true|null $result Resultado previo.
	 * @return \WP_Error|true|null
	 */
	public static function authentication_errors( $result ) {
		if ( ! empty( $result ) || null === self::$error || is_user_logged_in() ) {
			return $result;
		}

		return self::$error;
	}

	/**
	 * Token de la petición en curso, o null si se autenticó de otro modo.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function current() {
		return self::$current;
	}

	/**
	 * Error de token de la petición en curso.
	 *
	 * @return \WP_Error|null
	 */
	public static function current_error() {
		return self::$error;
	}

	/**
	 * Crea un token y devuelve su valor en claro (única vez que está disponible).
	 *
	 * @param array<string,mixed> $args name, user_id, scope, kind, ttl, client_id, grant_id.
	 * @return array{id:int,token:string}|\WP_Error
	 */
	public static function create( array $args ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'name'      => '',
				'user_id'   => 0,
				'scope'     => array(),
				'kind'      => 'access',
				'ttl'       => 0,
				'client_id' => '',
				'grant_id'  => '',
			)
		);

		$prefix = 'oauth_refresh' === $args['kind'] ? self::REFRESH_PREFIX : self::PREFIX;
		$token  = $prefix . wp_generate_password( 40, false );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert(
			Store::tokens_table(),
			array(
				'kind'       => $args['kind'],
				'token_hash' => hash( 'sha256', $token ),
				'name'       => mb_substr( sanitize_text_field( $args['name'] ), 0, 190 ),
				'user_id'    => (int) $args['user_id'],
				'scope'      => wp_json_encode( self::normalize_scope( (array) $args['scope'] ) ),
				'client_id'  => (string) $args['client_id'],
				'grant_id'   => (string) $args['grant_id'],
				'created_at' => current_time( 'mysql', true ),
				'expires_at' => $args['ttl'] > 0 ? gmdate( 'Y-m-d H:i:s', time() + (int) $args['ttl'] ) : null,
			)
		);

		if ( ! $inserted ) {
			return new \WP_Error( 'renova_mcp_token_not_saved', __( 'No se pudo guardar el token.', 'renova-mcp' ) );
		}

		return array(
			'id'    => (int) $wpdb->insert_id,
			'token' => $token,
		);
	}

	/**
	 * Busca un token vigente por su valor en claro.
	 *
	 * @param string   $token Token recibido.
	 * @param string[] $kinds Tipos aceptados.
	 * @return array<string,mixed>|null
	 */
	public static function find( $token, array $kinds ) {
		global $wpdb;

		$token = (string) $token;
		if ( 0 !== strpos( $token, self::PREFIX ) && 0 !== strpos( $token, self::REFRESH_PREFIX ) ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Store::tokens_table() . ' WHERE token_hash = %s', hash( 'sha256', $token ) ), ARRAY_A );
		if ( ! $row || ! in_array( $row['kind'], $kinds, true ) ) {
			return null;
		}

		if ( ! empty( $row['expires_at'] ) && strtotime( $row['expires_at'] . ' UTC' ) < time() ) {
			return null;
		}

		$scope        = json_decode( (string) $row['scope'], true );
		$row['scope'] = self::normalize_scope( is_array( $scope ) ? $scope : array() );

		return $row;
	}

	/**
	 * Normaliza el alcance de un token.
	 *
	 * @param array<string,mixed> $scope Alcance sin validar.
	 * @return array{groups:string[],read_only:bool}
	 */
	public static function normalize_scope( array $scope ) {
		$groups = isset( $scope['groups'] ) ? array_map( 'sanitize_key', (array) $scope['groups'] ) : array();
		$groups = array_values( array_intersect( Catalog::group_slugs(), $groups ) );

		// Todos los grupos marcados equivale a "sin restricción de grupo".
		if ( count( $groups ) === count( Catalog::group_slugs() ) ) {
			$groups = array();
		}

		return array(
			'groups'    => $groups,
			'read_only' => ! empty( $scope['read_only'] ),
		);
	}

	/**
	 * Anota el último uso (como mucho una vez cada cinco minutos por token).
	 *
	 * @param array<string,mixed> $row Fila del token.
	 */
	private static function touch( array $row ) {
		global $wpdb;

		if ( ! empty( $row['last_used_at'] ) && strtotime( $row['last_used_at'] . ' UTC' ) > time() - 5 * MINUTE_IN_SECONDS ) {
			return;
		}

		$now = current_time( 'mysql', true );
		$ip  = Request::client_ip();

		// Los tokens OAuth comparten concesión: el uso se refleja también en su token de refresco.
		if ( '' !== $row['grant_id'] ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . Store::tokens_table() . ' SET last_used_at = %s, last_ip = %s WHERE grant_id = %s', $now, $ip, $row['grant_id'] ) );
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Store::tokens_table() . ' SET last_used_at = %s, last_ip = %s WHERE id = %d', $now, $ip, (int) $row['id'] ) );
	}

	/**
	 * Tokens que se muestran en la administración: los creados a mano y una
	 * fila por cada conexión OAuth (su token de refresco).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function listing() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( 'SELECT * FROM ' . Store::tokens_table() . " WHERE kind IN ('access', 'oauth_refresh') ORDER BY id DESC", ARRAY_A );

		foreach ( (array) $rows as $index => $row ) {
			$scope                   = json_decode( (string) $row['scope'], true );
			$rows[ $index ]['scope'] = self::normalize_scope( is_array( $scope ) ? $scope : array() );
		}

		return (array) $rows;
	}

	/**
	 * Revoca un token. Si pertenece a una conexión OAuth, revoca toda la concesión.
	 *
	 * @param int $id ID del token.
	 * @return bool
	 */
	public static function revoke( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, grant_id FROM ' . Store::tokens_table() . ' WHERE id = %d', (int) $id ), ARRAY_A );
		if ( ! $row ) {
			return false;
		}

		if ( '' !== $row['grant_id'] ) {
			self::revoke_grant( $row['grant_id'] );
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->delete( Store::tokens_table(), array( 'id' => (int) $row['id'] ) );
	}

	/**
	 * Revoca todos los tokens de una concesión OAuth.
	 *
	 * @param string $grant_id ID de la concesión.
	 */
	public static function revoke_grant( $grant_id ) {
		global $wpdb;

		if ( '' === (string) $grant_id ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( Store::tokens_table(), array( 'grant_id' => (string) $grant_id ) );
	}

	/**
	 * Revoca los tokens de un usuario eliminado.
	 *
	 * @param int $user_id ID del usuario.
	 */
	public static function revoke_user( $user_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( Store::tokens_table(), array( 'user_id' => (int) $user_id ) );
	}
}
