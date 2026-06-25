<?php
/**
 * Abilities MCP para control total de Rank Math SEO.
 *
 * Como el plugin corre dentro de WordPress, manipula directamente la post-meta
 * `rank_math_*`, las opciones del plugin y las tablas de redirecciones / 404,
 * usando las clases internas de Rank Math cuando están disponibles y cayendo a
 * `$wpdb` si no. Si Rank Math no está activo, cada herramienta devuelve un
 * WP_Error claro al ejecutarse (mismo patrón que ACF y Elementor).
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Define y registra las capacidades de Rank Math expuestas por el servidor MCP.
 */
class Rankmath_Abilities {

	const CATEGORY = 'renova-mcp';

	/**
	 * Mapa de grupos de ajustes a su opción de WordPress.
	 *
	 * @var array<string,string>
	 */
	private static $settings_groups = array(
		'general' => 'rank-math-options-general',
		'titles'  => 'rank-math-options-titles',
		'sitemap' => 'rank-math-options-sitemap',
	);

	/**
	 * IDs de todas las abilities de Rank Math expuestas como herramientas MCP.
	 *
	 * @return string[]
	 */
	public static function tool_ids() {
		return array(
			'renova-mcp/rankmath-get-post-seo',
			'renova-mcp/rankmath-update-post-seo',
			'renova-mcp/rankmath-get-post-schema',
			'renova-mcp/rankmath-set-post-schema',
			'renova-mcp/rankmath-delete-post-schema',
			'renova-mcp/rankmath-get-settings',
			'renova-mcp/rankmath-update-settings',
			'renova-mcp/rankmath-list-redirections',
			'renova-mcp/rankmath-add-redirection',
			'renova-mcp/rankmath-update-redirection',
			'renova-mcp/rankmath-delete-redirection',
			'renova-mcp/rankmath-get-404-log',
			'renova-mcp/rankmath-clear-404-log',
		);
	}

	/**
	 * Registra todas las abilities de Rank Math.
	 */
	public static function register() {
		$obj  = array( 'type' => 'object' );
		$perm = array( Abilities::class, 'can_manage' );

		$post_id = array(
			'id' => array(
				'type'        => 'integer',
				'description' => __( 'ID de la entrada/página/CPT.', 'renova-mcp' ),
			),
		);

		wp_register_ability(
			'renova-mcp/rankmath-get-post-seo',
			array(
				'label'               => __( 'Leer SEO de contenido (Rank Math)', 'renova-mcp' ),
				'description'         => __( 'Devuelve los metadatos SEO de Rank Math de una entrada: título, descripción, palabra clave objetivo, robots, canonical, OpenGraph, Twitter y puntuación SEO.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => $post_id,
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_post_seo' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/rankmath-update-post-seo',
			array(
				'label'               => __( 'Actualizar SEO de contenido (Rank Math)', 'renova-mcp' ),
				'description'         => __( 'Fija el título SEO, descripción, palabra(s) clave objetivo, robots, canonical, breadcrumb, contenido pilar y campos OpenGraph/Twitter de una entrada.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'                 => $post_id['id'],
						'title'              => array(
							'type'        => 'string',
							'description' => __( 'Título SEO (admite variables Rank Math como %title%).', 'renova-mcp' ),
						),
						'description'        => array(
							'type'        => 'string',
							'description' => __( 'Meta descripción.', 'renova-mcp' ),
						),
						'focus_keyword'      => array(
							'type'        => 'string',
							'description' => __( 'Palabra(s) clave objetivo, separadas por comas.', 'renova-mcp' ),
						),
						'canonical_url'      => array(
							'type'        => 'string',
							'description' => __( 'URL canónica.', 'renova-mcp' ),
						),
						'robots'             => array(
							'type'        => 'array',
							'description' => __( 'Directivas robots: index, noindex, nofollow, noarchive, noimageindex, nosnippet.', 'renova-mcp' ),
							'items'       => array( 'type' => 'string' ),
						),
						'breadcrumb_title'   => array(
							'type'        => 'string',
							'description' => __( 'Título para las migas de pan.', 'renova-mcp' ),
						),
						'pillar_content'     => array(
							'type'        => 'boolean',
							'description' => __( 'Marca el contenido como pilar.', 'renova-mcp' ),
						),
						'facebook_title'     => array( 'type' => 'string' ),
						'facebook_description' => array( 'type' => 'string' ),
						'facebook_image'     => array( 'type' => 'string' ),
						'twitter_title'      => array( 'type' => 'string' ),
						'twitter_description' => array( 'type' => 'string' ),
						'twitter_image'      => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'update_post_seo' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/rankmath-get-post-schema',
			array(
				'label'               => __( 'Leer Schema de contenido (Rank Math)', 'renova-mcp' ),
				'description'         => __( 'Devuelve todos los datos de Schema (marcado estructurado) aplicados a una entrada.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => $post_id,
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_post_schema' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/rankmath-set-post-schema',
			array(
				'label'               => __( 'Fijar Schema de contenido (Rank Math)', 'renova-mcp' ),
				'description'         => __( 'Crea o reemplaza un bloque de Schema (ej. Article, FAQPage, Product) en una entrada. "data" es el array de propiedades del schema.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'   => $post_id['id'],
						'type' => array(
							'type'        => 'string',
							'description' => __( 'Tipo de Schema (Article, FAQPage, Product, HowTo, Recipe, etc.).', 'renova-mcp' ),
						),
						'data' => array(
							'type'        => 'object',
							'description' => __( 'Propiedades del schema. Debe incluir al menos @type y metadata.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id', 'type', 'data' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'set_post_schema' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/rankmath-delete-post-schema',
			array(
				'label'               => __( 'Eliminar Schema de contenido (Rank Math)', 'renova-mcp' ),
				'description'         => __( 'Elimina un bloque de Schema de una entrada por su tipo.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'   => $post_id['id'],
						'type' => array(
							'type'        => 'string',
							'description' => __( 'Tipo de Schema a eliminar.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id', 'type' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'delete_post_schema' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'destructive' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/rankmath-get-settings',
			array(
				'label'               => __( 'Leer ajustes globales (Rank Math)', 'renova-mcp' ),
				'description'         => __( 'Devuelve un grupo de ajustes globales de Rank Math: "general", "titles" (títulos y meta por tipo de contenido) o "sitemap".', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'group' => array(
							'type'        => 'string',
							'description' => __( 'Grupo: general, titles o sitemap.', 'renova-mcp' ),
							'default'     => 'general',
						),
					),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_settings' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/rankmath-update-settings',
			array(
				'label'               => __( 'Actualizar ajustes globales (Rank Math)', 'renova-mcp' ),
				'description'         => __( 'Fusiona ajustes en un grupo global de Rank Math (general, titles o sitemap) y refresca su caché.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'group'    => array(
							'type'        => 'string',
							'description' => __( 'Grupo: general, titles o sitemap.', 'renova-mcp' ),
						),
						'settings' => array(
							'type'        => 'object',
							'description' => __( 'Ajustes a fusionar.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'group', 'settings' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'update_settings' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/rankmath-list-redirections',
			array(
				'label'               => __( 'Listar redirecciones (Rank Math)', 'renova-mcp' ),
				'description'         => __( 'Lista las redirecciones configuradas en Rank Math con su origen, destino, código y estado.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'number' => array(
							'type'        => 'integer',
							'description' => __( 'Máximo de resultados.', 'renova-mcp' ),
							'default'     => 100,
						),
					),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'list_redirections' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/rankmath-add-redirection',
			array(
				'label'               => __( 'Crear redirección (Rank Math)', 'renova-mcp' ),
				'description'         => __( 'Crea una redirección de una o varias URL de origen a un destino con el código indicado (301, 302, 307, 410, 451).', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'sources'     => array(
							'type'        => 'array',
							'description' => __( 'URL(s) o patrones de origen.', 'renova-mcp' ),
							'items'       => array( 'type' => 'string' ),
						),
						'url_to'      => array(
							'type'        => 'string',
							'description' => __( 'URL de destino.', 'renova-mcp' ),
						),
						'comparison'  => array(
							'type'        => 'string',
							'description' => __( 'Tipo de coincidencia: exact, contains, start, end, regex.', 'renova-mcp' ),
							'default'     => 'exact',
						),
						'header_code' => array(
							'type'        => 'string',
							'description' => __( 'Código HTTP: 301, 302, 307, 410 o 451.', 'renova-mcp' ),
							'default'     => '301',
						),
					),
					'required'   => array( 'sources', 'url_to' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'add_redirection' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/rankmath-update-redirection',
			array(
				'label'               => __( 'Actualizar redirección (Rank Math)', 'renova-mcp' ),
				'description'         => __( 'Actualiza el destino, código o estado (active/inactive) de una redirección por su id.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'          => array(
							'type'        => 'integer',
							'description' => __( 'ID de la redirección.', 'renova-mcp' ),
						),
						'url_to'      => array( 'type' => 'string' ),
						'header_code' => array( 'type' => 'string' ),
						'status'      => array(
							'type'        => 'string',
							'description' => __( 'active o inactive.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'update_redirection' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/rankmath-delete-redirection',
			array(
				'label'               => __( 'Eliminar redirección (Rank Math)', 'renova-mcp' ),
				'description'         => __( 'Elimina una redirección por su id.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => __( 'ID de la redirección a eliminar.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'delete_redirection' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'destructive' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/rankmath-get-404-log',
			array(
				'label'               => __( 'Leer registro 404 (Rank Math)', 'renova-mcp' ),
				'description'         => __( 'Devuelve las entradas del monitor de errores 404 de Rank Math (URI, accesos, fecha).', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'number' => array(
							'type'        => 'integer',
							'description' => __( 'Máximo de resultados.', 'renova-mcp' ),
							'default'     => 100,
						),
					),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_404_log' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/rankmath-clear-404-log',
			array(
				'label'               => __( 'Vaciar registro 404 (Rank Math)', 'renova-mcp' ),
				'description'         => __( 'Borra todas las entradas del monitor de errores 404.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $obj,
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'clear_404_log' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'destructive' => true ) ),
			)
		);
	}

	/* ===================================================================== */
	/* Utilidades internas                                                    */
	/* ===================================================================== */

	/**
	 * Comprueba que Rank Math esté disponible.
	 *
	 * @return true|\WP_Error
	 */
	private static function require_rankmath() {
		if ( function_exists( 'rank_math' ) || defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath\Helper' ) ) {
			return true;
		}
		return new \WP_Error( 'renova_mcp_no_rankmath', 'Rank Math SEO no está activo en este sitio.' );
	}

	/**
	 * Valida que el post exista.
	 *
	 * @param int $id ID.
	 * @return true|\WP_Error
	 */
	private static function require_post( $id ) {
		if ( ! $id || ! get_post( $id ) ) {
			return new \WP_Error( 'renova_mcp_post_not_found', 'No se encontró el contenido indicado.' );
		}
		return true;
	}

	/* ===================================================================== */
	/* SEO por entrada                                                        */
	/* ===================================================================== */

	/**
	 * Lee toda la meta SEO de Rank Math de una entrada.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_post_seo( $input ) {
		$err = self::require_rankmath();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$id  = (int) ( $input['id'] ?? 0 );
		$err = self::require_post( $id );
		if ( is_wp_error( $err ) ) {
			return $err;
		}

		$meta = get_post_meta( $id );
		$seo  = array();
		foreach ( $meta as $key => $values ) {
			if ( 0 === strpos( $key, 'rank_math_' ) ) {
				$seo[ $key ] = maybe_unserialize( $values[0] );
			}
		}
		return array(
			'id'  => $id,
			'seo' => $seo,
		);
	}

	/**
	 * Actualiza la meta SEO de Rank Math de una entrada.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function update_post_seo( $input ) {
		$err = self::require_rankmath();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$id  = (int) ( $input['id'] ?? 0 );
		$err = self::require_post( $id );
		if ( is_wp_error( $err ) ) {
			return $err;
		}

		$map = array(
			'title'                => 'rank_math_title',
			'description'          => 'rank_math_description',
			'focus_keyword'        => 'rank_math_focus_keyword',
			'canonical_url'        => 'rank_math_canonical_url',
			'breadcrumb_title'     => 'rank_math_breadcrumb_title',
			'facebook_title'       => 'rank_math_facebook_title',
			'facebook_description' => 'rank_math_facebook_description',
			'facebook_image'       => 'rank_math_facebook_image',
			'twitter_title'        => 'rank_math_twitter_title',
			'twitter_description'  => 'rank_math_twitter_description',
			'twitter_image'        => 'rank_math_twitter_image',
		);

		$updated = array();
		foreach ( $map as $field => $meta_key ) {
			if ( array_key_exists( $field, $input ) ) {
				update_post_meta( $id, $meta_key, (string) $input[ $field ] );
				$updated[] = $meta_key;
			}
		}

		if ( isset( $input['robots'] ) && is_array( $input['robots'] ) ) {
			$robots = array_values( array_map( 'sanitize_text_field', $input['robots'] ) );
			update_post_meta( $id, 'rank_math_robots', $robots );
			$updated[] = 'rank_math_robots';
		}

		if ( array_key_exists( 'pillar_content', $input ) ) {
			update_post_meta( $id, 'rank_math_pillar_content', ! empty( $input['pillar_content'] ) ? 'on' : 'off' );
			$updated[] = 'rank_math_pillar_content';
		}

		return array(
			'success' => true,
			'id'      => $id,
			'updated' => $updated,
		);
	}

	/**
	 * Lee los bloques de Schema de una entrada.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_post_schema( $input ) {
		$err = self::require_rankmath();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$id  = (int) ( $input['id'] ?? 0 );
		$err = self::require_post( $id );
		if ( is_wp_error( $err ) ) {
			return $err;
		}

		$meta   = get_post_meta( $id );
		$schema = array();
		foreach ( $meta as $key => $values ) {
			if ( 0 === strpos( $key, 'rank_math_schema_' ) ) {
				$type            = substr( $key, strlen( 'rank_math_schema_' ) );
				$schema[ $type ] = maybe_unserialize( $values[0] );
			}
		}
		return array(
			'id'     => $id,
			'schema' => $schema,
		);
	}

	/**
	 * Crea o reemplaza un bloque de Schema.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function set_post_schema( $input ) {
		$err = self::require_rankmath();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$id  = (int) ( $input['id'] ?? 0 );
		$err = self::require_post( $id );
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$type = isset( $input['type'] ) ? preg_replace( '/[^A-Za-z0-9_]/', '', (string) $input['type'] ) : '';
		$data = isset( $input['data'] ) && is_array( $input['data'] ) ? $input['data'] : null;
		if ( '' === $type || null === $data ) {
			return new \WP_Error( 'renova_mcp_bad_schema', 'Indica "type" y "data" del schema.' );
		}
		if ( empty( $data['@type'] ) ) {
			$data['@type'] = $type;
		}
		update_post_meta( $id, 'rank_math_schema_' . $type, $data );
		return array(
			'success' => true,
			'id'      => $id,
			'type'    => $type,
		);
	}

	/**
	 * Elimina un bloque de Schema.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function delete_post_schema( $input ) {
		$err = self::require_rankmath();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$id   = (int) ( $input['id'] ?? 0 );
		$err  = self::require_post( $id );
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$type = isset( $input['type'] ) ? preg_replace( '/[^A-Za-z0-9_]/', '', (string) $input['type'] ) : '';
		if ( '' === $type ) {
			return new \WP_Error( 'renova_mcp_bad_schema', 'Falta "type".' );
		}
		$ok = delete_post_meta( $id, 'rank_math_schema_' . $type );
		return array(
			'success' => (bool) $ok,
			'id'      => $id,
			'type'    => $type,
		);
	}

	/* ===================================================================== */
	/* Ajustes globales                                                       */
	/* ===================================================================== */

	/**
	 * Lee un grupo de ajustes globales.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_settings( $input ) {
		$err = self::require_rankmath();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$group = isset( $input['group'] ) ? sanitize_key( $input['group'] ) : 'general';
		if ( ! isset( self::$settings_groups[ $group ] ) ) {
			return new \WP_Error( 'renova_mcp_bad_group', 'Grupo no válido. Usa: general, titles o sitemap.' );
		}
		return array(
			'group'    => $group,
			'settings' => get_option( self::$settings_groups[ $group ], array() ),
		);
	}

	/**
	 * Fusiona ajustes en un grupo global.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function update_settings( $input ) {
		$err = self::require_rankmath();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$group = isset( $input['group'] ) ? sanitize_key( $input['group'] ) : '';
		if ( ! isset( self::$settings_groups[ $group ] ) ) {
			return new \WP_Error( 'renova_mcp_bad_group', 'Grupo no válido. Usa: general, titles o sitemap.' );
		}
		$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();
		$option   = self::$settings_groups[ $group ];
		$current  = get_option( $option, array() );
		$current  = is_array( $current ) ? $current : array();
		$merged   = array_merge( $current, $settings );
		update_option( $option, $merged );

		// Refresca la caché interna de Rank Math si está disponible.
		if ( function_exists( 'rank_math' ) && class_exists( 'RankMath\Helper' ) && method_exists( 'RankMath\Helper', 'clear_cache' ) ) {
			\RankMath\Helper::clear_cache();
		}

		return array(
			'success'  => true,
			'group'    => $group,
			'settings' => $merged,
		);
	}

	/* ===================================================================== */
	/* Redirecciones                                                          */
	/* ===================================================================== */

	/**
	 * Nombre de la tabla de redirecciones.
	 *
	 * @return string
	 */
	private static function redirections_table() {
		global $wpdb;
		return $wpdb->prefix . 'rank_math_redirections';
	}

	/**
	 * Lista redirecciones.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function list_redirections( $input ) {
		$err = self::require_rankmath();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		global $wpdb;
		$table = self::redirections_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return new \WP_Error( 'renova_mcp_no_redirections', 'El módulo de Redirecciones de Rank Math no está activo.' );
		}
		$number = isset( $input['number'] ) ? (int) $input['number'] : 100;
		// phpcs:ignore WordPress.DB
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $number ), ARRAY_A );

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = array(
				'id'          => (int) $row['id'],
				'sources'     => maybe_unserialize( $row['sources'] ),
				'url_to'      => $row['url_to'],
				'header_code' => $row['header_code'],
				'hits'        => (int) $row['hits'],
				'status'      => $row['status'],
			);
		}
		return array(
			'count'        => count( $items ),
			'redirections' => $items,
		);
	}

	/**
	 * Crea una redirección.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function add_redirection( $input ) {
		$err = self::require_rankmath();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$sources_in  = isset( $input['sources'] ) ? (array) $input['sources'] : array();
		$url_to      = isset( $input['url_to'] ) ? esc_url_raw( $input['url_to'] ) : '';
		$comparison  = isset( $input['comparison'] ) ? sanitize_key( $input['comparison'] ) : 'exact';
		$header_code = isset( $input['header_code'] ) ? (string) $input['header_code'] : '301';
		if ( empty( $sources_in ) || '' === $url_to ) {
			return new \WP_Error( 'renova_mcp_bad_redirection', 'Indica al menos una "sources" y "url_to".' );
		}

		$sources = array();
		foreach ( $sources_in as $src ) {
			$sources[] = array(
				'pattern'    => (string) $src,
				'comparison' => $comparison,
				'ignore'     => '',
			);
		}

		// Usa la API interna de Rank Math si está disponible (gestiona la caché).
		if ( class_exists( 'RankMath\Redirections\DB' ) && method_exists( 'RankMath\Redirections\DB', 'add' ) ) {
			$rid = \RankMath\Redirections\DB::add(
				array(
					'sources'     => $sources,
					'url_to'      => $url_to,
					'header_code' => $header_code,
					'status'      => 'active',
				)
			);
			if ( ! $rid ) {
				return new \WP_Error( 'renova_mcp_redirection_failed', 'No se pudo crear la redirección.' );
			}
			return array(
				'success' => true,
				'id'      => (int) $rid,
			);
		}

		// Fallback directo a la tabla.
		global $wpdb;
		$now = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB
		$wpdb->insert(
			self::redirections_table(),
			array(
				'sources'     => maybe_serialize( $sources ),
				'url_to'      => $url_to,
				'header_code' => $header_code,
				'hits'        => 0,
				'status'      => 'active',
				'created'     => $now,
				'updated'     => $now,
			)
		);
		return array(
			'success' => (bool) $wpdb->insert_id,
			'id'      => (int) $wpdb->insert_id,
		);
	}

	/**
	 * Actualiza una redirección.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function update_redirection( $input ) {
		$err = self::require_rankmath();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$id = (int) ( $input['id'] ?? 0 );
		if ( ! $id ) {
			return new \WP_Error( 'renova_mcp_bad_redirection', 'Falta el id.' );
		}
		$fields = array( 'updated' => current_time( 'mysql' ) );
		if ( isset( $input['url_to'] ) ) {
			$fields['url_to'] = esc_url_raw( $input['url_to'] );
		}
		if ( isset( $input['header_code'] ) ) {
			$fields['header_code'] = (string) $input['header_code'];
		}
		if ( isset( $input['status'] ) ) {
			$fields['status'] = 'inactive' === $input['status'] ? 'inactive' : 'active';
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB
		$ok = $wpdb->update( self::redirections_table(), $fields, array( 'id' => $id ) );
		return array(
			'success' => false !== $ok,
			'id'      => $id,
		);
	}

	/**
	 * Elimina una redirección.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function delete_redirection( $input ) {
		$err = self::require_rankmath();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$id = (int) ( $input['id'] ?? 0 );
		if ( ! $id ) {
			return new \WP_Error( 'renova_mcp_bad_redirection', 'Falta el id.' );
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB
		$ok = $wpdb->delete( self::redirections_table(), array( 'id' => $id ) );
		return array(
			'success' => (bool) $ok,
			'id'      => $id,
		);
	}

	/* ===================================================================== */
	/* Monitor 404                                                            */
	/* ===================================================================== */

	/**
	 * Nombre de la tabla del monitor 404.
	 *
	 * @return string
	 */
	private static function log_404_table() {
		global $wpdb;
		return $wpdb->prefix . 'rank_math_404_logs';
	}

	/**
	 * Lee el registro 404.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_404_log( $input ) {
		$err = self::require_rankmath();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		global $wpdb;
		$table = self::log_404_table();
		// phpcs:ignore WordPress.DB
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return new \WP_Error( 'renova_mcp_no_404', 'El monitor 404 de Rank Math no está activo.' );
		}
		$number = isset( $input['number'] ) ? (int) $input['number'] : 100;
		// phpcs:ignore WordPress.DB
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $number ), ARRAY_A );
		return array(
			'count' => count( (array) $rows ),
			'logs'  => $rows ?: array(),
		);
	}

	/**
	 * Vacía el registro 404.
	 *
	 * @return array|\WP_Error
	 */
	public static function clear_404_log() {
		$err = self::require_rankmath();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		global $wpdb;
		$table = self::log_404_table();
		// phpcs:ignore WordPress.DB
		$wpdb->query( "TRUNCATE TABLE {$table}" );
		return array(
			'success' => true,
			'message' => 'Registro 404 vaciado.',
		);
	}
}
