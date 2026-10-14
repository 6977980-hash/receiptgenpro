<?php
/**
 * ReceiptGenPro — On-page SEO engine
 *
 * Outputs title tag, meta description, canonical, robots, Open Graph and
 * Twitter Card tags for every template WITHOUT requiring an SEO plugin.
 * (Fully compatible if the site owner later installs Yoast/RankMath —
 * this file backs off automatically, see rf_seo_plugin_active().)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function rf_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' );
}

/**
 * Central place that decides the title / description / canonical for
 * ANY URL on the site. Category-page data comes from inc/category-data.php
 * so each /xxx-receipt/ page gets a genuinely unique tag — this is the
 * fix for the keyword-cannibalization problem found on competitor sites,
 * where the homepage and a "/receipt-maker/" page fought for the same title.
 */
function rf_get_seo_data() {
	$data = array(
		'title'       => get_bloginfo( 'name' ),
		'description' => RF_BRAND_DESCRIPTION,
		'canonical'   => home_url( add_query_arg( array(), $GLOBALS['wp']->request ?? '' ) ),
		'robots'      => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
		'og_type'     => 'website',
		'image'       => get_template_directory_uri() . '/assets/img/og-default.png',
	);

	if ( is_front_page() ) {
		// v1.1.0 (audit M1): 71 → 55 characters so it isn't truncated on mobile.
		$data['title']       = 'Free Online Receipt Generator – No Signup, No Watermark';
		$data['description'] = 'Create a professional receipt online free in under 60 seconds. No signup, no watermark. Download as PDF, PNG or JPEG. For businesses, freelancers and landlords.';
		$data['canonical']   = home_url( '/' );
	} elseif ( rf_is_templates_hub() ) {
		$data['title']       = 'Free Receipt Templates (PDF, PNG & JPEG) – Fill Online';
		$data['description'] = 'Browse 16 free receipt templates — rent, HRA, payment, deposit, cash, donation, hotel, taxi, freelance and more. Fill online and download as PDF, PNG or JPEG.';
		$data['canonical']   = get_permalink();
	} elseif ( is_page_template( 'page-receipt-category.php' ) ) {
		global $post;
		$cat = rf_get_category_data( $post->post_name );
		if ( $cat ) {
			$data['title']       = $cat['seo_title'];
			$data['description'] = $cat['meta_description'];
		}
		$data['canonical'] = get_permalink();
	} elseif ( is_singular( 'post' ) ) {
		global $post;
		$meta_title = get_post_meta( $post->ID, 'rf_seo_title', true );
		$meta_desc  = get_post_meta( $post->ID, 'rf_seo_description', true );
		// v1.1.0 (audit M2): one consistent format. Custom title if set,
		// otherwise the post title, with the brand appended only when the
		// result still fits in ~60 characters.
		$plain_title = wp_strip_all_tags( html_entity_decode( get_the_title(), ENT_QUOTES, 'UTF-8' ) );
		if ( $meta_title ) {
			$data['title'] = $meta_title;
		} elseif ( mb_strlen( $plain_title . ' | ' . RF_BRAND_NAME ) <= 60 ) {
			$data['title'] = $plain_title . ' | ' . RF_BRAND_NAME;
		} else {
			$data['title'] = $plain_title;
		}
		$data['description'] = $meta_desc ? $meta_desc : wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), 30 );
		$data['canonical']   = get_permalink();
		$data['og_type']     = 'article';
		if ( has_post_thumbnail() ) {
			$data['image'] = get_the_post_thumbnail_url( $post->ID, 'full' );
		}
	} elseif ( is_home() && ! is_front_page() ) {
		$data['title']       = 'Receipt & Invoice Guides | ' . RF_BRAND_NAME . ' Blog';
		$data['description'] = 'Practical, country-specific guides on making receipts, record-keeping and tax rules for small businesses in the US, UK, Canada, Australia and India.';
		$data['canonical']   = get_permalink( get_option( 'page_for_posts' ) );
		$data                = rf_seo_paginate( $data );
	} elseif ( is_page() ) {
		$data['title']       = get_the_title() . ' | ' . RF_BRAND_NAME;
		$data['description'] = has_excerpt() ? get_the_excerpt() : rf_default_page_description( get_post_field( 'post_name' ) );
		$data['canonical']   = get_permalink();
	} elseif ( is_author() ) {
		$author             = get_queried_object();
		$data['title']       = 'Articles by ' . $author->display_name . ' | ' . RF_BRAND_NAME;
		$data['description'] = get_the_author_meta( 'rf_expertise', $author->ID ) ?: ( $author->display_name . ' writes receipt, invoicing and small-business finance guides for ' . RF_BRAND_NAME . '.' );
		$data['canonical']   = get_author_posts_url( $author->ID );

		// Sept 2026 audit, Section 8.1: on a single-author site the author
		// archive is just a duplicate of /blog/, and it's the page whose URL
		// leaked the site owner's real email address (fixed separately by
		// changing the WordPress user's nickname — see rf_hide_author_sitemap()
		// below for the matching sitemap fix). Noindexing it removes the
		// incentive for it to ever get crawled/indexed again under any slug.
		$data['robots'] = 'noindex, follow';
	} elseif ( is_category() || is_tag() ) {
		$term               = get_queried_object();
		$data['title']       = ucfirst( $term->name ) . ' Articles | ' . RF_BRAND_NAME . ' Blog';
		$data['description'] = $term->description ? wp_strip_all_tags( $term->description ) : rf_default_term_description( $term );
		$data['canonical']   = get_term_link( $term );

		// Thin-archive guard (Sept 2026 audit, Section 3.3): WordPress auto-
		// creates a public, indexable archive for every tag/category, no
		// matter how few posts it holds. With 34 tags spread across ~20
		// posts, most tag archives are near-duplicate, low-value pages that
		// dilute crawl attention without helping anyone — noindex any
		// taxonomy archive under this post-count threshold rather than
		// pretending every tag deserves its own indexed page. It stays
		// crawlable (follow) so link equity still flows, and it will
		// automatically start being indexed the moment enough posts exist.
		if ( $term->count < RF_THIN_ARCHIVE_MIN ) {
			$data['robots'] = 'noindex, follow';
		}
		// Oct 2026 audit (SEO-03): tag archives are never indexed, whatever
		// their size — they are already left out of the sitemap.
		if ( is_tag() ) {
			$data['robots'] = 'noindex, follow';
		}
		$data = rf_seo_paginate( $data );
	} elseif ( is_404() ) {
		$data['title']     = 'Page Not Found | ' . RF_BRAND_NAME;
		$data['robots']    = 'noindex, follow';
		$data['canonical'] = ''; // v1.1.0 (audit M4): a 404 must not canonicalize to itself.
	} elseif ( is_search() ) {
		$data['title']     = 'Search results for "' . get_search_query( false ) . '" | ' . RF_BRAND_NAME;
		$data['robots']    = 'noindex, follow';
		$data['canonical'] = ''; // noindexed; no canonical to the homepage.
	}

	return apply_filters( 'rf_seo_data', $data );
}

/**
 * Oct 2026 audit (SEO-01): page 2+ of the blog and of term archives
 * canonicalizes to itself (not page 1) and gets its own title/description.
 */
function rf_seo_paginate( $data ) {
	$paged = (int) get_query_var( 'paged' );
	if ( $paged > 1 ) {
		$data['canonical']    = get_pagenum_link( $paged );
		$data['title']       .= ' – Page ' . $paged;
		$data['description'] .= ' – Page ' . $paged;
	}
	return $data;
}

function rf_output_seo_meta_tags() {
	if ( rf_seo_plugin_active() ) {
		return; // Let Yoast/RankMath own the <head> instead of double-printing tags.
	}

	$seo = rf_get_seo_data();
	?>
	<title><?php echo esc_html( $seo['title'] ); ?></title>
	<meta name="description" content="<?php echo esc_attr( $seo['description'] ); ?>">
	<meta name="robots" content="<?php echo esc_attr( $seo['robots'] ); ?>">
	<?php if ( ! empty( $seo['canonical'] ) ) : ?>
	<link rel="canonical" href="<?php echo esc_url( $seo['canonical'] ); ?>">
	<?php endif; ?>

	<!-- Open Graph -->
	<meta property="og:site_name" content="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
	<meta property="og:type" content="<?php echo esc_attr( $seo['og_type'] ); ?>">
	<meta property="og:title" content="<?php echo esc_attr( $seo['title'] ); ?>">
	<meta property="og:description" content="<?php echo esc_attr( $seo['description'] ); ?>">
	<?php if ( ! empty( $seo['canonical'] ) ) : ?>
	<meta property="og:url" content="<?php echo esc_url( $seo['canonical'] ); ?>">
	<?php endif; ?>
	<meta property="og:image" content="<?php echo esc_url( $seo['image'] ); ?>">
	<?php if ( false !== strpos( $seo['image'], '/assets/img/og-default.png' ) ) : ?>
	<meta property="og:image:width" content="1200">
	<meta property="og:image:height" content="630">
	<?php endif; ?>
	<meta property="og:locale" content="en_US">

	<!-- Twitter Card -->
	<meta name="twitter:card" content="summary_large_image">
	<meta name="twitter:title" content="<?php echo esc_attr( $seo['title'] ); ?>">
	<meta name="twitter:description" content="<?php echo esc_attr( $seo['description'] ); ?>">
	<meta name="twitter:image" content="<?php echo esc_url( $seo['image'] ); ?>">

	<?php if ( is_singular( 'post' ) ) : ?>
	<meta property="article:published_time" content="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
	<meta property="article:modified_time" content="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>">
	<?php endif; ?>
	<?php
}
add_action( 'wp_head', 'rf_output_seo_meta_tags', 1 );

/**
 * Unique fallback descriptions for standard pages (audit M3: About,
 * Contact, Privacy, Terms and Cookie all shared the site default).
 * A manual excerpt on the page always wins over these.
 */
function rf_default_page_description( $slug ) {
	$map = array(
		'about-us'             => 'Who builds ReceiptGenPro, why it exists, and how the free browser-based receipt generator keeps your data on your own device.',
		'contact-us'           => 'Contact the ReceiptGenPro team with questions, feedback, template requests, privacy requests or business inquiries. We reply by email.',
		'privacy-policy'       => 'How ReceiptGenPro handles data: the receipt generator runs in your browser, so receipt details are never uploaded. Cookies, ads and your rights.',
		'terms-and-conditions' => 'The terms for using ReceiptGenPro, including acceptable use: receipts only for real transactions of your own business, never to mislead anyone.',
		'cookie-policy'        => 'Which cookies and similar storage ReceiptGenPro uses, why, and how to control or opt out of them in your browser and ad settings.',
		'editorial-policy'     => 'How ReceiptGenPro researches, writes, sources and updates its receipt, record-keeping and tax guides — and how to report an error.',
	);
	return $map[ $slug ] ?? RF_BRAND_DESCRIPTION;
}

/**
 * Real, specific descriptions for the blog categories (audit H4 — they
 * were "Guides and articles about How-To Guides."). Used for the meta
 * description AND printed as the archive intro when the category has no
 * description of its own in wp-admin.
 */
function rf_default_term_description( $term ) {
	$map = array(
		'how-to-guides'       => 'Step-by-step guides for creating, filling out, numbering and sending receipts — for landlords, freelancers, small shops, online sellers and nonprofits.',
		'legal-compliance'    => 'What a receipt legally needs to include, how long to keep business records, and what happens in an audit — with links to the official tax-authority guidance.',
		'country-guides'      => 'Receipt and invoice rules country by country: IRS (USA), HMRC VAT (UK), CRA (Canada), ATO (Australia) and GST (India), with official sources.',
		'comparisons'         => 'Side-by-side comparisons: receipt vs invoice, digital vs paper receipts, and Word, Excel, Google Docs or an online generator for making receipts.',
		'small-business-tips' => 'Practical record-keeping and receipt tips for small businesses, sole traders and side hustles.',
	);
	return $map[ $term->slug ] ?? ( 'Guides from ' . RF_BRAND_NAME . ' about ' . $term->name . ': receipts, record-keeping and small-business tax basics.' );
}

/**
 * Remove the default <title> tag output when we're printing our own,
 * and disable WP's generator meta tag (no need to broadcast the version).
 */
remove_action( 'wp_head', 'wp_generator' );
