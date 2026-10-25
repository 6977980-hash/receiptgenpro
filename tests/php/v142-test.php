<?php
/**
 * Theme 1.4.2 (audit of 25 Oct 2026, v2 Phase 6, G-6): the rent receipt
 * law data for Massachusetts, the Ohio cities, the UK and Canada, their
 * pages in rf_feature_pages(), the draft pass for pages at any depth, and
 * each page as rendered (tests/php/render-rent-law.php). Runs without
 * WordPress. Run: php tests/php/v142-test.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'RF_BRAND_NAME', 'ReceiptGenPro' );
define( 'OBJECT', 'OBJECT' );

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
function wp_parse_args( $a, $d ) {
	return array_merge( $d, (array) $a );
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $s ) {
	return esc_html( $s );
}

// The draft pass, with a fake hub and pages.
$GLOBALS['rf_pages']   = array();
$GLOBALS['rf_drafted'] = array();
function get_page_by_path( $path ) {
	return 'rent-receipt-laws' === $path ? (object) array( 'ID' => 1 ) : null;
}
function get_pages( $args ) {
	return 1 === ( $args['child_of'] ?? 0 ) ? $GLOBALS['rf_pages'] : array();
}
function get_page_uri( $page ) {
	return $page->uri;
}
function wp_update_post( $post ) {
	$GLOBALS['rf_drafted'][] = $post['ID'];
}

$root  = dirname( __DIR__, 2 );
$theme = $root . '/wp-content/themes/receiptgenpro';
foreach ( array( 'category-data', 'rent-laws', 'feature-pages' ) as $file ) {
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

$entries = rf_rent_law_entries();
$pages   = rf_feature_pages();

// Every new rule has a citation, the URL that was read, a trigger, a
// summary and a check date; every section of the UK page too.
$rules = rf_rent_receipt_city_laws() + rf_rent_receipt_intl_laws() + array( 'massachusetts' => rf_rent_receipt_laws()['massachusetts'] );
foreach ( $rules as $sub => $law ) {
	foreach ( array( 'citation', 'url', 'trigger', 'summary', 'checked', 'when_short' ) as $key ) {
		check( ! empty( $law[ $key ] ), "$sub has a $key" );
	}
	check( 0 === strpos( $law['url'], 'https://' ), "$sub url is https" );
	foreach ( (array) ( $law['sections'] ?? array() ) as $section ) {
		foreach ( array( 'region', 'citation', 'url', 'trigger' ) as $key ) {
			check( ! empty( $section[ $key ] ), "$sub section {$section['region']} has a $key" );
		}
	}
}
foreach ( rf_rent_city_rule_states() + rf_rent_law_countries() as $sub => $place ) {
	check( ! empty( $place['summary'] ) && ! empty( $place['checked'] ), "$sub has a summary and a check date" );
}
foreach ( rf_rent_receipt_intl_laws() as $sub => $law ) {
	check( in_array( $law['currency'], array( 'GBP', 'CAD' ), true ), "$sub has its currency" );
}
check( 'deposit-receipt' === $entries['massachusetts']['template'], 'Massachusetts uses the deposit receipt template' );
check( 'rent-receipt' === $entries['texas']['template'] && 'rent-us' === $entries['texas']['purpose'], 'Texas keeps the rent template and US rent check' );
check( array( 'massachusetts' ) === array_keys( rf_rent_deposit_only_laws() ), 'Massachusetts is the deposit-only state' );

// Meta descriptions of 155 characters or less, unique paths, parents first.
$seen = array();
foreach ( $entries as $sub => $entry ) {
	$meta = $entry['meta'] ?? $entry['summary'];
	check( mb_strlen( $meta ) <= 155, "$sub meta is " . mb_strlen( $meta ) . ' characters' );
	check( isset( $pages[ 'rent-receipt-laws/' . $sub ] ), "$sub is in rf_feature_pages() (not drafted)" );
	check( '' === $entry['parent'] || isset( $seen[ $entry['parent'] ] ), "$sub comes after its parent" );
	check( ! isset( $seen[ $sub ] ), "$sub is unique" );
	$seen[ $sub ] = true;
	check( preg_match( '/^[a-z-]+(\/[a-z-]+)?$/', $sub ), "$sub is a clean path" );
	check( mb_strlen( $pages[ 'rent-receipt-laws/' . $sub ]['seo_title'] ) <= 60, "$sub SEO title fits" );
}
$keys = array_merge( array_keys( rf_rent_receipt_laws() ), array_keys( rf_rent_city_rule_states() ), array_keys( rf_rent_receipt_city_laws() ), array_keys( rf_rent_law_countries() ), array_keys( rf_rent_receipt_intl_laws() ) );
check( count( $keys ) === count( array_unique( $keys ) ), 'no path is used twice across the lists' );
foreach ( array( 'massachusetts', 'ohio', 'ohio/columbus', 'ohio/cincinnati', 'uk', 'canada', 'canada/ontario', 'canada/british-columbia', 'canada/manitoba', 'canada/quebec' ) as $sub ) {
	check( isset( $entries[ $sub ] ), "/rent-receipt-laws/$sub/ exists" );
}
check( 'rent-receipt-laws/ohio' === $pages['rent-receipt-laws/ohio/columbus']['parent'], 'Columbus is a child of the Ohio page' );
check( 'rent-receipt-laws' === $pages['rent-receipt-laws/uk']['parent'], 'the UK page is a child of the hub' );
check( 'Ontario Rent Receipt Law: Is a Receipt Required?' === $pages['rent-receipt-laws/canada/ontario']['seo_title'], 'Ontario SEO title follows the pattern' );
// Existing state pages keep their title, SEO title and description.
check( 'Texas Rent Receipt Law' === $pages['rent-receipt-laws/texas']['title'], 'Texas title unchanged' );
check( 'DC Rent Receipt Law: Is a Receipt Required?' === $pages['rent-receipt-laws/district-of-columbia']['seo_title'], 'DC SEO title unchanged' );
check( rf_rent_receipt_laws()['new-york']['meta'] === $pages['rent-receipt-laws/new-york']['description'], 'New York description unchanged' );

// The draft pass checks pages at any depth by their full path.
$GLOBALS['rf_pages'] = array(
	(object) array( 'ID' => 10, 'uri' => 'rent-receipt-laws/texas' ),
	(object) array( 'ID' => 11, 'uri' => 'rent-receipt-laws/ohio/columbus' ),
	(object) array( 'ID' => 12, 'uri' => 'rent-receipt-laws/canada/ontario' ),
	(object) array( 'ID' => 13, 'uri' => 'rent-receipt-laws/ohio/dayton' ),
	(object) array( 'ID' => 14, 'uri' => 'rent-receipt-laws/atlantis' ),
);
rf_draft_removed_law_pages( $pages );
check( array( 13, 14 ) === $GLOBALS['rf_drafted'], 'only the pages without data are drafted (' . implode( ',', $GLOBALS['rf_drafted'] ) . ')' );

// FAQ and citations.
check( 3 === count( rf_rent_laws_faqs( '' ) ), 'the hub FAQ has the other-countries question' );
check( false !== strpos( rf_rent_laws_faqs( 'massachusetts' )[1][1], 'cover only the security deposit' ), 'Massachusetts FAQ says there is no monthly rent rule' );
check( 5 === count( rf_rent_laws_faqs( 'uk' ) ), 'the UK FAQ has a question per nation' );
check( 3 === count( rf_rent_law_citations( 'ohio' ) ), 'the Ohio page cites the state chapter and both ordinances' );
check( 5 === count( rf_rent_law_citations( 'uk' ) ), 'the UK page cites each nation' );

// llms.txt names cities and countries.
$src = file_get_contents( $theme . '/inc/seo-fixes.php' );
check( false !== strpos( $src, 'Columbus and Cincinnati, Ohio' ) && false !== strpos( $src, 'the UK and Canada' ), 'llms.txt line names the cities and countries' );

// The rendered pages.
function render( $sub ) {
	return shell_exec( 'php ' . escapeshellarg( __DIR__ . '/render-rent-law.php' ) . ' ' . escapeshellarg( $sub ) );
}
function faq_count( $html ) {
	$part = substr( $html, strpos( $html, 'rf-faq-section' ) );
	$part = substr( $part, 0, strpos( $part, '</section>' ) );
	return substr_count( $part, '<h3>' );
}
$hub = render( '' );
check( false === strpos( substr( $hub, 0, strpos( $hub, 'Deposits Only' ) ), '>Massachusetts</a></td>' ), 'Massachusetts is not in the hub\'s monthly rent table' );
check( false !== strpos( $hub, 'Deposits Only' ) && false !== strpos( $hub, '/rent-receipt-laws/massachusetts/' ), 'the hub lists Massachusetts under deposits only, linked' );
check( false !== strpos( $hub, 'at least 15 states and Washington, D.C.' ), 'the hub count leaves out California and Massachusetts' );
check( false !== strpos( $hub, '<h2>City Rules</h2>' ) && false !== strpos( $hub, '/rent-receipt-laws/ohio/columbus/' ), 'the hub has the city rules' );
check( false !== strpos( $hub, '<h2>Other Countries</h2>' ) && false !== strpos( $hub, '/rent-receipt-laws/uk/' ) && false !== strpos( $hub, '/rent-receipt-laws/canada/' ), 'the hub links the other countries' );
check( false !== strpos( $hub, 'only after reading its statute or ordinance' ), 'the hub promise covers cities' );
check( count( rf_rent_laws_faqs( '' ) ) === faq_count( $hub ), 'hub FAQ matches its schema source' );

$expect = array(
	'massachusetts'   => array( 'DEPOSIT RECEIPT', '' ),
	'ohio'            => array( 'RENT RECEIPT', '' ),
	'ohio/columbus'   => array( 'RENT RECEIPT', '' ),
	'ohio/cincinnati' => array( 'RENT RECEIPT', '' ),
	'uk'              => array( 'RENT RECEIPT', 'GBP' ),
	'canada'          => array( 'RENT RECEIPT', 'CAD' ),
	'canada/ontario'  => array( 'RENT RECEIPT', 'CAD' ),
	'canada/quebec'   => array( 'RENT RECEIPT', 'CAD' ),
	'texas'           => array( 'RENT RECEIPT', '' ),
);
foreach ( $expect as $sub => $want ) {
	$html = render( $sub );
	check( false !== strpos( $html, 'data-receipt-title="' . $want[0] . '"' ), "$sub generator is the {$want[0]} template" );
	check( false !== strpos( $html, 'data-currency="' . $want[1] . '"' ), "$sub generator currency is '{$want[1]}'" );
	check( count( rf_rent_laws_faqs( $sub ) ) === faq_count( $html ), "$sub FAQ matches its schema source" );
	check( false !== strpos( $html, 'not legal advice' ), "$sub has the disclaimer" );
	check( 1 === substr_count( $html, '<h1>' ), "$sub has one h1" );
	if ( in_array( $sub, array( 'uk', 'canada', 'canada/ontario', 'canada/quebec' ), true ) ) {
		$text = strip_tags( substr( $html, 0, strpos( $html, 'id="rf-generator"' ) ) );
		foreach ( array( 'statute', 'attorney', 'property manager', 'county' ) as $us_word ) {
			check( false === stripos( $text, $us_word ), "$sub says no '$us_word'" );
		}
		check( false === strpos( $html, 'value="rent-us" selected' ), "$sub doesn't preselect the US rent check" );
	}
}
check( false !== strpos( render( 'uk' ), 'Make a UK rent receipt' ), 'UK button reads "a UK"' );
check( false !== strpos( render( 'canada/ontario' ), 'Make an Ontario rent receipt' ), 'Ontario button reads "an Ontario"' );
check( false !== strpos( render( 'ohio/columbus' ), 'All of Ohio' ), 'a city page links back to its state' );

if ( $failures ) {
	echo "$failures check(s) failed.\n";
	exit( 1 );
}
echo "1.4.2 rent law checks OK.\n";
