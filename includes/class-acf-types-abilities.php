<?php
/**
 * Habilidades MCP para el control total de Custom Post Types y Taxonomías
 * registrados mediante Advanced Custom Fields (ACF 6.1+).
 *
 * ACF almacena los CPT como entradas del tipo interno `acf-post-type` y las
 * taxonomías como `acf-taxonomy`. Este archivo envuelve la API pública de ACF
 * (`acf_get_acf_post_types()`, `acf_update_post_type()`, etc.) para poder
 * crear, leer, actualizar, eliminar y exportar tanto CPT como taxonomías.
 *
 * Solo se ejecutan si ACF está activo; cada callback revalida en tiempo de
 * ejecución y devuelve un WP_Error claro si la API no está disponible.
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Registro de abilities para CPT y taxonomías gestionadas por ACF.
 */
class Acf_Types_Abilities {

	/**
	 * ¿Está disponible la API de CPT/taxonomías de ACF (6.1+)?
	 *
	 * @return bool
	 */
	public static function is_available() {
		return function_exists( 'acf_get_acf_post_types' ) && function_exists( 'acf_update_post_type' );
	}

	/**
	 * IDs de las abilities expuestas como herramientas MCP.
	 *
	 * @return string[]
	 */
	public static function tool_ids() {
		return array(
			// Custom Post Types (ACF).
			'renova-mcp/acf-list-post-types',
			'renova-mcp/acf-get-post-type',
			'renova-mcp/acf-save-post-type',
			'renova-mcp/acf-delete-post-type',
			'renova-mcp/acf-export-post-type',
			// Taxonomías (ACF).
			'renova-mcp/acf-list-taxonomies',
			'renova-mcp/acf-get-taxonomy',
			'renova-mcp/acf-save-taxonomy',
			'renova-mcp/acf-delete-taxonomy',
			'renova-mcp/acf-export-taxonomy',
		);
	}

	/**
	 * Permisos (administrador).
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Guarda de disponibilidad en tiempo de ejecución.
	 *
	 * @return \WP_Error|null
	 */
	private static function guard() {
		if ( ! self::is_available() ) {
			return new \WP_Error( 'renova_mcp_acf_types_inactive', 'La API de CPT/taxonomías de ACF (6.1+) no está disponible.' );
		}
		return null;
	}

	/**
	 * Registra todas las abilities.
	 */
	public static function register() {
		$obj = array( 'type' => 'object' );

		/* ================= Custom Post Types ================= */

		wp_register_ability(
			'renova-mcp/acf-list-post-types',
			array(
				'label'               => __( 'ACF: listar tipos de contenido', 'renova-mcp' ),
				'description'         => __( 'Lista todos los Custom Post Types creados con ACF (su key, slug, título y estado). Envuelve acf_get_acf_post_types().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => $obj,
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'list_post_types' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/acf-get-post-type',
			array(
				'label'               => __( 'ACF: obtener tipo de contenido', 'renova-mcp' ),
				'description'         => __( 'Devuelve la definición completa de un CPT de ACF por su key (ej. "post_type_abc123"), ID o slug. Envuelve acf_get_post_type().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => array( 'string', 'integer' ),
							'description' => __( 'Key (post_type_xxx), ID o slug del CPT.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_post_type' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/acf-save-post-type',
			array(
				'label'               => __( 'ACF: crear o actualizar tipo de contenido', 'renova-mcp' ),
				'description'         => __( 'Crea un nuevo CPT o actualiza uno existente. Para crear, indica al menos "post_type" (slug) y "title". Para actualizar, incluye también su "key" y/o "ID". Envuelve acf_update_post_type().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array(
							'type'        => 'object',
							'description' => __( 'Definición del CPT: post_type (slug, máx 20 car.), title, labels, public, hierarchical, supports, has_archive, menu_icon, etc. Mismo formato que la exportación de ACF.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'post_type' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'save_post_type' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		wp_register_ability(
			'renova-mcp/acf-delete-post-type',
			array(
				'label'               => __( 'ACF: eliminar tipo de contenido', 'renova-mcp' ),
				'description'         => __( 'Elimina por completo un CPT de ACF por su key o ID. Acción destructiva (no borra las entradas existentes de ese tipo, solo el registro del CPT). Envuelve acf_delete_post_type().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => array( 'string', 'integer' ),
							'description' => __( 'Key o ID del CPT.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'delete_post_type' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'destructive' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/acf-export-post-type',
			array(
				'label'               => __( 'ACF: exportar tipo de contenido', 'renova-mcp' ),
				'description'         => __( 'Devuelve la definición portable de un CPT (apta para recrearlo). Envuelve acf_get_post_type() + acf_prepare_post_type_for_export().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => array( 'string', 'integer' ),
							'description' => __( 'Key o ID del CPT.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'export_post_type' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		/* ===================== Taxonomías ==================== */

		wp_register_ability(
			'renova-mcp/acf-list-taxonomies',
			array(
				'label'               => __( 'ACF: listar taxonomías', 'renova-mcp' ),
				'description'         => __( 'Lista todas las taxonomías creadas con ACF (key, slug, título, tipos de objeto y estado). Envuelve acf_get_acf_taxonomies().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => $obj,
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'list_taxonomies' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/acf-get-taxonomy',
			array(
				'label'               => __( 'ACF: obtener taxonomía', 'renova-mcp' ),
				'description'         => __( 'Devuelve la definición completa de una taxonomía de ACF por su key (ej. "taxonomy_abc123"), ID o slug. Envuelve acf_get_taxonomy().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => array( 'string', 'integer' ),
							'description' => __( 'Key (taxonomy_xxx), ID o slug de la taxonomía.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_taxonomy' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/acf-save-taxonomy',
			array(
				'label'               => __( 'ACF: crear o actualizar taxonomía', 'renova-mcp' ),
				'description'         => __( 'Crea una nueva taxonomía o actualiza una existente. Para crear, indica al menos "taxonomy" (slug), "title" y "object_type" (array de post types asociados). Para actualizar, incluye su "key" y/o "ID". Envuelve acf_update_taxonomy().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'taxonomy' => array(
							'type'        => 'object',
							'description' => __( 'Definición: taxonomy (slug, máx 32 car.), title, object_type (array), labels, hierarchical, public, show_in_rest, etc. Mismo formato que la exportación de ACF.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'taxonomy' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'save_taxonomy' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		wp_register_ability(
			'renova-mcp/acf-delete-taxonomy',
			array(
				'label'               => __( 'ACF: eliminar taxonomía', 'renova-mcp' ),
				'description'         => __( 'Elimina por completo una taxonomía de ACF por su key o ID. Acción destructiva. Envuelve acf_delete_taxonomy().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => array( 'string', 'integer' ),
							'description' => __( 'Key o ID de la taxonomía.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'delete_taxonomy' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'destructive' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/acf-export-taxonomy',
			array(
				'label'               => __( 'ACF: exportar taxonomía', 'renova-mcp' ),
				'description'         => __( 'Devuelve la definición portable de una taxonomía (apta para recrearla). Envuelve acf_get_taxonomy() + acf_prepare_taxonomy_for_export().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => array( 'string', 'integer' ),
							'description' => __( 'Key o ID de la taxonomía.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'export_taxonomy' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);
	}

	/* ===================================================================== */
	/* Implementaciones — Custom Post Types                                   */
	/* ===================================================================== */

	/**
	 * @return array|\WP_Error
	 */
	public static function list_post_types() {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$items = array();
		foreach ( (array) acf_get_acf_post_types() as $pt ) {
			$items[] = array(
				'key'       => isset( $pt['key'] ) ? $pt['key'] : '',
				'id'        => isset( $pt['ID'] ) ? $pt['ID'] : 0,
				'post_type' => isset( $pt['post_type'] ) ? $pt['post_type'] : '',
				'title'     => isset( $pt['title'] ) ? $pt['title'] : '',
				'active'    => ! empty( $pt['active'] ),
			);
		}
		return array(
			'count'      => count( $items ),
			'post_types' => $items,
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_post_type( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$pt = acf_get_post_type( $input['id'] );
		if ( ! $pt ) {
			return new \WP_Error( 'renova_mcp_acf_post_type_not_found', 'No se encontró el tipo de contenido (CPT) indicado.' );
		}
		return array( 'post_type' => $pt );
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function save_post_type( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$pt = (array) $input['post_type'];
		if ( empty( $pt['post_type'] ) && empty( $pt['key'] ) && empty( $pt['ID'] ) ) {
			return new \WP_Error( 'renova_mcp_acf_post_type_invalid', 'Indica al menos "post_type" (slug) para crear, o "key"/"ID" para actualizar.' );
		}
		$result = acf_update_post_type( $pt );
		if ( ! $result || empty( $result['ID'] ) ) {
			return new \WP_Error( 'renova_mcp_acf_post_type_save_failed', 'No se pudo guardar el tipo de contenido.', $result );
		}
		return array(
			'success'   => true,
			'post_type' => $result,
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function delete_post_type( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$ok = acf_delete_post_type( $input['id'] );
		return array(
			'success' => (bool) $ok,
			'id'      => $input['id'],
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function export_post_type( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$pt = acf_get_post_type( $input['id'] );
		if ( ! $pt ) {
			return new \WP_Error( 'renova_mcp_acf_post_type_not_found', 'No se encontró el tipo de contenido (CPT) indicado.' );
		}
		$export = function_exists( 'acf_prepare_post_type_for_export' ) ? acf_prepare_post_type_for_export( $pt ) : $pt;
		return array( 'post_type' => $export );
	}

	/* ===================================================================== */
	/* Implementaciones — Taxonomías                                          */
	/* ===================================================================== */

	/**
	 * @return array|\WP_Error
	 */
	public static function list_taxonomies() {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$items = array();
		foreach ( (array) acf_get_acf_taxonomies() as $tax ) {
			$items[] = array(
				'key'         => isset( $tax['key'] ) ? $tax['key'] : '',
				'id'          => isset( $tax['ID'] ) ? $tax['ID'] : 0,
				'taxonomy'    => isset( $tax['taxonomy'] ) ? $tax['taxonomy'] : '',
				'title'       => isset( $tax['title'] ) ? $tax['title'] : '',
				'object_type' => isset( $tax['object_type'] ) ? $tax['object_type'] : array(),
				'active'      => ! empty( $tax['active'] ),
			);
		}
		return array(
			'count'      => count( $items ),
			'taxonomies' => $items,
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_taxonomy( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$tax = acf_get_taxonomy( $input['id'] );
		if ( ! $tax ) {
			return new \WP_Error( 'renova_mcp_acf_taxonomy_not_found', 'No se encontró la taxonomía indicada.' );
		}
		return array( 'taxonomy' => $tax );
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function save_taxonomy( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$tax = (array) $input['taxonomy'];
		if ( empty( $tax['taxonomy'] ) && empty( $tax['key'] ) && empty( $tax['ID'] ) ) {
			return new \WP_Error( 'renova_mcp_acf_taxonomy_invalid', 'Indica al menos "taxonomy" (slug) para crear, o "key"/"ID" para actualizar.' );
		}
		$result = acf_update_taxonomy( $tax );
		if ( ! $result || empty( $result['ID'] ) ) {
			return new \WP_Error( 'renova_mcp_acf_taxonomy_save_failed', 'No se pudo guardar la taxonomía.', $result );
		}
		return array(
			'success'  => true,
			'taxonomy' => $result,
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function delete_taxonomy( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$ok = acf_delete_taxonomy( $input['id'] );
		return array(
			'success' => (bool) $ok,
			'id'      => $input['id'],
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function export_taxonomy( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$tax = acf_get_taxonomy( $input['id'] );
		if ( ! $tax ) {
			return new \WP_Error( 'renova_mcp_acf_taxonomy_not_found', 'No se encontró la taxonomía indicada.' );
		}
		$export = function_exists( 'acf_prepare_taxonomy_for_export' ) ? acf_prepare_taxonomy_for_export( $tax ) : $tax;
		return array( 'taxonomy' => $export );
	}
}
