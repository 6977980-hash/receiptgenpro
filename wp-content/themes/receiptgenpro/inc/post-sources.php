<?php
/**
 * ReceiptGenPro — Official sources & key facts per blog post
 *
 * Sept 2026 audit, H2: all 21 guides (tax / record-keeping = YMYL topics)
 * had zero outbound citations. This registry adds, per post slug:
 *   'facts'   => short, verified key facts (each backed by a source below)
 *   'sources' => array( label, official URL )
 * single.php prints them as a "Key facts & official sources" box, and
 * inc/schema.php exposes the sources as Article "citation".
 *
 * Every URL and fact below was checked against the official page on
 * 26 Sept 2026. Re-check once a year (or when a rule changes) and bump
 * RF_SOURCES_REVIEWED.
 *
 * To override for one post without editing code, add a custom field
 * "rf_sources" with one "Label | URL" per line.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RF_SOURCES_REVIEWED', '2026-09-26' );

function rf_source_library() {
	return array(
		'irs_583'      => array( 'IRS Publication 583 — Starting a Business and Keeping Records', 'https://www.irs.gov/publications/p583' ),
		'irs_463'      => array( 'IRS Publication 463 — Travel, Gift, and Car Expenses', 'https://www.irs.gov/publications/p463' ),
		'irs_keep'     => array( 'IRS — How long should I keep records?', 'https://www.irs.gov/businesses/small-businesses-self-employed/how-long-should-i-keep-records' ),
		'irs_rec'      => array( 'IRS — Recordkeeping for small businesses', 'https://www.irs.gov/businesses/small-businesses-self-employed/recordkeeping' ),
		'irs_char'     => array( 'IRS — Substantiating charitable contributions', 'https://www.irs.gov/charities-non-profits/substantiating-charitable-contributions' ),
		'irs_1771'     => array( 'IRS Publication 1771 — Charitable Contributions: Substantiation and Disclosure', 'https://www.irs.gov/pub/irs-pdf/p1771.pdf' ),
		'uk_700'       => array( 'HMRC — VAT guide (VAT Notice 700)', 'https://www.gov.uk/guidance/vat-guide-notice-700' ),
		'uk_70021'     => array( 'HMRC — Record keeping (VAT Notice 700/21)', 'https://www.gov.uk/guidance/record-keeping-for-vat-notice-70021' ),
		'uk_invoice'   => array( 'GOV.UK — Invoices: what they must include', 'https://www.gov.uk/invoicing-and-taking-payment-from-customers/invoices-what-they-must-include' ),
		'uk_selfemp'   => array( 'GOV.UK — Self-employed records: how long to keep them', 'https://www.gov.uk/self-employed-records/how-long-to-keep-your-records' ),
		'cra_records'  => array( 'Canada Revenue Agency — Keeping records', 'https://www.canada.ca/en/revenue-agency/services/tax/businesses/topics/keeping-records.html' ),
		'cra_how_long' => array( 'CRA — Where to keep your records and for how long', 'https://www.canada.ca/en/revenue-agency/services/tax/businesses/topics/keeping-records/where-keep-your-records-long-request-permission-destroy-them-early.html' ),
		'ato_invoice'  => array( 'Australian Taxation Office — Tax invoices', 'https://www.ato.gov.au/businesses-and-organisations/gst-excise-and-indirect-taxes/gst/tax-invoices' ),
		'ato_records'  => array( 'ATO — Overview of record-keeping rules for business', 'https://www.ato.gov.au/businesses-and-organisations/preparing-lodging-and-paying/record-keeping-for-business/overview-of-record-keeping-rules-for-business' ),
		'in_cbic'      => array( 'CBIC — GST portal (Acts, Rules and FAQs)', 'https://cbic-gst.gov.in/' ),
		'in_gst'       => array( 'Goods and Services Tax Network — GST portal', 'https://www.gst.gov.in/' ),
		'in_einvoice'  => array( 'GST e-invoice portal (IRP): e-invoicing and IRN', 'https://einvoice1.gst.gov.in/' ),
	);
}

function rf_fact_library() {
	return array(
		'us_75'       => 'USA: the IRS generally expects written evidence (a receipt, invoice or paid bill) for business travel and gift expenses of $75 or more, and for all lodging regardless of amount (Publication 463).',
		'us_3yr'      => 'USA: keep records for 3 years in most cases; longer (6–7 years, or indefinitely) in specific situations such as underreported income or bad-debt deductions.',
		'us_250'      => 'USA: a donor needs a contemporaneous written acknowledgment from the charity for any single contribution of $250 or more.',
		'us_75_qpq'   => 'USA: when a donor receives goods or services in return for a payment over $75 (a "quid pro quo" contribution), the charity must give a written disclosure statement.',
		'uk_250'      => 'UK: VAT-registered businesses may issue a simplified VAT invoice for supplies of £250 or less (including VAT).',
		'uk_vat_6yr'  => 'UK: keep business records for VAT purposes for at least 6 years.',
		'uk_se_5yr'   => 'UK (self-employed): keep records for at least 5 years after the 31 January Self Assessment deadline of the tax year they relate to.',
		'ca_6yr'      => 'Canada: keep records and supporting documents for 6 years from the end of the last tax year they relate to.',
		'au_8250'     => 'Australia: if a customer asks for a tax invoice you must provide one within 28 days, unless the sale was $82.50 (including GST) or less.',
		'au_5yr'      => 'Australia: keep most business records for 5 years.',
	);
}

/**
 * slug => array( facts => [...fact keys], sources => [...source keys] )
 */
function rf_post_sources_map() {
	return array(
		'how-to-make-a-receipt'                          => array( array(), array( 'irs_583', 'uk_invoice', 'ato_invoice' ) ),
		'receipt-vs-invoice-whats-the-difference'        => array( array(), array( 'uk_invoice', 'irs_rec', 'ato_invoice' ) ),
		'what-must-be-on-a-receipt-legal-requirements-by-country' => array( array( 'us_75', 'uk_250', 'au_8250' ), array( 'irs_583', 'irs_463', 'uk_70021', 'cra_records', 'ato_invoice', 'in_cbic' ) ),
		'how-long-should-you-keep-business-receipts'     => array( array( 'us_3yr', 'uk_se_5yr', 'uk_vat_6yr', 'ca_6yr', 'au_5yr' ), array( 'irs_keep', 'uk_selfemp', 'uk_70021', 'cra_how_long', 'ato_records' ) ),
		'what-happens-if-youre-audited-and-dont-have-receipts' => array( array( 'us_3yr' ), array( 'irs_rec', 'irs_583', 'uk_selfemp' ) ),
		'how-to-fill-out-a-rent-receipt'                 => array( array(), array( 'irs_rec', 'irs_583' ) ),
		'how-to-create-a-receipt-for-freelance-work'     => array( array(), array( 'irs_583', 'uk_invoice' ) ),
		'how-to-send-receipts-to-customers-via-whatsapp' => array( array(), array( 'irs_583', 'cra_records' ) ),
		'how-to-write-a-cash-receipt'                    => array( array(), array( 'irs_583', 'irs_rec' ) ),
		'receipt-numbering-system-how-to-number-receipts' => array( array(), array( 'uk_invoice', 'ato_invoice' ) ),
		'digital-receipts-vs-paper-receipts'             => array( array(), array( 'irs_583', 'cra_records', 'uk_70021' ) ),
		'how-to-make-a-receipt-on-your-phone'            => array( array(), array( 'irs_583' ) ),
		'gst-receipt-requirements-in-india'              => array( array(), array( 'in_cbic', 'in_gst', 'in_einvoice' ) ),
		'vat-receipts-in-the-uk'                         => array( array( 'uk_250', 'uk_vat_6yr' ), array( 'uk_700', 'uk_70021' ) ),
		'receipt-requirements-for-small-businesses-in-the-usa' => array( array( 'us_75', 'us_3yr' ), array( 'irs_583', 'irs_463', 'irs_keep' ) ),
		'receipt-rules-for-businesses-in-canada'         => array( array( 'ca_6yr' ), array( 'cra_records', 'cra_how_long' ) ),
		'receipt-requirements-in-australia'              => array( array( 'au_8250', 'au_5yr' ), array( 'ato_invoice', 'ato_records' ) ),
		'how-to-make-a-receipt-for-etsy-and-online-sellers' => array( array(), array( 'irs_583', 'uk_invoice' ) ),
		'receipt-templates-word-vs-excel-vs-google-docs-vs-online-generator' => array( array(), array( 'uk_invoice', 'irs_583' ) ),
		'how-to-create-a-donation-receipt-for-nonprofits' => array( array( 'us_250', 'us_75_qpq' ), array( 'irs_char', 'irs_1771' ) ),
		'what-is-a-vat-receipt'                          => array( array( 'uk_250', 'uk_vat_6yr' ), array( 'uk_70021', 'uk_700' ) ),
	);
}

/**
 * @return array{facts: string[], sources: array<int, array{0:string,1:string}>}
 */
function rf_get_post_sources( $slug, $post_id = 0 ) {
	$out = array( 'facts' => array(), 'sources' => array() );

	// Per-post override via custom field "rf_sources" (Label | URL per line).
	if ( $post_id ) {
		$custom = get_post_meta( $post_id, 'rf_sources', true );
		if ( $custom ) {
			foreach ( preg_split( '/\r?\n/', $custom ) as $line ) {
				$parts = array_map( 'trim', explode( '|', $line, 2 ) );
				if ( 2 === count( $parts ) && wp_http_validate_url( $parts[1] ) ) {
					$out['sources'][] = $parts;
				}
			}
		}
	}

	$map = rf_post_sources_map();
	if ( isset( $map[ $slug ] ) ) {
		$lib   = rf_source_library();
		$facts = rf_fact_library();
		foreach ( $map[ $slug ][0] as $k ) {
			if ( isset( $facts[ $k ] ) ) {
				$out['facts'][] = $facts[ $k ];
			}
		}
		foreach ( $map[ $slug ][1] as $k ) {
			if ( isset( $lib[ $k ] ) ) {
				$out['sources'][] = $lib[ $k ];
			}
		}
	}
	return $out;
}

/**
 * Printed by single.php below the article body.
 */
function rf_render_post_sources() {
	$data = rf_get_post_sources( get_post_field( 'post_name' ), get_the_ID() );
	if ( empty( $data['sources'] ) && empty( $data['facts'] ) ) {
		return;
	}
	?>
	<aside class="rf-sources-box" aria-labelledby="rf-sources-title">
		<h2 id="rf-sources-title"><?php echo empty( $data['facts'] ) ? 'Official sources' : 'Key facts &amp; official sources'; ?></h2>
		<?php if ( $data['facts'] ) : ?>
			<ul class="rf-key-facts">
				<?php foreach ( $data['facts'] as $fact ) : ?>
					<li><?php echo esc_html( $fact ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( $data['sources'] ) : ?>
			<ol class="rf-source-list">
				<?php foreach ( $data['sources'] as $src ) : ?>
					<li><a href="<?php echo esc_url( $src[1] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( $src[0] ); ?></a></li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
		<p class="rf-sources-note">
			Sources last checked: <?php echo esc_html( date_i18n( 'F j, Y', strtotime( RF_SOURCES_REVIEWED ) ) ); ?>.
			This guide is general information, not tax or legal advice — rules change, so confirm with the official source or a qualified accountant.
			<?php if ( rf_editorial_policy_url() ) : ?>
				<a href="<?php echo esc_url( rf_editorial_policy_url() ); ?>">How we research our guides</a>.
			<?php endif; ?>
		</p>
	</aside>
	<?php
}
