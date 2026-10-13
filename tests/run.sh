#!/usr/bin/env bash
#
# Prueba de humo de extremo a extremo.
#
# Monta un WordPress desechable sobre SQLite (sin Docker ni MySQL), activa el
# plugin, crea credenciales y lanza tests/smoke.php contra él.
#
# Requisitos: php (con sqlite3 y curl), wp-cli, curl y unzip.
#
#   bash tests/run.sh
#
# Variables opcionales:
#   RENOVA_TEST_DIR   Carpeta de trabajo (por defecto, una en el directorio temporal)
#   RENOVA_TEST_PORT  Puerto del servidor de pruebas (por defecto 8899)

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
WORK="${RENOVA_TEST_DIR:-${TMPDIR:-/tmp}/renova-mcp-test}"
PORT="${RENOVA_TEST_PORT:-8899}"
URL="http://127.0.0.1:${PORT}"
WP_ROOT="$WORK/wp"
WP_BIN="$(command -v wp)"

# Los avisos de PHP del propio wp-cli se desvían a un archivo: mezclados en la
# salida estropearían los valores que se capturan (tokens, contadores).
wpc() {
	php -d display_errors=stderr -d error_reporting=8191 "$WP_BIN" --path="$WP_ROOT" "$@" 2>>"$WORK/wp-cli.log"
}

fail() {
	echo "$1" >&2
	[ -f "$WORK/wp-cli.log" ] && tail -n 20 "$WORK/wp-cli.log" >&2
	exit 1
}

echo "Sintaxis PHP"
while IFS= read -r file; do
	php -l "$file" >/dev/null || fail "Error de sintaxis en $file"
done < <(find "$PLUGIN_DIR" -name '*.php' -not -path "$PLUGIN_DIR/vendor/*")

echo "Preparando WordPress en $WORK"
mkdir -p "$WORK/cache"
rm -rf "$WP_ROOT" "$WORK/wp-cli.log"
[ -f "$WORK/cache/wordpress.zip" ] || curl -sSfL -o "$WORK/cache/wordpress.zip" https://wordpress.org/latest.zip
[ -f "$WORK/cache/sqlite.zip" ] || curl -sSfL -o "$WORK/cache/sqlite.zip" https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip
unzip -q "$WORK/cache/wordpress.zip" -d "$WORK"
mv "$WORK/wordpress" "$WP_ROOT"
unzip -q "$WORK/cache/sqlite.zip" -d "$WP_ROOT/wp-content/plugins"

SQLITE_DIR="$WP_ROOT/wp-content/plugins/sqlite-database-integration"
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#${SQLITE_DIR}#" \
	-e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
	"$SQLITE_DIR/db.copy" > "$WP_ROOT/wp-content/db.php"

# Entorno "local": WordPress acepta contraseñas de aplicación y tokens sin HTTPS.
printf "define( 'WP_ENVIRONMENT_TYPE', 'local' );\ndefine( 'WP_DEBUG', true );\ndefine( 'WP_DEBUG_LOG', true );\ndefine( 'WP_DEBUG_DISPLAY', false );\n" > "$WORK/extra.php"
wpc config create --dbname=wp --dbuser=wp --dbpass=wp --skip-check --extra-php < "$WORK/extra.php" >/dev/null || fail "No se pudo crear wp-config.php"
wpc core install --url="$URL" --title="Renova MCP Test" --admin_user=admin --admin_password=admin --admin_email=admin@example.test --skip-email >/dev/null || fail "No se pudo instalar WordPress"
ln -s "$PLUGIN_DIR" "$WP_ROOT/wp-content/plugins/renova-mcp"
wpc plugin activate renova-mcp >/dev/null || fail "No se pudo activar el plugin"
wpc rewrite structure '/%postname%/' >/dev/null

echo "WordPress $(wpc core version), PHP $(php -r 'echo PHP_VERSION;')"

echo "Creando credenciales"
RENOVA_TOKEN="$(wpc eval 'echo Renova\MCP\Tokens::create( array( "name" => "smoke", "user_id" => 1 ) )["token"];')"
RENOVA_TOKEN_READONLY="$(wpc eval 'echo Renova\MCP\Tokens::create( array( "name" => "smoke-ro", "user_id" => 1, "scope" => array( "groups" => array( "content" ), "read_only" => true ) ) )["token"];')"
APP_PASSWORD="$(wpc user application-password create admin smoke --porcelain)"
RENOVA_EXPECTED_TOOLS="$(wpc eval 'echo count( Renova\MCP\Catalog::exposed_tool_ids() );')"
wpc eval 'Renova\MCP\Settings::update( array( "oauth_enabled" => true ) );'
[ -n "$RENOVA_TOKEN" ] && [ -n "$RENOVA_TOKEN_READONLY" ] && [ -n "$APP_PASSWORD" ] || fail "No se pudieron crear las credenciales"

php -S "127.0.0.1:${PORT}" -t "$WP_ROOT" "$PLUGIN_DIR/tests/router.php" >"$WORK/server.log" 2>&1 &
SERVER_PID=$!
trap 'kill "$SERVER_PID" 2>/dev/null || true' EXIT

for _ in $(seq 1 30); do
	curl -s -o /dev/null "$URL/wp-json/" && break
	sleep 0.5
done

export RENOVA_URL="$URL" RENOVA_TOKEN RENOVA_TOKEN_READONLY RENOVA_EXPECTED_TOOLS
export RENOVA_BASIC="admin:${APP_PASSWORD}" RENOVA_LOGIN="admin:admin"

STATUS=0
RENOVA_PHASE=main php "$PLUGIN_DIR/tests/smoke.php" || STATUS=1

wpc eval 'Renova\MCP\Settings::update( array( "read_only" => true, "disabled_groups" => array( "users" ) ) );'
RENOVA_PHASE=restricted php "$PLUGIN_DIR/tests/smoke.php" || STATUS=1

wpc eval 'Renova\MCP\Settings::update( array( "read_only" => false, "disabled_groups" => array(), "rate_limit" => 5 ) );'
RENOVA_PHASE=ratelimit php "$PLUGIN_DIR/tests/smoke.php" || STATUS=1

echo
echo "Registro de actividad"
wpc eval '
	$rows   = Renova\MCP\Audit_Log::recent( 500 );
	$calls  = array_filter( $rows, static function ( $r ) { return "tools/call" === $r["method"]; } );
	$denied = array_filter( $rows, static function ( $r ) { return "denied" === $r["status"]; } );
	$leaks  = array_filter( $rows, static function ( $r ) { return false !== strpos( $r["detail"], "Creado por tests" ); } );
	$checks = array(
		"registra las llamadas a herramientas"      => count( $calls ) >= 5,
		"registra los intentos con token inválido"  => count( $denied ) >= 1,
		"no vuelca el contenido de los argumentos"  => 0 === count( $leaks ),
	);
	$failed = 0;
	foreach ( $checks as $label => $ok ) {
		echo ( $ok ? "  ok    " : "  FALLA " ) . $label . "\n";
		$failed += $ok ? 0 : 1;
	}
	exit( $failed > 0 ? 1 : 0 );
' || STATUS=1

# Avisos de PHP registrados durante la prueba (se descartan los del phar de wp-cli).
NOTICES="$(grep "PHP \(Fatal\|Parse\|Warning\|Notice\|Deprecated\)" "$WP_ROOT/wp-content/debug.log" 2>/dev/null | grep -v "phar://" | sed 's/^\[[^]]*\] //' | sort | uniq -c || true)"
if [ -n "$NOTICES" ]; then
	echo
	echo "Avisos de PHP durante la prueba:"
	echo "$NOTICES" | head -n 20
	STATUS=1
fi

echo
[ "$STATUS" -eq 0 ] && echo "Todo correcto." || echo "Hay comprobaciones fallidas."
exit "$STATUS"
