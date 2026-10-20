<?php
/**
 * Checks for the SEO data the theme keeps in code (audit of 20 Oct 2026,
 * Phase 2): title and description lengths, and the /blog/ link rewrite.
 * Runs without WordPress: the few core functions the files touch on load
 * are stubbed below. Run: php tests/php/seo-test.php
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

// Titles 60 characters or less, descriptions 155 or less (Google cuts
// descriptions at about 160; 155 leaves room for " – Page 2" and such).
foreach ( rf_feature_pages() as $path => $page ) {
	if ( ! empty( $page['seo_title'] ) ) {
		check( mb_strlen( $page['seo_title'] ) <= 60, "/$path/ title is " . mb_strlen( $page['seo_title'] ) . ' characters' );
	}
	if ( ! empty( $page['description'] ) ) {
		check( mb_strlen( $page['description'] ) <= 155, "/$path/ description is " . mb_strlen( $page['description'] ) . ' characters' );
	}
}
foreach ( rf_receipt_categories() as $slug => $cat ) {
	check( mb_strlen( $cat['seo_title'] ) <= 60, "/$slug/ title is " . mb_strlen( $cat['seo_title'] ) . ' characters' );
	check( mb_strlen( $cat['meta_description'] ) <= 155, "/$slug/ description is " . mb_strlen( $cat['meta_description'] ) . ' characters' );
}
foreach ( rf_seo_title_patches() as $slug => $title ) {
	check( mb_strlen( $title ) <= 60, "/$slug/ SEO title is " . mb_strlen( $title ) . ' characters' );
}
check( 'DC Rent Receipt Law: Is a Receipt Required?' === rf_feature_page( 'rent-receipt-laws/district-of-columbia' )['seo_title'], 'DC title uses the short name' );

// /blog/<slug>/ links go to /<slug>/, but the blog's own feed, category,
// tag and page URLs stay as they are.
$cases = array(
	'<a href="/blog/how-to-make-a-receipt/">'                        => '<a href="/how-to-make-a-receipt/">',
	'<a href="https://example.com/blog/how-to-make-a-receipt/#faq">' => '<a href="https://example.com/how-to-make-a-receipt/#faq">',
	'<a href="/blog/feed/">'                                         => '<a href="/blog/feed/">',
	'<a href="/blog/feed">'                                          => '<a href="/blog/feed">',
	'<a href="/blog/category/how-to-guides/">'                       => '<a href="/blog/category/how-to-guides/">',
	'<a href="/blog/tag/rent/">'                                     => '<a href="/blog/tag/rent/">',
	'<a href="/blog/page/2/">'                                       => '<a href="/blog/page/2/">',
	'<a href="/blog/pages-of-receipts/">'                            => '<a href="/pages-of-receipts/">',
);
foreach ( $cases as $in => $want ) {
	$got = rf_rewrite_internal_links( $in );
	check( $got === $want, "rewrite $in gave $got" );
}

if ( $failures ) {
	echo "$failures check(s) failed\n";
	exit( 1 );
}
echo "All SEO checks passed\n";
