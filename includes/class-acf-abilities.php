<?php
/**
 * Habilidades MCP basadas en la API de Advanced Custom Fields (ACF).
 *
 * Cada ability envuelve una función pública de ACF. Solo se registran si ACF
 * está activo (función `get_field` disponible). Cada callback revalida en
 * tiempo de ejecución y devuelve WP_Error si ACF no está presente.
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Registro de abilities ACF.
 */
class Acf_Abilities {

	/**
	 * ¿Está ACF disponible?
	 *
	 * @return bool
	 */
	public static function is_available() {
		return function_exists( 'get_field' ) && function_exists( 'acf_get_field_groups' );
	}

	/**
	 * IDs de las abilities ACF expuestas como herramientas MCP.
	 *
	 * @return string[]
	 */
	public static function tool_ids() {
		return array(
			'renova-mcp/acf-get-field',
			'renova-mcp/acf-get-field-object',
			'renova-mcp/acf-get-fields',
			'renova-mcp/acf-get-field-objects',
			'renova-mcp/acf-update-field',
			'renova-mcp/acf-delete-field',
			'renova-mcp/acf-add-row',
			'renova-mcp/acf-update-row',
			'renova-mcp/acf-delete-row',
			'renova-mcp/acf-list-field-groups',
			'renova-mcp/acf-get-field-group',
			'renova-mcp/acf-get-fields-in-group',
			'renova-mcp/acf-create-field-group',
			'renova-mcp/acf-update-field-group',
			'renova-mcp/acf-delete-field-group',
			'renova-mcp/acf-export-field-group',
		);
	}

	/**
	 * Comprueba permisos (administrador).
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
			return new \WP_Error( 'renova_mcp_acf_inactive', 'El plugin Advanced Custom Fields no está activo.' );
		}
		return null;
	}

	/**
	 * Registra todas las abilities ACF (solo si ACF está activo).
	 */
	public static function register() {
		if ( ! self::is_available() ) {
			return;
		}

		$obj      = array( 'type' => 'object' );
		$post_id  = array(
			'type'        => array( 'string', 'integer' ),
			'description' => __( 'ID del contenido. Acepta un ID numérico, "option" (página de opciones), "user_X" o "term_X".', 'renova-mcp' ),
		);
		$selector = array(
			'type'        => 'string',
			'description' => __( 'Nombre o clave (key) del campo ACF.', 'renova-mcp' ),
		);

		// get_field ----------------------------------------------------------------
		wp_register_ability(
			'renova-mcp/acf-get-field',
			array(
				'label'               => __( 'ACF: leer campo', 'renova-mcp' ),
				'description'         => __( 'Devuelve el valor de un campo ACF para un contenido. Envuelve get_field().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'selector'     => $selector,
						'post_id'      => $post_id,
						'format_value' => array(
							'type'        => 'boolean',
							'description' => __( 'Aplicar formato de ACF al valor.', 'renova-mcp' ),
							'default'     => true,
						),
					),
					'required'   => array( 'selector', 'post_id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_field' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		// get_field_object ---------------------------------------------------------
		wp_register_ability(
			'renova-mcp/acf-get-field-object',
			array(
				'label'               => __( 'ACF: objeto de campo', 'renova-mcp' ),
				'description'         => __( 'Devuelve la definición completa de un campo (tipo, etiqueta, opciones) y su valor. Envuelve get_field_object().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'selector' => $selector,
						'post_id'  => $post_id,
					),
					'required'   => array( 'selector', 'post_id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_field_object' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		// get_fields ---------------------------------------------------------------
		wp_register_ability(
			'renova-mcp/acf-get-fields',
			array(
				'label'               => __( 'ACF: todos los campos', 'renova-mcp' ),
				'description'         => __( 'Devuelve todos los valores de campos ACF de un contenido. Envuelve get_fields().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'post_id' => $post_id ),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_fields' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		// get_field_objects --------------------------------------------------------
		wp_register_ability(
			'renova-mcp/acf-get-field-objects',
			array(
				'label'               => __( 'ACF: objetos de todos los campos', 'renova-mcp' ),
				'description'         => __( 'Devuelve las definiciones y valores de todos los campos de un contenido. Envuelve get_field_objects().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'post_id' => $post_id ),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_field_objects' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		// update_field -------------------------------------------------------------
		wp_register_ability(
			'renova-mcp/acf-update-field',
			array(
				'label'               => __( 'ACF: actualizar campo', 'renova-mcp' ),
				'description'         => __( 'Crea o actualiza el valor de un campo ACF para un contenido. Envuelve update_field().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'selector' => $selector,
						'value'    => array( 'description' => __( 'Nuevo valor (texto, número, booleano, array u objeto).', 'renova-mcp' ) ),
						'post_id'  => $post_id,
					),
					'required'   => array( 'selector', 'value', 'post_id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'update_field' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		// delete_field -------------------------------------------------------------
		wp_register_ability(
			'renova-mcp/acf-delete-field',
			array(
				'label'               => __( 'ACF: eliminar campo', 'renova-mcp' ),
				'description'         => __( 'Elimina el valor de un campo ACF de un contenido. Envuelve delete_field().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'selector' => $selector,
						'post_id'  => $post_id,
					),
					'required'   => array( 'selector', 'post_id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'delete_field' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'destructive' => true ) ),
			)
		);

		// add_row ------------------------------------------------------------------
		wp_register_ability(
			'renova-mcp/acf-add-row',
			array(
				'label'               => __( 'ACF: añadir fila (repeater)', 'renova-mcp' ),
				'description'         => __( 'Añade una fila a un campo repeater/flexible. Envuelve add_row().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'selector' => $selector,
						'row'      => array(
							'type'        => 'object',
							'description' => __( 'Objeto con los valores de los subcampos (nombre => valor).', 'renova-mcp' ),
						),
						'post_id'  => $post_id,
					),
					'required'   => array( 'selector', 'row', 'post_id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'add_row' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		// update_row ---------------------------------------------------------------
		wp_register_ability(
			'renova-mcp/acf-update-row',
			array(
				'label'               => __( 'ACF: actualizar fila (repeater)', 'renova-mcp' ),
				'description'         => __( 'Actualiza una fila concreta de un repeater por su índice (base 1). Envuelve update_row().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'selector'  => $selector,
						'row_index' => array(
							'type'        => 'integer',
							'description' => __( 'Índice de la fila (empezando en 1).', 'renova-mcp' ),
						),
						'row'       => array(
							'type'        => 'object',
							'description' => __( 'Valores de los subcampos.', 'renova-mcp' ),
						),
						'post_id'   => $post_id,
					),
					'required'   => array( 'selector', 'row_index', 'row', 'post_id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'update_row' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		// delete_row ---------------------------------------------------------------
		wp_register_ability(
			'renova-mcp/acf-delete-row',
			array(
				'label'               => __( 'ACF: eliminar fila (repeater)', 'renova-mcp' ),
				'description'         => __( 'Elimina una fila de un repeater por su índice (base 1). Envuelve delete_row().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'selector'  => $selector,
						'row_index' => array(
							'type'        => 'integer',
							'description' => __( 'Índice de la fila (empezando en 1).', 'renova-mcp' ),
						),
						'post_id'   => $post_id,
					),
					'required'   => array( 'selector', 'row_index', 'post_id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'delete_row' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'destructive' => true ) ),
			)
		);

		// acf_get_field_groups -----------------------------------------------------
		wp_register_ability(
			'renova-mcp/acf-list-field-groups',
			array(
				'label'               => __( 'ACF: listar grupos de campos', 'renova-mcp' ),
				'description'         => __( 'Lista todos los grupos de campos registrados. Envuelve acf_get_field_groups().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => $obj,
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'list_field_groups' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		// acf_get_field_group ------------------------------------------------------
		wp_register_ability(
			'renova-mcp/acf-get-field-group',
			array(
				'label'               => __( 'ACF: obtener grupo de campos', 'renova-mcp' ),
				'description'         => __( 'Devuelve un grupo de campos por su clave (key) o ID. Envuelve acf_get_field_group().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => array( 'string', 'integer' ),
							'description' => __( 'Clave (ej. "group_abc123") o ID del grupo.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_field_group' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		// acf_get_fields -----------------------------------------------------------
		wp_register_ability(
			'renova-mcp/acf-get-fields-in-group',
			array(
				'label'               => __( 'ACF: campos de un grupo', 'renova-mcp' ),
				'description'         => __( 'Devuelve las definiciones de los campos de un grupo. Envuelve acf_get_fields().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'group' => array(
							'type'        => array( 'string', 'integer' ),
							'description' => __( 'Clave o ID del grupo de campos.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'group' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_fields_in_group' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		// acf_import_field_group ---------------------------------------------------
		wp_register_ability(
			'renova-mcp/acf-create-field-group',
			array(
				'label'               => __( 'ACF: crear grupo de campos', 'renova-mcp' ),
				'description'         => __( 'Crea (importa) un grupo de campos con sus campos y reglas de ubicación. Envuelve acf_import_field_group(). Acepta el mismo formato que la exportación de ACF.', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'field_group' => array(
							'type'        => 'object',
							'description' => __( 'Definición del grupo: title, fields (array), location (array), etc.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'field_group' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'create_field_group' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		// acf_update_field_group ---------------------------------------------------
		wp_register_ability(
			'renova-mcp/acf-update-field-group',
			array(
				'label'               => __( 'ACF: actualizar grupo de campos', 'renova-mcp' ),
				'description'         => __( 'Actualiza las propiedades de un grupo de campos existente. Envuelve acf_update_field_group().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'field_group' => array(
							'type'        => 'object',
							'description' => __( 'Grupo con su "key" e ID y las propiedades a cambiar.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'field_group' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'update_field_group' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		// acf_delete_field_group ---------------------------------------------------
		wp_register_ability(
			'renova-mcp/acf-delete-field-group',
			array(
				'label'               => __( 'ACF: eliminar grupo de campos', 'renova-mcp' ),
				'description'         => __( 'Elimina un grupo de campos. Acción destructiva. Envuelve acf_delete_field_group().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => array( 'string', 'integer' ),
							'description' => __( 'Clave o ID del grupo.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'delete_field_group' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => array( 'annotations' => array( 'destructive' => true ) ),
			)
		);

		// acf_export_field_group ---------------------------------------------------
		wp_register_ability(
			'renova-mcp/acf-export-field-group',
			array(
				'label'               => __( 'ACF: exportar grupo de campos', 'renova-mcp' ),
				'description'         => __( 'Exporta un grupo de campos a un array portable (apto para crear el mismo grupo en otro sitio). Envuelve acf_export_field_group().', 'renova-mcp' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => array( 'string', 'integer' ),
							'description' => __( 'Clave o ID del grupo.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'export_field_group' ),
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
	 * @return array|\WP_Error
	 */
	public static function get_field( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$format = isset( $input['format_value'] ) ? (bool) $input['format_value'] : true;
		$value  = get_field( $input['selector'], $input['post_id'], $format );
		return array(
			'selector' => $input['selector'],
			'post_id'  => $input['post_id'],
			'value'    => $value,
			'exists'   => ( null !== $value && false !== $value ),
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_field_object( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$field = get_field_object( $input['selector'], $input['post_id'] );
		if ( ! $field ) {
			return new \WP_Error( 'renova_mcp_acf_field_not_found', 'No se encontró el campo ACF indicado.' );
		}
		return array( 'field' => $field );
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_fields( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$fields = get_fields( $input['post_id'] );
		return array(
			'post_id' => $input['post_id'],
			'fields'  => ( false === $fields ) ? array() : $fields,
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_field_objects( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$fields = get_field_objects( $input['post_id'] );
		return array(
			'post_id' => $input['post_id'],
			'fields'  => ( false === $fields ) ? array() : $fields,
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function update_field( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$ok = update_field( $input['selector'], $input['value'], $input['post_id'] );
		return array(
			'success'  => (bool) $ok,
			'selector' => $input['selector'],
			'post_id'  => $input['post_id'],
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function delete_field( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$ok = delete_field( $input['selector'], $input['post_id'] );
		return array(
			'success'  => (bool) $ok,
			'selector' => $input['selector'],
			'post_id'  => $input['post_id'],
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function add_row( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$index = add_row( $input['selector'], (array) $input['row'], $input['post_id'] );
		return array(
			'success'   => (bool) $index,
			'row_index' => $index,
			'post_id'   => $input['post_id'],
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function update_row( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$ok = update_row( $input['selector'], (int) $input['row_index'], (array) $input['row'], $input['post_id'] );
		return array(
			'success'   => (bool) $ok,
			'row_index' => (int) $input['row_index'],
			'post_id'   => $input['post_id'],
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function delete_row( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$ok = delete_row( $input['selector'], (int) $input['row_index'], $input['post_id'] );
		return array(
			'success'   => (bool) $ok,
			'row_index' => (int) $input['row_index'],
			'post_id'   => $input['post_id'],
		);
	}

	/**
	 * @return array|\WP_Error
	 */
	public static function list_field_groups() {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$groups = acf_get_field_groups();
		$items  = array();
		foreach ( (array) $groups as $grp ) {
			$items[] = array(
				'key'      => isset( $grp['key'] ) ? $grp['key'] : '',
				'id'       => isset( $grp['ID'] ) ? $grp['ID'] : 0,
				'title'    => isset( $grp['title'] ) ? $grp['title'] : '',
				'active'   => ! empty( $grp['active'] ),
				'location' => isset( $grp['location'] ) ? $grp['location'] : array(),
			);
		}
		return array(
			'count'        => count( $items ),
			'field_groups' => $items,
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_field_group( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$group = acf_get_field_group( $input['id'] );
		if ( ! $group ) {
			return new \WP_Error( 'renova_mcp_acf_group_not_found', 'No se encontró el grupo de campos.' );
		}
		return array( 'field_group' => $group );
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_fields_in_group( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$fields = acf_get_fields( $input['group'] );
		return array(
			'group'  => $input['group'],
			'fields' => ( false === $fields ) ? array() : $fields,
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function create_field_group( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		if ( ! function_exists( 'acf_import_field_group' ) ) {
			return new \WP_Error( 'renova_mcp_acf_unsupported', 'acf_import_field_group no está disponible.' );
		}
		$result = acf_import_field_group( (array) $input['field_group'] );
		return array(
			'success'     => ! empty( $result['key'] ),
			'field_group' => $result,
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function update_field_group( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$result = acf_update_field_group( (array) $input['field_group'] );
		return array(
			'success'     => ! empty( $result['ID'] ) || ! empty( $result['key'] ),
			'field_group' => $result,
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function delete_field_group( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		$ok = acf_delete_field_group( $input['id'] );
		return array(
			'success' => (bool) $ok,
			'id'      => $input['id'],
		);
	}

	/**
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function export_field_group( $input ) {
		$g = self::guard();
		if ( $g ) {
			return $g;
		}
		if ( ! function_exists( 'acf_export_field_group' ) ) {
			return new \WP_Error( 'renova_mcp_acf_unsupported', 'acf_export_field_group no está disponible.' );
		}
		$group = acf_get_field_group( $input['id'] );
		if ( ! $group ) {
			return new \WP_Error( 'renova_mcp_acf_group_not_found', 'No se encontró el grupo de campos.' );
		}
		$export = acf_export_field_group( $group );
		return array( 'field_group' => $export );
	}
}
