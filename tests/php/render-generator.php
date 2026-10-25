<?php
/**
 * Prints the generator markup and its window.rfReceipt data as JSON, for
 * the jsdom tests (tests/v135-dom.test.js). Runs without WordPress.
 * Run: php tests/php/render-generator.php [template-slug] [embed]
 * No slug: the homepage form with the receipt type picker. "embed": the
 * form as /embed/<slug>/ shows it.
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

$theme = dirname( __DIR__, 2 ) . '/wp-content/themes/receiptgenpro';
foreach ( array( 'category-data', 'requirements', 'feature-pages' ) as $file ) {
	require $theme . '/inc/' . $file . '.php';
}

$slug = $argv[1] ?? '';
$cat  = $slug ? rf_get_category_data( $slug ) : null;
$args = $cat
	? array( 'form' => $cat['form'], 'purpose' => $cat['purpose'] ?? '' )
	: array( 'picker' => true );
if ( 'embed' === ( $argv[2] ?? '' ) ) {
	$args = array( 'form' => $cat['form'] ?? array(), 'purpose' => $cat['purpose'] ?? '', 'embed' => true );
}

ob_start();
include $theme . '/template-parts/generator-embed.php';
$html = ob_get_clean();

echo json_encode( array( 'html' => $html, 'data' => rf_receipt_script_data() ) );
