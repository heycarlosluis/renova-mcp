<?php
/**
 * Genera docs/tools.md y docs/tools.json desde las abilities registradas.
 * Uso: wp --path=/ruta/wordpress eval-file scripts/generate-tool-reference.php
 *
 * No ejecuta herramientas ni modifica los ajustes de WordPress.
 *
 * @package Renova\MCP
 */

namespace Renova\MCP\Docs;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( "Ejecuta este archivo mediante wp eval-file.\n" );
}

if ( ! class_exists( '\Renova\MCP\Catalog' ) ) {
	\WP_CLI::error( 'Activa Renova MCP antes de generar la referencia.' );
}

function cell( $value ) {
	return str_replace( array( '|', "\r", "\n" ), array( '&#124;', '', ' ' ), (string) $value );
}

function json( $value ) {
	return wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

$sources = array(
	'Abilities'             => 'class-abilities.php',
	'Acf_Abilities'         => 'class-acf-abilities.php',
	'Acf_Types_Abilities'   => 'class-acf-types-abilities.php',
	'Taxonomy_Abilities'    => 'class-taxonomy-abilities.php',
	'Elementor_Abilities'   => 'class-elementor-abilities.php',
	'Rankmath_Abilities'    => 'class-rankmath-abilities.php',
	'Users_Media_Abilities' => 'class-users-media-abilities.php',
	'Site_Abilities'        => 'class-site-abilities.php',
);
$files = array();
foreach ( $sources as $class => $file ) {
	$class = '\\Renova\\MCP\\' . $class;
	foreach ( $class::tool_ids() as $id ) {
		$files[ $id ] = 'includes/' . $file;
	}
}

$ids = \Renova\MCP\Catalog::all_tool_ids();
$registered = array();
foreach ( wp_get_abilities() as $ability ) {
	if ( 0 === strpos( $ability->get_name(), 'renova-mcp/' ) ) {
		$registered[] = $ability->get_name();
	}
}
if ( count( $ids ) !== count( array_unique( $ids ) ) || array_diff( $ids, $registered ) || array_diff( $registered, $ids ) ) {
	\WP_CLI::error( 'El catálogo y las abilities registradas no coinciden o contienen IDs duplicados.' );
}

$tools = array();
foreach ( $ids as $id ) {
	$ability = wp_get_ability( $id );
	if ( ! $ability ) {
		\WP_CLI::error( 'Ability sin registrar: ' . $id );
	}
	$meta = $ability->get_meta();
	$tools[] = array(
		'ability'      => $id,
		'name'         => \Renova\MCP\Catalog::tool_name( $id ),
		'group'        => \Renova\MCP\Catalog::group_of( $id ),
		'label'        => $ability->get_label(),
		'description'  => $ability->get_description(),
		'annotations'  => $meta['annotations'] ?? array(),
		'inputSchema'  => $ability->get_input_schema(),
		'outputSchema' => $ability->get_output_schema(),
		'source'       => $files[ $id ],
	);
}

$groups = \Renova\MCP\Catalog::groups();
$md = "# Referencia de herramientas\n\n";
$md .= 'Catálogo completo de Renova MCP Control ' . RENOVA_MCP_VERSION . ': **' . count( $tools ) . " herramientas**.\n\n";
$md .= "Generado desde las abilities registradas por [scripts/generate-tool-reference.php](../scripts/generate-tool-reference.php). No editar a mano. Los parámetros, valores predeterminados y anotaciones reflejan el registro; consulta las [notas de comportamiento](behavior.md) para las condiciones adicionales de ejecución.\n\n";
$md .= "El nombre usado en `tools/call` lleva el prefijo `renova-mcp-`; el ID de la Abilities API usa `renova-mcp/`. Este catálogo incluye integraciones inactivas: `tools/list` devuelve solo las herramientas disponibles para la configuración y credencial actuales.\n\n";
$md .= "[Inicio](../README.md) · [Conexión](connection.md) · [Seguridad](security.md) · [Esquemas completos en JSON](tools.json)\n\n";
$md .= "| Grupo | Total | Lectura | Escritura |\n| --- | ---: | ---: | ---: |\n";
foreach ( $groups as $group => $data ) {
	$items = array_values( array_filter( $tools, static function ( $tool ) use ( $group ) { return $tool['group'] === $group; } ) );
	$reads = count( array_filter( $items, static function ( $tool ) { return ! empty( $tool['annotations']['readonly'] ); } ) );
	$md .= '| [' . cell( $data['label'] ) . '](#' . $group . ') | ' . count( $items ) . ' | ' . $reads . ' | ' . ( count( $items ) - $reads ) . " |\n";
}

foreach ( $groups as $group => $data ) {
	$md .= "\n<a id=\"" . $group . "\"></a>\n\n## " . $data['label'] . "\n\n" . $data['description'] . "\n";
	foreach ( $tools as $tool ) {
		if ( $tool['group'] !== $group ) {
			continue;
		}
		$md .= "\n### `" . $tool['name'] . "`\n\n" . $tool['description'] . "\n\n";
		$md .= 'Ability: `' . $tool['ability'] . '`. [Implementación](../' . $tool['source'] . ").\n\n";
		$annotations = array();
		foreach ( array( 'readonly' => 'readOnlyHint', 'destructive' => 'destructiveHint', 'idempotent' => 'idempotentHint' ) as $key => $hint ) {
			if ( array_key_exists( $key, $tool['annotations'] ) ) {
				$annotations[] = '`' . $hint . '=' . ( $tool['annotations'][ $key ] ? 'true' : 'false' ) . '`';
			}
		}
		$md .= 'Anotaciones: ' . implode( ', ', $annotations ) . ".\n\n";
		$schema = $tool['inputSchema'];
		if ( empty( $schema['properties'] ) ) {
			$md .= "Sin parámetros; envía `arguments: {}`.\n";
			continue;
		}
		$md .= "| Parámetro | Tipo | Obligatorio | Predeterminado | Descripción |\n| --- | --- | --- | --- | --- |\n";
		foreach ( $schema['properties'] as $name => $property ) {
			$type = isset( $property['type'] ) ? implode( ' / ', (array) $property['type'] ) : 'cualquiera';
			$description = $property['description'] ?? '';
			if ( isset( $property['enum'] ) ) {
				$description .= ' Valores: `' . json( $property['enum'] ) . '`.';
			}
			$default = array_key_exists( 'default', $property ) ? '`' . cell( json( $property['default'] ) ) . '`' : '—';
			$required = in_array( $name, $schema['required'] ?? array(), true ) ? 'Sí' : 'No';
			$md .= '| `' . cell( $name ) . '` | ' . cell( $type ) . ' | ' . $required . ' | ' . $default . ' | ' . cell( $description ) . " |\n";
		}
	}
}

$directory = dirname( __DIR__ ) . '/docs';
if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
	\WP_CLI::error( 'No se pudo crear docs/.' );
}
$export = array( 'version' => RENOVA_MCP_VERSION, 'tools' => $tools );
$encoded = wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
if ( false === $encoded || false === file_put_contents( $directory . '/tools.json', $encoded . "\n" ) || false === file_put_contents( $directory . '/tools.md', $md ) ) {
	\WP_CLI::error( 'No se pudo escribir la referencia.' );
}
\WP_CLI::success( count( $tools ) . ' herramientas documentadas en docs/tools.md y docs/tools.json.' );
