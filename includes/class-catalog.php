<?php
/**
 * Catálogo de herramientas: grupos, disponibilidad y qué se expone al servidor MCP.
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Agrupa las abilities por ámbito y decide cuáles se exponen.
 */
class Catalog {

	/**
	 * Herramientas de cada grupo que no se distingue por prefijo. Las de ACF,
	 * Elementor y Rank Math se reconocen por su prefijo (`acf-`, `elementor-`,
	 * `rankmath-`). Una herramienta que no figure aquí cae en `system`, el grupo
	 * más restringido.
	 *
	 * @var array<string,string[]>
	 */
	private static $members = array(
		'content' => array(
			'list-posts',
			'get-post',
			'create-post',
			'update-post',
			'delete-post',
			'get-post-meta',
			'update-post-meta',
			'delete-post-meta',
			'list-post-types',
			'list-taxonomies',
			'list-terms',
			'get-term',
			'create-term',
			'update-term',
			'delete-term',
			'set-post-terms',
			'get-post-terms',
			'list-media',
			'get-media',
			'upload-media',
			'delete-media',
			'set-featured-image',
			'list-menus',
			'get-menu',
			'create-menu',
			'add-menu-item',
			'delete-menu-item',
			'assign-menu-location',
			'list-comments',
			'moderate-comment',
			'delete-comment',
		),
		'site'    => array(
			'get-site-info',
			'get-option',
			'update-option',
			'get-permalink-structure',
			'update-permalink-structure',
			'flush-rewrite-rules',
			'get-theme-mod',
			'update-theme-mod',
			'manage-transient',
			'flush-cache',
		),
		'users'   => array(
			'list-users',
			'get-user',
			'create-user',
			'update-user',
			'delete-user',
		),
		'system'  => array(
			'list-plugins',
			'activate-plugin',
			'deactivate-plugin',
			'install-plugin',
			'delete-plugin',
			'list-themes',
			'activate-theme',
			'check-updates',
			'run-update',
		),
	);

	/**
	 * Slugs de los grupos. No pasa por las traducciones, así que puede usarse
	 * antes de `init` (por ejemplo, al autenticar una petición).
	 *
	 * @return string[]
	 */
	public static function group_slugs() {
		return array( 'content', 'site', 'users', 'system', 'acf', 'elementor', 'rankmath' );
	}

	/**
	 * Grupos de herramientas con su nombre y descripción.
	 *
	 * @return array<string,array{label:string,description:string}>
	 */
	public static function groups() {
		return array(
			'content'   => array(
				'label'       => __( 'Contenido', 'renova-mcp' ),
				'description' => __( 'Entradas, páginas y CPT, metadatos, términos, medios, menús y comentarios.', 'renova-mcp' ),
			),
			'site'      => array(
				'label'       => __( 'Ajustes del sitio', 'renova-mcp' ),
				'description' => __( 'Información del sitio, opciones, permalinks, theme mods, transients y caché.', 'renova-mcp' ),
			),
			'users'     => array(
				'label'       => __( 'Usuarios', 'renova-mcp' ),
				'description' => __( 'Listar, crear, editar y eliminar usuarios.', 'renova-mcp' ),
			),
			'system'    => array(
				'label'       => __( 'Plugins, temas y actualizaciones', 'renova-mcp' ),
				'description' => __( 'Instalar, activar y eliminar plugins, cambiar de tema y actualizar.', 'renova-mcp' ),
			),
			'acf'       => array(
				'label'       => __( 'Advanced Custom Fields', 'renova-mcp' ),
				'description' => __( 'Valores de campos, grupos de campos y registro de CPT y taxonomías.', 'renova-mcp' ),
			),
			'elementor' => array(
				'label'       => __( 'Elementor', 'renova-mcp' ),
				'description' => __( 'Árbol de elementos, plantillas, ajustes de página y kit global.', 'renova-mcp' ),
			),
			'rankmath'  => array(
				'label'       => __( 'Rank Math SEO', 'renova-mcp' ),
				'description' => __( 'Meta SEO, schema, ajustes, redirecciones y monitor 404.', 'renova-mcp' ),
			),
		);
	}

	/**
	 * IDs de todas las abilities del plugin.
	 *
	 * @return string[]
	 */
	public static function all_tool_ids() {
		return array_merge(
			Abilities::tool_ids(),
			Acf_Abilities::tool_ids(),
			Acf_Types_Abilities::tool_ids(),
			Taxonomy_Abilities::tool_ids(),
			Elementor_Abilities::tool_ids(),
			Rankmath_Abilities::tool_ids(),
			Users_Media_Abilities::tool_ids(),
			Site_Abilities::tool_ids()
		);
	}

	/**
	 * Grupo al que pertenece una ability.
	 *
	 * @param string $ability_id ID de la ability (ej. "renova-mcp/get-post").
	 * @return string
	 */
	public static function group_of( $ability_id ) {
		$slug = substr( (string) $ability_id, strlen( Abilities::CATEGORY ) + 1 );

		foreach ( array( 'acf', 'elementor', 'rankmath' ) as $prefix ) {
			if ( 0 === strpos( $slug, $prefix . '-' ) ) {
				return $prefix;
			}
		}

		foreach ( self::$members as $group => $slugs ) {
			if ( in_array( $slug, $slugs, true ) ) {
				return $group;
			}
		}

		return 'system';
	}

	/**
	 * Indica si el plugin del que depende un grupo está activo.
	 *
	 * @param string $group Slug del grupo.
	 * @return bool
	 */
	public static function group_available( $group ) {
		switch ( $group ) {
			case 'acf':
				return function_exists( 'get_field' ) && function_exists( 'acf_get_field_groups' );
			case 'elementor':
				return did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' );
			case 'rankmath':
				return function_exists( 'rank_math' ) || defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath\Helper' );
		}

		return true;
	}

	/**
	 * Grupos cuyo plugin está activo.
	 *
	 * @return string[]
	 */
	public static function available_groups() {
		return array_values( array_filter( self::group_slugs(), array( __CLASS__, 'group_available' ) ) );
	}

	/**
	 * Reduce una selección de grupos hecha en un formulario. Marcar todos los
	 * que se ofrecían equivale a no restringir: el acceso seguirá cubriendo
	 * un grupo cuyo plugin se active más adelante.
	 *
	 * @param string[] $selected Grupos marcados.
	 * @return string[] Lista de grupos, o todos si no hay restricción.
	 */
	public static function resolve_selection( array $selected ) {
		if ( ! array_diff( self::available_groups(), $selected ) ) {
			return self::group_slugs();
		}

		return array_values( array_intersect( self::available_groups(), $selected ) );
	}

	/**
	 * Indica si un grupo está expuesto: disponible y no desactivado en los ajustes.
	 *
	 * @param string $group Slug del grupo.
	 * @return bool
	 */
	public static function group_enabled( $group ) {
		return self::group_available( $group ) && ! in_array( $group, (array) Settings::get( 'disabled_groups' ), true );
	}

	/**
	 * Indica si una ability es de solo lectura según sus anotaciones.
	 *
	 * @param string $ability_id ID de la ability.
	 * @return bool
	 */
	public static function is_readonly( $ability_id ) {
		$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( $ability_id ) : null;
		if ( ! $ability ) {
			return false;
		}

		$meta = $ability->get_meta();

		return isset( $meta['annotations']['readonly'] ) && true === $meta['annotations']['readonly'];
	}

	/**
	 * Abilities que el servidor MCP expone con los ajustes actuales.
	 *
	 * @return string[]
	 */
	public static function exposed_tool_ids() {
		$read_only = (bool) Settings::get( 'read_only' );
		$exposed   = array();

		foreach ( self::all_tool_ids() as $ability_id ) {
			if ( ! self::group_enabled( self::group_of( $ability_id ) ) ) {
				continue;
			}
			if ( $read_only && ! self::is_readonly( $ability_id ) ) {
				continue;
			}
			$exposed[] = $ability_id;
		}

		return $exposed;
	}

	/**
	 * Nombre de la herramienta MCP que el adaptador genera para una ability.
	 *
	 * @param string $ability_id ID de la ability.
	 * @return string
	 */
	public static function tool_name( $ability_id ) {
		return str_replace( '/', '-', $ability_id );
	}

	/**
	 * Resumen por grupo para la pantalla de ajustes.
	 *
	 * @return array<string,array{total:int,readonly:int}>
	 */
	public static function counts() {
		$counts = array();
		foreach ( self::group_slugs() as $group ) {
			$counts[ $group ] = array(
				'total'    => 0,
				'readonly' => 0,
			);
		}

		foreach ( self::all_tool_ids() as $ability_id ) {
			$group = self::group_of( $ability_id );
			++$counts[ $group ]['total'];
			if ( self::is_readonly( $ability_id ) ) {
				++$counts[ $group ]['readonly'];
			}
		}

		return $counts;
	}
}
