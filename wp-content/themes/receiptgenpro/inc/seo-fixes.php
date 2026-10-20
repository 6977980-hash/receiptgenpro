<?php
/**
 * ReceiptGenPro — Sept 2026 audit fixes (theme v1.1.0)
 *
 * Everything here is code-level and takes effect as soon as the theme is
 * updated. One-off DATABASE fixes (post dates, stored links, placeholder
 * text, risky wording, author slug) live in inc/admin-fixes.php so they
 * can be previewed and applied deliberately from Tools → ReceiptGenPro Fixes.
 *
 * Audit reference → section in this file
 *   H3  duplicate robots meta + duplicate canonical ...... 1
 *   C6  REST users endpoint / email author slug ......... 2
 *   M7  security headers + X-Powered-By ................. 3
 *   H4  sitemap (noindex tags, thin categories) ......... 4
 *   H7  /receipt-templates/ hub page .................... 5
 *   M8  /llms.txt ........................................ 6
 *   H1  /blog/ internal-link redirects (runtime) ........ 7
 *   C1  placeholder text safety net (runtime) ........... 7
 *   H6  IndexNow ......................................... 8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. ONE robots meta tag only (H3)
   inc/seo-meta.php already prints a complete robots tag. WordPress core
   printed a second one ("max-image-preview:large") via wp_robots().
   ========================================================================== */
remove_action( 'wp_head', 'wp_robots', 1 );

// Same problem for canonical: WordPress core's rel_canonical() printed a
// second <link rel="canonical"> on every singular page, on top of the one
// from inc/seo-meta.php. One canonical per page.
function rf_remove_core_canonical() {
	if ( ! rf_seo_plugin_active() ) {
		remove_action( 'wp_head', 'rel_canonical' );
	}
}
add_action( 'wp', 'rf_remove_core_canonical' );

/* ==========================================================================
   2. Stop leaking usernames (C6)
   ========================================================================== */

// Hide /wp-json/wp/v2/users from logged-out visitors (it exposed the
// "6977980gmail-com" login slug).
function rf_restrict_rest_users( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
}
add_filter( 'rest_endpoints', 'rf_restrict_rest_users' );

// Author archives are pure duplicates of /blog/ on this single-author,
// team-branded site and were the page whose URL carried the email slug.
// Send every author URL to About Us instead (301), and point author links
// there too, so no page ever links to /author/<login>/ again.
function rf_redirect_author_archives() {
	if ( is_author() || ( isset( $_GET['author'] ) && ! is_admin() ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		wp_safe_redirect( home_url( '/about-us/' ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'rf_redirect_author_archives', 1 );

function rf_author_link_to_about( $link ) {
	return home_url( '/about-us/' );
}
add_filter( 'author_link', 'rf_author_link_to_about' );

/* ==========================================================================
   3. Security headers (M7)
   ========================================================================== */
function rf_security_headers( $wp = null ) {
	if ( headers_sent() ) {
		return;
	}
	if ( function_exists( 'header_remove' ) ) {
		header_remove( 'X-Powered-By' );
	}
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	// v1.2.0: /embed/<template>/ is made to be shown in other sites' iframes.
	// Only a valid template on an /embed/ path gets frame-ancestors *.
	if ( $wp instanceof WP && isset( $wp->query_vars['rf_embed'] ) && function_exists( 'rf_embed_slug' ) && '' !== rf_embed_slug( $wp->query_vars['rf_embed'] ) ) {
		header( "Content-Security-Policy: frame-ancestors *" );
	} else {
		header( 'X-Frame-Options: SAMEORIGIN' );
	}
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
	if ( is_ssl() ) {
		header( 'Strict-Transport-Security: max-age=31536000' );
	}
}
add_action( 'send_headers', 'rf_security_headers' );

/* ==========================================================================
   4. Sitemap hygiene (H4)
   ========================================================================== */

// Tag archives are noindexed (thin, 1 post each) → never list them.
function rf_sitemap_taxonomies( $taxonomies ) {
	unset( $taxonomies['post_tag'] );
	return $taxonomies;
}
add_filter( 'wp_sitemaps_taxonomies', 'rf_sitemap_taxonomies' );

// Categories with fewer than 4 posts are noindexed by seo-meta.php, so
// exclude exactly those from the sitemap too (sitemap ⇔ robots in sync).
function rf_sitemap_category_args( $args, $taxonomy ) {
	if ( 'category' === $taxonomy ) {
		$terms   = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
		$exclude = array();
		foreach ( (array) $terms as $term ) {
			if ( $term instanceof WP_Term && $term->count < RF_THIN_ARCHIVE_MIN ) {
				$exclude[] = (int) $term->term_id;
			}
		}
		if ( $exclude ) {
			$args['exclude'] = $exclude;
		}
	}
	return $args;
}
add_filter( 'wp_sitemaps_taxonomies_query_args', 'rf_sitemap_category_args', 10, 2 );

if ( ! defined( 'RF_THIN_ARCHIVE_MIN' ) ) {
	define( 'RF_THIN_ARCHIVE_MIN', 4 );
}

/* ==========================================================================
   5. /receipt-templates/ hub (H7)
   A real WordPress Page (so it is editable, in the sitemap and in menus)
   using the page-receipt-templates.php template. Created automatically
   the first time an administrator loads wp-admin after the update.
   ========================================================================== */
/**
 * URL of the published Editorial Policy page, or '' if it doesn't exist
 * yet (created from Tools → ReceiptGenPro Fixes). Links to it are only
 * printed when it exists, so the theme never links to a 404.
 */
function rf_editorial_policy_url() {
	static $url = null;
	if ( null === $url ) {
		$page = get_page_by_path( 'editorial-policy', OBJECT, 'page' );
		$url  = ( $page && 'publish' === $page->post_status ) ? get_permalink( $page ) : '';
	}
	return $url;
}

function rf_is_templates_hub() {
	return is_page_template( 'page-receipt-templates.php' );
}

function rf_maybe_create_templates_hub() {
	if ( get_option( 'rf_hub_page_id' ) || ! current_user_can( 'publish_pages' ) ) {
		return;
	}
	$existing = get_page_by_path( 'receipt-templates', OBJECT, 'page' );
	if ( $existing ) {
		update_post_meta( $existing->ID, '_wp_page_template', 'page-receipt-templates.php' );
		update_option( 'rf_hub_page_id', $existing->ID );
		return;
	}
	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Receipt Templates',
			'post_name'    => 'receipt-templates',
			'post_content' => '',
			'post_excerpt' => 'Free receipt templates for landlords, freelancers, shops, hotels, restaurants, drivers, nonprofits and contractors — fill online and download as PDF, PNG or JPEG.',
		)
	);
	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, '_wp_page_template', 'page-receipt-templates.php' );
		update_option( 'rf_hub_page_id', $id );
	}
}
add_action( 'admin_init', 'rf_maybe_create_templates_hub' );

/* ==========================================================================
   6. /llms.txt (M8) — plain-text site summary for AI assistants
   ========================================================================== */
function rf_txt_rewrites() {
	add_rewrite_rule( '^llms\.txt$', 'index.php?rf_llms_txt=1', 'top' );
	add_rewrite_rule( '^([a-f0-9]{32})\.txt$', 'index.php?rf_indexnow_key=$matches[1]', 'top' );
}
add_action( 'init', 'rf_txt_rewrites' );

function rf_txt_query_vars( $vars ) {
	$vars[] = 'rf_llms_txt';
	$vars[] = 'rf_indexnow_key';
	return $vars;
}
add_filter( 'query_vars', 'rf_txt_query_vars' );

// Flush rewrite rules once for this version (adds llms.txt + IndexNow key).
function rf_flush_rewrites_v110() {
	if ( get_option( 'rf_rewrite_version' ) !== RF_THEME_VERSION ) {
		flush_rewrite_rules( false );
		update_option( 'rf_rewrite_version', RF_THEME_VERSION );
	}
}
add_action( 'init', 'rf_flush_rewrites_v110', 99 );

// Never add a trailing slash to *.txt / *.webmanifest endpoints (/ads.txt → /ads.txt/ bug).
function rf_no_slash_for_txt( $redirect_url, $requested_url ) {
	if ( preg_match( '#\.(txt|webmanifest)/?$#', (string) wp_parse_url( $requested_url, PHP_URL_PATH ) ) ) {
		return false;
	}
	return $redirect_url;
}
add_filter( 'redirect_canonical', 'rf_no_slash_for_txt', 10, 2 );

function rf_render_llms_txt() {
	if ( ! get_query_var( 'rf_llms_txt' ) ) {
		return;
	}
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );

	$lines   = array();
	$lines[] = '# ' . RF_BRAND_NAME;
	$lines[] = '';
	$lines[] = '> ' . RF_BRAND_NAME . ' is a free, browser-based receipt generator. Businesses, landlords, freelancers and nonprofits fill in a form and download a receipt as PDF, PNG or JPEG. No signup, no watermark, and receipt data never leaves the visitor\'s browser.';
	$lines[] = '';
	$lines[] = 'Intended use: receipts that a business or person issues for its own real transactions. Creating receipts on behalf of another business, or for payments that did not happen, is prohibited by the Terms.';
	$lines[] = '';
	$lines[] = '## Tools';
	$lines[] = '- [Receipt Generator](' . home_url( '/' ) . '): general-purpose itemized receipt maker';
	$lines[] = '- [All Receipt Templates](' . home_url( '/receipt-templates/' ) . '): overview of every template';
	foreach ( rf_receipt_categories() as $slug => $cat ) {
		$lines[] = '- [' . rf_category_label( $cat ) . '](' . home_url( '/' . $slug . '/' ) . '): ' . $cat['meta_description'];
	}
	$lines[] = '- [Receipt Requirements Checker](' . home_url( '/receipt-requirements/' ) . '): what a receipt must show for an HRA claim, a dependent care FSA, a $250+ donation, an expense report or a US rent payment, with official sources';
	$lines[] = '- [Verify a Receipt](' . home_url( '/verify-receipt/' ) . '): reads the verification QR code printed on ' . RF_BRAND_NAME . ' receipts to show the details stored in it (not proof of authenticity)';
	$lines[] = '- [Rent Receipt Laws by State](' . home_url( '/rent-receipt-laws/' ) . '): US states that require landlords to give rent receipts, with statute citations';
	$lines[] = '- [Receipt Generator Widget](' . home_url( '/receipt-generator-widget/' ) . '): free embed code to put any template on another website';
	$lines[] = '';
	$lines[] = '## Guides';
	$posts = get_posts(
		array(
			'numberposts' => 50,
			'post_status' => 'publish',
		)
	);
	foreach ( $posts as $p ) {
		$lines[] = '- [' . html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ) . '](' . get_permalink( $p ) . ')';
	}
	$lines[] = '';
	$lines[] = '## About';
	$lines[] = '- [About Us](' . home_url( '/about-us/' ) . ')';
	if ( rf_editorial_policy_url() ) {
		$lines[] = '- [Editorial Policy](' . rf_editorial_policy_url() . ')';
	}
	$lines[] = '- [Privacy Policy](' . home_url( '/privacy-policy/' ) . ')';
	$lines[] = '- [Terms and Conditions](' . home_url( '/terms-and-conditions/' ) . ')';
	$lines[] = '- Contact: ' . RF_SUPPORT_EMAIL;

	echo implode( "\n", $lines ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}
add_action( 'template_redirect', 'rf_render_llms_txt', 0 );

/* ==========================================================================
   7. Runtime content clean-up (H1, C1)
   These filters make the live pages correct immediately, even before the
   permanent database fixes in Tools → ReceiptGenPro Fixes are applied.
   ========================================================================== */
function rf_fix_content_links( $html ) {
	if ( ! is_string( $html ) || '' === $html ) {
		return $html;
	}
	return rf_rewrite_internal_links( $html );
}

/**
 * Shared by the runtime filter and the admin DB tool.
 *  - href="/blog/<slug>/"  → href="/<slug>/"  (posts live at the root; the
 *    /blog/ prefix only worked through WordPress's 404-guess redirect)
 *  - href="/#templates"    → href="/receipt-templates/"
 */
function rf_rewrite_internal_links( $html ) {
	$home = preg_quote( untrailingslashit( home_url() ), '~' );
	$html = preg_replace(
		'~href=(["\'])(' . $home . ')?/blog/(?!page/)([a-z0-9\-]+/?)(["\'#?])~i',
		'href=$1$2/$3$4',
		$html
	);
	$html = preg_replace(
		'~href=(["\'])(' . $home . ')?/#templates(["\'])~i',
		'href=$1$2/receipt-templates/$3',
		$html
	);
	return $html;
}
add_filter( 'the_content', 'rf_fix_content_links', 20 );

function rf_strip_placeholders( $html ) {
	if ( ! is_string( $html ) || false === strpos( $html, '[' ) ) {
		return $html;
	}
	$date = get_the_modified_date( 'F j, Y' );
	// "Last updated: [add the date…]. <boilerplate/lawyer note>" → real date.
	$html = preg_replace(
		'#<p><em>Last updated: \[add the date you publish this page\][^<]*</em></p>#i',
		'<p><em>Last updated: ' . esc_html( $date ) . '.</em></p>',
		$html
	);
	$html = str_replace( '[add the date you publish this page]', esc_html( $date ), $html );
	// Any remaining "[Site owner: …]" developer note.
	$html = preg_replace( '#<p>\s*<em>\s*\[Site owner:[^\]]*\]\s*</em>\s*</p>#i', '', $html );
	$html = preg_replace( '#\[Site owner:[^\]]*\]#i', '', $html );
	return $html;
}
add_filter( 'the_content', 'rf_strip_placeholders', 21 );

/* ==========================================================================
   8. IndexNow (H6) — instant ping to Bing / Yandex / Seznam / Naver when a
   post or page is published or updated. Bing's index also feeds ChatGPT
   Search and Copilot, so this matters for GEO as well as classic SEO.
   ========================================================================== */
function rf_indexnow_key() {
	$key = get_option( 'rf_indexnow_key' );
	if ( ! $key ) {
		$key = md5( wp_generate_password( 32, true, true ) . home_url() );
		update_option( 'rf_indexnow_key', $key, false );
	}
	return $key;
}

function rf_render_indexnow_key() {
	$req = get_query_var( 'rf_indexnow_key' );
	if ( ! $req ) {
		return;
	}
	if ( hash_equals( rf_indexnow_key(), $req ) ) {
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( $req );
		exit;
	}
	// Oct 2026 audit (SEO-02): any other 32-hex .txt is a real 404, not the blog page with a 200.
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}
add_action( 'template_redirect', 'rf_render_indexnow_key', 0 );

function rf_indexnow_ping( $new_status, $old_status, $post ) {
	if ( 'publish' !== $new_status || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
		return;
	}
	if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return;
	}
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( ! $host || in_array( $host, array( 'localhost', '127.0.0.1' ), true ) ) {
		return;
	}
	$key = rf_indexnow_key();
	wp_remote_post(
		'https://api.indexnow.org/indexnow',
		array(
			'timeout'  => 3,
			'blocking' => false,
			'headers'  => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'     => wp_json_encode(
				array(
					'host'        => $host,
					'key'         => $key,
					'keyLocation' => home_url( '/' . $key . '.txt' ),
					'urlList'     => array( get_permalink( $post ) ),
				)
			),
		)
	);
}
add_action( 'transition_post_status', 'rf_indexnow_ping', 10, 3 );
