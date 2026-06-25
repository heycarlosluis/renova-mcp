<?php
/**
 * Abilities MCP para control total de Elementor y Elementor Pro.
 *
 * Como el plugin corre dentro de WordPress, manipula directamente el árbol
 * JSON de `_elementor_data`, la caché de CSS y el kit global, usando las APIs
 * de Elementor cuando están disponibles. Si Elementor no está activo, cada
 * herramienta devuelve un WP_Error claro al ejecutarse (igual que las ACF).
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Define y registra las capacidades de Elementor expuestas por el servidor MCP.
 */
class Elementor_Abilities {

	const CATEGORY = 'renova-mcp';

	/**
	 * IDs de todas las abilities de Elementor expuestas como herramientas MCP.
	 *
	 * @return string[]
	 */
	public static function tool_ids() {
		return array(
			// Lectura / inspección.
			'renova-mcp/elementor-list-content',
			'renova-mcp/elementor-get-data',
			'renova-mcp/elementor-get-structure',
			'renova-mcp/elementor-get-element',
			'renova-mcp/elementor-find-elements',
			'renova-mcp/elementor-get-page-settings',
			// Escritura del árbol.
			'renova-mcp/elementor-update-data',
			'renova-mcp/elementor-update-element',
			'renova-mcp/elementor-add-element',
			'renova-mcp/elementor-delete-element',
			'renova-mcp/elementor-move-element',
			'renova-mcp/elementor-duplicate-element',
			'renova-mcp/elementor-reorder-elements',
			'renova-mcp/elementor-update-page-settings',
			// Copias de seguridad y caché.
			'renova-mcp/elementor-backup-data',
			'renova-mcp/elementor-restore-backup',
			'renova-mcp/elementor-clear-cache',
			// Plantillas (biblioteca).
			'renova-mcp/elementor-list-templates',
			'renova-mcp/elementor-get-template',
			'renova-mcp/elementor-create-template',
			'renova-mcp/elementor-apply-template',
			// Kit global (Pro: colores, tipografías, ajustes del sitio).
			'renova-mcp/elementor-get-global-settings',
			'renova-mcp/elementor-update-global-settings',
		);
	}

	/**
	 * Registra todas las abilities de Elementor.
	 */
	public static function register() {
		$obj  = array( 'type' => 'object' );
		$perm = array( Abilities::class, 'can_manage' );

		$id_prop = array(
			'id' => array(
				'type'        => 'integer',
				'description' => __( 'ID de la página/entrada/CPT editada con Elementor.', 'renova-mcp' ),
			),
		);

		// --- Lectura --------------------------------------------------------------
		wp_register_ability(
			'renova-mcp/elementor-list-content',
			array(
				'label'               => __( 'Listar contenido Elementor', 'renova-mcp' ),
				'description'         => __( 'Lista entradas/páginas/CPT indicando si están construidas con Elementor (edit_mode "builder").', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array(
							'type'        => 'string',
							'description' => __( 'Tipo de contenido a listar.', 'renova-mcp' ),
							'default'     => 'page',
						),
						'number'    => array(
							'type'        => 'integer',
							'description' => __( 'Máximo de resultados.', 'renova-mcp' ),
							'default'     => 50,
						),
						'only_elementor' => array(
							'type'        => 'boolean',
							'description' => __( 'Si es true, solo devuelve los construidos con Elementor.', 'renova-mcp' ),
							'default'     => false,
						),
					),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'list_content' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/elementor-get-data',
			array(
				'label'               => __( 'Obtener datos Elementor', 'renova-mcp' ),
				'description'         => __( 'Devuelve el árbol completo de `_elementor_data` (JSON decodificado) de una página.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => $id_prop,
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_data' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/elementor-get-structure',
			array(
				'label'               => __( 'Estructura Elementor', 'renova-mcp' ),
				'description'         => __( 'Devuelve un esquema simplificado del árbol (id, tipo, widgetType y una vista previa de texto) para entender la página sin todo el JSON.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => $id_prop,
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_structure' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/elementor-get-element',
			array(
				'label'               => __( 'Obtener elemento Elementor', 'renova-mcp' ),
				'description'         => __( 'Devuelve un único elemento (sección, contenedor, columna o widget) por su id, con todos sus ajustes.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'         => $id_prop['id'],
						'element_id' => array(
							'type'        => 'string',
							'description' => __( 'ID del elemento dentro del árbol Elementor (7 caracteres).', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id', 'element_id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_element' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/elementor-find-elements',
			array(
				'label'               => __( 'Buscar elementos Elementor', 'renova-mcp' ),
				'description'         => __( 'Busca elementos por widgetType (ej. "heading", "button", "image") o por elType (section, container, column, widget).', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'          => $id_prop['id'],
						'widget_type' => array(
							'type'        => 'string',
							'description' => __( 'Tipo de widget a buscar (ej. "heading", "button", "text-editor", "image").', 'renova-mcp' ),
						),
						'el_type'     => array(
							'type'        => 'string',
							'description' => __( 'Tipo de elemento: section, container, column o widget.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'find_elements' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/elementor-get-page-settings',
			array(
				'label'               => __( 'Ajustes de página Elementor', 'renova-mcp' ),
				'description'         => __( 'Devuelve `_elementor_page_settings` (plantilla, fondo, padding, CSS personalizado de página, etc.).', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => $id_prop,
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_page_settings' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		// --- Escritura ------------------------------------------------------------
		wp_register_ability(
			'renova-mcp/elementor-update-data',
			array(
				'label'               => __( 'Reemplazar datos Elementor', 'renova-mcp' ),
				'description'         => __( 'Reemplaza por completo `_elementor_data` con un nuevo árbol JSON. Crea una copia de seguridad previa automática y regenera la caché. Acción potente.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'   => $id_prop['id'],
						'data' => array(
							'type'        => 'array',
							'description' => __( 'Árbol Elementor completo (array de elementos de nivel superior). Acepta array u objeto JSON.', 'renova-mcp' ),
							'items'       => array( 'type' => 'object' ),
						),
					),
					'required'   => array( 'id', 'data' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'update_data' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/elementor-update-element',
			array(
				'label'               => __( 'Actualizar elemento Elementor', 'renova-mcp' ),
				'description'         => __( 'Fusiona ajustes en un elemento/widget concreto por su id sin recargar toda la página. Ej.: cambiar el texto de un "heading" pasando settings {"title":"Nuevo"}.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'         => $id_prop['id'],
						'element_id' => array(
							'type'        => 'string',
							'description' => __( 'ID del elemento a modificar.', 'renova-mcp' ),
						),
						'settings'   => array(
							'type'        => 'object',
							'description' => __( 'Ajustes a fusionar en `settings` del elemento.', 'renova-mcp' ),
						),
						'replace'    => array(
							'type'        => 'boolean',
							'description' => __( 'Si es true, reemplaza `settings` por completo en lugar de fusionar.', 'renova-mcp' ),
							'default'     => false,
						),
					),
					'required'   => array( 'id', 'element_id', 'settings' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'update_element' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/elementor-add-element',
			array(
				'label'               => __( 'Añadir elemento Elementor', 'renova-mcp' ),
				'description'         => __( 'Añade una sección, contenedor, columna o widget. Indica elType (y widgetType si es widget) + settings, o pasa un subárbol completo en "element". Los IDs se generan solos. parent_id vacío = nivel superior.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'          => $id_prop['id'],
						'parent_id'   => array(
							'type'        => 'string',
							'description' => __( 'ID del elemento contenedor. Vacío para añadir al nivel superior de la página.', 'renova-mcp' ),
						),
						'position'    => array(
							'type'        => 'integer',
							'description' => __( 'Índice donde insertar entre los hijos del padre (0 = primero). Por defecto, al final.', 'renova-mcp' ),
						),
						'el_type'     => array(
							'type'        => 'string',
							'description' => __( 'section, container, column o widget.', 'renova-mcp' ),
						),
						'widget_type' => array(
							'type'        => 'string',
							'description' => __( 'Tipo de widget si el_type es "widget" (ej. "heading", "button", "image", "text-editor").', 'renova-mcp' ),
						),
						'settings'    => array(
							'type'        => 'object',
							'description' => __( 'Ajustes iniciales del elemento.', 'renova-mcp' ),
						),
						'element'     => array(
							'type'        => 'object',
							'description' => __( 'Alternativa: subárbol Elementor completo a insertar tal cual (se le regeneran los IDs).', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'add_element' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/elementor-delete-element',
			array(
				'label'               => __( 'Eliminar elemento Elementor', 'renova-mcp' ),
				'description'         => __( 'Elimina un elemento (y sus hijos) por su id. Acción destructiva; se hace copia de seguridad previa.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'         => $id_prop['id'],
						'element_id' => array(
							'type'        => 'string',
							'description' => __( 'ID del elemento a eliminar.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id', 'element_id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'delete_element' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'destructive' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/elementor-move-element',
			array(
				'label'               => __( 'Mover elemento Elementor', 'renova-mcp' ),
				'description'         => __( 'Mueve un elemento a otro padre y/o posición.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'            => $id_prop['id'],
						'element_id'    => array(
							'type'        => 'string',
							'description' => __( 'ID del elemento a mover.', 'renova-mcp' ),
						),
						'new_parent_id' => array(
							'type'        => 'string',
							'description' => __( 'ID del nuevo padre. Vacío para moverlo al nivel superior.', 'renova-mcp' ),
						),
						'position'      => array(
							'type'        => 'integer',
							'description' => __( 'Índice destino entre los hijos del nuevo padre. Por defecto, al final.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id', 'element_id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'move_element' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/elementor-duplicate-element',
			array(
				'label'               => __( 'Duplicar elemento Elementor', 'renova-mcp' ),
				'description'         => __( 'Clona un elemento (y sus hijos) con nuevos IDs, insertándolo justo después del original.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'         => $id_prop['id'],
						'element_id' => array(
							'type'        => 'string',
							'description' => __( 'ID del elemento a duplicar.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id', 'element_id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'duplicate_element' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/elementor-reorder-elements',
			array(
				'label'               => __( 'Reordenar elementos Elementor', 'renova-mcp' ),
				'description'         => __( 'Reordena los hijos directos de un padre según una lista de IDs. parent_id vacío = nivel superior.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'        => $id_prop['id'],
						'parent_id' => array(
							'type'        => 'string',
							'description' => __( 'ID del padre cuyos hijos se reordenan. Vacío = nivel superior.', 'renova-mcp' ),
						),
						'order'     => array(
							'type'        => 'array',
							'description' => __( 'Lista de IDs de hijos en el nuevo orden.', 'renova-mcp' ),
							'items'       => array( 'type' => 'string' ),
						),
					),
					'required'   => array( 'id', 'order' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'reorder_elements' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/elementor-update-page-settings',
			array(
				'label'               => __( 'Actualizar ajustes de página', 'renova-mcp' ),
				'description'         => __( 'Fusiona ajustes en `_elementor_page_settings` (plantilla, fondo, CSS de página, etc.) y regenera la caché.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => $id_prop['id'],
						'settings' => array(
							'type'        => 'object',
							'description' => __( 'Ajustes a fusionar.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'id', 'settings' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'update_page_settings' ),
				'permission_callback' => $perm,
			)
		);

		// --- Copias de seguridad / caché ------------------------------------------
		wp_register_ability(
			'renova-mcp/elementor-backup-data',
			array(
				'label'               => __( 'Copia de seguridad Elementor', 'renova-mcp' ),
				'description'         => __( 'Guarda una copia del `_elementor_data` actual en un meta de respaldo y devuelve su marca de tiempo.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => $id_prop,
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'backup_data' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/elementor-restore-backup',
			array(
				'label'               => __( 'Restaurar copia Elementor', 'renova-mcp' ),
				'description'         => __( 'Restaura el último respaldo guardado de `_elementor_data` y regenera la caché.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => $id_prop,
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'restore_backup' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/elementor-clear-cache',
			array(
				'label'               => __( 'Limpiar caché Elementor', 'renova-mcp' ),
				'description'         => __( 'Regenera el CSS de Elementor. Sin "id" limpia toda la caché del sitio; con "id" solo la de esa página.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => __( 'ID de página para limpiar solo su caché (opcional).', 'renova-mcp' ),
						),
					),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'clear_cache' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'idempotent' => true ) ),
			)
		);

		// --- Plantillas -----------------------------------------------------------
		wp_register_ability(
			'renova-mcp/elementor-list-templates',
			array(
				'label'               => __( 'Listar plantillas Elementor', 'renova-mcp' ),
				'description'         => __( 'Lista las plantillas de la biblioteca (CPT elementor_library): páginas, secciones, contenedores, cabeceras, pies, popups, etc.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type' => array(
							'type'        => 'string',
							'description' => __( 'Filtra por tipo de plantilla (page, section, container, header, footer, popup, etc.).', 'renova-mcp' ),
						),
					),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'list_templates' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/elementor-get-template',
			array(
				'label'               => __( 'Obtener plantilla Elementor', 'renova-mcp' ),
				'description'         => __( 'Devuelve el árbol JSON de una plantilla de la biblioteca por su ID.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => $id_prop,
					'required'   => array( 'id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_template' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/elementor-create-template',
			array(
				'label'               => __( 'Crear plantilla Elementor', 'renova-mcp' ),
				'description'         => __( 'Crea una plantilla en la biblioteca a partir de un árbol JSON, o copiando el contenido de una página existente (source_id).', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'     => array(
							'type'        => 'string',
							'description' => __( 'Nombre de la plantilla.', 'renova-mcp' ),
						),
						'type'      => array(
							'type'        => 'string',
							'description' => __( 'Tipo de plantilla (page, section, container, header, footer, popup...).', 'renova-mcp' ),
							'default'     => 'page',
						),
						'data'      => array(
							'type'        => 'array',
							'description' => __( 'Árbol Elementor de la plantilla.', 'renova-mcp' ),
							'items'       => array( 'type' => 'object' ),
						),
						'source_id' => array(
							'type'        => 'integer',
							'description' => __( 'Alternativa a "data": ID de una página existente de la que copiar el contenido.', 'renova-mcp' ),
						),
					),
					'required'   => array( 'title' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'create_template' ),
				'permission_callback' => $perm,
			)
		);

		wp_register_ability(
			'renova-mcp/elementor-apply-template',
			array(
				'label'               => __( 'Aplicar plantilla Elementor', 'renova-mcp' ),
				'description'         => __( 'Inserta el contenido de una plantilla en una página: "replace" sustituye el contenido o "append" lo añade al final. Regenera la caché.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'          => array(
							'type'        => 'integer',
							'description' => __( 'ID de la página destino.', 'renova-mcp' ),
						),
						'template_id' => array(
							'type'        => 'integer',
							'description' => __( 'ID de la plantilla a aplicar.', 'renova-mcp' ),
						),
						'mode'        => array(
							'type'        => 'string',
							'description' => __( '"replace" (por defecto) o "append".', 'renova-mcp' ),
							'default'     => 'replace',
						),
					),
					'required'   => array( 'id', 'template_id' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'apply_template' ),
				'permission_callback' => $perm,
			)
		);

		// --- Kit global (Elementor Pro / colores y tipografías globales) ----------
		wp_register_ability(
			'renova-mcp/elementor-get-global-settings',
			array(
				'label'               => __( 'Ajustes globales Elementor', 'renova-mcp' ),
				'description'         => __( 'Devuelve los ajustes del kit activo: colores del sistema y personalizados, tipografías globales y ajustes del sitio.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $obj,
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'get_global_settings' ),
				'permission_callback' => $perm,
				'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'renova-mcp/elementor-update-global-settings',
			array(
				'label'               => __( 'Actualizar ajustes globales', 'renova-mcp' ),
				'description'         => __( 'Fusiona ajustes en el kit activo (colores, tipografías, layout del sitio) y regenera la caché global.', 'renova-mcp' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'settings' => array(
							'type'        => 'object',
							'description' => __( 'Ajustes del kit a fusionar (ej. system_colors, custom_colors, system_typography, custom_typography).', 'renova-mcp' ),
						),
					),
					'required'   => array( 'settings' ),
				),
				'output_schema'       => $obj,
				'execute_callback'    => array( __CLASS__, 'update_global_settings' ),
				'permission_callback' => $perm,
			)
		);
	}

	/* ===================================================================== */
	/* Utilidades internas                                                    */
	/* ===================================================================== */

	const BACKUP_META = '_renova_elementor_backup';

	/**
	 * Comprueba que Elementor esté disponible.
	 *
	 * @return true|\WP_Error
	 */
	private static function require_elementor() {
		if ( did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ) ) {
			return true;
		}
		return new \WP_Error( 'renova_mcp_no_elementor', 'Elementor no está activo en este sitio.' );
	}

	/**
	 * Genera un ID de elemento Elementor (7 caracteres hexadecimales).
	 *
	 * @return string
	 */
	private static function generate_id() {
		if ( class_exists( '\Elementor\Utils' ) && method_exists( '\Elementor\Utils', 'generate_random_string' ) ) {
			return \Elementor\Utils::generate_random_string();
		}
		return substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 7 );
	}

	/**
	 * Lee y decodifica `_elementor_data` de un post.
	 *
	 * @param int $id ID del post.
	 * @return array|\WP_Error Árbol de elementos.
	 */
	private static function read_data( $id ) {
		if ( ! get_post( $id ) ) {
			return new \WP_Error( 'renova_mcp_post_not_found', 'No se encontró el contenido indicado.' );
		}
		$raw = get_post_meta( $id, '_elementor_data', true );
		if ( '' === $raw || null === $raw ) {
			return array();
		}
		if ( is_array( $raw ) ) {
			return $raw;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'renova_mcp_elementor_bad_data', 'El `_elementor_data` no es un JSON válido.' );
		}
		return $data;
	}

	/**
	 * Persiste el árbol en `_elementor_data`, marca la página como Elementor y regenera la caché.
	 *
	 * @param int   $id   ID del post.
	 * @param array $data Árbol de elementos.
	 * @return true|\WP_Error
	 */
	private static function write_data( $id, array $data ) {
		// Elementor espera el JSON con slashes (update_metadata quita uno).
		$json = wp_json_encode( $data );
		if ( false === $json ) {
			return new \WP_Error( 'renova_mcp_elementor_encode', 'No se pudo serializar el árbol Elementor.' );
		}
		update_metadata( 'post', $id, '_elementor_data', wp_slash( $json ) );

		if ( ! get_post_meta( $id, '_elementor_edit_mode', true ) ) {
			update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		}
		if ( class_exists( '\Elementor\Plugin' ) && defined( 'ELEMENTOR_VERSION' ) ) {
			update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
		}

		self::flush_page_cache( $id );
		return true;
	}

	/**
	 * Regenera la caché de CSS de una página concreta.
	 *
	 * @param int $id ID del post.
	 */
	private static function flush_page_cache( $id ) {
		delete_post_meta( $id, '_elementor_css' );
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( isset( $plugin->files_manager ) ) {
			// Borra y regenera el CSS de este post.
			try {
				$css = \Elementor\Core\Files\CSS\Post::create( $id );
				$css->update();
			} catch ( \Throwable $e ) {
				$plugin->files_manager->clear_cache();
			}
		}
	}

	/**
	 * Busca un elemento por id y devuelve una referencia.
	 *
	 * @param array  $elements Árbol (por referencia).
	 * @param string $el_id    ID buscado.
	 * @return array|null Referencia al elemento o null.
	 */
	private static function &find_ref( array &$elements, $el_id ) {
		$null = null;
		foreach ( $elements as &$el ) {
			if ( isset( $el['id'] ) && $el['id'] === $el_id ) {
				return $el;
			}
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$found = &self::find_ref( $el['elements'], $el_id );
				if ( null !== $found ) {
					return $found;
				}
			}
		}
		return $null;
	}

	/**
	 * Localiza el array padre y el índice de un elemento.
	 *
	 * @param array  $elements Árbol (por referencia).
	 * @param string $el_id    ID buscado.
	 * @return array{0:array,1:int}|null [&$siblings, $index] o null.
	 */
	private static function &find_parent_ref( array &$elements, $el_id, &$index ) {
		$null = null;
		foreach ( $elements as $i => &$el ) {
			if ( isset( $el['id'] ) && $el['id'] === $el_id ) {
				$index = $i;
				return $elements;
			}
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$found = &self::find_parent_ref( $el['elements'], $el_id, $index );
				if ( null !== $found ) {
					return $found;
				}
			}
		}
		return $null;
	}

	/**
	 * Regenera recursivamente los IDs de un subárbol (para clonar/insertar).
	 *
	 * @param array $element Elemento (por referencia).
	 */
	private static function regenerate_ids( array &$element ) {
		$element['id'] = self::generate_id();
		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			foreach ( $element['elements'] as &$child ) {
				self::regenerate_ids( $child );
			}
		}
	}

	/**
	 * Resumen simplificado de un elemento (para get-structure).
	 *
	 * @param array $element Elemento.
	 * @return array
	 */
	private static function summarize( array $element ) {
		$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		$preview  = '';
		foreach ( array( 'title', 'editor', 'text', 'caption', 'button_text' ) as $key ) {
			if ( ! empty( $settings[ $key ] ) && is_string( $settings[ $key ] ) ) {
				$preview = wp_trim_words( wp_strip_all_tags( $settings[ $key ] ), 12, '…' );
				break;
			}
		}
		$node = array(
			'id'      => $element['id'] ?? '',
			'elType'  => $element['elType'] ?? '',
		);
		if ( ! empty( $element['widgetType'] ) ) {
			$node['widgetType'] = $element['widgetType'];
		}
		if ( '' !== $preview ) {
			$node['preview'] = $preview;
		}
		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$node['elements'] = array_map( array( __CLASS__, 'summarize' ), $element['elements'] );
		}
		return $node;
	}

	/**
	 * Recorre el árbol invocando un callback por cada elemento.
	 *
	 * @param array    $elements Árbol.
	 * @param callable $cb       Callback que recibe el elemento.
	 */
	private static function walk( array $elements, callable $cb ) {
		foreach ( $elements as $el ) {
			$cb( $el );
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				self::walk( $el['elements'], $cb );
			}
		}
	}

	/* ===================================================================== */
	/* Implementaciones — lectura                                             */
	/* ===================================================================== */

	/**
	 * Lista contenido con estado Elementor.
	 *
	 * @param array $input Entrada.
	 * @return array
	 */
	public static function list_content( $input ) {
		$posts = get_posts(
			array(
				'post_type'      => isset( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'page',
				'post_status'    => 'any',
				'posts_per_page' => isset( $input['number'] ) ? (int) $input['number'] : 50,
			)
		);
		$only  = ! empty( $input['only_elementor'] );
		$items = array();
		foreach ( $posts as $post ) {
			$is_elementor = 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true );
			if ( $only && ! $is_elementor ) {
				continue;
			}
			$items[] = array(
				'id'           => $post->ID,
				'title'        => get_the_title( $post ),
				'type'         => $post->post_type,
				'status'       => $post->post_status,
				'is_elementor' => $is_elementor,
				'url'          => get_permalink( $post ),
			);
		}
		return array(
			'count' => count( $items ),
			'items' => $items,
		);
	}

	/**
	 * Devuelve el árbol completo.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_data( $input ) {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$data = self::read_data( (int) ( $input['id'] ?? 0 ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return array(
			'id'   => (int) $input['id'],
			'data' => $data,
		);
	}

	/**
	 * Devuelve la estructura simplificada.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_structure( $input ) {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$data = self::read_data( (int) ( $input['id'] ?? 0 ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return array(
			'id'        => (int) $input['id'],
			'structure' => array_map( array( __CLASS__, 'summarize' ), $data ),
		);
	}

	/**
	 * Devuelve un único elemento.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_element( $input ) {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$data = self::read_data( (int) ( $input['id'] ?? 0 ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$el_id = (string) ( $input['element_id'] ?? '' );
		$found = &self::find_ref( $data, $el_id );
		if ( null === $found ) {
			return new \WP_Error( 'renova_mcp_element_not_found', sprintf( 'No se encontró el elemento "%s".', $el_id ) );
		}
		return array(
			'id'      => (int) $input['id'],
			'element' => $found,
		);
	}

	/**
	 * Busca elementos por tipo.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function find_elements( $input ) {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$data = self::read_data( (int) ( $input['id'] ?? 0 ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$widget  = isset( $input['widget_type'] ) ? (string) $input['widget_type'] : '';
		$el_type = isset( $input['el_type'] ) ? (string) $input['el_type'] : '';
		$matches = array();
		self::walk(
			$data,
			static function ( $el ) use ( $widget, $el_type, &$matches ) {
				$ok = true;
				if ( '' !== $widget ) {
					$ok = ( ( $el['widgetType'] ?? '' ) === $widget );
				}
				if ( $ok && '' !== $el_type ) {
					$ok = ( ( $el['elType'] ?? '' ) === $el_type );
				}
				if ( $ok && ( '' !== $widget || '' !== $el_type ) ) {
					$matches[] = self::summarize( $el );
				}
			}
		);
		return array(
			'id'      => (int) $input['id'],
			'count'   => count( $matches ),
			'matches' => $matches,
		);
	}

	/**
	 * Devuelve los ajustes de página.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_page_settings( $input ) {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$id = (int) ( $input['id'] ?? 0 );
		if ( ! get_post( $id ) ) {
			return new \WP_Error( 'renova_mcp_post_not_found', 'No se encontró el contenido indicado.' );
		}
		return array(
			'id'       => $id,
			'settings' => get_post_meta( $id, '_elementor_page_settings', true ) ?: array(),
		);
	}

	/* ===================================================================== */
	/* Implementaciones — escritura                                           */
	/* ===================================================================== */

	/**
	 * Reemplaza todo el árbol.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function update_data( $input ) {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$id = (int) ( $input['id'] ?? 0 );
		if ( ! get_post( $id ) ) {
			return new \WP_Error( 'renova_mcp_post_not_found', 'No se encontró el contenido indicado.' );
		}
		$data = $input['data'] ?? null;
		if ( is_string( $data ) ) {
			$data = json_decode( $data, true );
		}
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'renova_mcp_elementor_bad_data', 'El campo "data" debe ser un array de elementos Elementor.' );
		}
		self::backup_data( array( 'id' => $id ) );
		$ok = self::write_data( $id, $data );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return array(
			'success' => true,
			'id'      => $id,
			'message' => 'Datos de Elementor reemplazados.',
		);
	}

	/**
	 * Fusiona/reemplaza ajustes de un elemento.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function update_element( $input ) {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$id   = (int) ( $input['id'] ?? 0 );
		$data = self::read_data( $id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$el_id    = (string) ( $input['element_id'] ?? '' );
		$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();
		$found    = &self::find_ref( $data, $el_id );
		if ( null === $found ) {
			return new \WP_Error( 'renova_mcp_element_not_found', sprintf( 'No se encontró el elemento "%s".', $el_id ) );
		}
		$current = isset( $found['settings'] ) && is_array( $found['settings'] ) ? $found['settings'] : array();
		$found['settings'] = ! empty( $input['replace'] ) ? $settings : array_merge( $current, $settings );

		$ok = self::write_data( $id, $data );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return array(
			'success'    => true,
			'id'         => $id,
			'element_id' => $el_id,
			'settings'   => $found['settings'],
		);
	}

	/**
	 * Añade un elemento al árbol.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function add_element( $input ) {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$id   = (int) ( $input['id'] ?? 0 );
		$data = self::read_data( $id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// Construye el elemento a insertar.
		if ( ! empty( $input['element'] ) && is_array( $input['element'] ) ) {
			$element = $input['element'];
		} else {
			$el_type = isset( $input['el_type'] ) ? (string) $input['el_type'] : 'widget';
			$element = array(
				'elType'   => $el_type,
				'settings' => isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array(),
				'elements' => array(),
			);
			if ( 'widget' === $el_type ) {
				$element['widgetType'] = isset( $input['widget_type'] ) ? (string) $input['widget_type'] : 'text-editor';
			}
		}
		self::regenerate_ids( $element );

		$parent_id = isset( $input['parent_id'] ) ? (string) $input['parent_id'] : '';
		$position  = isset( $input['position'] ) ? (int) $input['position'] : -1;

		if ( '' === $parent_id ) {
			$target = &$data;
		} else {
			$parent = &self::find_ref( $data, $parent_id );
			if ( null === $parent ) {
				return new \WP_Error( 'renova_mcp_parent_not_found', sprintf( 'No se encontró el padre "%s".', $parent_id ) );
			}
			if ( ! isset( $parent['elements'] ) || ! is_array( $parent['elements'] ) ) {
				$parent['elements'] = array();
			}
			$target = &$parent['elements'];
		}

		if ( $position < 0 || $position >= count( $target ) ) {
			$target[] = $element;
		} else {
			array_splice( $target, $position, 0, array( $element ) );
		}

		$ok = self::write_data( $id, $data );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return array(
			'success'    => true,
			'id'         => $id,
			'element_id' => $element['id'],
			'message'    => 'Elemento añadido.',
		);
	}

	/**
	 * Elimina un elemento.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function delete_element( $input ) {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$id   = (int) ( $input['id'] ?? 0 );
		$data = self::read_data( $id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$el_id  = (string) ( $input['element_id'] ?? '' );
		$index  = -1;
		$parent = &self::find_parent_ref( $data, $el_id, $index );
		if ( null === $parent || $index < 0 ) {
			return new \WP_Error( 'renova_mcp_element_not_found', sprintf( 'No se encontró el elemento "%s".', $el_id ) );
		}
		self::backup_data( array( 'id' => $id ) );
		array_splice( $parent, $index, 1 );

		$ok = self::write_data( $id, $data );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return array(
			'success'    => true,
			'id'         => $id,
			'element_id' => $el_id,
			'message'    => 'Elemento eliminado.',
		);
	}

	/**
	 * Mueve un elemento a otro padre/posición.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function move_element( $input ) {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$id   = (int) ( $input['id'] ?? 0 );
		$data = self::read_data( $id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$el_id = (string) ( $input['element_id'] ?? '' );
		$index = -1;
		$parent = &self::find_parent_ref( $data, $el_id, $index );
		if ( null === $parent || $index < 0 ) {
			return new \WP_Error( 'renova_mcp_element_not_found', sprintf( 'No se encontró el elemento "%s".', $el_id ) );
		}
		// Extrae el elemento.
		$moved = $parent[ $index ];
		array_splice( $parent, $index, 1 );

		$new_parent_id = isset( $input['new_parent_id'] ) ? (string) $input['new_parent_id'] : '';
		$position      = isset( $input['position'] ) ? (int) $input['position'] : -1;

		if ( '' === $new_parent_id ) {
			$target = &$data;
		} else {
			$np = &self::find_ref( $data, $new_parent_id );
			if ( null === $np ) {
				return new \WP_Error( 'renova_mcp_parent_not_found', sprintf( 'No se encontró el padre "%s".', $new_parent_id ) );
			}
			if ( ! isset( $np['elements'] ) || ! is_array( $np['elements'] ) ) {
				$np['elements'] = array();
			}
			$target = &$np['elements'];
		}

		if ( $position < 0 || $position >= count( $target ) ) {
			$target[] = $moved;
		} else {
			array_splice( $target, $position, 0, array( $moved ) );
		}

		$ok = self::write_data( $id, $data );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return array(
			'success'    => true,
			'id'         => $id,
			'element_id' => $el_id,
			'message'    => 'Elemento movido.',
		);
	}

	/**
	 * Duplica un elemento justo después del original.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function duplicate_element( $input ) {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$id   = (int) ( $input['id'] ?? 0 );
		$data = self::read_data( $id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$el_id = (string) ( $input['element_id'] ?? '' );
		$index = -1;
		$parent = &self::find_parent_ref( $data, $el_id, $index );
		if ( null === $parent || $index < 0 ) {
			return new \WP_Error( 'renova_mcp_element_not_found', sprintf( 'No se encontró el elemento "%s".', $el_id ) );
		}
		$clone = $parent[ $index ];
		self::regenerate_ids( $clone );
		array_splice( $parent, $index + 1, 0, array( $clone ) );

		$ok = self::write_data( $id, $data );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return array(
			'success'    => true,
			'id'         => $id,
			'element_id' => $el_id,
			'new_id'     => $clone['id'],
			'message'    => 'Elemento duplicado.',
		);
	}

	/**
	 * Reordena los hijos directos de un padre.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function reorder_elements( $input ) {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$id   = (int) ( $input['id'] ?? 0 );
		$data = self::read_data( $id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$order = isset( $input['order'] ) && is_array( $input['order'] ) ? array_map( 'strval', $input['order'] ) : array();
		if ( empty( $order ) ) {
			return new \WP_Error( 'renova_mcp_missing_order', 'Falta la lista "order" de IDs.' );
		}
		$parent_id = isset( $input['parent_id'] ) ? (string) $input['parent_id'] : '';

		if ( '' === $parent_id ) {
			$target = &$data;
		} else {
			$parent = &self::find_ref( $data, $parent_id );
			if ( null === $parent || empty( $parent['elements'] ) ) {
				return new \WP_Error( 'renova_mcp_parent_not_found', sprintf( 'No se encontró el padre "%s" o no tiene hijos.', $parent_id ) );
			}
			$target = &$parent['elements'];
		}

		$by_id = array();
		foreach ( $target as $child ) {
			if ( isset( $child['id'] ) ) {
				$by_id[ $child['id'] ] = $child;
			}
		}
		$reordered = array();
		foreach ( $order as $oid ) {
			if ( isset( $by_id[ $oid ] ) ) {
				$reordered[] = $by_id[ $oid ];
				unset( $by_id[ $oid ] );
			}
		}
		// Conserva al final los que no se mencionaron.
		foreach ( $by_id as $child ) {
			$reordered[] = $child;
		}
		$target = $reordered;

		$ok = self::write_data( $id, $data );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return array(
			'success' => true,
			'id'      => $id,
			'message' => 'Elementos reordenados.',
		);
	}

	/**
	 * Fusiona ajustes de página.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function update_page_settings( $input ) {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$id = (int) ( $input['id'] ?? 0 );
		if ( ! get_post( $id ) ) {
			return new \WP_Error( 'renova_mcp_post_not_found', 'No se encontró el contenido indicado.' );
		}
		$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();
		$current  = get_post_meta( $id, '_elementor_page_settings', true );
		$current  = is_array( $current ) ? $current : array();
		$merged   = array_merge( $current, $settings );
		update_post_meta( $id, '_elementor_page_settings', $merged );
		self::flush_page_cache( $id );
		return array(
			'success'  => true,
			'id'       => $id,
			'settings' => $merged,
		);
	}

	/* ===================================================================== */
	/* Implementaciones — copias de seguridad y caché                         */
	/* ===================================================================== */

	/**
	 * Guarda una copia de seguridad del `_elementor_data` actual.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function backup_data( $input ) {
		$id = (int) ( $input['id'] ?? 0 );
		if ( ! get_post( $id ) ) {
			return new \WP_Error( 'renova_mcp_post_not_found', 'No se encontró el contenido indicado.' );
		}
		$raw       = get_post_meta( $id, '_elementor_data', true );
		$timestamp = current_time( 'mysql' );
		update_post_meta(
			$id,
			self::BACKUP_META,
			array(
				'data' => $raw,
				'time' => $timestamp,
			)
		);
		return array(
			'success' => true,
			'id'      => $id,
			'time'    => $timestamp,
			'message' => 'Copia de seguridad guardada.',
		);
	}

	/**
	 * Restaura la última copia de seguridad.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function restore_backup( $input ) {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$id     = (int) ( $input['id'] ?? 0 );
		$backup = get_post_meta( $id, self::BACKUP_META, true );
		if ( empty( $backup ) || ! isset( $backup['data'] ) ) {
			return new \WP_Error( 'renova_mcp_no_backup', 'No hay copia de seguridad para esta página.' );
		}
		update_metadata( 'post', $id, '_elementor_data', wp_slash( (string) $backup['data'] ) );
		self::flush_page_cache( $id );
		return array(
			'success' => true,
			'id'      => $id,
			'time'    => $backup['time'] ?? null,
			'message' => 'Copia de seguridad restaurada.',
		);
	}

	/**
	 * Limpia la caché de Elementor (global o por página).
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function clear_cache( $input ) {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		if ( $id ) {
			self::flush_page_cache( $id );
			return array(
				'success' => true,
				'id'      => $id,
				'message' => 'Caché de la página regenerada.',
			);
		}
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
		return array(
			'success' => true,
			'message' => 'Caché global de Elementor regenerada.',
		);
	}

	/* ===================================================================== */
	/* Implementaciones — plantillas                                          */
	/* ===================================================================== */

	/**
	 * Lista plantillas de la biblioteca.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function list_templates( $input ) {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$args = array(
			'post_type'      => 'elementor_library',
			'post_status'    => 'any',
			'posts_per_page' => 100,
		);
		if ( ! empty( $input['type'] ) ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_elementor_template_type',
					'value' => sanitize_key( $input['type'] ),
				),
			);
		}
		$items = array();
		foreach ( get_posts( $args ) as $tpl ) {
			$items[] = array(
				'id'    => $tpl->ID,
				'title' => get_the_title( $tpl ),
				'type'  => get_post_meta( $tpl->ID, '_elementor_template_type', true ),
				'date'  => $tpl->post_modified_gmt,
			);
		}
		return array(
			'count'     => count( $items ),
			'templates' => $items,
		);
	}

	/**
	 * Devuelve el árbol de una plantilla.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_template( $input ) {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$id  = (int) ( $input['id'] ?? 0 );
		$tpl = get_post( $id );
		if ( ! $tpl || 'elementor_library' !== $tpl->post_type ) {
			return new \WP_Error( 'renova_mcp_template_not_found', 'No se encontró la plantilla indicada.' );
		}
		$data = self::read_data( $id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return array(
			'id'    => $id,
			'title' => get_the_title( $tpl ),
			'type'  => get_post_meta( $id, '_elementor_template_type', true ),
			'data'  => $data,
		);
	}

	/**
	 * Crea una plantilla en la biblioteca.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function create_template( $input ) {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$type = isset( $input['type'] ) ? sanitize_key( $input['type'] ) : 'page';

		if ( ! empty( $input['source_id'] ) ) {
			$data = self::read_data( (int) $input['source_id'] );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
		} else {
			$data = $input['data'] ?? array();
			if ( is_string( $data ) ) {
				$data = json_decode( $data, true );
			}
			if ( ! is_array( $data ) ) {
				return new \WP_Error( 'renova_mcp_elementor_bad_data', 'Indica "data" (array Elementor) o "source_id".' );
			}
		}

		$tpl_id = wp_insert_post(
			array(
				'post_title'  => isset( $input['title'] ) ? wp_strip_all_tags( $input['title'] ) : 'Plantilla',
				'post_type'   => 'elementor_library',
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $tpl_id ) ) {
			return $tpl_id;
		}

		update_post_meta( $tpl_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $tpl_id, '_elementor_template_type', $type );
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			update_post_meta( $tpl_id, '_elementor_version', ELEMENTOR_VERSION );
		}
		wp_set_object_terms( $tpl_id, $type, 'elementor_library_type' );
		self::write_data( $tpl_id, $data );

		return array(
			'success'     => true,
			'template_id' => $tpl_id,
			'type'        => $type,
			'edit'        => get_edit_post_link( $tpl_id, 'raw' ),
			'message'     => 'Plantilla creada.',
		);
	}

	/**
	 * Aplica una plantilla a una página.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function apply_template( $input ) {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$id          = (int) ( $input['id'] ?? 0 );
		$template_id = (int) ( $input['template_id'] ?? 0 );
		if ( ! get_post( $id ) ) {
			return new \WP_Error( 'renova_mcp_post_not_found', 'No se encontró la página destino.' );
		}
		$tpl_data = self::read_data( $template_id );
		if ( is_wp_error( $tpl_data ) ) {
			return $tpl_data;
		}
		// Clona los elementos de la plantilla con nuevos IDs.
		foreach ( $tpl_data as &$el ) {
			self::regenerate_ids( $el );
		}
		unset( $el );

		$mode = isset( $input['mode'] ) ? (string) $input['mode'] : 'replace';
		self::backup_data( array( 'id' => $id ) );

		if ( 'append' === $mode ) {
			$current = self::read_data( $id );
			$current = is_wp_error( $current ) ? array() : $current;
			$data    = array_merge( $current, $tpl_data );
		} else {
			$data = $tpl_data;
		}

		$ok = self::write_data( $id, $data );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return array(
			'success'     => true,
			'id'          => $id,
			'template_id' => $template_id,
			'mode'        => $mode,
			'message'     => 'Plantilla aplicada.',
		);
	}

	/* ===================================================================== */
	/* Implementaciones — kit global                                          */
	/* ===================================================================== */

	/**
	 * Devuelve el ID del kit activo.
	 *
	 * @return int
	 */
	private static function active_kit_id() {
		return (int) get_option( 'elementor_active_kit' );
	}

	/**
	 * Devuelve los ajustes globales del kit activo.
	 *
	 * @return array|\WP_Error
	 */
	public static function get_global_settings() {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$kit_id = self::active_kit_id();
		if ( ! $kit_id ) {
			return new \WP_Error( 'renova_mcp_no_kit', 'No hay un kit de Elementor activo.' );
		}
		$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
		return array(
			'kit_id'   => $kit_id,
			'settings' => is_array( $settings ) ? $settings : array(),
		);
	}

	/**
	 * Fusiona ajustes en el kit activo.
	 *
	 * @param array $input Entrada.
	 * @return array|\WP_Error
	 */
	public static function update_global_settings( $input ) {
		$err = self::require_elementor();
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$kit_id = self::active_kit_id();
		if ( ! $kit_id ) {
			return new \WP_Error( 'renova_mcp_no_kit', 'No hay un kit de Elementor activo.' );
		}
		$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();
		$current  = get_post_meta( $kit_id, '_elementor_page_settings', true );
		$current  = is_array( $current ) ? $current : array();
		$merged   = array_merge( $current, $settings );
		update_post_meta( $kit_id, '_elementor_page_settings', $merged );

		// Regenera el CSS global del kit.
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		return array(
			'success'  => true,
			'kit_id'   => $kit_id,
			'settings' => $merged,
			'message'  => 'Ajustes globales actualizados.',
		);
	}
}
