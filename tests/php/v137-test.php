<?php
/**
 * Checks for the 1.3.7 content migration (audit 25 Oct, v2 phase 1): the
 * patch list loads, every 'find' is a valid, non-empty pattern, and each
 * patch applies to the page text read on 25 Oct (tests/php/fixtures/v137/,
 * the REST "rendered" content) and leaves none of the wrong sentences.
 * Runs without WordPress. Run: php tests/php/v137-test.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'RF_BRAND_NAME', 'ReceiptGenPro' );
define( 'RF_SUPPORT_EMAIL', 'support@example.com' );

function add_action() {}
function add_filter() {}
function date_i18n( $format ) {
	return gmdate( $format );
}
function home_url( $path = '' ) {
	return 'https://example.com' . $path;
}
function esc_html( $s ) {
	return htmlspecialchars( $s, ENT_QUOTES );
}
function esc_attr( $s ) {
	return htmlspecialchars( $s, ENT_QUOTES );
}
function wp_parse_args( $a, $d ) {
	return array_merge( $d, (array) $a );
}

$theme = dirname( __DIR__, 2 ) . '/wp-content/themes/receiptgenpro';
require $theme . '/inc/content-patches.php';

$failures = 0;
function check( $ok, $message ) {
	global $failures;
	if ( ! $ok ) {
		$failures++;
		echo "FAIL: $message\n";
	}
}

$patches = rf_v137_patches( 'October 25, 2026' );
check( count( $patches ) >= 7, 'the 1.3.7 patch list has every page' );

// The stored text may use straight or curly quotes; test both.
function rf_test_untexturize( $html ) {
	return str_replace( array( '&#8217;', '&#8216;', '&#8220;', '&#8221;' ), array( "'", "'", '"', '"' ), $html );
}

$wrong = array( 'estimated fair market value', 'nothing of substantial value', '<h2>Information We Do Collect</h2>', 'Does Not Use Cookies' );
foreach ( $patches as $slug => $page ) {
	check( in_array( $page['type'], array( 'page', 'post' ), true ), "/$slug/ has a post type" );
	foreach ( $page['patches'] as $patch ) {
		check( '' !== trim( (string) $patch['find'] ), "/$slug/ {$patch['label']}: empty find" );
		if ( ! empty( $patch['regex'] ) ) {
			check( false !== @preg_match( $patch['find'], '' ), "/$slug/ {$patch['label']}: invalid pattern" );
		}
	}
	$file = __DIR__ . "/fixtures/v137/$slug.html";
	check( is_file( $file ), "/$slug/ has a fixture" );
	if ( ! is_file( $file ) ) {
		continue;
	}
	foreach ( array( 'curly' => file_get_contents( $file ), 'straight' => rf_test_untexturize( file_get_contents( $file ) ) ) as $kind => $content ) {
		foreach ( $page['patches'] as $patch ) {
			list( $content, $status ) = rf_fixes_apply_patch( $content, $patch );
			check( 'applied' === $status, "/$slug/ ($kind quotes) {$patch['label']}: $status" );
		}
		foreach ( $wrong as $text ) {
			check( false === stripos( $content, $text ), "/$slug/ ($kind quotes) still says \"$text\"" );
		}
		// Running again changes nothing.
		$again = $content;
		foreach ( $page['patches'] as $patch ) {
			list( $again ) = rf_fixes_apply_patch( $again, $patch );
		}
		check( $again === $content, "/$slug/ ($kind quotes) changes on a second run" );
	}
}

// The policies name the storage keys the generator really uses.
$js = file_get_contents( $theme . '/assets/js/receipt-generator.js' ) . file_get_contents( $theme . '/assets/js/consent.js' );
preg_match_all( "#(?:var|const)\s+\w*KEY\s*=\s*'([a-z_-]+:?)#i", $js, $m );
check( count( $m[1] ) >= 4, 'storage keys found in the JS' );
foreach ( $m[1] as $key ) {
	check( false !== strpos( rf_v137_privacy_sections(), '<code>' . $key ), "the privacy policy names $key" );
}

$tasks = file_get_contents( $theme . '/inc/setup-tasks.php' );
check( false !== strpos( $tasks, 'rf_migrate_v137_content();' ), 'setup tasks run the 1.3.7 migration' );
check( false !== strpos( $tasks, "'rf_migration_v137'" ), 'the 1.3.7 flag is autoloaded' );

// A25-L2: the US rent form has "Received by", not the Indian PAN.
require $theme . '/inc/category-data.php';
$ids = array_column( rf_get_category_data( 'rent-receipt' )['form']['extra'], 'id' );
check( ! in_array( 'landlord_pan', $ids, true ), 'the rent receipt has no landlord PAN field' );
check( in_array( 'received_by', $ids, true ), 'the rent receipt has "Received by"' );

if ( $failures ) {
	echo "$failures failure(s)\n";
	exit( 1 );
}
echo "v137 checks passed\n";
