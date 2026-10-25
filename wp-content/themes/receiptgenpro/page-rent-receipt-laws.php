<?php
/**
 * Template Name: Rent Receipt Laws
 *
 * /rent-receipt-laws/ (hub) and /rent-receipt-laws/<state>/ (v1.2.0).
 * Every fact comes from inc/rent-laws.php; a law page also carries the
 * rent receipt generator.
 *
 * 1.4.2 (audit of 25 Oct, v2 Phase 6, G-6): the same template serves every
 * page in rf_rent_law_entries(): a state (Massachusetts with the deposit
 * receipt generator), Ohio with its city rules and each city, the UK with
 * a section per nation, and the Canada hub with each province. UK and
 * Canada pages use the law's own words (landlord, tenant, rent book) and
 * their own currency.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$rf_laws  = rf_rent_receipt_laws();
$rf_sub   = wp_get_post_parent_id( get_queried_object_id() ) ? rf_rent_law_sub() : '';
$rf_law   = '' !== $rf_sub ? rf_rent_law_entry( $rf_sub ) : null;
$rf_hub   = home_url( '/rent-receipt-laws/' );
$rf_seen  = rf_rent_laws_reviewed();
?>

<article id="rf-main-content" class="rf-tool-page">

<?php
if ( $rf_law ) :
	$rf_kind    = $rf_law['kind'];
	$rf_name    = $rf_law['name'];
	$rf_us      = in_array( $rf_kind, array( 'state', 'city-state', 'city' ), true );
	$rf_deposit = ! empty( $rf_law['deposit_only'] );
	$rf_doc     = $rf_deposit ? 'deposit receipt' : 'rent receipt';
	$rf_label   = 'country' === $rf_kind ? 'UK' : $rf_name;
	$rf_word    = 'city' === $rf_kind ? 'ordinance' : ( $rf_us ? 'statute' : 'law' );
	?>

	<section class="rf-category-hero">
		<div class="rf-container rf-category-hero-inner">
			<span class="rf-category-icon" aria-hidden="true">⚖️</span>
			<h1><?php echo esc_html( $rf_name ); ?> Rent Receipt Law</h1>
			<p class="rf-hero-sub rf-direct-answer"><?php echo esc_html( $rf_law['summary'] ); ?></p>
			<p class="rf-law-reviewed">Last reviewed: <?php echo esc_html( $rf_seen['label'] ); ?></p>
			<a href="#rf-generator" class="rf-btn rf-btn-primary">Make <?php echo esc_html( ( preg_match( '/^[AEIO]/', $rf_label ) ? 'an ' : 'a ' ) . $rf_label . ' ' . $rf_doc ); ?> ↓</a>
		</div>
	</section>

	<div class="rf-container rf-narrow">
		<section class="rf-section">

			<?php if ( 'city-state' === $rf_kind ) : ?>
				<div class="rf-law-facts rf-law-facts-2">
					<div class="rf-law-fact"><strong>Statewide rule</strong>None: the state's landlord-tenant law has no rent receipt duty.</div>
					<div class="rf-law-fact"><strong>The law we read</strong><a href="<?php echo esc_url( $rf_law['url'] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( $rf_law['citation'] ); ?></a></div>
				</div>
				<p><?php echo esc_html( $rf_law['note'] ); ?></p>
				<h2>City Rules in <?php echo esc_html( $rf_name ); ?></h2>
				<div class="rf-table-wrap">
					<table class="rf-compare-table">
						<thead>
							<tr><th>City</th><th>Receipt required</th><th>Law</th></tr>
						</thead>
						<tbody>
							<?php foreach ( rf_rent_law_children( $rf_sub ) as $rf_c_sub => $rf_c ) : ?>
								<tr>
									<td><?php echo rf_rent_law_link( $rf_c_sub, $rf_c['city'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></td>
									<td><?php echo esc_html( $rf_c['when_short'] ); ?></td>
									<td><a href="<?php echo esc_url( $rf_c['url'] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( $rf_c['citation'] ); ?></a></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<p>We list a city only after reading its ordinance. Other <?php echo esc_html( $rf_name ); ?> cities may have rules we have not checked yet, and your lease can add to them.</p>

			<?php elseif ( 'country-hub' === $rf_kind ) : ?>
				<h2>Rent Receipt Rules by Province</h2>
				<div class="rf-table-wrap">
					<table class="rf-compare-table">
						<thead>
							<tr><th>Province</th><th>Receipt required</th><th>Law</th></tr>
						</thead>
						<tbody>
							<?php foreach ( rf_rent_law_children( $rf_sub ) as $rf_c_sub => $rf_c ) : ?>
								<tr>
									<td><?php echo rf_rent_law_link( $rf_c_sub, $rf_c['region'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></td>
									<td><?php echo esc_html( $rf_c['when_short'] ); ?></td>
									<td><a href="<?php echo esc_url( $rf_c['url'] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( $rf_c['citation'] ); ?></a></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<h2>Other Provinces and Territories</h2>
				<ul>
					<?php foreach ( $rf_law['notes'] as $rf_note ) : ?>
						<li><strong><?php echo esc_html( $rf_note[0] ); ?>:</strong> <?php echo esc_html( $rf_note[1] ); ?><?php if ( $rf_note[2] ) : ?> (<a href="<?php echo esc_url( $rf_note[2] ); ?>" rel="noopener" target="_blank">the Act</a>)<?php endif; ?></li>
					<?php endforeach; ?>
				</ul>

			<?php elseif ( 'country' === $rf_kind ) : ?>
				<?php foreach ( $rf_law['sections'] as $rf_part ) : ?>
					<h2><?php echo esc_html( $rf_part['region'] ); ?></h2>
					<div class="rf-law-facts rf-law-facts-2">
						<div class="rf-law-fact"><strong>When it is required</strong><?php echo esc_html( $rf_part['trigger'] ); ?></div>
						<div class="rf-law-fact"><strong>The law</strong><a href="<?php echo esc_url( $rf_part['url'] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( $rf_part['citation'] ); ?></a></div>
					</div>
					<?php if ( $rf_part['contents'] ) : ?>
						<p><strong>What it must show:</strong></p>
						<ul>
							<?php foreach ( $rf_part['contents'] as $rf_item ) : ?>
								<li><?php echo esc_html( $rf_item ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php foreach ( $rf_part['details'] as $rf_para ) : ?>
						<p><?php echo esc_html( $rf_para ); ?></p>
					<?php endforeach; ?>
					<?php if ( $rf_part['penalty'] ) : ?>
						<p><strong>If the landlord doesn't comply:</strong> <?php echo esc_html( $rf_part['penalty'] ); ?></p>
					<?php endif; ?>
				<?php endforeach; ?>
				<p>The generator below makes a receipt in pounds with a field for each of these details. Add your signature after you print or download it.</p>

			<?php else : ?>
				<div class="rf-law-facts">
					<div class="rf-law-fact"><strong>When a receipt is required</strong><?php echo esc_html( $rf_law['trigger'] ); ?></div>
					<div class="rf-law-fact"><strong>The law</strong><a href="<?php echo esc_url( $rf_law['url'] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( $rf_law['citation'] ); ?></a></div>
					<div class="rf-law-fact"><strong>Landlord records</strong><?php echo esc_html( $rf_law['records'] ? $rf_law['records'] : 'The ' . $rf_word . ' sets no separate record-keeping rule.' ); ?></div>
				</div>

				<?php if ( ! empty( $rf_law['receipts'] ) ) : ?>
					<h2>What Each <?php echo esc_html( $rf_name ); ?> Receipt Must Show</h2>
					<?php foreach ( $rf_law['receipts'] as $rf_receipt ) : ?>
						<h3><?php echo esc_html( $rf_receipt['title'] ); ?></h3>
						<ul>
							<?php foreach ( $rf_receipt['items'] as $rf_item ) : ?>
								<li><?php echo esc_html( $rf_item ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endforeach; ?>
					<p>The deposit receipt generator below has fields for the amount, the date, who received it, the premises and a signature line; add the interest and forwarding-address statements in the notes for last month's rent.</p>
				<?php elseif ( ! empty( $rf_law['contents'] ) ) : ?>
					<h2>What a <?php echo esc_html( $rf_name ); ?> Rent Receipt Must Show</h2>
					<ul>
						<?php foreach ( $rf_law['contents'] as $rf_item ) : ?>
							<li><?php echo esc_html( $rf_item ); ?></li>
						<?php endforeach; ?>
					</ul>
					<p>The generator below has a field for each of these. Add your signature after you print or download it.</p>
				<?php else : ?>
					<h2>What to Put on the Receipt</h2>
					<p>The <?php echo esc_html( $rf_word ); ?> does not list required contents. A receipt that shows the date, the amount, the rent period, the property address, the tenant's name and the landlord's or agent's name and signature covers what tenants, courts and<?php echo $rf_us ? ' employers' : ' others'; ?> usually ask for.</p>
				<?php endif; ?>

				<?php if ( ! empty( $rf_law['details'] ) ) : ?>
					<h2>Details</h2>
					<?php foreach ( (array) $rf_law['details'] as $rf_para ) : ?>
						<p><?php echo esc_html( $rf_para ); ?></p>
					<?php endforeach; ?>
				<?php endif; ?>

				<?php if ( ! empty( $rf_law['penalty'] ) ) : ?>
					<h2>If the Landlord Doesn't Comply</h2>
					<p><?php echo esc_html( $rf_law['penalty'] ); ?></p>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $rf_us ) : ?>
				<p class="rf-law-disclaimer">Checked against the <?php echo esc_html( $rf_word ); ?> text in <?php echo esc_html( $rf_law['checked'] ); ?>. This is general information, not legal advice; <?php echo 'city' === $rf_kind ? 'state law' : 'city or county rules'; ?> and your lease can add to it. Read the <a href="<?php echo esc_url( $rf_law['url'] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( $rf_word ); ?></a> or ask a local tenant organization or lawyer about your situation.</p>
			<?php else : ?>
				<p class="rf-law-disclaimer">Checked against the text of the law in <?php echo esc_html( $rf_law['checked'] ); ?>. This is general information, not legal advice; your tenancy agreement and local rules can add to it. Read the linked law or ask a tenant advice service or a lawyer about your situation.</p>
			<?php endif; ?>
		</section>
	</div>

	<?php
	$rf_tpl  = rf_get_category_data( $rf_law['template'] );
	$rf_form = $rf_tpl['form'] ?? array();
	if ( $rf_law['currency'] ) {
		$rf_form['currency'] = $rf_law['currency'];
	}
	if ( ! $rf_us && ! $rf_deposit ) {
		$rf_form['business_label'] = 'Landlord or agent';
		$rf_form['business_ph']    = 'country' === $rf_kind ? 'e.g. Sarah Collins' : 'e.g. Sarah Tremblay';
		$rf_form['client_ph']      = 'country' === $rf_kind ? 'e.g. James Wilson' : 'e.g. Daniel Roy';
	}
	get_template_part(
		'template-parts/generator-embed',
		null,
		array(
			'heading' => $rf_label . ' ' . ucwords( $rf_doc ) . ' — Fill in the Details',
			'form'    => $rf_form,
			'purpose' => $rf_law['purpose'],
		)
	);
	?>

	<div class="rf-container rf-narrow">
		<section class="rf-section rf-faq-section">
			<h2><?php echo esc_html( $rf_name ); ?> Rent Receipt FAQ</h2>
			<div class="rf-faq">
				<?php foreach ( rf_rent_laws_faqs( $rf_sub ) as $rf_faq ) : ?>
					<h3><?php echo esc_html( $rf_faq[0] ); ?></h3>
					<p><?php echo esc_html( $rf_faq[1] ); ?></p>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="rf-section">
			<?php
			// Sibling pages: other states (and city-rule states) for a state,
			// the other cities or provinces under the same parent, or the
			// other countries.
			$rf_links = array();
			if ( in_array( $rf_kind, array( 'state', 'city-state' ), true ) ) {
				$rf_heading = 'Other States';
				foreach ( rf_rent_law_children( '' ) as $rf_other => $rf_other_law ) {
					if ( $rf_other !== $rf_sub && in_array( $rf_other_law['kind'], array( 'state', 'city-state' ), true ) ) {
						$rf_links[ $rf_other ] = $rf_other_law['name'];
					}
				}
				asort( $rf_links );
			} elseif ( in_array( $rf_kind, array( 'city', 'region' ), true ) ) {
				$rf_heading = 'city' === $rf_kind ? 'Other Cities' : 'Other Provinces';
				foreach ( rf_rent_law_children( $rf_law['parent'] ) as $rf_other => $rf_other_law ) {
					if ( $rf_other !== $rf_sub ) {
						$rf_links[ $rf_other ] = $rf_other_law['city'] ?? $rf_other_law['region'];
					}
				}
				$rf_links[ $rf_law['parent'] ] = 'All of ' . rf_rent_law_entry( $rf_law['parent'] )['name'];
			} else {
				$rf_heading = 'Other Countries';
				foreach ( rf_rent_law_children( '' ) as $rf_other => $rf_other_law ) {
					if ( $rf_other !== $rf_sub && in_array( $rf_other_law['kind'], array( 'country', 'country-hub' ), true ) ) {
						$rf_links[ $rf_other ] = 'country' === $rf_other_law['kind'] ? 'United Kingdom' : $rf_other_law['name'];
					}
				}
			}
			$rf_html = array();
			foreach ( $rf_links as $rf_other => $rf_text ) {
				$rf_html[] = rf_rent_law_link( $rf_other, $rf_text );
			}
			?>
			<?php if ( $rf_html ) : ?>
				<h2><?php echo esc_html( $rf_heading ); ?></h2>
				<p><?php echo implode( ' · ', $rf_html ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></p>
			<?php endif; ?>
			<p><a href="<?php echo esc_url( $rf_hub ); ?>"><?php echo $rf_us ? 'All rent receipt laws by state' : 'Rent receipt laws in the US, by state'; ?></a></p>
		</section>
	</div>

<?php else : ?>

	<section class="rf-category-hero">
		<div class="rf-container rf-category-hero-inner">
			<span class="rf-category-icon" aria-hidden="true">⚖️</span>
			<h1>Rent Receipt Laws by State</h1>
			<p class="rf-hero-sub rf-direct-answer">
				<?php
				// Audit 20 Oct (#65): a state whose only rule is a general one
				// ('general', e.g. California's debtor rule) is listed but not
				// counted as a landlord rule. 1.4.2: nor is a deposit-only rule
				// (Massachusetts), which has its own list below the table.
				$rf_deposit = rf_rent_deposit_only_laws();
				$rf_monthly = array_diff_key( $rf_laws, $rf_deposit );
				$rf_landlord = array_filter(
					$rf_monthly,
					function ( $l ) {
						return empty( $l['general'] );
					}
				);
				$rf_general  = array_diff_key( $rf_monthly, $rf_landlord );
				$rf_has_dc   = isset( $rf_landlord['district-of-columbia'] );
				?>
				There is no federal rule, but at least <?php echo esc_html( count( $rf_landlord ) - ( $rf_has_dc ? 1 : 0 ) ); ?> states<?php echo $rf_has_dc ? ' and Washington, D.C.' : ''; ?> have a rule that makes a landlord give a written rent receipt,
				most often when rent is paid in cash<?php echo $rf_general ? esc_html( '; in ' . implode( ' and ', wp_list_pluck( $rf_general, 'state' ) ) . ' a general rule lets anyone who pays a debt, rent included, ask for one' ) : ''; ?>. The table shows when each one applies and links to the statute.
			</p>
			<p class="rf-law-reviewed">Last reviewed: <?php echo esc_html( $rf_seen['label'] ); ?></p>
		</div>
	</section>

	<div class="rf-container rf-narrow">
		<section class="rf-section">
			<div class="rf-table-wrap">
				<table class="rf-compare-table">
					<thead>
						<tr><th>State</th><th>Receipt required</th><th>Law</th></tr>
					</thead>
					<tbody>
						<?php foreach ( $rf_monthly as $rf_s => $rf_l ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( $rf_hub . $rf_s . '/' ); ?>"><?php echo esc_html( $rf_l['state'] ); ?></a></td>
								<td><?php echo esc_html( $rf_l['when_short'] ); ?></td>
								<td><a href="<?php echo esc_url( $rf_l['url'] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( $rf_l['citation'] ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php if ( $rf_deposit ) : ?>
				<h2>States With a Receipt Rule for Deposits Only</h2>
				<ul>
					<?php foreach ( $rf_deposit as $rf_s => $rf_d ) : ?>
						<li><strong><a href="<?php echo esc_url( $rf_hub . $rf_s . '/' ); ?>"><?php echo esc_html( $rf_d['state'] ); ?></a>:</strong> <?php echo esc_html( $rf_d['trigger'] ); ?> (<a href="<?php echo esc_url( $rf_d['url'] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( $rf_d['citation'] ); ?></a>)</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php $rf_cities = rf_rent_receipt_city_laws(); ?>
			<?php if ( $rf_cities ) : ?>
				<h2>City Rules</h2>
				<p>Some cities require rent receipts where their state does not.</p>
				<div class="rf-table-wrap">
					<table class="rf-compare-table">
						<thead>
							<tr><th>City</th><th>Receipt required</th><th>Law</th></tr>
						</thead>
						<tbody>
							<?php foreach ( $rf_cities as $rf_s => $rf_c ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( $rf_hub . $rf_s . '/' ); ?>"><?php echo esc_html( $rf_c['city'] . ', ' . $rf_c['state'] ); ?></a></td>
									<td><?php echo esc_html( $rf_c['when_short'] ); ?></td>
									<td><a href="<?php echo esc_url( $rf_c['url'] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( $rf_c['citation'] ); ?></a></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<h2>Your State Isn't Listed?</h2>
			<p>We list a state or city only after reading its statute or ordinance. If yours isn't here, we haven't found a rent receipt rule for it, but a local ordinance or your lease may still require one. Giving a receipt for every payment is good practice anyway: it protects the landlord in a dispute and gives the tenant proof of payment for taxes or benefits.</p>
			<p class="rf-cta-row"><a class="rf-btn rf-btn-primary" href="<?php echo esc_url( home_url( '/rent-receipt/' ) ); ?>">Make a rent receipt</a></p>

			<h2>Other Countries</h2>
			<ul>
				<?php foreach ( rf_rent_law_children( '' ) as $rf_s => $rf_c ) : ?>
					<?php if ( in_array( $rf_c['kind'], array( 'country', 'country-hub' ), true ) ) : ?>
						<li><strong><a href="<?php echo esc_url( $rf_hub . $rf_s . '/' ); ?>"><?php echo esc_html( 'country' === $rf_c['kind'] ? 'United Kingdom' : $rf_c['name'] ); ?></a>:</strong> <?php echo esc_html( $rf_c['summary'] ); ?></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
			<p class="rf-law-disclaimer">General information, not legal advice. Each state's statute and each city's ordinance was read on the date shown on its page; laws change, so check the linked text.</p>
		</section>

		<section class="rf-section rf-faq-section">
			<h2>Rent Receipt Law FAQ</h2>
			<div class="rf-faq">
				<?php foreach ( rf_rent_laws_faqs( '' ) as $rf_faq ) : ?>
					<h3><?php echo esc_html( $rf_faq[0] ); ?></h3>
					<p><?php echo esc_html( $rf_faq[1] ); ?></p>
				<?php endforeach; ?>
			</div>
		</section>
	</div>

<?php endif; ?>

</article>

<?php
get_footer();
