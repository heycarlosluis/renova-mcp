<?php
/**
 * Limpieza al desinstalar Renova MCP Control.
 *
 * @package Renova\MCP
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/*
 * El plugin no registra opciones propias, pero sí almacena copias de seguridad
 * del árbol de Elementor (`_renova_elementor_backup`) como post-meta. Al
 * desinstalar, las eliminamos para no dejar residuos.
 */
delete_post_meta_by_key( '_renova_elementor_backup' );
