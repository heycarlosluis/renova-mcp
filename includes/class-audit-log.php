<?php
/**
 * Registro de actividad del servidor MCP.
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Guarda quién ejecutó qué herramienta, con qué credencial y con qué resultado.
 */
class Audit_Log {

	/**
	 * Claves cuyo valor identifica el objeto afectado y es seguro registrar.
	 * Del resto de argumentos solo se anota el tipo y el tamaño.
	 *
	 * @var string[]
	 */
	private static $identifying_keys = array(
		'id',
		'post_id',
		'user_id',
		'term_id',
		'menu_id',
		'comment_id',
		'plugin',
		'stylesheet',
		'slug',
		'name',
		'key',
		'option',
		'taxonomy',
		'post_type',
		'type',
		'action',
		'status',
		'role',
		'target',
		'group',
		'location',
		'selector',
		'field',
		'element_id',
		'template_id',
		'mode',
	);

	/**
	 * Resumen de los argumentos de la llamada en curso.
	 *
	 * @var string
	 */
	private static $pending_args = '';

	/** Engancha la captura de argumentos. */
	public static function register() {
		add_filter( 'mcp_adapter_pre_tool_call', array( __CLASS__, 'capture_args' ), 999 );
	}

	/**
	 * Guarda un resumen de los argumentos para adjuntarlo al registro.
	 *
	 * @param array<string,mixed>|\WP_Error $args Argumentos de la herramienta.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function capture_args( $args ) {
		self::$pending_args = is_array( $args ) ? self::summarize( $args ) : '';

		return $args;
	}

	/**
	 * Resume los argumentos sin volcar contenido: los identificadores se anotan
	 * tal cual y del resto solo se guarda el tipo y el tamaño.
	 *
	 * @param array<string,mixed> $args Argumentos.
	 * @return string
	 */
	public static function summarize( array $args ) {
		$parts = array();

		foreach ( $args as $key => $value ) {
			$key = (string) $key;

			if ( is_bool( $value ) ) {
				$shown = $value ? 'true' : 'false';
			} elseif ( is_int( $value ) || is_float( $value ) ) {
				$shown = (string) $value;
			} elseif ( is_string( $value ) ) {
				$shown = in_array( $key, self::$identifying_keys, true ) ? '"' . mb_substr( $value, 0, 120 ) . '"' : 'texto(' . strlen( $value ) . ')';
			} elseif ( is_array( $value ) ) {
				$shown = 'lista(' . count( $value ) . ')';
			} else {
				$shown = gettype( $value );
			}

			$parts[] = $key . '=' . $shown;
		}

		return mb_substr( implode( ', ', $parts ), 0, 600 );
	}

	/**
	 * Registra una petición MCP a partir de los datos del adaptador.
	 *
	 * @param array<string,mixed> $tags        Etiquetas del evento.
	 * @param float|null          $duration_ms Duración en milisegundos.
	 */
	public static function record_request( array $tags, $duration_ms ) {
		$method = isset( $tags['method'] ) ? (string) $tags['method'] : '';
		if ( ! in_array( $method, array( 'tools/call', 'initialize' ), true ) ) {
			return;
		}

		$detail = '';
		if ( 'tools/call' === $method ) {
			$detail             = self::$pending_args;
			self::$pending_args = '';
		}

		$status = isset( $tags['status'] ) ? (string) $tags['status'] : '';
		if ( 'success' !== $status && ! empty( $tags['failure_reason'] ) && is_string( $tags['failure_reason'] ) ) {
			$detail = trim( $detail . ' · ' . mb_substr( $tags['failure_reason'], 0, 300 ), ' ·' );
		}

		$token = Tokens::current();
		if ( $token ) {
			$auth = 'oauth_access' === $token['kind'] ? 'oauth' : 'token';
		} else {
			$auth = did_action( 'application_password_did_authenticate' ) ? 'basic' : 'session';
		}

		self::write(
			array(
				'user_id'     => get_current_user_id(),
				'token_id'    => $token ? (int) $token['id'] : 0,
				'auth'        => $auth,
				'method'      => $method,
				'tool'        => isset( $tags['tool_name'] ) ? (string) $tags['tool_name'] : '',
				'status'      => $status,
				'duration_ms' => null === $duration_ms ? 0 : (int) round( $duration_ms ),
				'detail'      => $detail,
			)
		);
	}

	/**
	 * Inserta una fila. No consulta el usuario actual: puede llamarse mientras
	 * WordPress todavía lo está determinando.
	 *
	 * @param array<string,mixed> $row Datos de la fila.
	 */
	public static function write( array $row ) {
		global $wpdb;

		$row = wp_parse_args(
			$row,
			array(
				'user_id'     => 0,
				'token_id'    => 0,
				'auth'        => '',
				'method'      => '',
				'tool'        => '',
				'status'      => '',
				'duration_ms' => 0,
				'detail'      => '',
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			Store::audit_table(),
			array(
				'created_at'  => current_time( 'mysql', true ),
				'user_id'     => (int) $row['user_id'],
				'token_id'    => (int) $row['token_id'],
				'auth'        => substr( (string) $row['auth'], 0, 16 ),
				'method'      => substr( (string) $row['method'], 0, 40 ),
				'tool'        => substr( (string) $row['tool'], 0, 120 ),
				'status'      => substr( (string) $row['status'], 0, 16 ),
				'duration_ms' => max( 0, (int) $row['duration_ms'] ),
				'ip'          => Request::client_ip(),
				'detail'      => (string) $row['detail'],
			)
		);
	}

	/**
	 * Últimas entradas del registro.
	 *
	 * @param int $limit Número máximo de filas.
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent( $limit = 100 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Store::audit_table() . ' ORDER BY id DESC LIMIT %d', max( 1, (int) $limit ) ), ARRAY_A );
	}

	/** Vacía el registro. */
	public static function clear() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( 'DELETE FROM ' . Store::audit_table() );
	}
}
