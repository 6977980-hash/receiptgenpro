<?php
/**
 * ReceiptGenPro Theme — functions.php
 *
 * Central bootstrap file. Every brand-specific string lives in the
 * constants below so the whole site can be rebranded from one place.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

/* ==========================================================================
   1. BRAND CONSTANTS — edit these, nothing else, to rebrand the whole site
   ========================================================================== */

define( 'RF_BRAND_NAME', 'ReceiptGenPro' );
define( 'RF_BRAND_DOMAIN', 'receiptgenpro.com' );
define( 'RF_BRAND_TAGLINE', 'Free Online Receipt Generator' );
define( 'RF_BRAND_DESCRIPTION', 'Create professional, itemized receipts online in seconds — free, no signup, no watermark. Download as PDF, PNG or JPEG.' );
define( 'RF_SUPPORT_EMAIL', 'support@receiptgenpro.com' );
define( 'RF_LOGO_TEXT', 'Receipt<span class="rf-accent">GenPro</span>' );
// Social profiles (Sept 2026 audit, H5): only fill these in once the
// profile really exists and is active. They are printed in the footer AND
// in Organization "sameAs" schema — a link to a profile that doesn't exist
// is a negative trust signal. Empty = hidden everywhere.
define( 'RF_SOCIAL_TWITTER', '' );  // e.g. 'https://x.com/receiptgenpro'
define( 'RF_SOCIAL_FACEBOOK', '' ); // e.g. 'https://facebook.com/receiptgenpro'
define( 'RF_SOCIAL_LINKEDIN', '' ); // e.g. 'https://linkedin.com/company/receiptgenpro'
define( 'RF_THEME_VERSION', '1.1.5' );

/* ==========================================================================
   2. THEME SETUP
   ========================================================================== */

function rf_theme_setup() {
	load_theme_textdomain( 'receiptgenpro', get_template_directory() . '/languages' );

	// NOTE: deliberately NOT calling add_theme_support( 'title-tag' ) here.
	// inc/seo-meta.php prints its own hand-crafted <title> tag (unique,
	// keyword-optimized per page — see rf_get_seo_data()). Enabling core's
	// title-tag support at the same time makes WordPress print a SECOND,
	// generic "Site Title – Tagline" <title> tag on every page; browsers
	// use whichever tag comes first in the HTML, which silently defeats
	// every custom SEO title this theme generates. Keep this disabled.
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support(
		'align-wide'
	);

	// Image sizes tuned for the blog / author avatars — keeps CLS low.
	set_post_thumbnail_size( 1200, 630, true ); // matches og:image ratio
	add_image_size( 'rf-card', 600, 400, true );
	add_image_size( 'rf-author', 160, 160, true );

	register_nav_menus(
		array(
			'primary' => __( 'Primary Menu', 'receiptgenpro' ),
			'footer-tools'   => __( 'Footer — Receipt Tools', 'receiptgenpro' ),
			'footer-more'    => __( 'Footer — More Receipts', 'receiptgenpro' ),
			'footer-support' => __( 'Footer — Support', 'receiptgenpro' ),
		)
	);
}
add_action( 'after_setup_theme', 'rf_theme_setup' );

/* ==========================================================================
   3. ASSETS — deliberately minimal. No page builder, no jQuery UI bloat.
   ========================================================================== */

/**
 * Asset version = the file's modification time, so browsers and the page
 * cache pick up a changed file on the next deploy even if RF_THEME_VERSION
 * was not bumped. Falls back to the theme version if the file is missing.
 */
function rf_asset_ver( $rel ) {
	$mtime = @filemtime( get_template_directory() . '/' . ltrim( $rel, '/' ) );
	return $mtime ? (string) $mtime : RF_THEME_VERSION;
}

function rf_enqueue_assets() {
	wp_enqueue_style( 'rf-main', get_template_directory_uri() . '/assets/css/main.css', array(), rf_asset_ver( 'assets/css/main.css' ) );

	wp_enqueue_script( 'rf-main', get_template_directory_uri() . '/assets/js/main.js', array(), rf_asset_ver( 'assets/js/main.js' ), true );

	// The receipt generator tool loads ONLY on the pages that contain it.
	// v1.1.0 (audit M5): jsPDF (~350 KB) and html2canvas (~200 KB) are no
	// longer enqueued up front — receipt-generator.js lazy-loads them from
	// cdnjs the first time a visitor clicks a Download button.
	if ( is_front_page() || is_page_template( 'page-receipt-category.php' ) ) {
		wp_enqueue_script( 'rf-receipt-generator', get_template_directory_uri() . '/assets/js/receipt-generator.js', array(), rf_asset_ver( 'assets/js/receipt-generator.js' ), true );
		wp_localize_script(
			'rf-receipt-generator',
			'rfReceipt',
			array(
				'brand'      => RF_BRAND_NAME,
				'currencies' => rf_supported_currencies(),
				// Subresource Integrity: the browser refuses the file if cdnjs ever
				// serves different bytes. Hashes are cdnjs's own published SRI values.
				'libs'       => array(
					'html2canvas' => array(
						'url'       => 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
						'integrity' => 'sha512-BNaRQnYJYiPSqHHDb58B0yaPfCu+Wgds8Gp/gU33kqBtgNS4tSPHuGibyoeqMV/TJlSKda6FXzoEyYGjTe+vXA==',
					),
					'jspdf'       => array(
						'url'       => 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
						'integrity' => 'sha512-qZvrmS2ekKPF2mSznTQsxqPgnpkI4DNTlrdUmTzrDgektczlKNRRhy5X5AAOnx5S09ydFYWWNSfcEqDTTHgtNA==',
					),
				),
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'rf_enqueue_assets' );

/**
 * A trimmed but genuinely useful currency list for the MVP tool.
 * Extend this array as the product grows — it is the single source of
 * truth for the currency <select> on every receipt form.
 */
function rf_supported_currencies() {
	return array(
		'USD' => '$ — US Dollar',
		'EUR' => '€ — Euro',
		'GBP' => '£ — British Pound',
		'PKR' => '₨ — Pakistani Rupee',
		'INR' => '₹ — Indian Rupee',
		'CAD' => 'CA$ — Canadian Dollar',
		'AUD' => 'AU$ — Australian Dollar',
		'AED' => 'د.إ — UAE Dirham',
		'SAR' => '﷼ — Saudi Riyal',
	);
}

/* ==========================================================================
   4. PERFORMANCE — undo the things that hurt Core Web Vitals by default
   ========================================================================== */
require get_template_directory() . '/inc/performance.php';

/* ==========================================================================
   5. SEO — title tags, meta description, canonical, Open Graph, Twitter Card
   ========================================================================== */
require get_template_directory() . '/inc/seo-meta.php';

/* ==========================================================================
   5b. FAVICONS / APP ICONS — favicon.ico, apple-touch-icon, PWA icons and
       the virtual /site.webmanifest endpoint.
   ========================================================================== */
require get_template_directory() . '/inc/favicon.php';

/* ==========================================================================
   6. STRUCTURED DATA — JSON-LD for SEO + GEO (generative engines) + AEO
   ========================================================================== */
require get_template_directory() . '/inc/schema.php';

/* ==========================================================================
   6b. ADSENSE READINESS — virtual ads.txt + consent-gated ad script.
   Inactive until you set RF_ADSENSE_CLIENT_ID (see inc/adsense.php).
   ========================================================================== */
require get_template_directory() . '/inc/adsense.php';

/* ==========================================================================
   7. RECEIPT CATEGORY PAGE DATA — the single source of truth that keeps
      every /xxx-receipt/ page unique (prevents the cannibalization bug
      found on competitor sites where two pages target one keyword).
   ========================================================================== */
require get_template_directory() . '/inc/category-data.php';

/* ==========================================================================
   7b. SEPT 2026 AUDIT FIXES (v1.1.0)
       - inc/seo-fixes.php     : robots/canonical/sitemap/security/REST/
                                 hub page/llms.txt/IndexNow/content filters
       - inc/post-sources.php  : official sources + key facts per post
       - inc/content-patches.php + inc/admin-fixes.php : one-click database
         fixes under Tools → ReceiptGenPro Fixes (dates, links, placeholder
         text, risky wording, author slug, editorial policy page)
   ========================================================================== */
require get_template_directory() . '/inc/seo-fixes.php';
require get_template_directory() . '/inc/post-sources.php';
require get_template_directory() . '/inc/content-patches.php';
if ( is_admin() ) {
	require get_template_directory() . '/inc/admin-fixes.php';
}

/* ==========================================================================
   8. MISC THEME HELPERS
   ========================================================================== */

/**
 * Excerpt length + "read more" string, tuned for AEO snippet-ability:
 * short, direct, answer-first excerpts perform better as AI/Answer Engine
 * citations than vague teaser copy.
 */
add_filter( 'excerpt_length', fn() => 28 );
add_filter( 'excerpt_more', fn() => '&hellip;' );

/**
 * Register a simple "Job Title / Credentials" field on the user profile
 * screen so author bios can carry real E-E-A-T signals (used by
 * template-parts/author-box.php and inc/schema.php Person markup).
 */
function rf_user_contact_fields( $fields ) {
	$fields['rf_job_title']  = __( 'Job Title / Credentials (e.g. "Certified Public Accountant, 12 yrs experience")', 'receiptgenpro' );
	$fields['rf_expertise']  = __( 'One-line expertise statement (shown in author box + Person schema)', 'receiptgenpro' );
	$fields['linkedin']      = __( 'LinkedIn URL', 'receiptgenpro' );
	$fields['rf_avatar_url'] = __( 'Author photo URL (upload to Media Library, paste its URL; square, 96 px or larger)', 'receiptgenpro' );
	return $fields;
}
add_filter( 'user_contactmethods', 'rf_user_contact_fields' );

/**
 * Author avatar without Gravatar (audit phase 4, PERF-02): get_avatar()
 * made every blog post request secure.gravatar.com. This shows the photo
 * set in the profile field "Author photo URL", or the author's initials.
 */
function rf_author_avatar( $author_id, $size = 96 ) {
	$name = (string) get_the_author_meta( 'display_name', $author_id );
	$url  = (string) get_the_author_meta( 'rf_avatar_url', $author_id );
	if ( '' !== $url ) {
		return sprintf(
			'<img src="%1$s" width="%2$d" height="%2$d" alt="%3$s" class="rf-avatar" loading="lazy" decoding="async">',
			esc_url( $url ),
			(int) $size,
			esc_attr( $name . ' avatar' )
		);
	}
	$initials = '';
	foreach ( array_slice( preg_split( '/\s+/', trim( $name ) ), 0, 2 ) as $part ) {
		$initials .= function_exists( 'mb_substr' ) ? mb_substr( $part, 0, 1 ) : substr( $part, 0, 1 );
	}
	return sprintf(
		'<span class="rf-avatar rf-avatar-initials" style="width:%1$dpx;height:%1$dpx;font-size:%2$dpx" aria-hidden="true">%3$s</span>',
		(int) $size,
		(int) round( $size * 0.38 ),
		esc_html( strtoupper( $initials ) )
	);
}

function rf_extra_profile_fields( $user ) {
	?>
	<h2><?php esc_html_e( 'ReceiptGenPro Author / E-E-A-T Details', 'receiptgenpro' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><label for="rf_job_title"><?php esc_html_e( 'Job Title / Credentials', 'receiptgenpro' ); ?></label></th>
			<td><input type="text" name="rf_job_title" id="rf_job_title" value="<?php echo esc_attr( get_the_author_meta( 'rf_job_title', $user->ID ) ); ?>" class="regular-text" /></td>
		</tr>
		<tr>
			<th><label for="rf_expertise"><?php esc_html_e( 'Expertise statement', 'receiptgenpro' ); ?></label></th>
			<td><input type="text" name="rf_expertise" id="rf_expertise" value="<?php echo esc_attr( get_the_author_meta( 'rf_expertise', $user->ID ) ); ?>" class="regular-text" /></td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'rf_extra_profile_fields' );
add_action( 'edit_user_profile', 'rf_extra_profile_fields' );

function rf_save_extra_profile_fields( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return false;
	}
	if ( isset( $_POST['rf_job_title'] ) ) {
		update_user_meta( $user_id, 'rf_job_title', sanitize_text_field( $_POST['rf_job_title'] ) );
	}
	if ( isset( $_POST['rf_expertise'] ) ) {
		update_user_meta( $user_id, 'rf_expertise', sanitize_text_field( $_POST['rf_expertise'] ) );
	}
}
add_action( 'personal_options_update', 'rf_save_extra_profile_fields' );
add_action( 'edit_user_profile_update', 'rf_save_extra_profile_fields' );

/**
 * Breadcrumb helper used by template-parts/breadcrumbs.php and mirrored
 * into BreadcrumbList schema so the visible trail and the structured data
 * never fall out of sync (a common audit failure on competitor sites).
 */
function rf_get_breadcrumbs() {
	$trail = array(
		array( 'title' => 'Home', 'url' => home_url( '/' ) ),
	);

	if ( is_singular( 'post' ) ) {
		$trail[] = array( 'title' => 'Blog', 'url' => get_permalink( get_option( 'page_for_posts' ) ) );
		$trail[] = array( 'title' => get_the_title(), 'url' => get_permalink() );
	} elseif ( is_home() && ! is_front_page() ) {
		$trail[] = array( 'title' => 'Blog', 'url' => get_permalink( get_option( 'page_for_posts' ) ) );
	} elseif ( is_page_template( 'page-receipt-category.php' ) ) {
		$trail[] = array( 'title' => 'Receipt Templates', 'url' => home_url( '/receipt-templates/' ) );
		// Oct 2026 audit (SEO-05): same name as the page's H1, not the long SEO title.
		$cat     = rf_get_category_data( get_post_field( 'post_name' ) );
		$trail[] = array( 'title' => $cat ? rf_category_label( $cat ) : get_the_title(), 'url' => get_permalink() );
	} elseif ( rf_is_templates_hub() ) {
		$trail[] = array( 'title' => 'Receipt Templates', 'url' => home_url( '/receipt-templates/' ) );
	} elseif ( is_category() || is_tag() ) {
		$trail[] = array( 'title' => 'Blog', 'url' => get_permalink( get_option( 'page_for_posts' ) ) );
		$trail[] = array( 'title' => ucfirst( single_term_title( '', false ) ), 'url' => get_term_link( get_queried_object() ) );
	} elseif ( is_page() ) {
		$trail[] = array( 'title' => get_the_title(), 'url' => get_permalink() );
	} elseif ( is_author() ) {
		$trail[] = array( 'title' => 'Authors', 'url' => '' );
		$trail[] = array( 'title' => get_the_author(), 'url' => '' );
	}

	return $trail;
}

/**
 * Remove the auto-generated /wp-sitemap-users-1.xml sitemap.
 *
 * Sept 2026 audit, Section 8.1: WordPress's core sitemap publishes an
 * author-archive URL for every user, and on this site that URL exposed the
 * site owner's real Gmail address as a public, Google-indexed slug
 * (/author/6977980gmail-com/) since the archive slug is derived from the
 * account's user_nicename, not the display name. Changing the account's
 * nickname fixes future links; removing the users sitemap here stops
 * WordPress from ever re-advertising an author archive (for this or any
 * future user) as something worth crawling in the first place — the author
 * archive itself is also noindexed above (rf_get_seo_data(), is_author()
 * branch) as belt-and-suspenders since it's a pure duplicate of /blog/ on a
 * single-author site.
 */
function rf_remove_users_sitemap( $provider, $name ) {
	return ( 'users' === $name ) ? false : $provider;
}
add_filter( 'wp_sitemaps_add_provider', 'rf_remove_users_sitemap', 10, 2 );

