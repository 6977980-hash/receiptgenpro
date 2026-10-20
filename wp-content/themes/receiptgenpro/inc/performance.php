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
remove_action( 'template_redirect', 'wp_shortlink_header', 11 ); // Shortlink HTTP header
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

// DNS-prefetch the CDN that serves the generator's three libraries (only
// on pages with the tool). receipt-generator.js loads them itself:
// qrcode-generator as soon as the preview draws its QR code (on by
// default), html2canvas and jsPDF on the first Download click.
function rf_resource_hints( $urls, $relation_type ) {
	if ( 'dns-prefetch' === $relation_type && wp_script_is( 'rf-receipt-generator', 'enqueued' ) ) {
		$urls[] = 'https://cdnjs.cloudflare.com';
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'rf_resource_hints', 10, 2 );

// Defer the theme's own scripts only (audit 20 Oct, #49). The old filter
// added `defer` to every front-end <script src>, including other plugins'
// scripts with inline "after" code (jQuery + its inline callers), which
// then ran before their library. WordPress 6.3+ does this safely with a
// loading strategy: it drops `defer` itself when an inline "after" script
// or a non-deferred dependant needs the file earlier.
//
// rf-receipt-generator stays a normal footer script: the embed page and
// inline code expect it to have run before DOMContentLoaded handlers.
function rf_deferred_handles() {
	return array( 'rf-main', 'rf-consent', 'rf-verify-receipt', 'rf-widget-builder' );
}

function rf_defer_theme_scripts() {
	if ( ! version_compare( get_bloginfo( 'version' ), '6.3', '>=' ) ) {
		return; // older WordPress: rf_defer_scripts_legacy() below.
	}
	foreach ( rf_deferred_handles() as $handle ) {
		if ( wp_script_is( $handle, 'registered' ) ) {
			wp_script_add_data( $handle, 'strategy', 'defer' );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'rf_defer_theme_scripts', 99 );

// Before 6.3 there is no strategy: add the attribute to the same handles.
function rf_defer_scripts_legacy( $tag, $handle ) {
	if ( is_admin() || version_compare( get_bloginfo( 'version' ), '6.3', '>=' ) || ! in_array( $handle, rf_deferred_handles(), true ) ) {
		return $tag;
	}
	if ( false === strpos( $tag, ' defer' ) ) {
		$tag = str_replace( ' src=', ' defer src=', $tag );
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'rf_defer_scripts_legacy', 10, 2 );

// Post revisions: WordPress core defines WP_POST_REVISIONS before themes
// load, so a theme cannot change it. To limit revisions, add
// define( 'WP_POST_REVISIONS', 5 ); to wp-config.php instead.

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
