<?php
/**
 * ReceiptGenPro — Performance hardening
 *
 * The #1 technical finding against Elementor-based competitor sites was
 * bloated CSS/JS hurting Core Web Vitals (LCP / CLS / INP). This file
 * strips WordPress's own default bloat so a hand-coded theme starts from
 * a genuinely lean baseline.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Kill emoji script/styles (a full JS file loaded sitewide for emoji support
// almost nobody uses on a business tool site).
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );

// Remove unnecessary <head> cruft.
remove_action( 'wp_head', 'rsd_link' );                     // EditURI link
remove_action( 'wp_head', 'wlwmanifest_link' );              // Windows Live Writer
remove_action( 'wp_head', 'wp_shortlink_wp_head' );          // Shortlink
remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' ); // prev/next link noise

// Disable oEmbed discovery JS (saves a request on every page).
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
remove_action( 'wp_head', 'wp_oembed_add_host_js' );

// Dequeue block-library CSS on pages that don't use core blocks heavily —
// keep it on singular content (blog posts) where block content lives.
function rf_dequeue_block_css() {
	if ( ! is_singular( 'post' ) ) {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'classic-theme-styles' );
	}
}
add_action( 'wp_enqueue_scripts', 'rf_dequeue_block_css', 100 );

// Disable XML-RPC (attack surface + resource drain) — not needed unless
// the Jetpack mobile app is used.
add_filter( 'xmlrpc_enabled', '__return_false' );

// Add width/height + loading=lazy is native since WP 5.5, but make sure
// decoding=async is present too, which shaves a little rendering time.
//
// This filter also guarantees every image has a real alt attribute. The
// WXR-imported blog thumbnails never had alt text set on the attachment,
// so the_post_thumbnail() was printing alt="" sitewide (blog cards on the
// homepage, /blog/, category/author archives, and single posts) — that's
// both an accessibility failure and a missed image-SEO signal. Falling
// back to the attachment's own title (WordPress always has one, even for
// an import) means no image ever ships with an empty alt again, without
// needing to hand-edit every media item.
function rf_add_decoding_async( $attr, $attachment = null ) {
	$attr['decoding'] = 'async';
	if ( empty( $attr['alt'] ) && $attachment instanceof WP_Post ) {
		$attr['alt'] = $attachment->post_title ? $attachment->post_title : get_the_title( $attachment->post_parent );
	}
	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'rf_add_decoding_async', 10, 2 );

// DNS-prefetch the CDN that serves the export libraries (only on pages
// with the tool). v1.1.0: the libraries themselves are lazy-loaded on the
// first Download click, so a cheap dns-prefetch replaces the preconnect.
function rf_resource_hints( $urls, $relation_type ) {
	if ( 'dns-prefetch' === $relation_type && wp_script_is( 'rf-receipt-generator', 'enqueued' ) ) {
		$urls[] = 'https://cdnjs.cloudflare.com';
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'rf_resource_hints', 10, 2 );

// Defer/async every enqueued script by default except the two CDN libs
// that html2canvas/jsPDF need loaded in order (handled via dependency
// array, not async) — everything else can safely defer.
//
// FRONT-END ONLY — this must never run in wp-admin. WordPress's block
// editor (Gutenberg) enqueues its own core scripts (wp-i18n, wp-api-fetch,
// wp-data, wp-blocks, …) together with small inline "-after" bootstrap
// scripts that call them synchronously right after each library loads
// (e.g. wp.apiFetch.use(...), wp.i18n.setLocaleData(...)). Those inline
// bootstrap scripts are never deferred, so if this filter also adds
// `defer` to the *library* scripts on admin pages, the inline scripts run
// before their dependencies exist — every wp.* global comes back
// `undefined`, which crashes the editor and silently breaks the REST API
// client (apiFetch never gets its /wp-json/ root-URL middleware attached).
// Keeping this strictly to the front end is what fixes that.
function rf_defer_scripts( $tag, $handle, $src ) {
	if ( is_admin() ) {
		return $tag;
	}

	$no_defer = array( 'jspdf', 'html2canvas', 'rf-receipt-generator' );
	if ( in_array( $handle, $no_defer, true ) ) {
		return $tag;
	}
	if ( strpos( $tag, 'defer' ) === false ) {
		$tag = str_replace( ' src', ' defer src', $tag );
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'rf_defer_scripts', 10, 3 );

// Limit post revisions (keeps the DB — and therefore admin/query speed — lean).
if ( ! defined( 'WP_POST_REVISIONS' ) ) {
	define( 'WP_POST_REVISIONS', 5 );
}

// Disable self-pingbacks (common source of comment-table junk that never
// helps SEO).
function rf_disable_self_pingbacks( &$links ) {
	$home = get_option( 'home' );
	foreach ( $links as $l => $link ) {
		if ( 0 === strpos( $link, $home ) ) {
			unset( $links[ $l ] );
		}
	}
}
add_action( 'pre_ping', 'rf_disable_self_pingbacks' );
