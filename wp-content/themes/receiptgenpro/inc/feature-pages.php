<?php
/**
 * ReceiptGenPro — Pages for the v1.2.0 tools
 *
 *  /receipt-requirements/      what a receipt needs for HRA, FSA, $250+ gifts…
 *  /verify-receipt/            reads the QR code printed on a receipt
 *  /missing-receipt-form/      lost-receipt declaration (generator template)
 *  /rent-receipt-laws/<state>/ rent receipt laws by US state
 *  /receipt-generator-widget/  embed code for other websites
 *  /embed/<template>/          the generator alone, for iframes (noindex)
 *
 * The pages are real WordPress Pages (so they are in the sitemap, menus and
 * IndexNow pings) created automatically once per change of the list below.
 * Their text lives in the theme templates, except the missing receipt
 * form's article, which is normal page content you can edit in wp-admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * path => page settings. 'parent' is the parent page's path.
 */
function rf_feature_pages() {
	$pages = array(
		'receipt-requirements'     => array(
			'title'       => 'Receipt Requirements Checker',
			'template'    => 'page-receipt-requirements.php',
			'seo_title'   => 'Receipt Requirements Checker — HRA, FSA, Donations',
			'description' => 'What a receipt needs for an HRA claim, a dependent care FSA, a $250+ donation, an expense report or a rent payment, checked against the official sources.',
		),
		'verify-receipt'           => array(
			'title'       => 'Verify a Receipt',
			'template'    => 'page-verify-receipt.php',
			'seo_title'   => 'Verify a Receipt — Read a Receipt QR Code',
			'description' => 'Scan the QR code on a ReceiptGenPro receipt to read the details stored in it, and learn what a receipt QR code can and cannot prove.',
		),
		'missing-receipt-form'     => array(
			'title'    => 'Missing Receipt Form',
			'template' => 'page-receipt-category.php',
			'excerpt'  => 'Free missing receipt declaration for expense reports.',
			'content'  => 'rf_missing_receipt_page_content',
		),
		'receipt-generator-widget' => array(
			'title'       => 'Free Receipt Generator Widget',
			'template'    => 'page-receipt-widget.php',
			'seo_title'   => 'Free Receipt Generator Widget for Your Website',
			'description' => 'Add a free receipt generator to your website with one line of embed code: rent, cash, payment or any other receipt template. No signup, no data collected.',
		),
	);

	if ( function_exists( 'rf_rent_receipt_laws' ) ) {
		$pages['rent-receipt-laws'] = array(
			'title'       => 'Rent Receipt Laws by State',
			'template'    => 'page-rent-receipt-laws.php',
			'seo_title'   => 'Rent Receipt Laws by State — When Landlords Must Give One',
			'description' => 'Which US states require a landlord to give a rent receipt, when (cash, any payment or on request), what it must show, and the statute for each state.',
		);
		foreach ( rf_rent_receipt_laws() as $slug => $law ) {
			$pages[ 'rent-receipt-laws/' . $slug ] = array(
				'title'       => $law['state'] . ' Rent Receipt Law',
				'template'    => 'page-rent-receipt-laws.php',
				'parent'      => 'rent-receipt-laws',
				// Audit 20 Oct (#12): the old pattern ran to 61–73 characters.
				'seo_title'   => ( $law['short'] ?? $law['state'] ) . ' Rent Receipt Law: Is a Receipt Required?',
				'description' => $law['meta'] ?? $law['summary'],
			);
		}
	}
	return $pages;
}

function rf_feature_page( $path ) {
	$all = rf_feature_pages();
	return $all[ $path ] ?? null;
}

/** Path of the current page relative to the site, e.g. "rent-receipt-laws/texas". */
function rf_current_page_path() {
	$id = get_queried_object_id();
	return $id ? trim( (string) get_page_uri( $id ), '/' ) : '';
}

/* ==========================================================================
   Create the pages (once per change of the list)
   Runs from inc/setup-tasks.php (admin_init or WP-Cron), never while a
   visitor's page is being built (audit 20 Oct, #47).
   ========================================================================== */
function rf_feature_pages_hash() {
	// 'v2' (1.3.2) makes the pass below run once more so it can draft the
	// pages of states that were removed from the list.
	return md5( 'v2' . wp_json_encode( array_map( function ( $p ) { return array( $p['title'], $p['template'], $p['parent'] ?? '' ); }, rf_feature_pages() ) ) );
}

function rf_ensure_feature_pages() {
	if ( wp_installing() ) {
		return;
	}
	$pages = rf_feature_pages();
	$hash  = rf_feature_pages_hash();
	if ( get_option( 'rf_feature_pages_hash' ) === $hash ) {
		return;
	}
	// One request at a time; a lock older than 5 minutes is stale.
	$lock = get_option( 'rf_feature_pages_lock' );
	if ( $lock && ( time() - (int) $lock ) < 300 ) {
		return;
	}
	if ( ! $lock && ! add_option( 'rf_feature_pages_lock', time(), '', false ) ) {
		return;
	}
	update_option( 'rf_feature_pages_lock', time(), false );

	// Parents first (shorter paths sort first).
	uksort(
		$pages,
		function ( $a, $b ) {
			return substr_count( $a, '/' ) - substr_count( $b, '/' );
		}
	);
	// The hash is saved only when every page exists, so a page that could
	// not be made (no parent yet, a failed insert) is tried again next time.
	$complete = true;
	foreach ( $pages as $path => $p ) {
		$existing = get_page_by_path( $path, OBJECT, 'page' );
		if ( $existing ) {
			if ( get_post_meta( $existing->ID, '_wp_page_template', true ) !== $p['template'] ) {
				update_post_meta( $existing->ID, '_wp_page_template', $p['template'] );
			}
			continue;
		}
		$parent_id = 0;
		if ( ! empty( $p['parent'] ) ) {
			$parent = get_page_by_path( $p['parent'], OBJECT, 'page' );
			if ( ! $parent ) {
				$complete = false;
				continue;
			}
			$parent_id = $parent->ID;
		}
		$content = '';
		if ( ! empty( $p['content'] ) && is_callable( $p['content'] ) ) {
			$content = call_user_func( $p['content'] );
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $p['title'],
				'post_name'    => basename( $path ),
				'post_parent'  => $parent_id,
				'post_content' => $content,
				'post_excerpt' => $p['excerpt'] ?? ( $p['description'] ?? '' ),
				'post_author'  => rf_feature_pages_author(),
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			$complete = false;
		} else {
			update_post_meta( $id, '_wp_page_template', $p['template'] );
		}
	}
	rf_draft_removed_law_pages( $pages );
	if ( $complete ) {
		// Autoloaded: rf_setup_needed() reads it on every request.
		update_option( 'rf_feature_pages_hash', $hash, true );
	}
	delete_option( 'rf_feature_pages_lock' );
}

/**
 * Audit 20 Oct (#16): a state page whose state is no longer in
 * rf_rent_receipt_laws() is set to draft, so it leaves the sitemap and
 * stops showing the hub under a state's URL. Draft, not trash, so nothing
 * is lost if the state comes back.
 */
function rf_draft_removed_law_pages( $pages ) {
	if ( ! function_exists( 'rf_rent_receipt_laws' ) ) {
		return;
	}
	$hub = get_page_by_path( 'rent-receipt-laws', OBJECT, 'page' );
	if ( ! $hub ) {
		return;
	}
	$children = get_pages(
		array(
			'parent'      => $hub->ID,
			'post_status' => 'publish',
		)
	);
	foreach ( $children as $child ) {
		if ( ! isset( $pages[ 'rent-receipt-laws/' . $child->post_name ] ) ) {
			wp_update_post(
				array(
					'ID'          => $child->ID,
					'post_status' => 'draft',
				)
			);
		}
	}
}

/**
 * Audit 20 Oct (#16): until it is drafted, a child page of the hub with no
 * law data would show the hub's content under its own URL. Send it to the
 * hub instead.
 */
function rf_redirect_orphan_law_page() {
	if ( ! is_page_template( 'page-rent-receipt-laws.php' ) || ! wp_get_post_parent_id( get_queried_object_id() ) ) {
		return;
	}
	if ( ! isset( rf_rent_receipt_laws()[ basename( rf_current_page_path() ) ] ) ) {
		wp_safe_redirect( home_url( '/rent-receipt-laws/' ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'rf_redirect_orphan_law_page' );

/** The first administrator, so new pages aren't authored by "nobody". */
function rf_feature_pages_author() {
	$admins = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'orderby' => 'ID',
			'fields'  => 'ID',
		)
	);
	return $admins ? (int) $admins[0] : 0;
}

/* ==========================================================================
   SEO title / description for the template-driven pages
   ========================================================================== */
function rf_feature_pages_seo( $data ) {
	if ( ! is_page() || is_page_template( 'page-receipt-category.php' ) ) {
		return $data;
	}
	$page = rf_feature_page( rf_current_page_path() );
	if ( $page && ! empty( $page['seo_title'] ) ) {
		$data['title']       = $page['seo_title'];
		$data['description'] = $page['description'];
	}
	return $data;
}
add_filter( 'rf_seo_data', 'rf_feature_pages_seo' );

/* ==========================================================================
   FAQ schema for the new pages (built from the same arrays they print)
   ========================================================================== */
function rf_feature_pages_schema( $graph ) {
	$pairs = array();
	if ( is_page_template( 'page-receipt-requirements.php' ) ) {
		$pairs = rf_requirements_faqs();
	} elseif ( is_page_template( 'page-verify-receipt.php' ) ) {
		$pairs = rf_verify_faqs();
	} elseif ( is_page_template( 'page-receipt-widget.php' ) ) {
		$pairs = rf_widget_faqs();
	} elseif ( is_page_template( 'page-rent-receipt-laws.php' ) && function_exists( 'rf_rent_laws_faqs' ) ) {
		$pairs = rf_rent_laws_faqs( wp_get_post_parent_id( get_queried_object_id() ) ? basename( rf_current_page_path() ) : '' );
	}
	if ( $pairs ) {
		$graph[] = rf_schema_faq_from_pairs( $pairs );
	}
	return $graph;
}
add_filter( 'rf_schema_graph', 'rf_feature_pages_schema' );

/**
 * Audit 20 Oct (#19): the law pages and the requirements checker state
 * legal rules, so their WebPage node says when they were last changed and
 * reviewed, by whom, and which official texts they rely on.
 */
function rf_legal_pages_schema( $graph ) {
	$citations = array();
	$reviewed  = '';
	if ( is_page_template( 'page-rent-receipt-laws.php' ) && function_exists( 'rf_rent_receipt_laws' ) ) {
		$laws = rf_rent_receipt_laws();
		$slug = wp_get_post_parent_id( get_queried_object_id() ) ? basename( rf_current_page_path() ) : '';
		foreach ( ( $slug && isset( $laws[ $slug ] ) ) ? array( $laws[ $slug ] ) : $laws as $law ) {
			$citations[ $law['citation'] ] = $law['url'];
		}
		$reviewed = rf_rent_laws_reviewed()['date'];
	} elseif ( is_page_template( 'page-receipt-requirements.php' ) ) {
		foreach ( rf_receipt_purposes() as $purpose ) {
			// A source's url can be '' (no official page to link to).
			foreach ( (array) ( $purpose['sources'] ?? array() ) as $src ) {
				$citations[ $src[0] ] = $src[1];
			}
		}
	} else {
		return $graph;
	}

	$page = array(
		'@type'        => 'WebPage',
		'@id'          => get_permalink() . '#webpage',
		'name'         => rf_schema_text( get_the_title() ),
		'url'          => get_permalink(),
		'isPartOf'     => array( '@id' => home_url( '/#website' ) ),
		'dateModified' => get_the_modified_date( 'c' ),
		'reviewedBy'   => array( '@id' => home_url( '/#organization' ) ),
		'citation'     => array(),
	);
	if ( $reviewed ) {
		$page['lastReviewed'] = $reviewed;
	}
	foreach ( $citations as $name => $url ) {
		$work = array(
			'@type' => 'CreativeWork',
			'name'  => $name,
		);
		if ( $url ) {
			$work['url'] = $url;
		}
		$page['citation'][] = $work;
	}
	$graph[] = $page;
	return $graph;
}
add_filter( 'rf_schema_graph', 'rf_legal_pages_schema' );

function rf_requirements_faqs() {
	return array(
		array( 'What does a receipt need to show?', 'At a minimum: who was paid, who paid, the date, what the payment was for and the amount. Some uses add more. An HRA claim needs the landlord\'s PAN once rent is above ₹1 lakh a year, a dependent care claim needs the provider\'s taxpayer ID, and a $250+ donation receipt needs a statement about goods or services received in return.' ),
		array( 'How does the receipt checker work?', 'Choose what the receipt is for under the preview of any generator on this site. Each requirement is ticked as you fill in the form, so you can see what is still missing before you download. It runs in your browser and nothing is uploaded.' ),
		array( 'Is the checker legal or tax advice?', 'No. It lists what the official sources linked on this page ask for. Employers, FSA plans and tax offices can ask for more, so check their instructions too.' ),
	);
}

function rf_verify_faqs() {
	return array(
		array( 'Does the QR code prove a receipt is real?', 'No. Scanning a ReceiptGenPro QR code shows the details stored in it, which helps you read and compare them. The code is not signed, so anyone can make one with any details. To confirm a receipt or a payment, ask the business or person named on it, or check a bank statement.' ),
		array( 'Does a matching QR code prove the payment happened?', 'No. A matching code only shows that the printed receipt and the code hold the same details. Only the business or person who received the money, or a bank statement, can confirm the payment itself.' ),
		array( 'Is my receipt uploaded when I scan the code?', 'No. The details are stored in the part of the link after the # sign, which browsers never send to a server. This page decodes them in your browser.' ),
	);
}

function rf_widget_faqs() {
	return array(
		array( 'Is the receipt generator widget free?', 'Yes. Copy the code, paste it into any page on your website, and your visitors can make receipts without leaving it. There is no signup, no fee and no limit.' ),
		array( 'Does the widget collect my visitors\' data?', 'No. Receipts are created in the visitor\'s browser and nothing they type is sent to us or to you. The widget shows no ads.' ),
		array( 'Will it work on WordPress, Wix or Squarespace?', 'Yes. Paste the code into a Custom HTML block (WordPress), an Embed or HTML element (Wix, Squarespace, Webflow) or straight into your page\'s HTML. If your site removes scripts, the widget still works at a fixed height.' ),
	);
}

/* ==========================================================================
   Article for the missing receipt form page (stored as page content)
   ========================================================================== */
function rf_missing_receipt_page_content() {
	return <<<'HTML'
<h2>What Is a Missing Receipt Form?</h2>
<p>A missing receipt form (also called a lost receipt declaration or missing receipt affidavit) is a signed statement an employee gives with an expense report when the original receipt is lost, damaged or was never issued. It records the same details the receipt would have shown, explains why the receipt is missing, and lets a manager approve the claim.</p>
<p>It is meant for expenses you really paid. It is not a replacement receipt from the merchant, and it should never be used for an expense you did not make.</p>

<h2>What to Include</h2>
<ul>
<li><strong>Your name and department</strong>, so finance can match it to your expense report.</li>
<li><strong>The vendor or merchant</strong> you paid, and where.</li>
<li><strong>The date and amount</strong> of each expense, listed separately.</li>
<li><strong>The business purpose</strong>: who it was for and why.</li>
<li><strong>Why the receipt is missing</strong>: lost, damaged, never issued, or not available (parking meters, tips).</li>
<li><strong>Your signature and your manager's approval.</strong></li>
</ul>
<p>Attach any other proof you have, such as a card or bank statement line, a booking confirmation or an email from the vendor. The IRS says that when records are incomplete, you prove an expense with your own written statement plus other supporting evidence (<a href="https://www.irs.gov/publications/p463" rel="noopener" target="_blank">IRS Publication 463</a>, "What if I have incomplete records?").</p>

<h2>How to Fill It In</h2>
<ol>
<li>Enter your name, your company and today's date.</li>
<li>Add the vendor, the date of the expense, the business purpose and the reason the receipt is missing.</li>
<li>Add one line per lost expense with its amount. The total is calculated for you.</li>
<li>Keep the declaration ticked, download the PDF, sign it and ask your manager to sign it.</li>
</ol>

<h2>Before You Use One</h2>
<p>Read your company's expense policy first. Many policies cap how often a missing receipt form can be used, set a maximum amount, or ask for the vendor's duplicate receipt first. If the merchant can reprint or email a copy of the receipt, that is always the better option.</p>

<h2>Missing Receipt Form FAQ</h2>
<div class="rf-faq">
<h3>Can I get reimbursed without a receipt?</h3>
<p>Often yes. Most employers accept a signed missing receipt declaration with a manager's approval, especially when you can attach a card or bank statement showing the charge. Some policies limit how often or how much you can claim this way.</p>
<h3>Is a missing receipt form the same as a fake receipt?</h3>
<p>No. A missing receipt form is your own signed statement that you paid an expense whose receipt you no longer have. Creating a receipt that looks like it came from the merchant is not allowed and can be fraud.</p>
<h3>Can I list several lost receipts on one form?</h3>
<p>Yes. Add one line per expense with its own description and amount. If the expenses were on different dates or with different vendors, note them in each line's description.</p>
</div>
HTML;
}

/* ==========================================================================
   /embed/<template>/ — the generator alone, for other websites' iframes
   ========================================================================== */
function rf_embed_rewrite() {
	add_rewrite_rule( '^embed/([a-z0-9-]+)/?$', 'index.php?rf_embed=$matches[1]', 'top' );
}
add_action( 'init', 'rf_embed_rewrite' );

function rf_embed_query_var( $vars ) {
	$vars[] = 'rf_embed';
	return $vars;
}
add_filter( 'query_vars', 'rf_embed_query_var' );

/** Templates that can be embedded: 'receipt' (general) + every category. */
function rf_embed_templates() {
	$list = array( 'receipt' => 'Receipt Generator (general)' );
	foreach ( rf_receipt_categories() as $slug => $cat ) {
		$list[ $slug ] = rf_category_label( $cat );
	}
	return $list;
}

/**
 * The auto-height listener of the widget embed code, the same script
 * assets/js/widget-builder.js builds (v1.3.3): it resizes the iframe whose
 * window sent the message, for messages from the site's own origin.
 */
function rf_widget_height_script( $site ) {
	$parts  = wp_parse_url( $site );
	$origin = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? '' ) . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
	return '<script>addEventListener("message",function(e){if(e.origin!==' . wp_json_encode( $origin, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . '||!e.data||!e.data.rgpHeight)return;'
		. 'document.querySelectorAll("iframe").forEach(function(f){if(f.contentWindow===e.source)f.style.height=e.data.rgpHeight+"px"})})</script>';
}

/** True when the request path is /embed/…, the only place rf_embed counts. */
function rf_is_embed_path() {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
	$home = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	return 0 === strpos( $path, $home . '/embed/' );
}

/**
 * The embed template asked for, or '' when there is none or it is not valid.
 * Pass the raw query var; it can be an array (?rf_embed[]=x), so it is
 * checked before being used as an array key.
 */
function rf_embed_slug( $raw ) {
	if ( ! is_string( $raw ) || '' === $raw || ! rf_is_embed_path() ) {
		return '';
	}
	return isset( rf_embed_templates()[ $raw ] ) ? $raw : '';
}

/** Drop rf_embed from any request that is not a plain /embed/<slug>/ string. */
function rf_embed_clean_request( $vars ) {
	if ( isset( $vars['rf_embed'] ) && ( ! is_string( $vars['rf_embed'] ) || '' === $vars['rf_embed'] || ! rf_is_embed_path() ) ) {
		unset( $vars['rf_embed'] );
	}
	return $vars;
}
add_filter( 'request', 'rf_embed_clean_request' );

function rf_render_embed() {
	$slug = get_query_var( 'rf_embed' );
	if ( ! is_string( $slug ) || '' === $slug || ! rf_is_embed_path() ) {
		return;
	}
	if ( '' === rf_embed_slug( $slug ) ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		return;
	}
	status_header( 200 );
	require get_template_directory() . '/embed-generator.php';
	exit;
}
add_action( 'template_redirect', 'rf_render_embed', 0 );

/** The data receipt-generator.js reads from window.rfReceipt. */
function rf_receipt_script_data() {
	return array(
		'brand'      => RF_BRAND_NAME,
		'currencies' => rf_supported_currencies(),
		'purposes'   => rf_receipt_purposes_for_js(),
		'verifyUrl'  => home_url( '/verify-receipt/' ),
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
			'qrcode'      => array(
				'url'       => 'https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js',
				'integrity' => 'sha512-ZDSPMa/JM1D+7kdg2x3BsruQ6T/JpJo3jWDWkCZsP+5yVyp1KfESqLI+7RqB5k24F7p2cV7i2YHh/890y6P6Sw==',
			),
		),
	);
}
