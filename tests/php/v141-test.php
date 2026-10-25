<?php
/**
 * Theme 1.4.1 (audit of 25 Oct 2026, v2 Phase 5): the refund receipt
 * template and its page, the HRA and donation form keys, the refund
 * requirement check, and the donation FAQ patch, which applies once to
 * the live page text of 25 Oct (tests/php/fixtures/v141/). Runs without
 * WordPress. Run: php tests/php/v141-test.php
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
foreach ( array( 'content-patches', 'category-data', 'requirements', 'feature-pages' ) as $file ) {
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

// G-9: the category, its page and its links.
$refund = rf_get_category_data( 'refund-receipt' );
check( null !== $refund, 'refund-receipt is a category' );
check( mb_strlen( $refund['meta_description'] ) <= 155, 'meta description is ' . mb_strlen( $refund['meta_description'] ) . ' characters' );
check( mb_strlen( $refund['seo_title'] ) <= 60, 'SEO title is ' . mb_strlen( $refund['seo_title'] ) . ' characters' );
check( count( $refund['default_fields'] ) >= 4 && count( $refund['highlights'] ) >= 3, 'default fields and highlights' );
$form = rf_generator_form( $refund['form'] );
check( 'REFUND RECEIPT' === $form['title'] && $form['refund'], 'refund form: title and flag' );
check( array( 'refund', 'credit' ) === array_keys( $form['documents'] ), 'refund form: Document menu' );
check( ! $form['tip'] && ! $form['paid'], 'no tip and no amount paid' );
$extras = wp_list_pluck( $form['extra'], 'id' );
foreach ( array( 'original_ref', 'original_date', 'reason', 'refund_method' ) as $id ) {
	check( in_array( $id, $extras, true ), "refund form has $id" );
}
$page = rf_feature_page( 'refund-receipt' );
check( $page && 'page-receipt-category.php' === $page['template'], 'the page is in rf_feature_pages() with the category template' );
check( is_callable( $page['content'] ), 'the page has a content callback' );
$content = call_user_func( $page['content'] );
check( 1 === substr_count( $content, 'class="rf-faq"' ), 'one FAQ block' );
$faqs = substr_count( $content, '<h3>' );
check( $faqs >= 3 && $faqs <= 5, "$faqs FAQs" );
check( false !== strpos( $content, 'section 34 of the CGST Act, 2017' ) && false !== strpos( $content, 'rule 53(1A)' ), 'the GST answer cites section 34 and rule 53(1A)' );
foreach ( array( 'payment-receipt', 'cash-receipt', 'freelance-receipt' ) as $slug ) {
	check( in_array( 'refund-receipt', rf_get_category_data( $slug )['related'], true ), "$slug links to refund-receipt" );
}
check( isset( rf_embed_templates()['refund-receipt'] ), '/embed/refund-receipt/ exists' );
check( isset( rf_generator_types()['refund-receipt'] ), 'the homepage picker has it' );
$purpose = rf_get_receipt_purpose( 'refund' );
check( 'refund-receipt' === $purpose['template'] && 'refund' === $refund['purpose'], 'the refund check is the page default' );
check( in_array( 'x:original_ref', wp_list_pluck( $purpose['checks'], 'field' ), true ), 'the checker asks for the original receipt number' );

// G-4 and G-7: form keys.
check( true === rf_get_category_data( 'hra-rent-receipt' )['form']['months'], 'HRA has the months panel' );
check( array( 'single', 'annual' ) === array_keys( rf_get_category_data( 'donation-receipt' )['form']['documents'] ), 'donation Document menu' );
check( false === rf_generator_form( array() )['months'] && array() === rf_generator_form( array() )['documents'], 'defaults off' );

// The REFUND note: the same text in the template and the script.
$embed = file_get_contents( $theme . '/template-parts/generator-embed.php' );
$js    = file_get_contents( $theme . '/assets/js/receipt-generator.js' );
preg_match( '~id="rf-refund-gst-note" hidden>([^<]+)</p>~', $embed, $m );
check( ! empty( $m[1] ) && false !== strpos( str_replace( "\\'", "'", $js ), "'" . $m[1] . "'" ), 'the GST note is the same in PHP and JS' );

// The donation FAQ patch.
function rf_test_untexturize( $html ) {
	return str_replace( array( '&#8217;', '&#8216;', '&#8220;', '&#8221;' ), array( "'", "'", '"', '"' ), $html );
}
$patches = rf_v141_patches();
check( array( 'donation-receipt' ) === array_keys( $patches ), 'the 1.4.1 patch list has the donation page' );
$fixture = file_get_contents( __DIR__ . '/fixtures/v141/donation-receipt.html' );
foreach ( array( 'curly' => $fixture, 'straight' => rf_test_untexturize( $fixture ) ) as $kind => $c ) {
	foreach ( $patches['donation-receipt']['patches'] as $patch ) {
		check( '' !== trim( (string) $patch['unless'] ), "{$patch['label']}: no 'unless'" );
		list( $c, $status ) = rf_fixes_apply_patch( $c, $patch );
		check( 'applied' === $status, "($kind) {$patch['label']}: $status" );
	}
	check( 1 === substr_count( $c, 'Can I give a donor one statement for the whole year?' ), "($kind) the FAQ is added once" );
	check( (bool) preg_match( '~the donor does\.</p>\s*<h3>Can I give a donor one statement~', $c ), "($kind) right after the in-kind answer" );
	check( false !== strpos( $c, 'https://www.irs.gov/pub/irs-pdf/p1771.pdf' ), "($kind) links Pub 1771" );
	check( (bool) preg_match( '~one statement for the whole year\?</h3>\s*<p>[^<]*<a [^>]+>IRS Publication 1771</a>[^<]*</p>\s*<h3>Do I need~', $c ), "($kind) the answer sits inside the FAQ block" );
	$again = $c;
	foreach ( $patches['donation-receipt']['patches'] as $patch ) {
		list( $again ) = rf_fixes_apply_patch( $again, $patch );
	}
	check( $again === $c, "($kind) a second run changes nothing" );
}

// The migration is registered with the other setup tasks.
$setup = file_get_contents( $theme . '/inc/setup-tasks.php' );
foreach ( array( '! rf_migration_v141_done()', 'rf_migrate_v141_content();', "'rf_migration_v141'" ) as $needle ) {
	check( false !== strpos( $setup, $needle ), "setup-tasks.php has $needle" );
}

if ( $failures ) {
	echo "$failures failure(s)\n";
	exit( 1 );
}
echo "v141 checks passed\n";
