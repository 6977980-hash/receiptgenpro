<?php
/**
 * Theme 1.4.4 (Search Console queries of 1 Nov 2026): guide titles and
 * descriptions, the VAT guides' changes on their live text, the template
 * pages' extra sections and FAQs, and the Alaska and Idaho law pages.
 * Runs without WordPress. Run: php tests/php/v144-test.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'RF_BRAND_NAME', 'ReceiptGenPro' );

function add_action() {}
function add_filter() {}
function remove_action() {}
function date_i18n( $format ) {
	return gmdate( $format );
}
function home_url( $path = '' ) {
	return 'https://example.com' . $path;
}
function untrailingslashit( $s ) {
	return rtrim( $s, '/\\' );
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $s ) {
	return esc_html( $s );
}

$theme = dirname( __DIR__, 2 ) . '/wp-content/themes/receiptgenpro';
foreach ( array( 'seo-meta', 'category-data', 'rent-laws', 'requirements', 'feature-pages', 'search-opportunities' ) as $file ) {
	require $theme . '/inc/' . $file . '.php';
}
// rf_extract_faq_from_html() reads the FAQ the way the schema does.
$schema = file_get_contents( $theme . '/inc/schema.php' );
preg_match( '#function rf_extract_faq_from_html.*?\n}\n#s', $schema, $m );
eval( $m[0] );

$failures = 0;
function check( $ok, $message ) {
	global $failures;
	if ( ! $ok ) {
		$failures++;
		echo "FAIL: $message\n";
	}
}

// Titles ≤ 60, descriptions ≤ 155, all different.
$titles = array();
foreach ( rf_v144_post_seo() as $slug => $seo ) {
	check( mb_strlen( $seo[0] ) <= 60, "/$slug/ title is " . mb_strlen( $seo[0] ) . ' characters' );
	check( mb_strlen( $seo[1] ) <= 155, "/$slug/ description is " . mb_strlen( $seo[1] ) . ' characters' );
	$titles[] = $seo[0];
}
check( count( $titles ) === count( array_unique( $titles ) ), 'guide titles are unique' );

// The two VAT guides no longer compete: only one title says "What Is".
$seo = rf_v144_post_seo();
check( 0 === strpos( $seo['what-is-a-vat-receipt'][0], 'What Is a VAT Receipt?' ), 'what-is page leads with the question' );
check( false === stripos( $seo['vat-receipts-in-the-uk'][0], 'what is' ) && false !== strpos( $seo['vat-receipts-in-the-uk'][0], 'HMRC' ), 'UK page is the HMRC checklist' );

// UK direct answer replaced once on the live text.
$uk  = file_get_contents( __DIR__ . '/fixtures/v144/vat-receipts-in-the-uk.html' );
$new = rf_v144_post_content( $uk, 'vat-receipts-in-the-uk' );
check( 1 === substr_count( $new, 'rf-direct-answer' ), 'UK page keeps one direct answer' );
check( false !== strpos( $new, 'Under HMRC rules' ) && false === strpos( $new, 'A UK VAT receipt (VAT invoice) generally needs' ), 'UK direct answer replaced' );
check( false !== strpos( $new, '£250 or less' ), 'UK answer names the simplified invoice limit' );
check( rf_v144_post_content( $new, 'vat-receipts-in-the-uk' ) === $new, 'UK change is stable when applied twice' );

// What-is page: three FAQs added inside its FAQ block, once.
$what  = file_get_contents( __DIR__ . '/fixtures/v144/what-is-a-vat-receipt.html' );
$before = rf_extract_faq_from_html( $what );
$after  = rf_v144_post_content( $what, 'what-is-a-vat-receipt' );
$pairs  = rf_extract_faq_from_html( $after );
check( count( $pairs ) === count( $before ) + 3, 'what-is FAQ grows by 3 (' . count( $before ) . ' → ' . count( $pairs ) . ')' );
check( 1 === substr_count( $after, 'class="rf-faq"' ), 'FAQs go into the existing block' );
check( rf_v144_post_content( $after, 'what-is-a-vat-receipt' ) === $after, 'FAQs are not added twice' );
check( 'Can you claim VAT on supermarket receipts?' === $pairs[ count( $before ) ]['q'], 'new FAQ order' );
check( rf_v144_post_content( '<p>x</p>', 'some-other-post' ) === '<p>x</p>', 'other guides unchanged' );

// Template pages: every slug exists, has FAQs, and the FAQ HTML parses.
$cats = rf_receipt_categories();
foreach ( rf_v144_category_extras() as $slug => $extra ) {
	check( isset( $cats[ $slug ] ), "$slug is a template page" );
	check( ! empty( $extra['faqs'] ), "$slug has FAQs" );
	$parsed = rf_extract_faq_from_html( rf_v144_category_faq_html( $slug ) );
	check( count( $parsed ) === count( $extra['faqs'] ), "$slug FAQ parses for the schema" );
	$html = rf_v144_category_extras_html( $slug );
	check( false !== strpos( $html, 'More Questions' ), "$slug prints its FAQ" );
	check( ! preg_match( '/\b(fake|uber|motel 6|ace hardware|yellow cab)\b/i', $html ), "$slug targets no brand or fake searches" );
}
check( '' === rf_v144_category_extras_html( 'rent-receipt' ), 'pages without extras print nothing' );
check( false !== strpos( $cats['taxi-receipt']['seo_title'], 'Cab' ), 'taxi title says cab' );
check( false !== stripos( $cats['hotel-receipt']['seo_title'], 'folio' ), 'hotel title says folio' );
check( 0 === strpos( $cats['payment-receipt']['seo_title'], 'Proof of Payment' ), 'payment title leads with proof of payment' );

// Alaska and Idaho: no-rule pages, read in full, not counted as rules.
$entries = rf_rent_law_entries();
foreach ( array( 'alaska', 'idaho' ) as $slug ) {
	$law = $entries[ $slug ] ?? null;
	check( $law && 'no-rule' === $law['kind'], "$slug is a no-rule page" );
	if ( ! $law ) {
		continue;
	}
	foreach ( array( 'citation', 'url', 'note', 'summary', 'meta', 'checked' ) as $key ) {
		check( ! empty( $law[ $key ] ), "$slug has $key" );
	}
	check( 0 === strpos( $law['url'], 'https://' ) && preg_match( '#(akleg\.gov|legislature\.idaho\.gov)#', $law['url'] ), "$slug links the official text" );
	check( mb_strlen( $law['meta'] ) <= 155, "$slug meta ≤ 155" );
	check( ! isset( rf_rent_receipt_laws()[ $slug ] ), "$slug not in the table of states with a rule" );
	check( '' === $law['parent'], "$slug sits under the hub" );
	$page = rf_feature_page( 'rent-receipt-laws/' . $slug );
	check( $page && mb_strlen( $page['seo_title'] ) <= 60, "$slug page exists with a short title" );
	$faqs = rf_rent_laws_faqs( $slug );
	check( 3 === count( $faqs ) && $faqs[0][1] === $law['summary'], "$slug FAQ" );
}
check( ! isset( $entries['louisiana'] ) && ! isset( $entries['arkansas'] ), 'states whose law we could not read stay off the list' );

// Rendered law pages.
$render = __DIR__ . '/render-rent-law.php';
$ak     = shell_exec( 'php ' . escapeshellarg( $render ) . ' alaska' );
check( false !== strpos( $ak, 'Alaska Rent Receipt Law' ) && false !== strpos( $ak, 'Statewide rule' ) && false !== strpos( $ak, 'Last reviewed: November 2026' ), 'Alaska page renders' );
check( false !== strpos( $ak, 'Other States' ) && false !== strpos( $ak, '/rent-receipt-laws/idaho/' ), 'Alaska links the other states' );
$hub = shell_exec( 'php ' . escapeshellarg( $render ) );
check( false !== strpos( $hub, 'States With No Rent Receipt Law' ) && false !== strpos( $hub, '/rent-receipt-laws/alaska/' ), 'hub lists the no-rule states' );
check( 1 === preg_match( '/at least (\d+) states/', $hub, $n ) && (int) $n[1] === count( array_filter( rf_rent_receipt_laws(), function ( $l ) {
	return empty( $l['general'] ) && empty( $l['deposit_only'] );
} ) ) - 1, 'hub count unchanged' );

if ( $failures ) {
	echo "$failures check(s) failed\n";
	exit( 1 );
}
echo "v144 checks passed\n";
