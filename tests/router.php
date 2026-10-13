<?php
/**
 * Enrutador para el servidor integrado de PHP (`php -S`) en las pruebas:
 * sirve los archivos que existen y envía el resto a WordPress, como haría
 * una regla de reescritura con enlaces permanentes.
 *
 * @package Renova\MCP
 */

// phpcs:disable -- Script de pruebas, fuera de WordPress.

// Solo tiene sentido bajo `php -S`: en cualquier otro servidor no hace nada.
if ( 'cli-server' !== PHP_SAPI ) {
	exit;
}

$root = $_SERVER['DOCUMENT_ROOT'];
$path = '/' . ltrim( (string) parse_url( urldecode( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ), '/' );

if ( is_dir( $root . $path ) ) {
	$path = rtrim( $path, '/' ) . '/index.php';
}

if ( is_file( $root . $path ) ) {
	if ( '.php' !== substr( $path, -4 ) ) {
		return false;
	}

	$_SERVER['SCRIPT_NAME']     = $path;
	$_SERVER['PHP_SELF']        = $path;
	$_SERVER['SCRIPT_FILENAME'] = $root . $path;
	chdir( dirname( $root . $path ) );
	require $root . $path;
	return true;
}

$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
chdir( $root );
require $root . '/index.php';
