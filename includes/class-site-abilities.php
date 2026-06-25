<?php
/**
 * Abilities MCP para menús, comentarios y utilidades de administración de WordPress.
 *
 * Cierra los huecos de control del núcleo que faltaban: menús de navegación,
 * moderación de comentarios, estructura de enlaces permanentes (permalinks)
 * y flush de reglas de reescritura, theme mods, transients, comprobación y
 * ejecución de actualizaciones, y limpieza de caché de objetos.
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Registra las abilities de menús, comentarios y utilidades de sitio.
 */
class Site_Abilities {

	const CATEGORY = 'renova-mcp';

	/**
	 * IDs de todas las abilities expuestas como herramientas MCP.
	 *
	 * @return string[]
	 */
	public static function tool_ids() {
		return array(
			// Menús.
			'renova-mcp/list-menus',
			'renova-mcp/get-menu',
			'renova-mcp/create-menu',
			'renova-mcp/add-menu-item',
			'renova-mcp/delete-menu-item',
			'renova-mcp/assign-menu-location',
			// Comentarios.
			'renova-mcp/list-comments',
			'renova-mcp/moderate-comment',
			'renova-mcp/delete-comment',
			// Utilidades de sitio.
			'renova-mcp/get-permalink-structure',
			'renova-mcp/update-permalink-structure',
			'renova-mcp/flush-rewrite-rules',
			'renova-mcp/get-theme-mod',
			'renova-mcp/update-theme-mod',
			'renova-mcp/manage-transient',
			'renova-mcp/check-updates',
			'renova-mcp/run-update',
			'renova-mcp/flush-cache',
		);
	}

	/**
	 * Registra todas las abilities.
	 */
	public static function register() {
		$obj  = array( 'type' => 'object' );
		$perm = array( Abilities::class, 'can_manage' );

		/* ----- Menús -------------------------------------------------------- */
		wp_register_ability(
			'renova-mcp/list-menus',
			array(
				'label'               => __( 'Listar menús', 'renova-mcp' ),
				'description'         => __( 'Lista los menús de navegación con su número de elementos y ubicaciones asignadas.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $obj,
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'list_menus' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/get-menu',
			array(
				'label'               => __( 'Obtener menú', 'renova-mcp' ),
				'description'         => __( 'Devuelve los elementos de un menú por su ID, nombre o slug.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'menu' => array(
							'type'        => 'string',
							'description' => __( 'ID, nombre o slug del menú.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'menu' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_menu' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/create-menu',
			array(
				'label'               => __( 'Crear menú', 'renova-mcp' ),
				'description'         => __( 'Crea un menú de navegación vacío con el nombre indicado.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => __( 'Nombre del menú.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'name' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'create_menu' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/add-menu-item',
			array(
				'label'               => __( 'Añadir elemento al menú', 'renova-mcp' ),
				'description'         => __( 'Añade un elemento a un menú: enlace a una entrada/página (object_id), una URL personalizada, o un término de taxonomía.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'menu'      => array(
							'type'        => 'string',
							'description' => __( 'ID, nombre o slug del menú.', 'renova-mcp' ),
						),
						'title'     => array(
							'type'        => 'string',
							'description' => __( 'Texto del enlace.', 'renova-mcp' ),
						),
						'url'       => array(
							'type'        => 'string',
							'description' => __( 'URL personalizada (para enlaces personalizados).', 'renova-mcp' ),
						),
						'object_id' => array(
							'type'        => 'integer',
							'description' => __( 'ID de la entrada/página a enlazar.', 'renova-mcp' ),
						),
						'object'    => array(
							'type'        => 'string',
							'description' => __( 'Tipo de objeto enlazado (post, page, category, etc.). Por defecto "page" si hay object_id.', 'renova-mcp' ),
						),
						'parent'    => array(
							'type'        => 'integer',
							'description' => __( 'ID del elemento de menú padre (para submenús).', 'renova-mcp' ),
						),
					),
					'required'   => array( 'menu', 'title' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'add_menu_item' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/delete-menu-item',
			array(
				'label'               => __( 'Eliminar elemento del menú', 'renova-mcp' ),
				'description'         => __( 'Elimina un elemento de un menú por su ID.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'item_id' => array(
							'type'        => 'integer',
							'description' => __( 'ID del elemento del menú.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'item_id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'delete_menu_item' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'destructive' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/assign-menu-location',
			array(
				'label'               => __( 'Asignar menú a ubicación', 'renova-mcp' ),
				'description'         => __( 'Asigna un menú a una ubicación registrada por el tema (ej. "primary", "footer").', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'menu'     => array(
							'type'        => 'string',
							'description' => __( 'ID, nombre o slug del menú.', 'renova-mcp' ),
						),
						'location' => array(
							'type'        => 'string',
							'description' => __( 'Slug de la ubicación del tema.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'menu', 'location' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'assign_menu_location' ),
				'permission_callback' => $perm,
			)
		);

		/* ----- Comentarios -------------------------------------------------- */
		wp_register_ability(
			'renova-mcp/list-comments',
			array(
				'label'               => __( 'Listar comentarios', 'renova-mcp' ),
				'description'         => __( 'Lista comentarios con filtros de estado (approve, hold, spam, trash) y entrada.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'status'  => array(
							'type'        => 'string',
							'description' => __( 'approve, hold, spam, trash o all.', 'renova-mcp' ),
							'default'     => 'all',
						),
						'post_id' => array(
							'type'        => 'integer',
							'description' => __( 'Filtra por entrada.', 'renova-mcp' ),
						),
						'number'  => array(
							'type'        => 'integer',
							'description' => __( 'Máximo de resultados.', 'renova-mcp' ),
							'default'     => 50,
						),
					),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'list_comments' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/moderate-comment',
			array(
				'label'               => __( 'Moderar comentario', 'renova-mcp' ),
				'description'         => __( 'Cambia el estado de un comentario: approve, hold, spam o trash.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'     => array(
							'type'        => 'integer',
							'description' => __( 'ID del comentario.', 'renova-mcp' ),
						),
						'status' => array(
							'type'        => 'string',
							'description' => __( 'approve, hold, spam o trash.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id', 'status' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'moderate_comment' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/delete-comment',
			array(
				'label'               => __( 'Eliminar comentario', 'renova-mcp' ),
				'description'         => __( 'Envía a la papelera o elimina definitivamente un comentario.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array(
							'type'        => 'integer',
							'description' => __( 'ID del comentario.', 'renova-mcp' ),
						),
						'force' => array(
							'type'        => 'boolean',
							'description' => __( 'Eliminar definitivamente.', 'renova-mcp' ),
							'default'     => false,
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'delete_comment' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'destructive' => true ) ),
			)
		);

		/* ----- Permalinks / rewrite ---------------------------------------- */
		wp_register_ability(
			'renova-mcp/get-permalink-structure',
			array(
				'label'               => __( 'Leer estructura de enlaces', 'renova-mcp' ),
				'description'         => __( 'Devuelve la estructura de enlaces permanentes (permalinks) actual.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $obj,
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_permalink_structure' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/update-permalink-structure',
			array(
				'label'               => __( 'Actualizar estructura de enlaces', 'renova-mcp' ),
				'description'         => __( 'Cambia la estructura de permalinks y refresca las reglas de reescritura. Ej.: "/%postname%/" activa los enlaces bonitos y hace que /wp-json/ funcione.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'structure' => array(
							'type'        => 'string',
							'description' => __( 'Estructura, ej. "/%postname%/". Cadena vacía = enlaces simples (query string).', 'renova-mcp' ),
						),
					),
					'required'   => array( 'structure' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'update_permalink_structure' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/flush-rewrite-rules',
			array(
				'label'               => __( 'Refrescar reglas de reescritura', 'renova-mcp' ),
				'description'         => __( 'Regenera las reglas de reescritura (equivalente a guardar los Enlaces permanentes). Útil tras registrar CPT o cambiar permalinks.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $obj,
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'flush_rewrite_rules' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'idempotent' => true ) ),
			)
		);

		/* ----- Theme mods --------------------------------------------------- */
		wp_register_ability(
			'renova-mcp/get-theme-mod',
			array(
				'label'               => __( 'Leer theme mod', 'renova-mcp' ),
				'description'         => __( 'Devuelve un ajuste del personalizador (theme mod) del tema activo, o todos si no se indica clave.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'key' => array(
							'type'        => 'string',
							'description' => __( 'Clave del theme mod (opcional).', 'renova-mcp' ),
						),
					),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_theme_mod' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/update-theme-mod',
			array(
				'label'               => __( 'Actualizar theme mod', 'renova-mcp' ),
				'description'         => __( 'Crea o actualiza un ajuste del personalizador (theme mod) del tema activo.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'key'   => array(
							'type'        => 'string',
							'description' => __( 'Clave del theme mod.', 'renova-mcp' ),
						),
						'value' => array(
							'description' => __( 'Valor.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'key', 'value' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'update_theme_mod' ),
				'permission_callback' => $perm,
			)
		);

		/* ----- Transients --------------------------------------------------- */
		wp_register_ability(
			'renova-mcp/manage-transient',
			array(
				'label'               => __( 'Gestionar transient', 'renova-mcp' ),
				'description'         => __( 'Lee, fija o elimina un transient (caché temporal). action: get, set o delete.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'action'     => array(
							'type'        => 'string',
							'description' => __( 'get, set o delete.', 'renova-mcp' ),
						),
						'key'        => array(
							'type'        => 'string',
							'description' => __( 'Nombre del transient.', 'renova-mcp' ),
						),
						'value'      => array(
							'description' => __( 'Valor (para set).', 'renova-mcp' ),
						),
						'expiration' => array(
							'type'        => 'integer',
							'description' => __( 'Caducidad en segundos (para set). 0 = sin caducidad.', 'renova-mcp' ),
							'default'     => 0,
						),
					),
					'required'   => array( 'action', 'key' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'manage_transient' ),
				'permission_callback' => $perm,
			)
		);

		/* ----- Actualizaciones / caché ------------------------------------- */
		wp_register_ability(
			'renova-mcp/check-updates',
			array(
				'label'               => __( 'Comprobar actualizaciones', 'renova-mcp' ),
				'description'         => __( 'Comprueba actualizaciones disponibles del núcleo, plugins y temas.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $obj,
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'check_updates' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/run-update',
			array(
				'label'               => __( 'Ejecutar actualización', 'renova-mcp' ),
				'description'         => __( 'Actualiza un plugin o un tema a su última versión. type: plugin o theme; target: ruta del plugin o stylesheet del tema.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type'   => array(
							'type'        => 'string',
							'description' => __( 'plugin o theme.', 'renova-mcp' ),
						),
						'target' => array(
							'type'        => 'string',
							'description' => __( 'Ruta del plugin (ej. "akismet/akismet.php") o stylesheet del tema.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'type', 'target' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'run_update' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/flush-cache',
			array(
				'label'               => __( 'Limpiar caché de objetos', 'renova-mcp' ),
				'description'         => __( 'Vacía la caché de objetos de WordPress (wp_cache_flush).', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $obj,
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'flush_cache' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'idempotent' => true ) ),
			)
		);
	}

	/* ===================================================================== */
	/* Menús                                                                  */
	/* ===================================================================== */

	/**
	 * Resuelve un menú a su objeto.
	 *
	 * @param mixed $value ID, nombre o slug.
	 * @return \WP_Term|false
	 */
	private static function resolve_menu( $value ) {
		return wp_get_nav_menu_object( is_numeric( $value ) ? (int) $value : (string) $value );
	}

	/**
	 * Lista menús.
	 *
	 * @return array
	 */
	public static function list_menus() {
		$locations = get_nav_menu_locations();
		$by_menu   = array();
		foreach ( $locations as $loc => $menu_id ) {
			$by_menu[ $menu_id ][] = $loc;
		}
		$items = array();
		foreach ( wp_get_nav_menus() as $menu ) {
			$items[] = array(
				'id'        => $menu->term_id,
				'name'      => $menu->name,
				'slug'      => $menu->slug,
				'count'     => $menu->count,
				'locations' => $by_menu[ $menu->term_id ] ?? array(),
			);
		}
		return array(
			'count' => count( $items ),
			'menus' => $items,
		);
	}

	/**
	 * Obtiene un menú y sus elementos.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_menu( $input ) {
		$menu = self::resolve_menu( $input['menu'] ?? '' );
		if ( ! $menu ) {
			return new \WP_Error( 'renova_mcp_menu_not_found', 'No se encontró el menú indicado.' );
		}
		$items = array();
		foreach ( wp_get_nav_menu_items( $menu->term_id ) ?: array() as $item ) {
			$items[] = array(
				'id'        => $item->ID,
				'title'     => $item->title,
				'url'       => $item->url,
				'parent'    => (int) $item->menu_item_parent,
				'order'     => (int) $item->menu_order,
				'type'      => $item->type,
				'object'    => $item->object,
				'object_id' => (int) $item->object_id,
			);
		}
		return array(
			'id'    => $menu->term_id,
			'name'  => $menu->name,
			'items' => $items,
		);
	}

	/**
	 * Crea un menú.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function create_menu( $input ) {
		$name = isset( $input['name'] ) ? (string) $input['name'] : '';
		if ( '' === $name ) {
			return new \WP_Error( 'renova_mcp_missing_name', 'Falta el nombre del menú.' );
		}
		$id = wp_create_nav_menu( $name );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return array(
			'success' => true,
			'id'      => $id,
			'message' => 'Menú creado.',
		);
	}

	/**
	 * Añade un elemento a un menú.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function add_menu_item( $input ) {
		$menu = self::resolve_menu( $input['menu'] ?? '' );
		if ( ! $menu ) {
			return new \WP_Error( 'renova_mcp_menu_not_found', 'No se encontró el menú indicado.' );
		}
		$args = array(
			'menu-item-title'  => isset( $input['title'] ) ? (string) $input['title'] : '',
			'menu-item-status' => 'publish',
		);
		if ( ! empty( $input['object_id'] ) ) {
			$object               = isset( $input['object'] ) ? sanitize_key( $input['object'] ) : 'page';
			$args['menu-item-type']      = in_array( $object, array( 'category', 'post_tag' ), true ) ? 'taxonomy' : 'post_type';
			$args['menu-item-object']    = $object;
			$args['menu-item-object-id'] = (int) $input['object_id'];
		} else {
			$args['menu-item-type'] = 'custom';
			$args['menu-item-url']  = isset( $input['url'] ) ? esc_url_raw( $input['url'] ) : '';
		}
		if ( ! empty( $input['parent'] ) ) {
			$args['menu-item-parent-id'] = (int) $input['parent'];
		}
		$item_id = wp_update_nav_menu_item( $menu->term_id, 0, $args );
		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}
		return array(
			'success' => true,
			'item_id' => $item_id,
			'menu_id' => $menu->term_id,
		);
	}

	/**
	 * Elimina un elemento de un menú.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function delete_menu_item( $input ) {
		$item_id = isset( $input['item_id'] ) ? (int) $input['item_id'] : 0;
		if ( ! $item_id ) {
			return new \WP_Error( 'renova_mcp_missing_item', 'Falta el item_id.' );
		}
		$ok = is_nav_menu_item( $item_id ) ? wp_delete_post( $item_id, true ) : false;
		return array(
			'success' => (bool) $ok,
			'item_id' => $item_id,
		);
	}

	/**
	 * Asigna un menú a una ubicación del tema.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function assign_menu_location( $input ) {
		$menu = self::resolve_menu( $input['menu'] ?? '' );
		if ( ! $menu ) {
			return new \WP_Error( 'renova_mcp_menu_not_found', 'No se encontró el menú indicado.' );
		}
		$location  = isset( $input['location'] ) ? sanitize_key( $input['location'] ) : '';
		$locations = get_theme_mod( 'nav_menu_locations', array() );
		$locations = is_array( $locations ) ? $locations : array();
		$locations[ $location ] = $menu->term_id;
		set_theme_mod( 'nav_menu_locations', $locations );
		return array(
			'success'  => true,
			'menu_id'  => $menu->term_id,
			'location' => $location,
		);
	}

	/* ===================================================================== */
	/* Comentarios                                                            */
	/* ===================================================================== */

	/**
	 * Lista comentarios.
	 *
	 * @param array $input Entrada.
	 * @return array
	 */
	public static function list_comments( $input ) {
		$args = array(
			'status' => isset( $input['status'] ) ? sanitize_key( $input['status'] ) : 'all',
			'number' => isset( $input['number'] ) ? (int) $input['number'] : 50,
		);
		if ( ! empty( $input['post_id'] ) ) {
			$args['post_id'] = (int) $input['post_id'];
		}
		$items = array();
		foreach ( get_comments( $args ) as $c ) {
			$items[] = array(
				'id'        => (int) $c->comment_ID,
				'post_id'   => (int) $c->comment_post_ID,
				'author'    => $c->comment_author,
				'email'     => $c->comment_author_email,
				'content'   => wp_trim_words( $c->comment_content, 30, '…' ),
				'status'    => wp_get_comment_status( $c->comment_ID ),
				'date'      => $c->comment_date_gmt,
			);
		}
		return array(
			'count'    => count( $items ),
			'comments' => $items,
		);
	}

	/**
	 * Modera un comentario.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function moderate_comment( $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		if ( ! $id || ! get_comment( $id ) ) {
			return new \WP_Error( 'renova_mcp_comment_not_found', 'No se encontró el comentario indicado.' );
		}
		$status = isset( $input['status'] ) ? sanitize_key( $input['status'] ) : '';
		$map    = array(
			'approve' => 'approve',
			'hold'    => 'hold',
			'spam'    => 'spam',
			'trash'   => 'trash',
		);
		if ( ! isset( $map[ $status ] ) ) {
			return new \WP_Error( 'renova_mcp_bad_status', 'Estado no válido. Usa: approve, hold, spam o trash.' );
		}
		$ok = wp_set_comment_status( $id, $map[ $status ] );
		return array(
			'success' => (bool) $ok,
			'id'      => $id,
			'status'  => $status,
		);
	}

	/**
	 * Elimina un comentario.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function delete_comment( $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		if ( ! $id || ! get_comment( $id ) ) {
			return new \WP_Error( 'renova_mcp_comment_not_found', 'No se encontró el comentario indicado.' );
		}
		$ok = wp_delete_comment( $id, ! empty( $input['force'] ) );
		return array(
			'success' => (bool) $ok,
			'id'      => $id,
		);
	}

	/* ===================================================================== */
	/* Permalinks / rewrite                                                   */
	/* ===================================================================== */

	/**
	 * Lee la estructura de permalinks.
	 *
	 * @return array
	 */
	public static function get_permalink_structure() {
		return array(
			'structure'    => get_option( 'permalink_structure' ),
			'is_pretty'    => '' !== (string) get_option( 'permalink_structure' ),
			'category_base' => get_option( 'category_base' ),
			'tag_base'     => get_option( 'tag_base' ),
		);
	}

	/**
	 * Actualiza la estructura de permalinks y refresca las reglas.
	 *
	 * @param array $input Entrada.
	 * @return array
	 */
	public static function update_permalink_structure( $input ) {
		global $wp_rewrite;
		$structure = isset( $input['structure'] ) ? (string) $input['structure'] : '';
		$wp_rewrite->set_permalink_structure( $structure );
		update_option( 'permalink_structure', $structure );
		$wp_rewrite->flush_rules( true );
		return array(
			'success'   => true,
			'structure' => $structure,
			'message'   => 'Estructura de permalinks actualizada y reglas refrescadas.',
		);
	}

	/**
	 * Refresca las reglas de reescritura.
	 *
	 * @return array
	 */
	public static function flush_rewrite_rules() {
		flush_rewrite_rules( true );
		return array(
			'success' => true,
			'message' => 'Reglas de reescritura refrescadas.',
		);
	}

	/* ===================================================================== */
	/* Theme mods / transients                                                */
	/* ===================================================================== */

	/**
	 * Lee theme mods.
	 *
	 * @param array $input Entrada.
	 * @return array
	 */
	public static function get_theme_mod( $input ) {
		$key = isset( $input['key'] ) ? (string) $input['key'] : '';
		if ( '' !== $key ) {
			return array(
				'key'   => $key,
				'value' => get_theme_mod( $key ),
			);
		}
		return array(
			'theme' => get_stylesheet(),
			'mods'  => get_theme_mods() ?: array(),
		);
	}

	/**
	 * Actualiza un theme mod.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function update_theme_mod( $input ) {
		$key = isset( $input['key'] ) ? (string) $input['key'] : '';
		if ( '' === $key ) {
			return new \WP_Error( 'renova_mcp_missing_key', 'Falta la clave del theme mod.' );
		}
		set_theme_mod( $key, $input['value'] );
		return array(
			'success' => true,
			'key'     => $key,
			'value'   => get_theme_mod( $key ),
		);
	}

	/**
	 * Gestiona un transient.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function manage_transient( $input ) {
		$action = isset( $input['action'] ) ? sanitize_key( $input['action'] ) : '';
		$key    = isset( $input['key'] ) ? (string) $input['key'] : '';
		if ( '' === $key ) {
			return new \WP_Error( 'renova_mcp_missing_key', 'Falta la clave del transient.' );
		}
		switch ( $action ) {
			case 'get':
				return array(
					'key'   => $key,
					'value' => get_transient( $key ),
				);
			case 'set':
				$expiration = isset( $input['expiration'] ) ? (int) $input['expiration'] : 0;
				set_transient( $key, $input['value'] ?? '', $expiration );
				return array(
					'success' => true,
					'key'     => $key,
				);
			case 'delete':
				return array(
					'success' => (bool) delete_transient( $key ),
					'key'     => $key,
				);
			default:
				return new \WP_Error( 'renova_mcp_bad_action', 'action debe ser get, set o delete.' );
		}
	}

	/* ===================================================================== */
	/* Actualizaciones / caché                                                */
	/* ===================================================================== */

	/**
	 * Comprueba actualizaciones disponibles.
	 *
	 * @return array
	 */
	public static function check_updates() {
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		wp_update_plugins();
		wp_update_themes();
		wp_version_check();

		$plugin_updates = get_plugin_updates();
		$plugins        = array();
		foreach ( (array) $plugin_updates as $file => $data ) {
			$plugins[] = array(
				'plugin'      => $file,
				'name'        => $data->Name, // phpcs:ignore WordPress.NamingConventions.ValidVariableName
				'current'     => $data->Version, // phpcs:ignore WordPress.NamingConventions.ValidVariableName
				'new_version' => $data->update->new_version ?? null,
			);
		}

		$theme_updates = get_theme_updates();
		$themes        = array();
		foreach ( (array) $theme_updates as $stylesheet => $theme ) {
			$themes[] = array(
				'stylesheet'  => $stylesheet,
				'name'        => $theme->get( 'Name' ),
				'current'     => $theme->get( 'Version' ),
				'new_version' => $theme->update['new_version'] ?? null,
			);
		}

		$core    = get_core_updates();
		$core_up = ( ! empty( $core ) && isset( $core[0]->response ) && 'upgrade' === $core[0]->response )
			? $core[0]->current : null;

		return array(
			'core'    => array(
				'current'   => get_bloginfo( 'version' ),
				'available' => $core_up,
			),
			'plugins' => $plugins,
			'themes'  => $themes,
		);
	}

	/**
	 * Ejecuta una actualización de plugin o tema.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function run_update( $input ) {
		$type   = isset( $input['type'] ) ? sanitize_key( $input['type'] ) : '';
		$target = isset( $input['target'] ) ? (string) $input['target'] : '';
		if ( '' === $target ) {
			return new \WP_Error( 'renova_mcp_missing_target', 'Falta el target.' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$skin = new \Automatic_Upgrader_Skin();

		if ( 'plugin' === $type ) {
			wp_update_plugins();
			$upgrader = new \Plugin_Upgrader( $skin );
			$result   = $upgrader->upgrade( $target );
		} elseif ( 'theme' === $type ) {
			wp_update_themes();
			$upgrader = new \Theme_Upgrader( $skin );
			$result   = $upgrader->upgrade( $target );
		} else {
			return new \WP_Error( 'renova_mcp_bad_type', 'type debe ser plugin o theme.' );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result || null === $result ) {
			return new \WP_Error( 'renova_mcp_update_failed', 'No se pudo completar la actualización.', $skin->get_errors() );
		}
		return array(
			'success' => true,
			'type'    => $type,
			'target'  => $target,
			'message' => 'Actualización completada.',
		);
	}

	/**
	 * Vacía la caché de objetos.
	 *
	 * @return array
	 */
	public static function flush_cache() {
		wp_cache_flush();
		return array(
			'success' => true,
			'message' => 'Caché de objetos vaciada.',
		);
	}
}
