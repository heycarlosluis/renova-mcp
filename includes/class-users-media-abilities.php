<?php
/**
 * Abilities MCP para usuarios y biblioteca de medios de WordPress.
 *
 * Cubre dos áreas del núcleo que no estaban expuestas: gestión de usuarios
 * (roles incluidos) y la biblioteca de medios (subir desde URL, listar,
 * eliminar y asignar imagen destacada).
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Registra las abilities de usuarios y medios.
 */
class Users_Media_Abilities {

	const CATEGORY = 'renova-mcp';

	/**
	 * IDs de todas las abilities expuestas como herramientas MCP.
	 *
	 * @return string[]
	 */
	public static function tool_ids() {
		return array(
			// Usuarios.
			'renova-mcp/list-users',
			'renova-mcp/get-user',
			'renova-mcp/create-user',
			'renova-mcp/update-user',
			'renova-mcp/delete-user',
			// Medios.
			'renova-mcp/list-media',
			'renova-mcp/get-media',
			'renova-mcp/upload-media',
			'renova-mcp/delete-media',
			'renova-mcp/set-featured-image',
		);
	}

	/**
	 * Registra todas las abilities.
	 */
	public static function register() {
		$obj  = array( 'type' => 'object' );
		$perm = array( Abilities::class, 'can_manage' );

		/* ----- Usuarios ----------------------------------------------------- */
		wp_register_ability(
			'renova-mcp/list-users',
			array(
				'label'               => __( 'Listar usuarios', 'renova-mcp' ),
				'description'         => __( 'Lista usuarios con su rol, email y login. Permite filtrar por rol y buscar.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'role'   => array(
							'type'        => 'string',
							'description' => __( 'Filtra por rol (administrator, editor, author, etc.).', 'renova-mcp' ),
						),
						'search' => array(
							'type'        => 'string',
							'description' => __( 'Término de búsqueda (login, email, nombre).', 'renova-mcp' ),
						),
						'number' => array(
							'type'        => 'integer',
							'description' => __( 'Máximo de resultados.', 'renova-mcp' ),
							'default'     => 50,
						),
					),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'list_users' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/get-user',
			array(
				'label'               => __( 'Obtener usuario', 'renova-mcp' ),
				'description'         => __( 'Devuelve los datos de un usuario por su ID, login o email.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array(
							'type'        => 'integer',
							'description' => __( 'ID del usuario.', 'renova-mcp' ),
						),
						'login' => array(
							'type'        => 'string',
							'description' => __( 'Login del usuario (alternativa a id).', 'renova-mcp' ),
						),
						'email' => array(
							'type'        => 'string',
							'description' => __( 'Email del usuario (alternativa a id).', 'renova-mcp' ),
						),
					),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_user' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/create-user',
			array(
				'label'               => __( 'Crear usuario', 'renova-mcp' ),
				'description'         => __( 'Crea un usuario con login, email, contraseña y rol.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'username'     => array( 'type' => 'string' ),
						'email'        => array( 'type' => 'string' ),
						'password'     => array(
							'type'        => 'string',
							'description' => __( 'Contraseña. Si se omite, se genera una segura.', 'renova-mcp' ),
						),
						'role'         => array(
							'type'        => 'string',
							'description' => __( 'Rol (subscriber por defecto).', 'renova-mcp' ),
							'default'     => 'subscriber',
						),
						'display_name' => array( 'type' => 'string' ),
						'first_name'   => array( 'type' => 'string' ),
						'last_name'    => array( 'type' => 'string' ),
					),
					'required'   => array( 'username', 'email' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'create_user' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/update-user',
			array(
				'label'               => __( 'Actualizar usuario', 'renova-mcp' ),
				'description'         => __( 'Actualiza datos de un usuario: email, rol, contraseña, nombre. Acepta cualquier combinación.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'           => array(
							'type'        => 'integer',
							'description' => __( 'ID del usuario.', 'renova-mcp' ),
						),
						'email'        => array( 'type' => 'string' ),
						'password'     => array( 'type' => 'string' ),
						'role'         => array( 'type' => 'string' ),
						'display_name' => array( 'type' => 'string' ),
						'first_name'   => array( 'type' => 'string' ),
						'last_name'    => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'update_user' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/delete-user',
			array(
				'label'               => __( 'Eliminar usuario', 'renova-mcp' ),
				'description'         => __( 'Elimina un usuario. Opcionalmente reasigna su contenido a otro usuario. Acción destructiva.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'         => array(
							'type'        => 'integer',
							'description' => __( 'ID del usuario a eliminar.', 'renova-mcp' ),
						),
						'reassign'   => array(
							'type'        => 'integer',
							'description' => __( 'ID del usuario al que reasignar el contenido (opcional).', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'delete_user' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'destructive' => true ) ),
			)
		);

		/* ----- Medios ------------------------------------------------------- */
		wp_register_ability(
			'renova-mcp/list-media',
			array(
				'label'               => __( 'Listar medios', 'renova-mcp' ),
				'description'         => __( 'Lista archivos de la biblioteca de medios con su URL, tipo MIME y título.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search'    => array( 'type' => 'string' ),
						'mime_type' => array(
							'type'        => 'string',
							'description' => __( 'Filtra por tipo MIME (ej. "image", "image/png", "application/pdf").', 'renova-mcp' ),
						),
						'number'    => array(
							'type'        => 'integer',
							'description' => __( 'Máximo de resultados.', 'renova-mcp' ),
							'default'     => 30,
						),
					),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'list_media' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/get-media',
			array(
				'label'               => __( 'Obtener medio', 'renova-mcp' ),
				'description'         => __( 'Devuelve los datos de un adjunto: URL, tipo, metadatos, tamaños y texto alternativo.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => __( 'ID del adjunto.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_media' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/upload-media',
			array(
				'label'               => __( 'Subir medio desde URL', 'renova-mcp' ),
				'description'         => __( 'Descarga un archivo desde una URL y lo añade a la biblioteca de medios. Opcionalmente lo adjunta a una entrada y fija título/alt.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'url'       => array(
							'type'        => 'string',
							'description' => __( 'URL del archivo a importar.', 'renova-mcp' ),
						),
						'post_id'   => array(
							'type'        => 'integer',
							'description' => __( 'ID de la entrada a la que adjuntar (opcional).', 'renova-mcp' ),
						),
						'title'     => array( 'type' => 'string' ),
						'alt'       => array(
							'type'        => 'string',
							'description' => __( 'Texto alternativo de la imagen.', 'renova-mcp' ),
						),
						'featured'  => array(
							'type'        => 'boolean',
							'description' => __( 'Si es true y hay post_id, lo fija como imagen destacada.', 'renova-mcp' ),
							'default'     => false,
						),
					),
					'required'   => array( 'url' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'upload_media' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/delete-media',
			array(
				'label'               => __( 'Eliminar medio', 'renova-mcp' ),
				'description'         => __( 'Elimina un adjunto de la biblioteca de medios (y su archivo). Acción destructiva.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array(
							'type'        => 'integer',
							'description' => __( 'ID del adjunto.', 'renova-mcp' ),
						),
						'force' => array(
							'type'        => 'boolean',
							'description' => __( 'Eliminar definitivamente (sin papelera).', 'renova-mcp' ),
							'default'     => true,
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'delete_media' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'destructive' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/set-featured-image',
			array(
				'label'               => __( 'Fijar imagen destacada', 'renova-mcp' ),
				'description'         => __( 'Asigna (o quita) la imagen destacada de una entrada usando el ID de un adjunto existente.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'       => array(
							'type'        => 'integer',
							'description' => __( 'ID de la entrada.', 'renova-mcp' ),
						),
						'attachment_id' => array(
							'type'        => 'integer',
							'description' => __( 'ID del adjunto. Usa 0 para quitar la imagen destacada.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'post_id', 'attachment_id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'set_featured_image' ),
				'permission_callback' => $perm,
			)
		);
	}

	/* ===================================================================== */
	/* Usuarios                                                               */
	/* ===================================================================== */

	/**
	 * Serializa un usuario.
	 *
	 * @param \WP_User $user Usuario.
	 * @return array
	 */
	private static function user_to_array( $user ) {
		return array(
			'id'           => $user->ID,
			'login'        => $user->user_login,
			'email'        => $user->user_email,
			'display_name' => $user->display_name,
			'roles'        => array_values( $user->roles ),
			'registered'   => $user->user_registered,
			'url'          => $user->user_url,
		);
	}

	/**
	 * Lista usuarios.
	 *
	 * @param array $input Entrada.
	 * @return array
	 */
	public static function list_users( $input ) {
		$args = array(
			'number' => isset( $input['number'] ) ? (int) $input['number'] : 50,
		);
		if ( ! empty( $input['role'] ) ) {
			$args['role'] = sanitize_key( $input['role'] );
		}
		if ( ! empty( $input['search'] ) ) {
			$args['search']         = '*' . $input['search'] . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}
		$users = array();
		foreach ( get_users( $args ) as $user ) {
			$users[] = self::user_to_array( $user );
		}
		return array(
			'count' => count( $users ),
			'users' => $users,
		);
	}

	/**
	 * Obtiene un usuario.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_user( $input ) {
		$user = false;
		if ( ! empty( $input['id'] ) ) {
			$user = get_user_by( 'id', (int) $input['id'] );
		} elseif ( ! empty( $input['login'] ) ) {
			$user = get_user_by( 'login', (string) $input['login'] );
		} elseif ( ! empty( $input['email'] ) ) {
			$user = get_user_by( 'email', (string) $input['email'] );
		}
		if ( ! $user ) {
			return new \WP_Error( 'renova_mcp_user_not_found', 'No se encontró el usuario indicado.' );
		}
		$data         = self::user_to_array( $user );
		$data['meta'] = array(
			'first_name' => get_user_meta( $user->ID, 'first_name', true ),
			'last_name'  => get_user_meta( $user->ID, 'last_name', true ),
		);
		return $data;
	}

	/**
	 * Crea un usuario.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function create_user( $input ) {
		$username = isset( $input['username'] ) ? sanitize_user( $input['username'] ) : '';
		$email    = isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '';
		if ( '' === $username || '' === $email ) {
			return new \WP_Error( 'renova_mcp_missing_user', 'Faltan username o email.' );
		}
		$password = ! empty( $input['password'] ) ? (string) $input['password'] : wp_generate_password( 20, true );
		$userdata = array(
			'user_login' => $username,
			'user_email' => $email,
			'user_pass'  => $password,
			'role'       => isset( $input['role'] ) ? sanitize_key( $input['role'] ) : 'subscriber',
		);
		foreach ( array( 'display_name', 'first_name', 'last_name' ) as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$userdata[ $field ] = (string) $input[ $field ];
			}
		}
		$id = wp_insert_user( $userdata );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return array(
			'success' => true,
			'id'      => $id,
			'message' => 'Usuario creado.',
		);
	}

	/**
	 * Actualiza un usuario.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function update_user( $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		if ( ! $id || ! get_user_by( 'id', $id ) ) {
			return new \WP_Error( 'renova_mcp_user_not_found', 'No se encontró el usuario indicado.' );
		}
		$userdata = array( 'ID' => $id );
		if ( isset( $input['email'] ) ) {
			$userdata['user_email'] = sanitize_email( $input['email'] );
		}
		if ( ! empty( $input['password'] ) ) {
			$userdata['user_pass'] = (string) $input['password'];
		}
		if ( isset( $input['role'] ) ) {
			$userdata['role'] = sanitize_key( $input['role'] );
		}
		foreach ( array( 'display_name', 'first_name', 'last_name' ) as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$userdata[ $field ] = (string) $input[ $field ];
			}
		}
		$result = wp_update_user( $userdata );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'success' => true,
			'id'      => $id,
			'message' => 'Usuario actualizado.',
		);
	}

	/**
	 * Elimina un usuario.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function delete_user( $input ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		if ( ! $id || ! get_user_by( 'id', $id ) ) {
			return new \WP_Error( 'renova_mcp_user_not_found', 'No se encontró el usuario indicado.' );
		}
		$reassign = ! empty( $input['reassign'] ) ? (int) $input['reassign'] : null;
		$ok       = wp_delete_user( $id, $reassign );
		return array(
			'success' => (bool) $ok,
			'id'      => $id,
		);
	}

	/* ===================================================================== */
	/* Medios                                                                 */
	/* ===================================================================== */

	/**
	 * Serializa un adjunto.
	 *
	 * @param int $id ID del adjunto.
	 * @return array
	 */
	private static function media_to_array( $id ) {
		return array(
			'id'        => $id,
			'title'     => get_the_title( $id ),
			'url'       => wp_get_attachment_url( $id ),
			'mime_type' => get_post_mime_type( $id ),
			'alt'       => get_post_meta( $id, '_wp_attachment_image_alt', true ),
		);
	}

	/**
	 * Lista medios.
	 *
	 * @param array $input Entrada.
	 * @return array
	 */
	public static function list_media( $input ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => isset( $input['number'] ) ? (int) $input['number'] : 30,
		);
		if ( ! empty( $input['mime_type'] ) ) {
			$args['post_mime_type'] = (string) $input['mime_type'];
		}
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = (string) $input['search'];
		}
		$items = array();
		foreach ( get_posts( $args ) as $att ) {
			$items[] = self::media_to_array( $att->ID );
		}
		return array(
			'count' => count( $items ),
			'media' => $items,
		);
	}

	/**
	 * Obtiene un medio.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_media( $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		if ( ! $id || 'attachment' !== get_post_type( $id ) ) {
			return new \WP_Error( 'renova_mcp_media_not_found', 'No se encontró el adjunto indicado.' );
		}
		$data             = self::media_to_array( $id );
		$data['metadata'] = wp_get_attachment_metadata( $id );
		$data['sizes']    = array();
		foreach ( get_intermediate_image_sizes() as $size ) {
			$src = wp_get_attachment_image_src( $id, $size );
			if ( $src ) {
				$data['sizes'][ $size ] = $src[0];
			}
		}
		return $data;
	}

	/**
	 * Sube un medio desde una URL.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function upload_media( $input ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$url = isset( $input['url'] ) ? esc_url_raw( $input['url'] ) : '';
		if ( '' === $url ) {
			return new \WP_Error( 'renova_mcp_missing_url', 'Falta la URL del archivo.' );
		}
		$post_id = ! empty( $input['post_id'] ) ? (int) $input['post_id'] : 0;

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		$file_array = array(
			'name'     => basename( wp_parse_url( $url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp,
		);
		$attachment_id = media_handle_sideload( $file_array, $post_id, isset( $input['title'] ) ? (string) $input['title'] : null );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return $attachment_id;
		}
		if ( isset( $input['alt'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt'] ) );
		}
		if ( $post_id && ! empty( $input['featured'] ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
		return array(
			'success' => true,
			'id'      => $attachment_id,
			'url'     => wp_get_attachment_url( $attachment_id ),
			'message' => 'Medio importado.',
		);
	}

	/**
	 * Elimina un medio.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function delete_media( $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		if ( ! $id || 'attachment' !== get_post_type( $id ) ) {
			return new \WP_Error( 'renova_mcp_media_not_found', 'No se encontró el adjunto indicado.' );
		}
		$force = ! isset( $input['force'] ) || ! empty( $input['force'] );
		$ok    = wp_delete_attachment( $id, $force );
		return array(
			'success' => (bool) $ok,
			'id'      => $id,
		);
	}

	/**
	 * Fija la imagen destacada.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function set_featured_image( $input ) {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		if ( ! $post_id || ! get_post( $post_id ) ) {
			return new \WP_Error( 'renova_mcp_post_not_found', 'No se encontró la entrada indicada.' );
		}
		$attachment_id = isset( $input['attachment_id'] ) ? (int) $input['attachment_id'] : 0;
		if ( $attachment_id <= 0 ) {
			delete_post_thumbnail( $post_id );
			return array(
				'success' => true,
				'post_id' => $post_id,
				'message' => 'Imagen destacada eliminada.',
			);
		}
		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return new \WP_Error( 'renova_mcp_media_not_found', 'El adjunto indicado no existe.' );
		}
		set_post_thumbnail( $post_id, $attachment_id );
		return array(
			'success'       => true,
			'post_id'       => $post_id,
			'attachment_id' => $attachment_id,
		);
	}
}
