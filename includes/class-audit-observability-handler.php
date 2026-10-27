<?php
/**
 * Puente entre los eventos del MCP Adapter y el registro de actividad.
 *
 * @package Renova\MCP
 */

namespace Renova\MCP;

use WP\MCP\Infrastructure\Observability\Contracts\McpObservabilityHandlerInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Recibe los eventos `mcp.request` del adaptador.
 */
class Audit_Observability_Handler implements McpObservabilityHandlerInterface {

	/**
	 * Registra un evento del adaptador.
	 *
	 * @param string              $event       Nombre del evento.
	 * @param array<string,mixed> $tags        Etiquetas.
	 * @param float|null          $duration_ms Duración en milisegundos.
	 */
	public function record_event( string $event, array $tags = array(), ?float $duration_ms = null ): void {
		if ( 'mcp.request' !== $event ) {
			return;
		}

		Audit_Log::record_request( $tags, $duration_ms );
	}
}
