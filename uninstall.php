<?php
/**
 * Limpieza al desinstalar Renova MCP Control.
 *
 * @package Renova\MCP
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/*
 * Copias de seguridad del árbol de Elementor (`_renova_elementor_backup`),
 * guardadas como post-meta.
 */
delete_post_meta_by_key( '_renova_elementor_backup' );

// Ajustes, clientes OAuth y versión del esquema.
delete_option( 'renova_mcp_settings' );
delete_option( 'renova_mcp_oauth_clients' );
delete_option( 'renova_mcp_db_version' );

// Tablas de tokens y de actividad.
global $wpdb;
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}renova_mcp_tokens" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}renova_mcp_audit" );
// phpcs:enable

wp_clear_scheduled_hook( 'renova_mcp_prune' );
