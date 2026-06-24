<?php
/**
 * Registro de "abilities" (herramientas MCP) para control total de WordPress.
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Define y registra todas las capacidades expuestas por el servidor MCP.
 */
class Abilities {

	/**
	 * Slug de la categoría de abilities.
	 */
	const CATEGORY = 'renova-mcp';

	/**
	 * IDs de todas las abilities expuestas como herramientas MCP.
	 *
	 * @return string[]
	 */
	public static function tool_ids() {
		return array(
			'renova-mcp/get-site-info',
			'renova-mcp/list-plugins',
			'renova-mcp/activate-plugin',
			'renova-mcp/deactivate-plugin',
			'renova-mcp/install-plugin',
			'renova-mcp/delete-plugin',
			'renova-mcp/list-themes',
			'renova-mcp/activate-theme',
			'renova-mcp/list-posts',
			'renova-mcp/get-post',
			'renova-mcp/create-post',
			'renova-mcp/update-post',
			'renova-mcp/delete-post',
			'renova-mcp/get-post-meta',
			'renova-mcp/update-post-meta',
			'renova-mcp/delete-post-meta',
			'renova-mcp/get-option',
			'renova-mcp/update-option',
		);
	}

	/**
	 * Registra la categoría de abilities.
	 */
	public static function register_category() {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Renova MCP', 'renova-mcp' ),
				'description' => __( 'Control total del sitio WordPress vía MCP.', 'renova-mcp' ),
			)
		);
	}

	/**
	 * Comprueba que el usuario actual puede administrar el sitio.
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
		$obj = array( 'type' => 'object' );

		// --- Información del sitio -------------------------------------------------
		wp_register_ability(
			'renova-mcp/get-site-info',
			array(
				'label'               => __( 'Información del sitio', 'renova-mcp' ),
				'description'         => __( 'Devuelve datos generales: nombre, URL, versión de WordPress/PHP, tema activo y conteos de contenido.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $obj,
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_site_info' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		// --- Plugins --------------------------------------------------------------
		wp_register_ability(
			'renova-mcp/list-plugins',
			array(
				'label'               => __( 'Listar plugins', 'renova-mcp' ),
				'description'         => __( 'Lista todos los plugins instalados con su estado (activo/inactivo), versión y descripción.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $obj,
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'list_plugins' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		$plugin_input = array(
			'type'       => 'object',
			'properties' => array(
				'plugin' => array(
					'type'        => 'string',
					'description' => __( 'Ruta del plugin (ej. "akismet/akismet.php") o su carpeta (ej. "akismet").', 'renova-mcp' ),
				),
			),
			'required'   => array( 'plugin' ),
		);

		wp_register_ability(
			'renova-mcp/activate-plugin',
			array(
				'label'               => __( 'Activar plugin', 'renova-mcp' ),
				'description'         => __( 'Activa un plugin instalado.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $plugin_input,
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'activate_plugin' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'idempotent' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/deactivate-plugin',
			array(
				'label'               => __( 'Desactivar plugin', 'renova-mcp' ),
				'description'         => __( 'Desactiva un plugin activo.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $plugin_input,
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'deactivate_plugin' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'idempotent' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/install-plugin',
			array(
				'label'               => __( 'Instalar plugin', 'renova-mcp' ),
				'description'         => __( 'Instala un plugin desde el repositorio de WordPress.org por su slug. Opcionalmente lo activa.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'slug'     => array(
							'type'        => 'string',
							'description' => __( 'Slug del plugin en WordPress.org (ej. "wordpress-seo").', 'renova-mcp' ),
						),
						'activate' => array(
							'type'        => 'boolean',
							'description' => __( 'Activar tras instalar.', 'renova-mcp' ),
							'default'     => false,
						),
					),
					'required'   => array( 'slug' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'install_plugin' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		wp_register_ability(
			'renova-mcp/delete-plugin',
			array(
				'label'               => __( 'Eliminar plugin', 'renova-mcp' ),
				'description'         => __( 'Desinstala y elimina por completo un plugin del servidor. Acción destructiva.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $plugin_input,
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'delete_plugin' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'destructive' => true ) ),
			)
		);

		// --- Temas ----------------------------------------------------------------
		wp_register_ability(
			'renova-mcp/list-themes',
			array(
				'label'               => __( 'Listar temas', 'renova-mcp' ),
				'description'         => __( 'Lista los temas instalados e indica cuál está activo.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $obj,
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'list_themes' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/activate-theme',
			array(
				'label'               => __( 'Activar tema', 'renova-mcp' ),
				'description'         => __( 'Cambia el tema activo del sitio.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'stylesheet' => array(
							'type'        => 'string',
							'description' => __( 'Carpeta del tema (stylesheet), ej. "twentytwentyfive".', 'renova-mcp' ),
						),
					),
					'required'   => array( 'stylesheet' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'activate_theme' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		// --- Contenido (entradas y páginas) ---------------------------------------
		wp_register_ability(
			'renova-mcp/list-posts',
			array(
				'label'               => __( 'Listar contenido', 'renova-mcp' ),
				'description'         => __( 'Lista entradas o páginas con filtros de tipo, estado, búsqueda y cantidad.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array(
							'type'        => 'string',
							'description' => __( 'Tipo de contenido (post, page, etc.).', 'renova-mcp' ),
							'default'     => 'post',
						),
						'status'    => array(
							'type'        => 'string',
							'description' => __( 'Estado (publish, draft, pending, private, any).', 'renova-mcp' ),
							'default'     => 'any',
						),
						'search'    => array(
							'type'        => 'string',
							'description' => __( 'Término de búsqueda.', 'renova-mcp' ),
						),
						'number'    => array(
							'type'        => 'integer',
							'description' => __( 'Número máximo de resultados.', 'renova-mcp' ),
							'default'     => 20,
						),
					),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'list_posts' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/get-post',
			array(
				'label'               => __( 'Obtener contenido', 'renova-mcp' ),
				'description'         => __( 'Devuelve una entrada/página/CPT completa por su ID: campos del post, meta y términos de todas sus taxonomías.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => __( 'ID del contenido.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_post' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/create-post',
			array(
				'label'               => __( 'Crear entrada o página', 'renova-mcp' ),
				'description'         => __( 'Crea una nueva entrada o página con título, contenido y estado.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'     => array(
							'type'        => 'string',
							'description' => __( 'Título.', 'renova-mcp' ),
						),
						'content'   => array(
							'type'        => 'string',
							'description' => __( 'Contenido (HTML o bloques).', 'renova-mcp' ),
						),
						'post_type' => array(
							'type'        => 'string',
							'description' => __( 'Tipo: "post" o "page".', 'renova-mcp' ),
							'default'     => 'page',
						),
						'status'    => array(
							'type'        => 'string',
							'description' => __( 'Estado: publish, draft, pending, private.', 'renova-mcp' ),
							'default'     => 'publish',
						),
						'excerpt'   => array(
							'type'        => 'string',
							'description' => __( 'Extracto opcional.', 'renova-mcp' ),
						),
						'slug'      => array(
							'type'        => 'string',
							'description' => __( 'Slug/URL amigable opcional.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'title' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'create_post' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		wp_register_ability(
			'renova-mcp/update-post',
			array(
				'label'               => __( 'Actualizar contenido', 'renova-mcp' ),
				'description'         => __( 'Actualiza una entrada o página existente por su ID.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array(
							'type'        => 'integer',
							'description' => __( 'ID del contenido a actualizar.', 'renova-mcp' ),
						),
						'title'   => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string' ),
						'status'  => array( 'type' => 'string' ),
						'excerpt' => array( 'type' => 'string' ),
						'slug'    => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'update_post' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		wp_register_ability(
			'renova-mcp/delete-post',
			array(
				'label'               => __( 'Eliminar contenido', 'renova-mcp' ),
				'description'         => __( 'Envía a la papelera o elimina definitivamente una entrada o página.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array(
							'type'        => 'integer',
							'description' => __( 'ID del contenido.', 'renova-mcp' ),
						),
						'force' => array(
							'type'        => 'boolean',
							'description' => __( 'Si es true, elimina definitivamente (sin papelera).', 'renova-mcp' ),
							'default'     => false,
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'delete_post' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'destructive' => true ) ),
			)
		);

		// --- Post meta ------------------------------------------------------------
		wp_register_ability(
			'renova-mcp/get-post-meta',
			array(
				'label'               => __( 'Leer meta de contenido', 'renova-mcp' ),
				'description'         => __( 'Devuelve los metadatos de una entrada/CPT. Si se indica "key", devuelve solo esa clave; si no, todas. Envuelve get_post_meta().', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'  => array(
							'type'        => 'integer',
							'description' => __( 'ID del contenido.', 'renova-mcp' ),
						),
						'key' => array(
							'type'        => 'string',
							'description' => __( 'Clave meta concreta (opcional).', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_post_meta' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/update-post-meta',
			array(
				'label'               => __( 'Actualizar meta de contenido', 'renova-mcp' ),
				'description'         => __( 'Crea o actualiza un metadato de una entrada/CPT. Envuelve update_post_meta(). Para campos ACF usa preferiblemente acf-update-field.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array(
							'type'        => 'integer',
							'description' => __( 'ID del contenido.', 'renova-mcp' ),
						),
						'key'   => array(
							'type'        => 'string',
							'description' => __( 'Clave meta.', 'renova-mcp' ),
						),
						'value' => array(
							'description' => __( 'Valor (texto, número, booleano, array u objeto).', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id', 'key', 'value' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'update_post_meta' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		wp_register_ability(
			'renova-mcp/delete-post-meta',
			array(
				'label'               => __( 'Eliminar meta de contenido', 'renova-mcp' ),
				'description'         => __( 'Elimina un metadato de una entrada/CPT. Acción destructiva. Envuelve delete_post_meta().', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'  => array(
							'type'        => 'integer',
							'description' => __( 'ID del contenido.', 'renova-mcp' ),
						),
						'key' => array(
							'type'        => 'string',
							'description' => __( 'Clave meta a eliminar.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id', 'key' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'delete_post_meta' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'destructive' => true ) ),
			)
		);

		// --- Opciones del sitio ---------------------------------------------------
		wp_register_ability(
			'renova-mcp/get-option',
			array(
				'label'               => __( 'Leer opción', 'renova-mcp' ),
				'description'         => __( 'Devuelve el valor de una opción de WordPress por su nombre.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => __( 'Nombre de la opción (ej. "blogname", "blogdescription").', 'renova-mcp' ),
						),
					),
					'required'   => array( 'name' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_option' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/update-option',
			array(
				'label'               => __( 'Actualizar opción', 'renova-mcp' ),
				'description'         => __( 'Crea o actualiza una opción de WordPress. Úsalo con cuidado.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name'  => array(
							'type'        => 'string',
							'description' => __( 'Nombre de la opción.', 'renova-mcp' ),
						),
						'value' => array(
							'description' => __( 'Nuevo valor (texto, número o booleano).', 'renova-mcp' ),
						),
					),
					'required'   => array( 'name', 'value' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'update_option' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);
	}

	/* ===================================================================== */
	/* Implementaciones                                                       */
	/* ===================================================================== */

	/**
	 * Resuelve una ruta de plugin a partir de "dir/archivo.php" o solo "dir".
	 *
	 * @param string $value Entrada del usuario.
	 * @return string|\WP_Error Ruta válida del plugin o error.
	 */
	private static function resolve_plugin( $value ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$value = (string) $value;
		$all   = get_plugins();

		if ( isset( $all[ $value ] ) ) {
			return $value;
		}

		// Coincidencia por carpeta del plugin.
		foreach ( array_keys( $all ) as $file ) {
			if ( $file === $value || strpos( $file, trailingslashit( $value ) ) === 0 || dirname( $file ) === $value ) {
				return $file;
			}
		}

		return new \WP_Error( 'renova_mcp_plugin_not_found', sprintf( 'No se encontró el plugin "%s".', $value ) );
	}

	/**
	 * Información general del sitio.
	 *
	 * @return array
	 */
	public static function get_site_info() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$theme = wp_get_theme();

		return array(
			'name'          => get_bloginfo( 'name' ),
			'description'   => get_bloginfo( 'description' ),
			'url'           => home_url(),
			'admin_url'     => admin_url(),
			'wp_version'    => get_bloginfo( 'version' ),
			'php_version'   => PHP_VERSION,
			'active_theme'  => array(
				'name'       => $theme->get( 'Name' ),
				'stylesheet' => $theme->get_stylesheet(),
				'version'    => $theme->get( 'Version' ),
			),
			'active_plugins' => count( (array) get_option( 'active_plugins', array() ) ),
			'counts'        => array(
				'posts' => (int) wp_count_posts( 'post' )->publish,
				'pages' => (int) wp_count_posts( 'page' )->publish,
				'users' => (int) count_users()['total_users'],
			),
		);
	}

	/**
	 * Lista de plugins.
	 *
	 * @return array
	 */
	public static function list_plugins() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$all    = get_plugins();
		$active = (array) get_option( 'active_plugins', array() );
		$items  = array();

		foreach ( $all as $file => $data ) {
			$items[] = array(
				'plugin'      => $file,
				'name'        => $data['Name'],
				'version'     => $data['Version'],
				'active'      => in_array( $file, $active, true ),
				'description' => wp_strip_all_tags( $data['Description'] ),
			);
		}

		return array(
			'count'   => count( $items ),
			'plugins' => $items,
		);
	}

	/**
	 * Activa un plugin.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function activate_plugin( $input ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugin = self::resolve_plugin( $input['plugin'] ?? '' );
		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		$result = activate_plugin( $plugin );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success' => true,
			'plugin'  => $plugin,
			'message' => 'Plugin activado.',
		);
	}

	/**
	 * Desactiva un plugin.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function deactivate_plugin( $input ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugin = self::resolve_plugin( $input['plugin'] ?? '' );
		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		deactivate_plugins( $plugin );

		return array(
			'success' => true,
			'plugin'  => $plugin,
			'message' => 'Plugin desactivado.',
		);
	}

	/**
	 * Instala un plugin desde WordPress.org.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function install_plugin( $input ) {
		$slug = isset( $input['slug'] ) ? sanitize_key( $input['slug'] ) : '';
		if ( '' === $slug ) {
			return new \WP_Error( 'renova_mcp_missing_slug', 'Falta el slug del plugin.' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array( 'sections' => false ),
			)
		);
		if ( is_wp_error( $api ) ) {
			return $api;
		}

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result || null === $result ) {
			return new \WP_Error( 'renova_mcp_install_failed', 'No se pudo instalar el plugin.', $skin->get_errors() );
		}

		$installed = $upgrader->plugin_info();
		$activated = false;

		if ( ! empty( $input['activate'] ) && $installed ) {
			$activate = activate_plugin( $installed );
			$activated = ! is_wp_error( $activate );
		}

		return array(
			'success'   => true,
			'slug'      => $slug,
			'plugin'    => $installed,
			'activated' => $activated,
			'message'   => 'Plugin instalado.',
		);
	}

	/**
	 * Elimina un plugin del servidor.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function delete_plugin( $input ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$plugin = self::resolve_plugin( $input['plugin'] ?? '' );
		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		if ( is_plugin_active( $plugin ) ) {
			deactivate_plugins( $plugin );
		}

		WP_Filesystem();
		$result = delete_plugins( array( $plugin ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result ) {
			return new \WP_Error( 'renova_mcp_delete_failed', 'No se pudo eliminar el plugin (¿permisos del sistema de archivos?).' );
		}

		return array(
			'success' => true,
			'plugin'  => $plugin,
			'message' => 'Plugin eliminado.',
		);
	}

	/**
	 * Lista de temas.
	 *
	 * @return array
	 */
	public static function list_themes() {
		$active = get_stylesheet();
		$items  = array();

		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$items[] = array(
				'stylesheet' => $stylesheet,
				'name'       => $theme->get( 'Name' ),
				'version'    => $theme->get( 'Version' ),
				'active'     => ( $stylesheet === $active ),
			);
		}

		return array(
			'count'  => count( $items ),
			'themes' => $items,
		);
	}

	/**
	 * Activa un tema.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function activate_theme( $input ) {
		$stylesheet = isset( $input['stylesheet'] ) ? (string) $input['stylesheet'] : '';
		$theme      = wp_get_theme( $stylesheet );

		if ( ! $theme->exists() ) {
			return new \WP_Error( 'renova_mcp_theme_not_found', sprintf( 'No se encontró el tema "%s".', $stylesheet ) );
		}

		switch_theme( $stylesheet );

		return array(
			'success'    => true,
			'stylesheet' => $stylesheet,
			'message'    => 'Tema activado.',
		);
	}

	/**
	 * Lista entradas/páginas.
	 *
	 * @param array $input Entrada.
	 * @return array
	 */
	public static function list_posts( $input ) {
		$query = array(
			'post_type'      => isset( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'post',
			'post_status'    => isset( $input['status'] ) ? sanitize_key( $input['status'] ) : 'any',
			'posts_per_page' => isset( $input['number'] ) ? (int) $input['number'] : 20,
		);
		if ( ! empty( $input['search'] ) ) {
			$query['s'] = (string) $input['search'];
		}

		$items = array();
		foreach ( get_posts( $query ) as $post ) {
			$items[] = array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'status'    => $post->post_status,
				'type'      => $post->post_type,
				'url'       => get_permalink( $post ),
				'modified'  => $post->post_modified_gmt,
			);
		}

		return array(
			'count' => count( $items ),
			'posts' => $items,
		);
	}

	/**
	 * Crea una entrada o página.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function create_post( $input ) {
		$postarr = array(
			'post_title'   => isset( $input['title'] ) ? wp_strip_all_tags( $input['title'] ) : '',
			'post_content' => isset( $input['content'] ) ? (string) $input['content'] : '',
			'post_type'    => isset( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'page',
			'post_status'  => isset( $input['status'] ) ? sanitize_key( $input['status'] ) : 'publish',
		);
		if ( isset( $input['excerpt'] ) ) {
			$postarr['post_excerpt'] = (string) $input['excerpt'];
		}
		if ( ! empty( $input['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( $input['slug'] );
		}

		$id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return array(
			'success' => true,
			'id'      => $id,
			'url'     => get_permalink( $id ),
			'edit'    => get_edit_post_link( $id, 'raw' ),
			'message' => 'Contenido creado.',
		);
	}

	/**
	 * Actualiza una entrada o página.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function update_post( $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		if ( ! $id || ! get_post( $id ) ) {
			return new \WP_Error( 'renova_mcp_post_not_found', 'No se encontró el contenido indicado.' );
		}

		$postarr = array( 'ID' => $id );
		if ( isset( $input['title'] ) ) {
			$postarr['post_title'] = wp_strip_all_tags( $input['title'] );
		}
		if ( isset( $input['content'] ) ) {
			$postarr['post_content'] = (string) $input['content'];
		}
		if ( isset( $input['status'] ) ) {
			$postarr['post_status'] = sanitize_key( $input['status'] );
		}
		if ( isset( $input['excerpt'] ) ) {
			$postarr['post_excerpt'] = (string) $input['excerpt'];
		}
		if ( ! empty( $input['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( $input['slug'] );
		}

		$result = wp_update_post( $postarr, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success' => true,
			'id'      => $id,
			'url'     => get_permalink( $id ),
			'message' => 'Contenido actualizado.',
		);
	}

	/**
	 * Elimina una entrada o página.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function delete_post( $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		if ( ! $id || ! get_post( $id ) ) {
			return new \WP_Error( 'renova_mcp_post_not_found', 'No se encontró el contenido indicado.' );
		}

		$force  = ! empty( $input['force'] );
		$result = wp_delete_post( $id, $force );
		if ( ! $result ) {
			return new \WP_Error( 'renova_mcp_delete_failed', 'No se pudo eliminar el contenido.' );
		}

		return array(
			'success'   => true,
			'id'        => $id,
			'permanent' => $force,
			'message'   => $force ? 'Contenido eliminado definitivamente.' : 'Contenido enviado a la papelera.',
		);
	}

	/**
	 * Devuelve un contenido completo: campos, meta y términos.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_post( $input ) {
		$id   = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$post = $id ? get_post( $id ) : null;
		if ( ! $post ) {
			return new \WP_Error( 'renova_mcp_post_not_found', 'No se encontró el contenido indicado.' );
		}

		$terms = array();
		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			$assigned = wp_get_object_terms( $id, $taxonomy );
			if ( is_wp_error( $assigned ) || empty( $assigned ) ) {
				continue;
			}
			$terms[ $taxonomy ] = array_map(
				static function ( $t ) {
					return array(
						'term_id' => (int) $t->term_id,
						'name'    => $t->name,
						'slug'    => $t->slug,
					);
				},
				$assigned
			);
		}

		return array(
			'id'        => $post->ID,
			'title'     => $post->post_title,
			'content'   => $post->post_content,
			'excerpt'   => $post->post_excerpt,
			'status'    => $post->post_status,
			'type'      => $post->post_type,
			'slug'      => $post->post_name,
			'parent'    => (int) $post->post_parent,
			'author'    => (int) $post->post_author,
			'url'       => get_permalink( $post ),
			'edit'      => get_edit_post_link( $post->ID, 'raw' ),
			'modified'  => $post->post_modified_gmt,
			'meta'      => get_post_meta( $id ),
			'terms'     => $terms,
		);
	}

	/**
	 * Lee metadatos de un contenido.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_post_meta( $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		if ( ! $id || ! get_post( $id ) ) {
			return new \WP_Error( 'renova_mcp_post_not_found', 'No se encontró el contenido indicado.' );
		}
		$key = isset( $input['key'] ) ? (string) $input['key'] : '';
		if ( '' !== $key ) {
			return array(
				'id'    => $id,
				'key'   => $key,
				'value' => get_post_meta( $id, $key, true ),
			);
		}
		return array(
			'id'   => $id,
			'meta' => get_post_meta( $id ),
		);
	}

	/**
	 * Crea o actualiza un metadato.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function update_post_meta( $input ) {
		$id  = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$key = isset( $input['key'] ) ? (string) $input['key'] : '';
		if ( ! $id || ! get_post( $id ) ) {
			return new \WP_Error( 'renova_mcp_post_not_found', 'No se encontró el contenido indicado.' );
		}
		if ( '' === $key ) {
			return new \WP_Error( 'renova_mcp_missing_meta_key', 'Falta la clave meta.' );
		}
		update_post_meta( $id, $key, $input['value'] );
		return array(
			'success' => true,
			'id'      => $id,
			'key'     => $key,
			'value'   => get_post_meta( $id, $key, true ),
		);
	}

	/**
	 * Elimina un metadato.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function delete_post_meta( $input ) {
		$id  = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$key = isset( $input['key'] ) ? (string) $input['key'] : '';
		if ( ! $id || ! get_post( $id ) ) {
			return new \WP_Error( 'renova_mcp_post_not_found', 'No se encontró el contenido indicado.' );
		}
		if ( '' === $key ) {
			return new \WP_Error( 'renova_mcp_missing_meta_key', 'Falta la clave meta.' );
		}
		$ok = delete_post_meta( $id, $key );
		return array(
			'success' => (bool) $ok,
			'id'      => $id,
			'key'     => $key,
		);
	}

	/**
	 * Lee una opción.
	 *
	 * @param array $input Entrada.
	 * @return array
	 */
	public static function get_option( $input ) {
		$name = isset( $input['name'] ) ? (string) $input['name'] : '';
		return array(
			'name'  => $name,
			'value' => get_option( $name ),
		);
	}

	/**
	 * Crea o actualiza una opción.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function update_option( $input ) {
		$name = isset( $input['name'] ) ? (string) $input['name'] : '';
		if ( '' === $name ) {
			return new \WP_Error( 'renova_mcp_missing_option', 'Falta el nombre de la opción.' );
		}

		update_option( $name, $input['value'] );

		return array(
			'success' => true,
			'name'    => $name,
			'value'   => get_option( $name ),
			'message' => 'Opción actualizada.',
		);
	}
}
