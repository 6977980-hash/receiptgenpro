<?php
/**
 * Theme 1.4.3: Google Analytics 4 is switched on behind the consent banner,
 * and the privacy and cookie policy patches apply once to the live page
 * text of 26 Oct (tests/php/fixtures/v143/). Runs without WordPress.
 * Run: php tests/php/v143-test.php
 */

define( 'ABSPATH', __DIR__ . '/' );

function add_action() {}
function add_filter() {}
function date_i18n( $format ) {
	return gmdate( $format );
}

$theme = dirname( __DIR__, 2 ) . '/wp-content/themes/receiptgenpro';
require $theme . '/inc/adsense.php';
require $theme . '/inc/content-patches.php';

$failures = 0;
function check( $ok, $message ) {
	global $failures;
	if ( ! $ok ) {
		$failures++;
		echo "FAIL: $message\n";
	}
}

// The tag: set, consent-gated, and the CSP lets it load.
check( 'G-T47P9EX906' === RF_GA4_ID, 'RF_GA4_ID is the site measurement ID' );
check( rf_needs_consent(), 'the consent banner is on' );
$adsense = file_get_contents( $theme . '/inc/adsense.php' );
check( (bool) preg_match( "~addEventListener\('rfConsentGranted'[\s\S]*?googletagmanager\.com/gtag/js~", $adsense ), 'gtag.js loads only after consent' );
check( false !== strpos( $adsense, "analytics_storage: 'denied'" ), 'Consent Mode v2 defaults to denied' );
$seo = file_get_contents( $theme . '/inc/seo-fixes.php' );
check( false !== strpos( $seo, 'https://www.googletagmanager.com' ) && false !== strpos( $seo, "connect-src 'self' https:" ), 'the CSP allows googletagmanager and the collect endpoint' );

// The policy patches.
function rf_test_untexturize( $html ) {
	return str_replace( array( '&#8217;', '&#8216;', '&#8220;', '&#8221;' ), array( "'", "'", '"', '"' ), $html );
}
$patches = rf_v143_patches( 'October 26, 2026' );
check( array( 'privacy-policy', 'cookie-policy' ) === array_keys( $patches ), 'the 1.4.3 patch list has both policies' );
foreach ( $patches as $slug => $page ) {
	$fixture = file_get_contents( __DIR__ . "/fixtures/v143/$slug.html" );
	foreach ( array( 'curly' => $fixture, 'straight' => rf_test_untexturize( $fixture ) ) as $kind => $c ) {
		foreach ( $page['patches'] as $patch ) {
			check( '' !== trim( (string) $patch['unless'] ), "$slug {$patch['label']}: no 'unless'" );
			list( $c, $status ) = rf_fixes_apply_patch( $c, $patch );
			check( 'applied' === $status, "($kind) $slug {$patch['label']}: $status" );
		}
		check( false !== strpos( $c, 'Last updated: October 26, 2026.' ), "($kind) $slug: date" );
		check( false !== strpos( $c, '_ga_T47P9EX906' ), "($kind) $slug: names the GA4 cookie" );
		foreach ( array( 'no advertising network or analytics service is active', 'does not run analytics', 'would be used only', 'Cookies We Would Use', 'If We Turn On Ads or Analytics' ) as $old ) {
			check( false === strpos( $c, $old ), "($kind) $slug: '$old' is gone" );
		}
		$again = $c;
		foreach ( $page['patches'] as $patch ) {
			list( $again ) = rf_fixes_apply_patch( $again, $patch );
		}
		check( $again === $c, "($kind) $slug: a second run changes nothing" );
	}
}

// The migration is registered with the other setup tasks.
$setup = file_get_contents( $theme . '/inc/setup-tasks.php' );
foreach ( array( '! rf_migration_v143_done()', 'rf_migrate_v143_content();', "'rf_migration_v143'" ) as $needle ) {
	check( false !== strpos( $setup, $needle ), "setup-tasks.php has $needle" );
}

if ( $failures ) {
	echo "$failures failure(s)\n";
	exit( 1 );
}
echo "v143 checks passed\n";
