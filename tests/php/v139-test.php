<?php
/**
 * Theme 1.3.9 (audit of 25 Oct 2026, v2 Phase 3, A25-L13): the
 * Content-Security-Policy-Report-Only header, its policy and the requests
 * it is sent on. Runs without WordPress. Run: php tests/php/v139-test.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'RF_BRAND_NAME', 'ReceiptGenPro' );

function add_action() {}
function add_filter() {}
function remove_action() {}
function home_url( $path = '' ) {
	return 'https://example.com' . $path;
}
function is_admin() {
	return ! empty( $GLOBALS['rf_test_admin'] );
}
class WP {
	public $query_vars = array();
	public function __construct( $vars = array() ) {
		$this->query_vars = $vars;
	}
}

function date_i18n( $format ) {
	return gmdate( $format );
}
function untrailingslashit( $s ) {
	return rtrim( $s, '/\\' );
}

$theme = dirname( __DIR__, 2 ) . '/wp-content/themes/receiptgenpro';
foreach ( array( 'seo-meta', 'category-data', 'rent-laws', 'requirements', 'feature-pages', 'content-patches', 'seo-fixes' ) as $file ) {
	require $theme . '/inc/' . $file . '.php';
}

$failures = 0;
function check( $ok, $message ) {
	global $failures;
	if ( ! $ok ) {
		$failures++;
		echo "FAIL: $message\n";
	}
}

$site  = rf_csp_policy( false );
$embed = rf_csp_policy( true );
check(
	"default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://pagead2.googlesyndication.com https://www.googletagmanager.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; font-src 'self' data:; connect-src 'self' https:; frame-src 'self' https://googleads.g.doubleclick.net https://tpc.googlesyndication.com; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'" === $site,
	"site policy: $site"
);
check( substr( $embed, -strlen( '; frame-ancestors *' ) ) === '; frame-ancestors *', "embed policy ends with frame-ancestors *: $embed" );
check( false === strpos( $embed, "frame-ancestors 'self'" ), 'embed policy has no frame-ancestors self' );
check( false === strpos( $site . $embed, 'report-uri' ), 'no report-uri' );

// Pages get it; feeds, sitemaps, robots.txt and wp-admin don't.
check( rf_is_frontend_html( new WP( array( 'pagename' => 'hra-rent-receipt' ) ) ), 'a page' );
check( rf_is_frontend_html( new WP( array( 'rf_embed' => 'rent-receipt' ) ) ), 'an embed' );
check( rf_is_frontend_html( new WP() ), 'the homepage' );
check( rf_is_frontend_html( null ), 'no $wp' );
foreach ( array( 'feed' => 'feed', 'sitemap' => 'posts', 'robots' => '1', 'rest_route' => '/wp/v2/posts' ) as $var => $value ) {
	check( ! rf_is_frontend_html( new WP( array( $var => $value ) ) ), "not for $var" );
}
$GLOBALS['rf_test_admin'] = true;
check( ! rf_is_frontend_html( new WP() ), 'not in wp-admin' );
$GLOBALS['rf_test_admin'] = false;

// The header is sent next to the other security headers.
$src = file_get_contents( $theme . '/inc/seo-fixes.php' );
check( false !== strpos( $src, "header( 'Content-Security-Policy-Report-Only: ' . rf_csp_policy( \$embed ) );" ), 'rf_security_headers sends the report-only header' );
check( false !== strpos( $src, 'header( "Content-Security-Policy: frame-ancestors *" );' ), 'the enforcing embed frame-ancestors stays' );

if ( $failures ) {
	echo "$failures failure(s)\n";
	exit( 1 );
}
echo "v139 CSP checks passed\n";
