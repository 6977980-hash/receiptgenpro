<?php
/**
 * Prints one rent receipt law page (page-rent-receipt-laws.php) as a full
 * HTML document, for tests/php/v142-test.php and a local browser check.
 * Runs without WordPress. Run: php tests/php/render-rent-law.php [path]
 * The path is below /rent-receipt-laws/ ("ohio/columbus", "uk"); no path
 * renders the hub. The page links /main.css and /receipt-generator.js and
 * sets window.rfReceipt, so a static server over this output, the CSS and
 * the JS shows the page as WordPress would (header and footer aside).
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'RF_BRAND_NAME', 'ReceiptGenPro' );

function add_action() {}
function add_filter() {}
function remove_action() {}
function home_url( $path = '' ) {
	return 'https://example.com' . $path;
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}
function wp_json_encode( $data, $options = 0 ) {
	return json_encode( $data, $options );
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $s ) {
	return esc_html( $s );
}
function esc_url( $s ) {
	return esc_html( $s );
}
function esc_html_e( $s ) {
	echo esc_html( $s );
}
function __( $s ) {
	return $s;
}
function sanitize_key( $s ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) );
}
function selected( $a, $b = true, $echo = true ) {
	$r = (string) $a === (string) $b ? ' selected=\'selected\'' : '';
	if ( $echo ) {
		echo $r; // phpcs:ignore
	}
	return $r;
}
function checked( $a, $b = true, $echo = true ) {
	$r = (string) $a === (string) $b ? ' checked=\'checked\'' : '';
	if ( $echo ) {
		echo $r; // phpcs:ignore
	}
	return $r;
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}
function date_i18n( $format ) {
	return gmdate( $format );
}
function wp_list_pluck( $list, $field ) {
	return array_map(
		function ( $row ) use ( $field ) {
			return $row[ $field ];
		},
		$list
	);
}
function get_queried_object_id() {
	return 1;
}
function wp_get_post_parent_id() {
	return '' === $GLOBALS['rf_test_sub'] ? 0 : 1;
}
function get_page_uri() {
	return rtrim( 'rent-receipt-laws/' . $GLOBALS['rf_test_sub'], '/' );
}
function get_header() {
	echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Rent law test</title><link rel="stylesheet" href="/main.css"></head><body>';
}
function get_footer() {
	echo '<script>window.rfReceipt = ' . json_encode( rf_receipt_script_data() ) . ';</script><script src="/receipt-generator.js"></script></body></html>';
}
function get_template_part( $slug, $name = null, $args = array() ) {
	include $GLOBALS['rf_theme'] . '/' . $slug . '.php';
}

$GLOBALS['rf_theme']    = dirname( __DIR__, 2 ) . '/wp-content/themes/receiptgenpro';
$GLOBALS['rf_test_sub'] = trim( $argv[1] ?? '', '/' );
foreach ( array( 'category-data', 'requirements', 'rent-laws', 'feature-pages' ) as $file ) {
	require $GLOBALS['rf_theme'] . '/inc/' . $file . '.php';
}

include $GLOBALS['rf_theme'] . '/page-rent-receipt-laws.php';
