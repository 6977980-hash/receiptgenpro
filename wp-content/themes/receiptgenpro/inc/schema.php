<?php
/**
 * ReceiptGenPro — Structured Data (JSON-LD) engine
 *
 * Covers SEO (rich results), GEO — Generative Engine Optimization, i.e.
 * making the page easy for ChatGPT/Perplexity/Google AI Overviews to
 * parse, quote and cite correctly — and AEO — Answer Engine Optimization,
 * i.e. structuring direct Q&A pairs so voice assistants and answer boxes
 * can lift a clean answer straight from the page.
 *
 * Design choice: FAQ schema is AUTO-EXTRACTED from the visible HTML
 * (elements carrying .rf-faq) instead of being
 * hand-duplicated in a separate field. This guarantees the structured
 * data always matches what the user actually sees — a mismatch between
 * visible content and schema is a Google spam-policy violation and a
 * mistake several competitor sites are exposed to when their schema (if
 * any) drifts from on-page copy.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core Organization + WebSite graph, printed on every page. This is what
 * ties every page together into one Knowledge-Graph-friendly entity and
 * is the single most important GEO signal for a small brand: it tells
 * generative engines unambiguously who "ReceiptGenPro" is.
 */
/**
 * Oct 2026 audit (SEO-04): plain text for JSON-LD — WordPress titles carry
 * HTML entities (&#8217;, &#038;) that would otherwise show up literally.
 */
function rf_schema_text( $text ) {
	return html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
}

function rf_schema_organization() {
	return array(
		'@type' => 'Organization',
		'@id'   => home_url( '/#organization' ),
		'name'  => RF_BRAND_NAME,
		'url'   => home_url( '/' ),
		'logo'  => array(
			'@type' => 'ImageObject',
			'url'   => get_template_directory_uri() . '/assets/img/logo.png',
		),
		'founder'      => array(
			'@type'    => 'Person',
			'name'     => RF_FOUNDER_NAME,
			'jobTitle' => RF_FOUNDER_TITLE,
			'url'      => home_url( '/about-us/' ),
			'sameAs'   => array( RF_FOUNDER_LINKEDIN ),
		),
		'email'        => RF_SUPPORT_EMAIL,
		'contactPoint' => array(
			'@type'       => 'ContactPoint',
			'contactType' => 'customer support',
			'email'       => RF_SUPPORT_EMAIL,
			'url'         => home_url( '/contact-us/' ),
		),
	) + ( array_filter( array( RF_SOCIAL_TWITTER, RF_SOCIAL_FACEBOOK, RF_SOCIAL_LINKEDIN ) )
		? array( 'sameAs' => array_values( array_filter( array( RF_SOCIAL_TWITTER, RF_SOCIAL_FACEBOOK, RF_SOCIAL_LINKEDIN ) ) ) )
		: array() );
}

function rf_schema_website() {
	return array(
		'@type'           => 'WebSite',
		'@id'             => home_url( '/#website' ),
		'url'             => home_url( '/' ),
		'name'            => RF_BRAND_NAME,
		'description'     => RF_BRAND_DESCRIPTION,
		'publisher'       => array( '@id' => home_url( '/#organization' ) ),
		'inLanguage'      => 'en',
		// v1.1.0 (audit H5): SearchAction removed — Google retired the
		// Sitelinks Search Box in Nov 2024, and /?s= doesn't search the tools.
	);
}

/**
 * The tool itself, described as SoftwareApplication so it is eligible for
 * SEO rich results AND so generative engines can correctly answer
 * "is there a free receipt generator with no signup" style prompts.
 */
function rf_schema_software_application() {
	return array(
		'@type'                 => 'SoftwareApplication',
		'@id'                   => home_url( '/#software' ),
		'name'                  => RF_BRAND_NAME . ' Receipt Generator',
		'url'                   => home_url( '/' ),
		'applicationCategory'   => 'BusinessApplication',
		'operatingSystem'       => 'Any (runs in browser)',
		'offers'                => array(
			'@type'         => 'Offer',
			'price'         => '0',
			'priceCurrency' => 'USD',
		),
		// NOTE: do NOT add an "aggregateRating" block back here until the
		// site has genuine, collected reviews. A fabricated rating/count is
		// misleading structured data under Google's own guidelines and was
		// flagged as a CRITICAL finding in the Sept 2026 site audit — it
		// risks a sitewide rich-results suppression and undermines the
		// AdSense application's trustworthiness signals. Add a real
		// AggregateRating (even from 5–10 genuine reviews) once you have one.
		'featureList' => array(
			'No signup required',
			'Client-side processing (your data never leaves your browser)',
			'PDF, PNG and JPEG export',
			'Multiple currencies',
			'Itemized line items with automatic tax and total calculation',
		),
	);
}

/**
 * BreadcrumbList — mirrors rf_get_breadcrumbs() exactly so the visible
 * trail and the structured data can never drift apart.
 */
function rf_schema_breadcrumbs() {
	$trail = rf_get_breadcrumbs();
	$items = array();
	foreach ( $trail as $i => $crumb ) {
		$item = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => rf_schema_text( $crumb['title'] ),
		);
		if ( ! empty( $crumb['url'] ) ) {
			$item['item'] = $crumb['url'];
		}
		$items[] = $item;
	}
	return array(
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $items,
	);
}

/**
 * Extract Q/A pairs from any ".rf-faq" block in $html.
 * Convention: alternating <h3> (question) / <p> or <div> (answer) inside
 * a container carrying the class "rf-faq". See template-parts/faq-block.php.
 */
function rf_extract_faq_from_html( $html ) {
	if ( empty( $html ) || false === stripos( $html, 'rf-faq' ) ) {
		return array();
	}

	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
	libxml_clear_errors();

	$xpath = new DOMXPath( $doc );
	$faqContainers = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' rf-faq ')]" );

	$pairs = array();
	foreach ( $faqContainers as $container ) {
		$question = null;
		foreach ( $container->childNodes as $node ) {
			if ( ! ( $node instanceof DOMElement ) ) {
				continue;
			}
			if ( preg_match( '/^h[2-4]$/i', $node->tagName ) ) {
				$question = trim( $node->textContent );
			} elseif ( $question && in_array( strtolower( $node->tagName ), array( 'p', 'div' ), true ) ) {
				$answer = trim( $node->textContent );
				if ( $answer !== '' ) {
					$pairs[] = array( 'q' => $question, 'a' => $answer );
					$question = null;
				}
			}
		}
	}
	return $pairs;
}

function rf_schema_faq_from_content( $html ) {
	$pairs = rf_extract_faq_from_html( $html );
	if ( empty( $pairs ) ) {
		return null;
	}
	$entities = array();
	foreach ( $pairs as $pair ) {
		$entities[] = array(
			'@type'          => 'Question',
			'name'           => $pair['q'],
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => $pair['a'],
			),
		);
	}
	return array(
		'@type'      => 'FAQPage',
		'mainEntity' => $entities,
	);
}

/**
 * Build FAQPage from an array of array( question, answer_html ).
 */
function rf_schema_faq_from_pairs( $pairs ) {
	if ( empty( $pairs ) ) {
		return null;
	}
	$entities = array();
	foreach ( $pairs as $pair ) {
		$entities[] = array(
			'@type'          => 'Question',
			'name'           => $pair[0],
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => wp_strip_all_tags( $pair[1] ),
			),
		);
	}
	return array(
		'@type'      => 'FAQPage',
		'mainEntity' => $entities,
	);
}

/**
 * Homepage FAQ — single source for the visible FAQ (front-page.php) and
 * the FAQPage schema, so the two can never drift apart.
 */
function rf_home_faqs() {
	return array(
		array( 'Is this receipt generator really free?', 'Yes. Creating and downloading a receipt costs nothing, requires no signup, and doesn\'t add a watermark to your file. There\'s no limit on how many receipts you can make.' ),
		array( 'Do I need to create an account?', 'No. You can generate and download a receipt immediately without registering, verifying an email, or providing a credit card.' ),
		array( 'Is my data safe?', 'Yes. The receipt is built and rendered entirely in your browser using client-side JavaScript. Your business name, customer details and amounts are never sent to or stored on our servers.' ),
		array( 'What file formats can I download?', 'You can export your finished receipt as a PDF (best for printing and email attachments), a PNG (sharpest image quality), or a JPEG (smaller file, ideal for WhatsApp and other messaging apps).' ),
		array( 'Can I add my own logo?', 'Yes. Upload your business logo and it will appear at the top of the receipt, next to your business name.' ),
		array( 'Is a receipt from this generator legally valid?', 'A receipt you issue here documents your own transaction the same way a handwritten or POS-printed receipt does. For tax or legal use, check the receipt-content rules in your country — see our <a href="' . esc_url( home_url( '/what-must-be-on-a-receipt-legal-requirements-by-country/' ) ) . '">legal requirements guide</a>.' ),
		array( 'Can I make a receipt for a purchase from another business?', 'No. Only the business or person who received the payment should issue a receipt. If you lost a receipt from a store, hotel or taxi, ask that business for a copy or use your employer\'s missing-receipt form — creating one on their behalf can be treated as fraud.' ),
		array( 'What\'s the difference between a receipt and an invoice?', 'An invoice is a request for payment sent before money changes hands; a receipt is proof that payment has already been received. Read our full <a href="' . esc_url( home_url( '/receipt-vs-invoice-whats-the-difference/' ) ) . '">receipt vs. invoice comparison</a>.' ),
	);
}

/**
 * FAQ for the /receipt-templates/ hub (visible + schema from one source).
 */
function rf_hub_faqs() {
	return array(
		array( 'Are these receipt templates free?', 'Yes. Every template is free to fill in and download as PDF, PNG or JPEG, with no signup and no watermark.' ),
		array( 'Can I edit a template after downloading it?', 'The download is a finished PDF or image. To change something, edit the form on the template page and download again — the form keeps your entries until you close the tab.' ),
		array( 'Which template do I need for a deposit or advance payment?', 'Use the <a href="' . esc_url( home_url( '/deposit-receipt/' ) ) . '">deposit receipt</a> for a deposit or advance payment, or the <a href="' . esc_url( home_url( '/payment-receipt/' ) ) . '">payment receipt</a> for a general payment, and fill in “Amount Paid” so the balance due is calculated for you.' ),
		array( 'Who should issue a receipt?', 'The business or person who received the money. A receipt should never be created on behalf of another business or for a payment that did not happen.' ),
	);
}

/**
 * Article / BlogPosting schema for single blog posts, including the
 * Person (author) sub-entity that carries real E-E-A-T signals.
 */
function rf_schema_article() {
	global $post;
	$author_id = $post->post_author;

	// v1.1.0 (audit H2/H5): a "Person" named "ReceiptGenPro Team" is not a
	// person. Team/brand accounts are credited to the Organization; a real
	// named author (display name without "Team") stays a Person.
	$display = get_the_author_meta( 'display_name', $author_id );
	if ( false !== stripos( $display, 'team' ) || false !== stripos( $display, RF_BRAND_NAME ) ) {
		$person = array( '@id' => home_url( '/#organization' ) );
	} else {
		$person = array(
			'@type' => 'Person',
			'name'  => $display,
			'url'   => home_url( '/about-us/' ),
		);
	}
	$job_title = get_the_author_meta( 'rf_job_title', $author_id );
	if ( isset( $person['@type'] ) ) {
		if ( $job_title ) {
			$person['jobTitle'] = $job_title;
		}
		$linkedin = get_the_author_meta( 'linkedin', $author_id );
		if ( $linkedin ) {
			$person['sameAs'] = array( $linkedin );
		}
		$expertise = get_the_author_meta( 'rf_expertise', $author_id );
		if ( $expertise ) {
			$person['description'] = $expertise;
		}
	}

	$article = array(
		'@type'            => 'Article',
		'headline'         => rf_schema_text( get_the_title() ),
		'description'      => rf_schema_text( has_excerpt() ? get_the_excerpt() : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 ) ),
		'author'           => $person,
		'publisher'        => array( '@id' => home_url( '/#organization' ) ),
		'datePublished'    => get_the_date( 'c' ),
		'dateModified'     => get_the_modified_date( 'c' ),
		'mainEntityOfPage' => array(
			'@type' => 'WebPage',
			'@id'   => get_permalink(),
		),
	);
	if ( has_post_thumbnail() ) {
		$article['image'] = get_the_post_thumbnail_url( $post->ID, 'full' );
	}
	// v1.1.0 (audit H2): the official sources shown in the "Sources" box
	// are also exposed as schema.org citations (useful for AI engines).
	$sources = rf_get_post_sources( $post->post_name, $post->ID );
	if ( ! empty( $sources['sources'] ) ) {
		$article['citation'] = array_map(
			function ( $src ) {
				return array( '@type' => 'CreativeWork', 'name' => $src[0], 'url' => $src[1] );
			},
			$sources['sources']
		);
	}
	return $article;
}

/**
 * Assemble and print the @graph for the current page. Using a single
 * @graph (instead of several separate <script> blocks) is the pattern
 * Google's own documentation recommends and keeps every entity properly
 * cross-referenced via @id.
 */
function rf_output_schema() {
	// Audit 20 Oct (#17): an SEO plugin prints its own graph; two graphs
	// would describe the same page twice.
	if ( rf_seo_plugin_active() ) {
		return;
	}
	$graph = array( rf_schema_organization(), rf_schema_website() );

	if ( is_front_page() ) {
		$graph[] = rf_schema_software_application();
		// v1.1.0 (audit H5): no BreadcrumbList on the homepage ("Home > Home").
		// FAQPage is built from the same array the homepage FAQ renders from.
		$graph[] = rf_schema_faq_from_pairs( rf_home_faqs() );
	} elseif ( rf_is_templates_hub() ) {
		$graph[] = rf_schema_breadcrumbs();
		$items   = array();
		$pos     = 1;
		foreach ( rf_receipt_categories() as $slug => $cat ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'url'      => home_url( '/' . $slug . '/' ),
				'name'     => rf_category_label( $cat ),
			);
		}
		$graph[] = array(
			'@type'      => 'CollectionPage',
			'@id'        => get_permalink() . '#webpage',
			'name'       => 'Free Receipt Templates',
			'url'        => get_permalink(),
			'isPartOf'   => array( '@id' => home_url( '/#website' ) ),
			'mainEntity' => array(
				'@type'           => 'ItemList',
				'itemListElement' => $items,
			),
		);
		$graph[] = rf_schema_faq_from_pairs( rf_hub_faqs() );
	} elseif ( is_page_template( 'page-receipt-category.php' ) ) {
		global $post;
		$cat = rf_get_category_data( $post->post_name );
		$graph[] = rf_schema_breadcrumbs();
		$graph[] = array(
			'@type'    => 'WebPage',
			'@id'      => get_permalink() . '#webpage',
			'name'     => $cat ? rf_category_label( $cat ) : rf_schema_text( get_the_title() ),
			'url'      => get_permalink(),
			'isPartOf' => array( '@id' => home_url( '/#website' ) ),
			'about'    => rf_schema_text( $cat['h1'] ?? get_the_title() ),
		);
		// v1.1.0 (audit H5): each template page describes its own tool.
		if ( $cat ) {
			$graph[] = array(
				'@type'               => 'WebApplication',
				'@id'                 => get_permalink() . '#app',
				'name'                => rf_category_label( $cat ),
				'url'                 => get_permalink(),
				'applicationCategory' => 'BusinessApplication',
				'operatingSystem'     => 'Any (runs in browser)',
				'browserRequirements' => 'Requires JavaScript',
				'isAccessibleForFree' => true,
				'offers'              => array(
					'@type'         => 'Offer',
					'price'         => '0',
					'priceCurrency' => 'USD',
				),
				'featureList'         => array_map( 'wp_strip_all_tags', (array) $cat['default_fields'] ),
				'publisher'           => array( '@id' => home_url( '/#organization' ) ),
			);
		}
		$faq = rf_schema_faq_from_content( $post->post_content );
		if ( $faq ) {
			$graph[] = $faq;
		}
	} elseif ( is_singular( 'post' ) ) {
		global $post;
		$graph[] = rf_schema_article();
		$graph[] = rf_schema_breadcrumbs();
		$faq = rf_schema_faq_from_content( $post->post_content );
		if ( $faq ) {
			$graph[] = $faq;
		}
		// HowTo schema intentionally no longer printed (v1.1.0): Google
		// retired HowTo rich results in Sept 2023, so it only added weight.
	} elseif ( is_home() && ! is_front_page() ) {
		// Audit 20 Oct (#19): the blog index is a list of articles.
		$graph[] = rf_schema_breadcrumbs();
		$items   = array();
		$pos     = 1;
		foreach ( $GLOBALS['wp_query']->posts as $item_post ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'url'      => get_permalink( $item_post ),
				'name'     => rf_schema_text( get_the_title( $item_post ) ),
			);
		}
		$graph[] = array(
			'@type'      => 'CollectionPage',
			'@id'        => rf_blog_url() . '#webpage',
			'name'       => RF_BRAND_NAME . ' Blog',
			'url'        => rf_blog_url(),
			'isPartOf'   => array( '@id' => home_url( '/#website' ) ),
			'publisher'  => array( '@id' => home_url( '/#organization' ) ),
			'mainEntity' => array(
				'@type'           => 'ItemList',
				'itemListElement' => $items,
			),
		);
	} elseif ( is_page() || is_category() ) {
		$graph[] = rf_schema_breadcrumbs();
	}

	$graph = array_values( array_filter( $graph ) );
	$graph = apply_filters( 'rf_schema_graph', $graph );

	$json = array(
		'@context' => 'https://schema.org',
		'@graph'   => $graph,
	);

	echo "\n<script type=\"application/ld+json\">\n" . wp_json_encode( $json, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n</script>\n";
}
add_action( 'wp_head', 'rf_output_schema', 5 );
