<?php
/**
 * Theme 1.4.5 (Search Console export of 23 Nov 2026): rent escrow on the
 * Virginia page, the lease copy rule on the New York page, and the
 * Louisiana no-rule page. Runs without WordPress. Run: php tests/php/v145-test.php
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
foreach ( array( 'seo-meta', 'category-data', 'rent-laws', 'requirements', 'feature-pages' ) as $file ) {
	require $theme . '/inc/' . $file . '.php';
}

$failures = 0;
function check( $ok, $label ) {
	global $failures;
	if ( ! $ok ) {
		$failures++;
		echo "FAIL: $label\n";
	}
}

$entries = rf_rent_law_entries();
$render  = __DIR__ . '/render-rent-law.php';

// Topics and extra FAQs on Virginia and New York.
$expect = array(
	'virginia' => array( 'Putting Rent in Escrow in Virginia', 'law.lis.virginia.gov', 'Can I put rent in escrow in Virginia?' ),
	'new-york' => array( 'Does a New York Landlord Have to Give You a Copy of the Lease?', 'law.cornell.edu/regulations/new-york/9-NYCRR-2522.5', 'Does a landlord have to provide a copy of the lease in New York?' ),
);
foreach ( $expect as $slug => $want ) {
	$law = $entries[ $slug ];
	check( 1 === count( $law['topics'] ), "$slug has one topic" );
	$topic = $law['topics'][0];
	check( $want[0] === $topic['heading'], "$slug topic heading" );
	check( false !== strpos( $topic['url'], $want[1] ) && 0 === strpos( $topic['url'], 'https://' ), "$slug topic links the text read" );
	check( count( $topic['paras'] ) >= 2, "$slug topic has paragraphs" );
	check( isset( rf_rent_law_citations( $slug )[ $topic['citation'] ] ), "$slug topic is in the citations" );
	$faqs = rf_rent_laws_faqs( $slug );
	$last = end( $faqs );
	check( $want[2] === $last[0] && strlen( $last[1] ) > 80, "$slug extra FAQ comes last" );
	check( $faqs[0][1] === $law['summary'], "$slug FAQ still starts with the summary" );
	check( count( $faqs ) === count( rf_rent_laws_base_faqs( $slug ) ) + 1, "$slug FAQ adds one pair" );
	check( 'November 2026' === $law['checked'], "$slug review date" );
	$html = shell_exec( 'php ' . escapeshellarg( $render ) . ' ' . escapeshellarg( $slug ) );
	check( false !== strpos( $html, esc_html( $want[0] ) ), "$slug page shows the topic" );
	check( false !== strpos( $html, esc_html( $want[2] ) ), "$slug page shows the FAQ" );
	check( false !== strpos( $html, 'class="rf-law-source"' ), "$slug page shows the topic source" );
}
check( false !== strpos( implode( ' ', $entries['virginia']['topics'][0]['paras'] ), '1 January 2027' ), 'Virginia escrow gives both versions' );
check( false !== strpos( implode( ' ', $entries['new-york']['topics'][0]['paras'] ), 'rent-stabilized apartments only' ), 'New York lease copy rule is scoped' );

// Louisiana: a no-rule page, read on legis.la.gov.
$la = $entries['louisiana'] ?? null;
check( $la && 'no-rule' === $la['kind'], 'louisiana is a no-rule page' );
if ( $la ) {
	foreach ( array( 'citation', 'url', 'note', 'summary', 'meta', 'checked' ) as $key ) {
		check( ! empty( $la[ $key ] ), "louisiana has $key" );
	}
	check( 0 === strpos( $la['url'], 'https://legis.la.gov/' ), 'louisiana links the official text' );
	check( mb_strlen( $la['meta'] ) <= 155, 'louisiana meta ≤ 155' );
	check( false !== strpos( $la['note'], '9:3358' ), 'louisiana note explains R.S. 9:3358' );
	check( ! isset( rf_rent_receipt_laws()['louisiana'] ), 'louisiana not in the table of states with a rule' );
	$page = rf_feature_page( 'rent-receipt-laws/louisiana' );
	check( $page && mb_strlen( $page['seo_title'] ) <= 60, 'louisiana page exists with a short title' );
	check( 3 === count( rf_rent_laws_faqs( 'louisiana' ) ), 'louisiana FAQ' );
	$html = shell_exec( 'php ' . escapeshellarg( $render ) . ' louisiana' );
	check( false !== strpos( $html, 'Louisiana Rent Receipt Law' ) && false !== strpos( $html, 'Last reviewed: November 2026' ), 'Louisiana page renders' );
}
$hub = shell_exec( 'php ' . escapeshellarg( $render ) );
check( false !== strpos( $hub, '/rent-receipt-laws/louisiana/' ), 'hub lists Louisiana' );

// Pages without topics or extra FAQs are unchanged.
foreach ( array( 'texas', 'oregon', 'alaska', 'uk' ) as $slug ) {
	check( rf_rent_laws_faqs( $slug ) === rf_rent_laws_base_faqs( $slug ), "$slug FAQ unchanged" );
	$html = shell_exec( 'php ' . escapeshellarg( $render ) . ' ' . escapeshellarg( $slug ) );
	check( false === strpos( $html, 'rf-law-source' ), "$slug has no topic section" );
}

if ( $failures ) {
	echo "$failures check(s) failed\n";
	exit( 1 );
}
echo "v145 checks passed\n";
