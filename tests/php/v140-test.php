<?php
/**
 * Theme 1.4.0 (audit of 25 Oct 2026, v2 Phase 4): the content migration
 * that lists rgp-business-profile loads, its finds are non-empty, valid
 * patterns, and each applies to the live page text of 25 Oct
 * (tests/php/fixtures/v140/) once; the currency list has the new codes
 * with their decimals. Runs without WordPress.
 * Run: php tests/php/v140-test.php
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
function esc_url( $s ) {
	return htmlspecialchars( $s, ENT_QUOTES );
}
function wp_parse_args( $a, $d ) {
	return array_merge( $d, (array) $a );
}
function wp_list_pluck( $list, $field ) {
	return array_map(
		function ( $row ) use ( $field ) {
			return $row[ $field ];
		},
		$list
	);
}

$theme = dirname( __DIR__, 2 ) . '/wp-content/themes/receiptgenpro';
require $theme . '/inc/content-patches.php';
require $theme . '/inc/category-data.php';

$failures = 0;
function check( $ok, $message ) {
	global $failures;
	if ( ! $ok ) {
		$failures++;
		echo "FAIL: $message\n";
	}
}

// The stored text may use straight or curly quotes; test both.
function rf_test_untexturize( $html ) {
	return str_replace( array( '&#8217;', '&#8216;', '&#8220;', '&#8221;' ), array( "'", "'", '"', '"' ), $html );
}

$patches = rf_v140_patches( 'October 25, 2026' );
check( array( 'privacy-policy', 'cookie-policy', 'about-us' ) === array_keys( $patches ), 'the 1.4.0 patch list has the three pages' );

foreach ( $patches as $slug => $page ) {
	check( 'page' === $page['type'], "/$slug/ is a page" );
	foreach ( $page['patches'] as $patch ) {
		check( '' !== trim( (string) $patch['find'] ), "/$slug/ {$patch['label']}: empty find" );
		check( '' !== trim( (string) $patch['unless'] ), "/$slug/ {$patch['label']}: no 'unless', so it could run twice" );
		if ( ! empty( $patch['regex'] ) ) {
			check( false !== @preg_match( $patch['find'], '' ), "/$slug/ {$patch['label']}: invalid pattern" );
		}
	}
	$file = __DIR__ . "/fixtures/v140/$slug.html";
	check( is_file( $file ), "/$slug/ has a fixture" );
	if ( ! is_file( $file ) ) {
		continue;
	}
	foreach ( array( 'curly' => file_get_contents( $file ), 'straight' => rf_test_untexturize( file_get_contents( $file ) ) ) as $kind => $content ) {
		$before = $content;
		foreach ( $page['patches'] as $patch ) {
			list( $content, $status ) = rf_fixes_apply_patch( $content, $patch );
			check( 'applied' === $status, "/$slug/ ($kind quotes) {$patch['label']}: $status" );
		}
		if ( 'about-us' === $slug ) {
			check( false !== strpos( $content, 'Remember my business details on this device' ), "/$slug/ ($kind) mentions the checkbox" );
		} else {
			check( 1 === substr_count( $content, '<code>rgp-business-profile</code> (localStorage' ), "/$slug/ ($kind) lists rgp-business-profile once" );
			check( false !== strpos( $content, 'Forget saved details' ), "/$slug/ ($kind) says how to remove it" );
			check( false !== strpos( $content, 'never uploaded' ), "/$slug/ ($kind) says it is never uploaded" );
			check( false !== strpos( $content, 'Last updated: October 25, 2026.' ), "/$slug/ ($kind) last updated" );
			// Right after the sessionStorage line, inside the same list.
			check( (bool) preg_match( '~close the tab\.</li>\s*<li><code>rgp-business-profile~', $content ), "/$slug/ ($kind) the new line follows rgp-form" );
		}
		check( strlen( $content ) > strlen( $before ), "/$slug/ ($kind) grew" );
		// Running again changes nothing.
		$again = $content;
		foreach ( $page['patches'] as $patch ) {
			list( $again ) = rf_fixes_apply_patch( $again, $patch );
		}
		check( $again === $content, "/$slug/ ($kind quotes) changes on a second run" );
	}
}

// The migration is registered with the other setup tasks.
$setup = file_get_contents( $theme . '/inc/setup-tasks.php' );
foreach ( array( '! rf_migration_v140_done()', 'rf_migrate_v140_content();', "'rf_migration_v140'" ) as $needle ) {
	check( false !== strpos( $setup, $needle ), "setup-tasks.php has $needle" );
}

// Currencies (G-5): the new codes, their symbols and decimals.
$cur = rf_currencies();
foreach ( array( 'NZD' => 'NZ$', 'SGD' => 'S$', 'ZAR' => 'R', 'NGN' => '₦', 'PHP' => '₱', 'MYR' => 'RM', 'JPY' => '¥', 'KWD' => 'KWD' ) as $code => $sym ) {
	check( isset( $cur[ $code ] ) && $sym === $cur[ $code ][0], "$code is listed with $sym" );
}
$dp = rf_currency_decimals();
check( 17 === count( $dp ), '17 currencies' );
check( 0 === $dp['JPY'] && 3 === $dp['KWD'] && 2 === $dp['USD'] && 2 === $dp['INR'], 'decimals: JPY 0, KWD 3, the rest 2' );
check( '¥ — Japanese Yen' === rf_supported_currencies()['JPY'], 'menu label' );

if ( $failures ) {
	echo "$failures failure(s)\n";
	exit( 1 );
}
echo "v140 checks passed\n";
