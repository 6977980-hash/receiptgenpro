<?php
/**
 * ReceiptGenPro — Favicons, app icons & the web app manifest
 *
 * Serves the full modern favicon set (browser tab icon, iOS home-screen
 * icon, Android/PWA icons) from theme assets, plus a virtual
 * /site.webmanifest endpoint — the same "no FTP access needed" pattern
 * used for /ads.txt in inc/adsense.php, so it always reflects the current
 * RF_BRAND_NAME even if the theme folder is renamed.
 *
 * Source images live in /assets/img/ — regenerate them (favicon.ico,
 * favicon-16x16.png, favicon-32x32.png, apple-touch-icon.png,
 * android-chrome-192x192.png, android-chrome-512x512.png, og-default.png)
 * if you ever change the brand colour or logo mark.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. <head> tags — favicon, apple-touch-icon, manifest, theme-color
   ========================================================================== */

function rf_output_favicon_tags() {
	$img = get_template_directory_uri() . '/assets/img';
	?>
	<link rel="icon" href="<?php echo esc_url( $img ); ?>/favicon.ico" sizes="any">
	<link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url( $img ); ?>/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="<?php echo esc_url( $img ); ?>/favicon-16x16.png">
	<link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url( $img ); ?>/apple-touch-icon.png">
	<link rel="manifest" href="<?php echo esc_url( home_url( '/site.webmanifest' ) ); ?>">
	<meta name="theme-color" content="#2563eb">
	<meta name="msapplication-TileColor" content="#2563eb">
	<meta name="msapplication-TileImage" content="<?php echo esc_url( $img ); ?>/android-chrome-192x192.png">
	<?php
}
add_action( 'wp_head', 'rf_output_favicon_tags', 2 );

/* ==========================================================================
   2. Virtual /site.webmanifest — always reflects the live brand name/colors,
      never a stale hardcoded file (same pattern as the /ads.txt endpoint).
   ========================================================================== */

function rf_manifest_rewrite() {
	add_rewrite_rule( '^site\.webmanifest$', 'index.php?rf_webmanifest=1', 'top' );
}
add_action( 'init', 'rf_manifest_rewrite' );

function rf_manifest_query_var( $vars ) {
	$vars[] = 'rf_webmanifest';
	return $vars;
}
add_filter( 'query_vars', 'rf_manifest_query_var' );

function rf_manifest_render() {
	if ( ! get_query_var( 'rf_webmanifest' ) ) {
		return;
	}

	$img = get_template_directory_uri() . '/assets/img';

	$manifest = array(
		'name'             => RF_BRAND_NAME,
		'short_name'       => RF_BRAND_NAME,
		'start_url'        => home_url( '/' ),
		'display'          => 'standalone',
		'background_color' => '#ffffff',
		'theme_color'      => '#2563eb',
		'icons'            => array(
			array(
				'src'   => $img . '/android-chrome-192x192.png',
				'sizes' => '192x192',
				'type'  => 'image/png',
			),
			array(
				'src'   => $img . '/android-chrome-512x512.png',
				'sizes' => '512x512',
				'type'  => 'image/png',
			),
		),
	);

	header( 'Content-Type: application/manifest+json; charset=utf-8' );
	echo wp_json_encode( $manifest );
	exit;
}
add_action( 'template_redirect', 'rf_manifest_render' );

// The rewrite rule above is flushed by rf_flush_rewrites_v110() (inc/seo-fixes.php)
// once per theme version, from admin or WP-Cron.
