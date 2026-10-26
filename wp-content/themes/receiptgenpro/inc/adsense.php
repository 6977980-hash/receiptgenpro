<?php
/**
 * ReceiptGenPro — AdSense readiness scaffold
 *
 * This file does NOT enable ads on its own — RF_ADSENSE_CLIENT_ID is empty
 * by default, so nothing here does anything until you fill it in after
 * you're approved. What it gives you ahead of time:
 *
 *  1. A virtual /ads.txt endpoint (Google requires this file at your
 *     domain root — this serves it from WordPress so you don't need FTP
 *     access to your server's document root).
 *  2. A lightweight cookie-consent banner that gates the AdSense script
 *     behind visitor consent (Google's EU User Consent Policy requires
 *     this for EEA/UK visitors — showing it to everyone is the simplest
 *     compliant default).
 *  3. The actual AdSense <script> tag, auto-inserted into <head> once you
 *     set RF_ADSENSE_CLIENT_ID — and only fired after consent.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Set this to your AdSense publisher ID (looks like "pub-1234567890123456")
 * once you're approved. Leave blank until then — nothing below activates
 * without it.
 */
if ( ! defined( 'RF_ADSENSE_CLIENT_ID' ) ) {
	define( 'RF_ADSENSE_CLIENT_ID', '' ); // e.g. 'pub-1234567890123456'
}

/**
 * Google Analytics 4 measurement ID (e.g. 'G-XXXXXXXXXX'). Optional.
 * Loaded only after the visitor accepts cookies (same consent gate as ads).
 * Sept 2026 audit H6: the site had no analytics at all. Theme 1.4.3: on.
 */
if ( ! defined( 'RF_GA4_ID' ) ) {
	define( 'RF_GA4_ID', 'G-T47P9EX906' );
}

/**
 * True when there is actually something that needs consent. The banner is
 * hidden until then (audit M11: asking for cookie consent on a site that
 * sets no cookies is friction with no purpose).
 *
 * IMPORTANT for AdSense: for visitors in the EEA, UK and Switzerland Google
 * requires a Google-certified CMP. When you get approved, create the free
 * "European regulations message" in AdSense → Privacy & messaging; it will
 * replace this simple banner for those visitors.
 */
function rf_needs_consent() {
	return (bool) ( RF_ADSENSE_CLIENT_ID || RF_GA4_ID );
}

/**
 * Audit 20 Oct (#66): no ads or analytics on /verify-receipt/. Its URL
 * carries the whole receipt after #r=, and while a fragment is not sent
 * in requests, ad and analytics scripts can read location.href.
 */
function rf_tracking_allowed_here() {
	return ! is_page_template( 'page-verify-receipt.php' );
}

/**
 * Google Consent Mode v2 (#66): everything is denied until the visitor
 * accepts; assets/js/consent.js sends the update. Printed first in <head>,
 * before any Google tag, and only when there is a tag to gate.
 */
function rf_output_consent_mode_default() {
	if ( ! rf_needs_consent() || ! rf_tracking_allowed_here() ) {
		return;
	}
	?>
	<script>
	window.dataLayer = window.dataLayer || [];
	window.gtag = window.gtag || function () { dataLayer.push(arguments); };
	gtag('consent', 'default', { ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied', wait_for_update: 500 });
	</script>
	<?php
}
add_action( 'wp_head', 'rf_output_consent_mode_default', 1 );

/* ==========================================================================
   1. Virtual /ads.txt
   ========================================================================== */

function rf_ads_txt_rewrite() {
	add_rewrite_rule( '^ads\.txt$', 'index.php?rf_ads_txt=1', 'top' );
}
add_action( 'init', 'rf_ads_txt_rewrite' );

function rf_ads_txt_query_var( $vars ) {
	$vars[] = 'rf_ads_txt';
	return $vars;
}
add_filter( 'query_vars', 'rf_ads_txt_query_var' );

function rf_ads_txt_render() {
	if ( ! get_query_var( 'rf_ads_txt' ) ) {
		return;
	}
	// v1.1.0 (audit M9): until there is an AdSense ID, /ads.txt is a real
	// 404 (it used to redirect to /ads.txt/ and serve an empty 200 page).
	if ( ! RF_ADSENSE_CLIENT_ID ) {
		return; // Let WordPress render its normal 404.
	}
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo "google.com, " . esc_html( RF_ADSENSE_CLIENT_ID ) . ", DIRECT, f08c47fec0942fa0\n";
	// Add additional ad-network lines here if you sign up for more than AdSense, e.g.:
	// echo "othernetwork.com, 12345, DIRECT\n";
	exit;
}
add_action( 'template_redirect', 'rf_ads_txt_render', 0 ); // before redirect_canonical

// When there's no ads.txt content, make sure the rewrite hit is a 404.
function rf_ads_txt_404( $wp ) {
	if ( ! empty( $wp->query_vars['rf_ads_txt'] ) && ! RF_ADSENSE_CLIENT_ID ) {
		$wp->query_vars = array( 'error' => '404' );
	}
}
add_action( 'parse_request', 'rf_ads_txt_404' );

// The rewrite rule above is flushed by rf_flush_rewrites_v110() (inc/seo-fixes.php)
// once per theme version, from admin or WP-Cron.

/* ==========================================================================
   2. Cookie consent banner (gates ad/analytics scripts)
   ========================================================================== */

function rf_enqueue_consent_banner() {
	if ( ! rf_needs_consent() ) {
		return;
	}
	wp_enqueue_script( 'rf-consent', get_template_directory_uri() . '/assets/js/consent.js', array(), rf_asset_ver( 'assets/js/consent.js' ), false );
}
add_action( 'wp_enqueue_scripts', 'rf_enqueue_consent_banner' );

function rf_render_consent_banner() {
	if ( ! rf_needs_consent() ) {
		return;
	}
	?>
	<div id="rf-consent-banner" class="rf-consent-banner" role="region" aria-label="Cookie consent" hidden>
		<div class="rf-consent-inner">
			<p>We use cookies for analytics and, where enabled, personalized advertising. See our <a href="<?php echo esc_url( home_url( '/cookie-policy/' ) ); ?>">Cookie Policy</a>.</p>
			<div class="rf-consent-actions">
				<button type="button" id="rf-consent-decline" class="rf-btn rf-btn-secondary">Decline</button>
				<button type="button" id="rf-consent-accept" class="rf-btn rf-btn-primary">Accept</button>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'rf_render_consent_banner', 5 );

/* ==========================================================================
   3. The actual AdSense script — only prints once you set the client ID
   above, and only executes client-side after consent is granted.
   ========================================================================== */

function rf_output_adsense_script() {
	if ( ! RF_ADSENSE_CLIENT_ID || ! rf_tracking_allowed_here() ) {
		return;
	}
	?>
	<script>
	document.addEventListener('rfConsentGranted', function () {
		if (window.rfAdsenseLoaded) { return; }
		window.rfAdsenseLoaded = true;
		var s = document.createElement('script');
		s.async = true;
		s.src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-<?php echo esc_js( RF_ADSENSE_CLIENT_ID ); ?>';
		s.crossOrigin = 'anonymous';
		document.head.appendChild(s);
	});
	</script>
	<?php
}
add_action( 'wp_head', 'rf_output_adsense_script', 20 );

function rf_output_ga4_script() {
	if ( ! RF_GA4_ID || ! rf_tracking_allowed_here() ) {
		return;
	}
	?>
	<script>
	document.addEventListener('rfConsentGranted', function () {
		if (window.rfGa4Loaded) { return; }
		window.rfGa4Loaded = true;
		var s = document.createElement('script');
		s.async = true;
		s.src = 'https://www.googletagmanager.com/gtag/js?id=<?php echo esc_js( RF_GA4_ID ); ?>';
		document.head.appendChild(s);
		window.dataLayer = window.dataLayer || [];
		window.gtag = window.gtag || function () { dataLayer.push(arguments); };
		gtag('js', new Date());
		gtag('config', '<?php echo esc_js( RF_GA4_ID ); ?>');
	});
	// Count receipt downloads as GA4 events (no receipt data is sent).
	document.addEventListener('click', function (e) {
		var b = e.target.closest && e.target.closest('#rf-download-pdf, #rf-download-png, #rf-download-jpg');
		if (b && window.gtag) {
			gtag('event', 'receipt_download', { format: b.id.replace('rf-download-', ''), page_path: location.pathname });
		}
	});
	</script>
	<?php
}
add_action( 'wp_head', 'rf_output_ga4_script', 21 );
