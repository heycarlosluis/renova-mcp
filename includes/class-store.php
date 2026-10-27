<?php
/**
 * Tablas propias del plugin: tokens de acceso y registro de actividad.
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Crea y mantiene el esquema de base de datos.
 */
class Store {

	const DB_VERSION        = '1';
	const DB_VERSION_OPTION = 'renova_mcp_db_version';
	const PRUNE_HOOK        = 'renova_mcp_prune';

	/** Engancha la instalación y el mantenimiento. */
	public static function register() {
		// El despliegue por Git no pasa por el hook de activación, así que el
		// esquema se comprueba en cada carga comparando la versión guardada.
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_install' ), 5 );
		add_action( 'init', array( __CLASS__, 'schedule_prune' ) );
		add_action( self::PRUNE_HOOK, array( __CLASS__, 'prune' ) );
	}

	/**
	 * Nombre de la tabla de tokens.
	 *
	 * @return string
	 */
	public static function tokens_table() {
		global $wpdb;

		return $wpdb->prefix . 'renova_mcp_tokens';
	}

	/**
	 * Nombre de la tabla del registro de actividad.
	 *
	 * @return string
	 */
	public static function audit_table() {
		global $wpdb;

		return $wpdb->prefix . 'renova_mcp_audit';
	}

	/** Crea o actualiza las tablas si la versión del esquema cambió. */
	public static function maybe_install() {
		if ( self::DB_VERSION === get_option( self::DB_VERSION_OPTION ) ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$tokens  = self::tokens_table();
		$audit   = self::audit_table();

		dbDelta(
			"CREATE TABLE {$tokens} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			kind varchar(16) NOT NULL DEFAULT 'access',
			token_hash char(64) NOT NULL,
			name varchar(190) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			scope text NOT NULL,
			client_id varchar(64) NOT NULL DEFAULT '',
			grant_id varchar(32) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			expires_at datetime DEFAULT NULL,
			last_used_at datetime DEFAULT NULL,
			last_ip varchar(45) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY grant_id (grant_id)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$audit} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			token_id bigint(20) unsigned NOT NULL DEFAULT 0,
			auth varchar(16) NOT NULL DEFAULT '',
			method varchar(40) NOT NULL DEFAULT '',
			tool varchar(120) NOT NULL DEFAULT '',
			status varchar(16) NOT NULL DEFAULT '',
			duration_ms int(10) unsigned NOT NULL DEFAULT 0,
			ip varchar(45) NOT NULL DEFAULT '',
			detail text NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at)
			) {$charset};"
		);

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/** Programa la limpieza diaria. */
	public static function schedule_prune() {
		if ( ! wp_next_scheduled( self::PRUNE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PRUNE_HOOK );
		}
	}

	/** Elimina tokens caducados y actividad más antigua que la retención. */
	public static function prune() {
		global $wpdb;

		$now = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::tokens_table() . ' WHERE expires_at IS NOT NULL AND expires_at < %s', $now ) );

		$limit = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * (int) Settings::get( 'audit_retention' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::audit_table() . ' WHERE created_at < %s', $limit ) );

		OAuth::prune_clients();
	}

	/** Cancela la limpieza programada (al desactivar el plugin). */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::PRUNE_HOOK );
	}
}
