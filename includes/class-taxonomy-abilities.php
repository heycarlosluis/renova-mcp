<?php
/**
 * Habilidades MCP para taxonomías y términos nativos de WordPress.
 *
 * A diferencia de class-acf-types-abilities.php (que gestiona el *registro* de
 * CPT y taxonomías vía ACF), este archivo opera sobre cualquier taxonomía ya
 * registrada: introspección de tipos de contenido/taxonomías, CRUD de términos
 * y la asignación de términos a entradas. No depende de ACF.
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Registro de abilities de taxonomías y términos.
 */
class Taxonomy_Abilities {

	/**
	 * IDs de las abilities expuestas como herramientas MCP.
	 *
	 * @return string[]
	 */
	public static function tool_ids() {
		return array(
			'renova-mcp/list-post-types',
			'renova-mcp/list-taxonomies',
			'renova-mcp/list-terms',
			'renova-mcp/get-term',
			'renova-mcp/create-term',
			'renova-mcp/update-term',
			'renova-mcp/delete-term',
			'renova-mcp/set-post-terms',
			'renova-mcp/get-post-terms',
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
	 * Registra todas las abilities.
	 */
	public static function register() {
		$obj      = array( 'type' => 'object' );
		$taxonomy = array(
			'type'        => 'string',
			'description' => __( 'Slug de la taxonomía (ej. "category", "post_tag" o una propia).', 'renova-mcp' ),
		);

		// --- Introspección de tipos de contenido registrados ---------------------
		wp_register_ability(
			'renova-mcp/list-post-types',
			array(
				'label'               => __( 'Listar tipos de contenido registrados', 'renova-mcp' ),
				'description'         => __( 'Lista todos los post types registrados en WordPress (nativos, de ACF y de otros plugins), con su slug, etiqueta y taxonomías asociadas.', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'public_only' => array(
							'type'        => 'boolean',
							'description' => __( 'Solo tipos públicos.', 'renova-mcp' ),
							'default'     => false,
						),
					),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'list_post_types' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/list-taxonomies',
			array(
				'label'               => __( 'Listar taxonomías registradas', 'renova-mcp' ),
				'description'         => __( 'Lista todas las taxonomías registradas en WordPress, con su slug, etiqueta, jerarquía y tipos de contenido asociados.', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type'   => array(
							'type'        => 'string',
							'description' => __( 'Filtrar por las taxonomías de un post type concreto.', 'renova-mcp' ),
						),
						'public_only' => array(
							'type'        => 'boolean',
							'description' => __( 'Solo taxonomías públicas.', 'renova-mcp' ),
							'default'     => false,
						),
					),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'list_taxonomies' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		// --- Términos -------------------------------------------------------------
		wp_register_ability(
			'renova-mcp/list-terms',
			array(
				'label'               => __( 'Listar términos', 'renova-mcp' ),
				'description'         => __( 'Lista los términos de una taxonomía con filtros de búsqueda, jerarquía y cantidad. Envuelve get_terms().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'taxonomy'   => $taxonomy,
						'search'     => array(
							'type'        => 'string',
							'description' => __( 'Término de búsqueda.', 'renova-mcp' ),
						),
						'hide_empty' => array(
							'type'        => 'boolean',
							'description' => __( 'Ocultar términos sin contenido asociado.', 'renova-mcp' ),
							'default'     => false,
						),
						'parent'     => array(
							'type'        => 'integer',
							'description' => __( 'ID del término padre (para taxonomías jerárquicas).', 'renova-mcp' ),
						),
						'number'     => array(
							'type'        => 'integer',
							'description' => __( 'Número máximo de resultados (0 = sin límite).', 'renova-mcp' ),
							'default'     => 100,
						),
					),
					'required'   => array( 'taxonomy' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'list_terms' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/get-term',
			array(
				'label'               => __( 'Obtener término', 'renova-mcp' ),
				'description'         => __( 'Devuelve un término por su ID (y opcionalmente su taxonomía). Envuelve get_term().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'term_id'  => array(
							'type'        => 'integer',
							'description' => __( 'ID del término.', 'renova-mcp' ),
						),
						'taxonomy' => $taxonomy,
					),
					'required'   => array( 'term_id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_term' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/create-term',
			array(
				'label'               => __( 'Crear término', 'renova-mcp' ),
				'description'         => __( 'Crea un término (categoría, etiqueta o término propio) en una taxonomía. Envuelve wp_insert_term().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'taxonomy'    => $taxonomy,
						'name'        => array(
							'type'        => 'string',
							'description' => __( 'Nombre del término.', 'renova-mcp' ),
						),
						'slug'        => array(
							'type'        => 'string',
							'description' => __( 'Slug opcional.', 'renova-mcp' ),
						),
						'description' => array(
							'type'        => 'string',
							'description' => __( 'Descripción opcional.', 'renova-mcp' ),
						),
						'parent'      => array(
							'type'        => 'integer',
							'description' => __( 'ID del término padre (taxonomías jerárquicas).', 'renova-mcp' ),
						),
					),
					'required'   => array( 'taxonomy', 'name' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'create_term' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		wp_register_ability(
			'renova-mcp/update-term',
			array(
				'label'               => __( 'Actualizar término', 'renova-mcp' ),
				'description'         => __( 'Actualiza un término existente por su ID. Envuelve wp_update_term().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'term_id'     => array(
							'type'        => 'integer',
							'description' => __( 'ID del término.', 'renova-mcp' ),
						),
						'taxonomy'    => $taxonomy,
						'name'        => array( 'type' => 'string' ),
						'slug'        => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'parent'      => array( 'type' => 'integer' ),
					),
					'required'   => array( 'term_id', 'taxonomy' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'update_term' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		wp_register_ability(
			'renova-mcp/delete-term',
			array(
				'label'               => __( 'Eliminar término', 'renova-mcp' ),
				'description'         => __( 'Elimina un término de una taxonomía. Acción destructiva. Envuelve wp_delete_term().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'term_id'  => array(
							'type'        => 'integer',
							'description' => __( 'ID del término.', 'renova-mcp' ),
						),
						'taxonomy' => $taxonomy,
					),
					'required'   => array( 'term_id', 'taxonomy' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'delete_term' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'destructive' => true ) ),
			)
		);

		// --- Asignación término <-> entrada --------------------------------------
		wp_register_ability(
			'renova-mcp/set-post-terms',
			array(
				'label'               => __( 'Asignar términos a una entrada', 'renova-mcp' ),
				'description'         => __( 'Asigna (o añade) términos de una taxonomía a una entrada/CPT. Acepta IDs, slugs o nombres. Envuelve wp_set_object_terms().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'  => array(
							'type'        => 'integer',
							'description' => __( 'ID de la entrada/CPT.', 'renova-mcp' ),
						),
						'taxonomy' => $taxonomy,
						'terms'    => array(
							'type'        => 'array',
							'description' => __( 'Lista de términos: IDs (integer), slugs o nombres (string). Si la taxonomía no es jerárquica, los nombres inexistentes se crean.', 'renova-mcp' ),
							'items'       => array( 'type' => array( 'string', 'integer' ) ),
						),
						'append'   => array(
							'type'        => 'boolean',
							'description' => __( 'Si es true, añade a los términos existentes en lugar de reemplazarlos.', 'renova-mcp' ),
							'default'     => false,
						),
					),
					'required'   => array( 'post_id', 'taxonomy', 'terms' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'set_post_terms' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		wp_register_ability(
			'renova-mcp/get-post-terms',
			array(
				'label'               => __( 'Obtener términos de una entrada', 'renova-mcp' ),
				'description'         => __( 'Devuelve los términos asignados a una entrada/CPT en una taxonomía. Envuelve wp_get_object_terms().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'  => array(
							'type'        => 'integer',
							'description' => __( 'ID de la entrada/CPT.', 'renova-mcp' ),
						),
						'taxonomy' => array(
							'type'        => array( 'string', 'array' ),
							'description' => __( 'Slug de la taxonomía o array de slugs.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'post_id', 'taxonomy' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_post_terms' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);
	}

	/* ===================================================================== */
	/* Implementaciones                                                       */
	/* ===================================================================== */

	/**
	 * @param array $input Entrada.
	 * @return array
	 */
	public static function list_post_types( $input ) {
		$args  = ! empty( $input['public_only'] ) ? array( 'public' => true ) : array();
		$items = array();
		foreach ( get_post_types( $args, 'objects' ) as $slug => $pt ) {
			$items[] = array(
				'slug'         => $slug,
				'label'        => $pt->label,
				'public'       => (bool) $pt->public,
				'hierarchical' => (bool) $pt->hierarchical,
				'taxonomies'   => get_object_taxonomies( $slug ),
				'count'        => (int) ( wp_count_posts( $slug )->publish ?? 0 ),
			);
		}
		return array(
			'count'      => count( $items ),
			'post_types' => $items,
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array
	 */
	public static function list_taxonomies( $input ) {
		if ( ! empty( $input['post_type'] ) ) {
			$taxes = get_object_taxonomies( sanitize_key( $input['post_type'] ), 'objects' );
		} else {
			$args  = ! empty( $input['public_only'] ) ? array( 'public' => true ) : array();
			$taxes = get_taxonomies( $args, 'objects' );
		}

		$items = array();
		foreach ( $taxes as $slug => $tax ) {
			$items[] = array(
				'slug'         => $slug,
				'label'        => $tax->label,
				'public'       => (bool) $tax->public,
				'hierarchical' => (bool) $tax->hierarchical,
				'object_type'  => (array) $tax->object_type,
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
	public static function list_terms( $input ) {
		$taxonomy = sanitize_key( $input['taxonomy'] );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new \WP_Error( 'renova_mcp_taxonomy_not_found', sprintf( 'La taxonomía "%s" no existe.', $taxonomy ) );
		}

		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => ! empty( $input['hide_empty'] ),
			'number'     => isset( $input['number'] ) ? (int) $input['number'] : 100,
		);
		if ( ! empty( $input['search'] ) ) {
			$args['search'] = (string) $input['search'];
		}
		if ( isset( $input['parent'] ) ) {
			$args['parent'] = (int) $input['parent'];
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$items = array();
		foreach ( $terms as $term ) {
			$items[] = self::format_term( $term );
		}
		return array(
			'count' => count( $items ),
			'terms' => $items,
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_term( $input ) {
		$taxonomy = isset( $input['taxonomy'] ) ? sanitize_key( $input['taxonomy'] ) : '';
		$term     = get_term( (int) $input['term_id'], $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return $term ? $term : new \WP_Error( 'renova_mcp_term_not_found', 'No se encontró el término indicado.' );
		}
		return array( 'term' => self::format_term( $term ) );
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function create_term( $input ) {
		$taxonomy = sanitize_key( $input['taxonomy'] );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new \WP_Error( 'renova_mcp_taxonomy_not_found', sprintf( 'La taxonomía "%s" no existe.', $taxonomy ) );
		}

		$args = array();
		if ( ! empty( $input['slug'] ) ) {
			$args['slug'] = sanitize_title( $input['slug'] );
		}
		if ( isset( $input['description'] ) ) {
			$args['description'] = (string) $input['description'];
		}
		if ( ! empty( $input['parent'] ) ) {
			$args['parent'] = (int) $input['parent'];
		}

		$result = wp_insert_term( (string) $input['name'], $taxonomy, $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'success'  => true,
			'term_id'  => (int) $result['term_id'],
			'taxonomy' => $taxonomy,
			'term'     => self::format_term( get_term( (int) $result['term_id'], $taxonomy ) ),
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function update_term( $input ) {
		$taxonomy = sanitize_key( $input['taxonomy'] );
		$term_id  = (int) $input['term_id'];

		$args = array();
		if ( isset( $input['name'] ) ) {
			$args['name'] = (string) $input['name'];
		}
		if ( isset( $input['slug'] ) ) {
			$args['slug'] = sanitize_title( $input['slug'] );
		}
		if ( isset( $input['description'] ) ) {
			$args['description'] = (string) $input['description'];
		}
		if ( isset( $input['parent'] ) ) {
			$args['parent'] = (int) $input['parent'];
		}

		$result = wp_update_term( $term_id, $taxonomy, $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'success'  => true,
			'term_id'  => (int) $result['term_id'],
			'taxonomy' => $taxonomy,
			'term'     => self::format_term( get_term( $term_id, $taxonomy ) ),
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function delete_term( $input ) {
		$taxonomy = sanitize_key( $input['taxonomy'] );
		$term_id  = (int) $input['term_id'];

		$result = wp_delete_term( $term_id, $taxonomy );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result ) {
			return new \WP_Error( 'renova_mcp_term_not_found', 'No se encontró el término indicado.' );
		}
		if ( 0 === $result ) {
			return new \WP_Error( 'renova_mcp_term_protected', 'No se puede eliminar el término por defecto de la taxonomía.' );
		}
		return array(
			'success'  => true,
			'term_id'  => $term_id,
			'taxonomy' => $taxonomy,
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function set_post_terms( $input ) {
		$post_id  = (int) $input['post_id'];
		$taxonomy = sanitize_key( $input['taxonomy'] );
		if ( ! get_post( $post_id ) ) {
			return new \WP_Error( 'renova_mcp_post_not_found', 'No se encontró la entrada indicada.' );
		}
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new \WP_Error( 'renova_mcp_taxonomy_not_found', sprintf( 'La taxonomía "%s" no existe.', $taxonomy ) );
		}

		// Normaliza: los IDs numéricos se pasan como int; el resto como string.
		$terms = array();
		foreach ( (array) $input['terms'] as $t ) {
			$terms[] = is_numeric( $t ) ? (int) $t : (string) $t;
		}

		$result = wp_set_object_terms( $post_id, $terms, $taxonomy, ! empty( $input['append'] ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'success'      => true,
			'post_id'      => $post_id,
			'taxonomy'     => $taxonomy,
			'term_taxonomy_ids' => array_map( 'intval', (array) $result ),
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_post_terms( $input ) {
		$post_id  = (int) $input['post_id'];
		$taxonomy = is_array( $input['taxonomy'] ) ? array_map( 'sanitize_key', $input['taxonomy'] ) : sanitize_key( $input['taxonomy'] );

		$terms = wp_get_object_terms( $post_id, $taxonomy );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$items = array();
		foreach ( $terms as $term ) {
			$items[] = self::format_term( $term );
		}
		return array(
			'post_id' => $post_id,
			'count'   => count( $items ),
			'terms'   => $items,
		);
	}

	/**
	 * Da formato uniforme a un término.
	 *
	 * @param \WP_Term|null $term Término.
	 * @return array
	 */
	private static function format_term( $term ) {
		if ( ! $term || is_wp_error( $term ) ) {
			return array();
		}
		return array(
			'term_id'     => (int) $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'taxonomy'    => $term->taxonomy,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
			'description' => $term->description,
		);
	}
}
