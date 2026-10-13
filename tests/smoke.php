<?php
/**
 * Prueba de humo del protocolo MCP contra un WordPress en marcha.
 *
 * No depende de PHPUnit: habla con el endpoint por HTTP igual que un cliente
 * MCP real. La lanza tests/run.sh, que prepara el sitio y las credenciales.
 *
 * Variables de entorno:
 *   RENOVA_URL             URL base del sitio (ej. http://127.0.0.1:8899)
 *   RENOVA_TOKEN           Token con acceso completo
 *   RENOVA_TOKEN_READONLY  Token de solo lectura limitado al grupo "content"
 *   RENOVA_BASIC           "usuario:contraseña de aplicación"
 *   RENOVA_LOGIN           "usuario:contraseña" para la pantalla de consentimiento OAuth
 *   RENOVA_EXPECTED_TOOLS  Número de herramientas que debe exponer el servidor
 *   RENOVA_PHASE           "main" (por defecto), "restricted" o "ratelimit"
 *
 * @package Renova\MCP
 */

// phpcs:disable -- Script de consola para pruebas, fuera de WordPress.

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$base  = rtrim( (string) getenv( 'RENOVA_URL' ), '/' );
$mcp   = $base . '/wp-json/renova-mcp/v1/mcp';
$phase = getenv( 'RENOVA_PHASE' ) ?: 'main';

$passed = 0;
$failed = 0;

function check( $label, $condition, $detail = '' ) {
	global $passed, $failed;
	if ( $condition ) {
		++$passed;
		echo "  ok    {$label}\n";
		return;
	}
	++$failed;
	echo "  FALLA {$label}" . ( '' !== $detail ? "\n          " . $detail : '' ) . "\n";
}

function section( $title ) {
	echo "\n{$title}\n";
}

/**
 * Petición HTTP. Devuelve estado, cabeceras (en minúsculas) y cuerpo.
 */
function http( $method, $url, array $headers = array(), $body = null, $cookie_jar = null ) {
	$ch = curl_init( $url );
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_TIMEOUT        => 60,
			CURLOPT_HTTPHEADER     => $headers,
		)
	);
	if ( null !== $body ) {
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
	}
	if ( null !== $cookie_jar ) {
		curl_setopt( $ch, CURLOPT_COOKIEJAR, $cookie_jar );
		curl_setopt( $ch, CURLOPT_COOKIEFILE, $cookie_jar );
	}

	$raw = curl_exec( $ch );
	if ( false === $raw ) {
		fwrite( STDERR, 'Error de conexión: ' . curl_error( $ch ) . "\n" );
		exit( 2 );
	}
	$status      = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$header_size = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );

	$headers_out = array();
	foreach ( explode( "\r\n", substr( $raw, 0, $header_size ) ) as $line ) {
		if ( false !== strpos( $line, ':' ) ) {
			list( $name, $value )                  = explode( ':', $line, 2 );
			$headers_out[ strtolower( trim( $name ) ) ] = trim( $value );
		}
	}

	return array(
		'status'  => $status,
		'headers' => $headers_out,
		'body'    => substr( $raw, $header_size ),
		'json'    => json_decode( substr( $raw, $header_size ), true ),
	);
}

/**
 * Llamada JSON-RPC al endpoint MCP.
 */
function rpc( $auth, $method, array $params = array(), $session = null, $id = 1 ) {
	global $mcp;

	$headers = array( 'Content-Type: application/json', 'Accept: application/json, text/event-stream' );
	if ( null !== $auth ) {
		$headers[] = 'Authorization: ' . $auth;
	}
	if ( null !== $session ) {
		$headers[] = 'Mcp-Session-Id: ' . $session;
		$headers[] = 'MCP-Protocol-Version: 2025-06-18';
	}

	$message = array(
		'jsonrpc' => '2.0',
		'method'  => $method,
	);
	if ( null !== $id ) {
		$message['id'] = $id;
	}
	if ( ! empty( $params ) ) {
		$message['params'] = $params;
	}

	return http( 'POST', $mcp, $headers, json_encode( $message ) );
}

/**
 * Abre una sesión MCP y devuelve su identificador.
 */
function open_session( $auth ) {
	$response = rpc(
		$auth,
		'initialize',
		array(
			'protocolVersion' => '2025-06-18',
			'capabilities'    => new stdClass(),
			'clientInfo'      => array(
				'name'    => 'renova-smoke',
				'version' => '1.0.0',
			),
		)
	);

	return array( $response, isset( $response['headers']['mcp-session-id'] ) ? $response['headers']['mcp-session-id'] : null );
}

function call_tool( $auth, $session, $name, array $arguments = array() ) {
	return rpc(
		$auth,
		'tools/call',
		array(
			'name'      => $name,
			'arguments' => empty( $arguments ) ? new stdClass() : $arguments,
		),
		$session,
		7
	);
}

function b64url( $binary ) {
	return rtrim( strtr( base64_encode( $binary ), '+/', '-_' ), '=' );
}

$token    = 'Bearer ' . getenv( 'RENOVA_TOKEN' );
$readonly = 'Bearer ' . getenv( 'RENOVA_TOKEN_READONLY' );

// --- Fase aparte: límite de peticiones ---------------------------------------------
if ( 'ratelimit' === $phase ) {
	section( 'Límite de peticiones (configurado a 5 por minuto)' );
	list( , $session ) = open_session( $token );
	$limited           = null;
	for ( $i = 0; $i < 8; $i++ ) {
		$response = rpc( $token, 'ping', array(), $session );
		if ( 429 === $response['status'] ) {
			$limited = $response;
			break;
		}
	}
	check( 'responde 429 al superar el límite', null !== $limited );
	check( 'incluye Retry-After', null !== $limited && isset( $limited['headers']['retry-after'] ) );
	check( 'identifica el error', null !== $limited && 'renova_mcp_rate_limited' === ( $limited['json']['code'] ?? '' ) );

	echo "\n{$passed} comprobaciones correctas, {$failed} fallidas.\n";
	exit( $failed > 0 ? 1 : 0 );
}

// --- Fase aparte: ajustes globales --------------------------------------------------
if ( 'restricted' === $phase ) {
	section( 'Modo solo lectura y grupo "users" desactivado' );
	list( , $session ) = open_session( $token );
	$tools             = rpc( $token, 'tools/list', array(), $session )['json']['result']['tools'] ?? array();
	$names             = array_column( $tools, 'name' );
	$writers           = array_filter(
		$tools,
		static function ( $tool ) {
			return true !== ( $tool['annotations']['readOnlyHint'] ?? null );
		}
	);
	check( 'el token completo solo ve herramientas de lectura', count( $tools ) > 0 && 0 === count( $writers ), count( $writers ) . ' de escritura' );
	check( 'el grupo desactivado desaparece del listado', ! in_array( 'renova-mcp-list-users', $names, true ) );
	check( 'los demás grupos siguen visibles', in_array( 'renova-mcp-list-posts', $names, true ) && in_array( 'renova-mcp-list-plugins', $names, true ) );

	$write = call_tool( $token, $session, 'renova-mcp-create-post', array( 'title' => 'No debería crearse' ) );
	check( 'una escritura se rechaza aunque se llame por su nombre', isset( $write['json']['error'] ) || true === ( $write['json']['result']['isError'] ?? false ), substr( $write['body'], 0, 200 ) );

	$users = call_tool( $token, $session, 'renova-mcp-list-users' );
	check( 'y también una herramienta del grupo desactivado', isset( $users['json']['error'] ) || true === ( $users['json']['result']['isError'] ?? false ), substr( $users['body'], 0, 200 ) );

	echo "\n{$passed} comprobaciones correctas, {$failed} fallidas.\n";
	exit( $failed > 0 ? 1 : 0 );
}

// --- Autenticación -----------------------------------------------------------------
section( 'Autenticación' );

$anonymous = rpc( null, 'ping' );
check( 'sin credenciales responde 401', 401 === $anonymous['status'], 'estado ' . $anonymous['status'] );
check( 'el 401 anuncia Bearer en WWW-Authenticate', 0 === strpos( $anonymous['headers']['www-authenticate'] ?? '', 'Bearer ' ) );

$invalid = rpc( 'Bearer rmcp_' . str_repeat( 'x', 40 ), 'ping' );
check( 'un token inventado responde 401', 401 === $invalid['status'], 'estado ' . $invalid['status'] );
check( 'y lo identifica como invalid_token', false !== strpos( $invalid['headers']['www-authenticate'] ?? '', 'error="invalid_token"' ) );
check( 'con un mensaje propio', 'renova_mcp_invalid_token' === ( $invalid['json']['code'] ?? '' ) );

list( $init, $session ) = open_session( $token );
check( 'initialize con token Bearer responde 200', 200 === $init['status'], 'estado ' . $init['status'] . ' ' . substr( $init['body'], 0, 200 ) );
check( 'devuelve Mcp-Session-Id', ! empty( $session ) );
check( 'negocia la versión del protocolo', '2025-06-18' === ( $init['json']['result']['protocolVersion'] ?? '' ) );
check( 'anuncia la capacidad de herramientas', isset( $init['json']['result']['capabilities']['tools'] ) );

$basic_credentials = (string) getenv( 'RENOVA_BASIC' );
if ( '' !== $basic_credentials ) {
	list( $basic_init ) = open_session( 'Basic ' . base64_encode( $basic_credentials ) );
	check( 'la contraseña de aplicación (Basic) sigue funcionando', 200 === $basic_init['status'], 'estado ' . $basic_init['status'] );
}

// --- Transporte --------------------------------------------------------------------
section( 'Transporte Streamable HTTP' );

$notification = rpc( $token, 'notifications/initialized', array(), $session, null );
check( 'una notificación responde 202 sin cuerpo', 202 === $notification['status'] && '' === trim( $notification['body'] ), 'estado ' . $notification['status'] );

$ping = rpc( $token, 'ping', array(), $session );
check( 'ping responde con un resultado', 200 === $ping['status'] && array_key_exists( 'result', (array) $ping['json'] ) );

$get = http( 'GET', $mcp, array( 'Authorization: ' . $token, 'Accept: text/event-stream' ) );
check( 'GET responde 405 (sin flujo SSE, permitido por la especificación)', 405 === $get['status'], 'estado ' . $get['status'] );

$no_session = rpc( $token, 'tools/list' );
check( 'una petición sin sesión se rechaza', isset( $no_session['json']['error'] ) );

$unknown = rpc( $token, 'no/existe', array(), $session );
check( 'un método desconocido devuelve -32601', -32601 === ( $unknown['json']['error']['code'] ?? 0 ) );

// --- Herramientas ------------------------------------------------------------------
section( 'Herramientas' );

$list  = rpc( $token, 'tools/list', array(), $session );
$tools = $list['json']['result']['tools'] ?? array();
check( 'tools/list responde 200', 200 === $list['status'] );

$expected = (int) getenv( 'RENOVA_EXPECTED_TOOLS' );
if ( $expected > 0 ) {
	check( "expone exactamente {$expected} herramientas", count( $tools ) === $expected, 'recibidas ' . count( $tools ) );
}

$hidden_integrations = array_filter(
	$tools,
	static function ( $tool ) {
		return (bool) preg_match( '/^renova-mcp-(acf|elementor|rankmath)-/', $tool['name'] );
	}
);
check( 'no expone herramientas de plugins que no están activos', 0 === count( $hidden_integrations ), count( $hidden_integrations ) . ' expuestas' );

$bad_names       = array();
$bad_schemas     = array();
$bad_annotations = array();
$undescribed     = array();
foreach ( $tools as $tool ) {
	if ( ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', $tool['name'] ) ) {
		$bad_names[] = $tool['name'];
	}
	if ( 'object' !== ( $tool['inputSchema']['type'] ?? '' ) ) {
		$bad_schemas[] = $tool['name'];
	}
	if ( '' === trim( $tool['description'] ?? '' ) ) {
		$undescribed[] = $tool['name'];
	}

	$annotations = $tool['annotations'] ?? array();
	$is_read     = true === ( $annotations['readOnlyHint'] ?? null );
	$is_write    = false === ( $annotations['readOnlyHint'] ?? null ) && is_bool( $annotations['destructiveHint'] ?? null ) && is_bool( $annotations['idempotentHint'] ?? null );
	if ( ! $is_read && ! $is_write ) {
		$bad_annotations[] = $tool['name'];
	}
}
check( 'todos los nombres caben en los límites de los clientes (64 caracteres)', empty( $bad_names ), implode( ', ', $bad_names ) );
check( 'todas declaran un inputSchema de tipo object', empty( $bad_schemas ), implode( ', ', $bad_schemas ) );
check( 'todas tienen descripción', empty( $undescribed ), implode( ', ', $undescribed ) );
check( 'todas declaran si leen, destruyen o son idempotentes', empty( $bad_annotations ), implode( ', ', $bad_annotations ) );

$info = call_tool( $token, $session, 'renova-mcp-get-site-info' );
check( 'una herramienta de lectura devuelve contenido', 200 === $info['status'] && empty( $info['json']['result']['isError'] ) && ! empty( $info['json']['result']['content'] ), substr( $info['body'], 0, 300 ) );

$created = call_tool(
	$token,
	$session,
	'renova-mcp-create-post',
	array(
		'title'     => 'Prueba de humo MCP',
		'content'   => 'Creado por tests/smoke.php',
		'post_type' => 'post',
		'status'    => 'draft',
	)
);
$post_id = (int) ( $created['json']['result']['structuredContent']['id'] ?? 0 );
check( 'una herramienta de escritura crea contenido', $post_id > 0, substr( $created['body'], 0, 300 ) );

if ( $post_id > 0 ) {
	$deleted = call_tool(
		$token,
		$session,
		'renova-mcp-delete-post',
		array(
			'id'    => $post_id,
			'force' => true,
		)
	);
	check( 'y una destructiva lo elimina', empty( $deleted['json']['result']['isError'] ), substr( $deleted['body'], 0, 300 ) );
}

$missing = call_tool( $token, $session, 'renova-mcp-no-existe' );
check( 'una herramienta inexistente devuelve un error de protocolo', isset( $missing['json']['error'] ) );

// --- Alcance de los tokens ---------------------------------------------------------
section( 'Alcance de los tokens' );

list( $ro_init, $ro_session ) = open_session( $readonly );
check( 'el token limitado abre sesión', 200 === $ro_init['status'] );

$ro_list  = rpc( $readonly, 'tools/list', array(), $ro_session );
$ro_tools = $ro_list['json']['result']['tools'] ?? array();
$ro_write = array_filter(
	$ro_tools,
	static function ( $tool ) {
		return true !== ( $tool['annotations']['readOnlyHint'] ?? null );
	}
);
$ro_names = array_column( $ro_tools, 'name' );
check( 'solo ve una parte de las herramientas', count( $ro_tools ) > 0 && count( $ro_tools ) < count( $tools ), count( $ro_tools ) . ' de ' . count( $tools ) );
check( 'ninguna de las que ve es de escritura', 0 === count( $ro_write ), count( $ro_write ) . ' de escritura' );
check( 've las de su grupo (list-posts)', in_array( 'renova-mcp-list-posts', $ro_names, true ) );
check( 'no ve las de otros grupos (list-users, list-plugins)', ! in_array( 'renova-mcp-list-users', $ro_names, true ) && ! in_array( 'renova-mcp-list-plugins', $ro_names, true ) );

$ro_read = call_tool( $readonly, $ro_session, 'renova-mcp-list-posts' );
check( 'puede leer dentro de su grupo', empty( $ro_read['json']['result']['isError'] ) && ! isset( $ro_read['json']['error'] ), substr( $ro_read['body'], 0, 300 ) );

$ro_create = call_tool(
	$readonly,
	$ro_session,
	'renova-mcp-create-post',
	array(
		'title'  => 'No debería crearse',
		'status' => 'draft',
	)
);
check( 'no puede escribir aunque llame a la herramienta por su nombre', true === ( $ro_create['json']['result']['isError'] ?? false ), substr( $ro_create['body'], 0, 300 ) );

$ro_users = call_tool( $readonly, $ro_session, 'renova-mcp-list-users' );
check( 'no puede leer fuera de su grupo', true === ( $ro_users['json']['result']['isError'] ?? false ), substr( $ro_users['body'], 0, 300 ) );

// --- OAuth 2.1 ---------------------------------------------------------------------
section( 'OAuth 2.1' );

$resource_metadata = http( 'GET', $base . '/.well-known/oauth-protected-resource/wp-json/renova-mcp/v1/mcp' );
check( 'publica los metadatos del recurso protegido', 200 === $resource_metadata['status'] && $mcp === ( $resource_metadata['json']['resource'] ?? '' ), 'estado ' . $resource_metadata['status'] );

$issuer = $resource_metadata['json']['authorization_servers'][0] ?? '';
$as     = http( 'GET', $issuer . '/.well-known/oauth-authorization-server' );
check( 'publica los metadatos del servidor de autorización', 200 === $as['status'] && $issuer === ( $as['json']['issuer'] ?? null ) );
check( 'exige PKCE S256', array( 'S256' ) === ( $as['json']['code_challenge_methods_supported'] ?? null ) );

$challenge_header = rpc( null, 'ping' )['headers']['www-authenticate'] ?? '';
check( 'el 401 apunta a los metadatos del recurso', false !== strpos( $challenge_header, 'resource_metadata="' . $base . '/.well-known/oauth-protected-resource' ) );

$redirect_uri = 'https://client.example/callback';
$registration = http(
	'POST',
	$as['json']['registration_endpoint'] ?? '',
	array( 'Content-Type: application/json' ),
	json_encode(
		array(
			'client_name'                => 'Cliente de prueba',
			'redirect_uris'              => array( $redirect_uri ),
			'token_endpoint_auth_method' => 'none',
		)
	)
);
$client_id    = $registration['json']['client_id'] ?? '';
check( 'registra un cliente dinámicamente', 201 === $registration['status'] && '' !== $client_id, substr( $registration['body'], 0, 200 ) );

$bad_registration = http(
	'POST',
	$as['json']['registration_endpoint'] ?? '',
	array( 'Content-Type: application/json' ),
	json_encode( array( 'redirect_uris' => array( 'javascript:alert(1)' ) ) )
);
check( 'rechaza redirect_uris peligrosas', 400 === $bad_registration['status'] );

$verifier  = b64url( random_bytes( 48 ) );
$challenge = b64url( hash( 'sha256', $verifier, true ) );
$authorize = ( $as['json']['authorization_endpoint'] ?? '' ) . '&' . http_build_query(
	array(
		'response_type'         => 'code',
		'client_id'             => $client_id,
		'redirect_uri'          => $redirect_uri,
		'state'                 => 'estado-123',
		'code_challenge'        => $challenge,
		'code_challenge_method' => 'S256',
	)
);

$logged_out = http( 'GET', $authorize );
check( 'sin sesión no muestra el consentimiento', 200 !== $logged_out['status'], 'estado ' . $logged_out['status'] );

$foreign = http( 'GET', str_replace( rawurlencode( $redirect_uri ), rawurlencode( 'https://evil.example/cb' ), $authorize ) );
check( 'una redirect_uri no registrada no redirige a ningún sitio', 400 === $foreign['status'] && ! isset( $foreign['headers']['location'] ), 'estado ' . $foreign['status'] );

$jar                   = tempnam( sys_get_temp_dir(), 'renova' );
list( $user, $pass )   = explode( ':', (string) getenv( 'RENOVA_LOGIN' ), 2 );
http( 'GET', $base . '/wp-login.php', array(), null, $jar );
$login = http(
	'POST',
	$base . '/wp-login.php',
	array( 'Content-Type: application/x-www-form-urlencoded' ),
	http_build_query(
		array(
			'log'        => $user,
			'pwd'        => $pass,
			'wp-submit'  => 'Log In',
			'testcookie' => '1',
		)
	),
	$jar
);
check( 'inicia sesión como administrador', 302 === $login['status'], 'estado ' . $login['status'] );

$consent = http( 'GET', $authorize, array(), null, $jar );
preg_match( '/name="_wpnonce" value="([a-z0-9]+)"/', $consent['body'], $nonce_match );
check( 'muestra la pantalla de consentimiento', 200 === $consent['status'] && ! empty( $nonce_match[1] ) && false !== strpos( $consent['body'], 'Cliente de prueba' ) );
check( 'la protege contra clickjacking', 'DENY' === ( $consent['headers']['x-frame-options'] ?? '' ) );

$forged = http( 'POST', $authorize, array( 'Content-Type: application/x-www-form-urlencoded' ), 'approve=1&groups[]=content&_wpnonce=falso', $jar );
check( 'sin nonce válido no emite código', ! isset( $forged['headers']['location'] ) );

$approval = http(
	'POST',
	$authorize,
	array( 'Content-Type: application/x-www-form-urlencoded' ),
	'approve=1&groups[]=content&groups[]=site&read_only=1&_wpnonce=' . ( $nonce_match[1] ?? '' ),
	$jar
);
parse_str( (string) parse_url( $approval['headers']['location'] ?? '', PHP_URL_QUERY ), $callback );
$code = $callback['code'] ?? '';
check( 'al autorizar redirige al cliente con el código', 302 === $approval['status'] && 0 === strpos( $approval['headers']['location'] ?? '', $redirect_uri . '?' ) && '' !== $code );
check( 'devuelve el state intacto', 'estado-123' === ( $callback['state'] ?? '' ) );

$token_endpoint = $as['json']['token_endpoint'] ?? '';
$exchange       = static function ( array $fields ) use ( $token_endpoint ) {
	return http( 'POST', $token_endpoint, array( 'Content-Type: application/x-www-form-urlencoded' ), http_build_query( $fields ) );
};

$grant = $exchange(
	array(
		'grant_type'    => 'authorization_code',
		'code'          => $code,
		'code_verifier' => $verifier,
		'client_id'     => $client_id,
		'redirect_uri'  => $redirect_uri,
	)
);
$access  = $grant['json']['access_token'] ?? '';
$refresh = $grant['json']['refresh_token'] ?? '';
check( 'canjea el código por tokens', 200 === $grant['status'] && '' !== $access && '' !== $refresh, substr( $grant['body'], 0, 200 ) );
check( 'la respuesta de token no se cachea', 'no-store' === ( $grant['headers']['cache-control'] ?? '' ) );

$reuse = $exchange(
	array(
		'grant_type'    => 'authorization_code',
		'code'          => $code,
		'code_verifier' => $verifier,
		'client_id'     => $client_id,
		'redirect_uri'  => $redirect_uri,
	)
);
check( 'el código es de un solo uso', 400 === $reuse['status'] && 'invalid_grant' === ( $reuse['json']['error'] ?? '' ) );

list( $oauth_init, $oauth_session ) = open_session( 'Bearer ' . $access );
check( 'el token OAuth abre sesión MCP', 200 === $oauth_init['status'] );

$oauth_tools = rpc( 'Bearer ' . $access, 'tools/list', array(), $oauth_session )['json']['result']['tools'] ?? array();
$oauth_write = array_filter(
	$oauth_tools,
	static function ( $tool ) {
		return true !== ( $tool['annotations']['readOnlyHint'] ?? null );
	}
);
check( 'respeta el alcance elegido en el consentimiento', count( $oauth_tools ) > 0 && 0 === count( $oauth_write ) && ! in_array( 'renova-mcp-list-users', array_column( $oauth_tools, 'name' ), true ) );

$rotated = $exchange(
	array(
		'grant_type'    => 'refresh_token',
		'refresh_token' => $refresh,
		'client_id'     => $client_id,
	)
);
check( 'renueva con el token de refresco', 200 === $rotated['status'] && ! empty( $rotated['json']['access_token'] ) );

$stale_refresh = $exchange(
	array(
		'grant_type'    => 'refresh_token',
		'refresh_token' => $refresh,
		'client_id'     => $client_id,
	)
);
check( 'el token de refresco anterior queda invalidado', 400 === $stale_refresh['status'] );
check( 'y también el token de acceso anterior', 401 === rpc( 'Bearer ' . $access, 'ping', array(), $oauth_session )['status'] );

// PKCE: un segundo código con un verificador incorrecto.
$consent2 = http( 'GET', $authorize, array(), null, $jar );
preg_match( '/name="_wpnonce" value="([a-z0-9]+)"/', $consent2['body'], $nonce_match2 );
$approval2 = http( 'POST', $authorize, array( 'Content-Type: application/x-www-form-urlencoded' ), 'approve=1&groups[]=content&_wpnonce=' . ( $nonce_match2[1] ?? '' ), $jar );
parse_str( (string) parse_url( $approval2['headers']['location'] ?? '', PHP_URL_QUERY ), $callback2 );
$wrong_verifier = $exchange(
	array(
		'grant_type'    => 'authorization_code',
		'code'          => $callback2['code'] ?? '',
		'code_verifier' => b64url( random_bytes( 48 ) ),
		'client_id'     => $client_id,
		'redirect_uri'  => $redirect_uri,
	)
);
check( 'rechaza un code_verifier que no corresponde (PKCE)', 400 === $wrong_verifier['status'] && 'invalid_grant' === ( $wrong_verifier['json']['error'] ?? '' ) );

$denied = http( 'POST', $authorize, array( 'Content-Type: application/x-www-form-urlencoded' ), 'deny=1&_wpnonce=' . ( $nonce_match2[1] ?? '' ), $jar );
check( 'al cancelar devuelve access_denied al cliente', false !== strpos( $denied['headers']['location'] ?? '', 'error=access_denied' ) );

@unlink( $jar );

echo "\n{$passed} comprobaciones correctas, {$failed} fallidas.\n";
exit( $failed > 0 ? 1 : 0 );
