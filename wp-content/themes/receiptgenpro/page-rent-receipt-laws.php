<?php
/**
 * Template Name: Rent Receipt Laws
 *
 * /rent-receipt-laws/ (hub) and /rent-receipt-laws/<state>/ (v1.2.0).
 * Every fact comes from rf_rent_receipt_laws() in inc/rent-laws.php; a
 * state page also carries the rent receipt generator.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$rf_laws  = rf_rent_receipt_laws();
$rf_slug  = wp_get_post_parent_id( get_queried_object_id() ) ? basename( rf_current_page_path() ) : '';
$rf_law   = $rf_slug && isset( $rf_laws[ $rf_slug ] ) ? $rf_laws[ $rf_slug ] : null;
$rf_hub   = home_url( '/rent-receipt-laws/' );
?>

<article id="rf-main-content" class="rf-tool-page">

<?php if ( $rf_law ) : ?>

	<section class="rf-category-hero">
		<div class="rf-container rf-category-hero-inner">
			<span class="rf-category-icon" aria-hidden="true">⚖️</span>
			<h1><?php echo esc_html( $rf_law['state'] ); ?> Rent Receipt Law</h1>
			<p class="rf-hero-sub rf-direct-answer"><?php echo esc_html( $rf_law['summary'] ); ?></p>
			<a href="#rf-generator" class="rf-btn rf-btn-primary">Make a <?php echo esc_html( $rf_law['state'] ); ?> rent receipt ↓</a>
		</div>
	</section>

	<div class="rf-container rf-narrow">
		<section class="rf-section">
			<div class="rf-law-facts">
				<div class="rf-law-fact"><strong>When a receipt is required</strong><?php echo esc_html( $rf_law['trigger'] ); ?></div>
				<div class="rf-law-fact"><strong>The law</strong><a href="<?php echo esc_url( $rf_law['url'] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( $rf_law['citation'] ); ?></a></div>
				<div class="rf-law-fact"><strong>Landlord records</strong><?php echo esc_html( $rf_law['records'] ? $rf_law['records'] : 'The statute sets no separate record-keeping rule.' ); ?></div>
			</div>

			<?php if ( ! empty( $rf_law['contents'] ) ) : ?>
				<h2>What a <?php echo esc_html( $rf_law['state'] ); ?> Rent Receipt Must Show</h2>
				<ul>
					<?php foreach ( $rf_law['contents'] as $rf_item ) : ?>
						<li><?php echo esc_html( $rf_item ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p>The generator below has a field for each of these. Add your signature after you print or download it.</p>
			<?php else : ?>
				<h2>What to Put on the Receipt</h2>
				<p>The statute does not list required contents. A receipt that shows the date, the amount, the rent period, the property address, the tenant's name and the landlord's or agent's name and signature covers what tenants, courts and employers usually ask for.</p>
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

			<p class="rf-law-disclaimer">Checked against the statute text on <?php echo esc_html( $rf_law['checked'] ); ?>. This is general information, not legal advice; city or county rules and your lease can add to it. Read the <a href="<?php echo esc_url( $rf_law['url'] ); ?>" rel="noopener" target="_blank">statute</a> or ask a local tenant organization or lawyer about your situation.</p>
		</section>
	</div>

	<?php
	$rf_rent = rf_get_category_data( 'rent-receipt' );
	get_template_part(
		'template-parts/generator-embed',
		null,
		array(
			'heading' => $rf_law['state'] . ' Rent Receipt — Fill in the Details',
			'form'    => $rf_rent['form'] ?? array(),
			'purpose' => 'rent-us',
		)
	);
	?>

	<div class="rf-container rf-narrow">
		<section class="rf-section rf-faq-section">
			<h2><?php echo esc_html( $rf_law['state'] ); ?> Rent Receipt FAQ</h2>
			<div class="rf-faq">
				<?php foreach ( rf_rent_laws_faqs( $rf_slug ) as $rf_faq ) : ?>
					<h3><?php echo esc_html( $rf_faq[0] ); ?></h3>
					<p><?php echo esc_html( $rf_faq[1] ); ?></p>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="rf-section">
			<h2>Other States</h2>
			<p>
				<?php
				$rf_links = array();
				foreach ( $rf_laws as $rf_other => $rf_other_law ) {
					if ( $rf_other !== $rf_slug ) {
						$rf_links[] = '<a href="' . esc_url( $rf_hub . $rf_other . '/' ) . '">' . esc_html( $rf_other_law['state'] ) . '</a>';
					}
				}
				echo implode( ' · ', $rf_links ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
				?>
			</p>
			<p><a href="<?php echo esc_url( $rf_hub ); ?>">All rent receipt laws by state</a></p>
		</section>
	</div>

<?php else : ?>

	<section class="rf-category-hero">
		<div class="rf-container rf-category-hero-inner">
			<span class="rf-category-icon" aria-hidden="true">⚖️</span>
			<h1>Rent Receipt Laws by State</h1>
			<p class="rf-hero-sub rf-direct-answer">
				There is no federal rule, but at least <?php echo esc_html( count( $rf_laws ) - ( isset( $rf_laws['district-of-columbia'] ) ? 1 : 0 ) ); ?> states<?php echo isset( $rf_laws['district-of-columbia'] ) ? ' and Washington, D.C.' : ''; ?> have a rule that makes a landlord give a written rent receipt,
				most often when rent is paid in cash. The table shows when each one applies and links to the statute.
			</p>
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
						<?php foreach ( $rf_laws as $rf_s => $rf_l ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( $rf_hub . $rf_s . '/' ); ?>"><?php echo esc_html( $rf_l['state'] ); ?></a></td>
								<td><?php echo esc_html( $rf_l['when_short'] ); ?></td>
								<td><a href="<?php echo esc_url( $rf_l['url'] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( $rf_l['citation'] ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php $rf_deposit = rf_rent_deposit_only_laws(); ?>
			<?php if ( $rf_deposit ) : ?>
				<h2>States With a Receipt Rule for Deposits Only</h2>
				<ul>
					<?php foreach ( $rf_deposit as $rf_d ) : ?>
						<li><strong><?php echo esc_html( $rf_d['state'] ); ?>:</strong> <?php echo esc_html( $rf_d['note'] ); ?><?php if ( ! empty( $rf_d['url'] ) ) : ?> (<a href="<?php echo esc_url( $rf_d['url'] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( $rf_d['citation'] ); ?></a>)<?php endif; ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<h2>Your State Isn't Listed?</h2>
			<p>We list a state only after reading its statute. If yours isn't here, we haven't found a statewide rent receipt rule, but a city ordinance or your lease may still require one. Giving a receipt for every payment is good practice anyway: it protects the landlord in a dispute and gives the tenant proof of payment for taxes or benefits.</p>
			<p class="rf-cta-row"><a class="rf-btn rf-btn-primary" href="<?php echo esc_url( home_url( '/rent-receipt/' ) ); ?>">Make a rent receipt</a></p>
			<p class="rf-law-disclaimer">General information, not legal advice. Each state's statute was read on the date shown on its page; laws change, so check the linked text.</p>
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
