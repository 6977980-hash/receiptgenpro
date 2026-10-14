<?php
/**
 * Template Name: Receipt Requirements Checker
 *
 * /receipt-requirements/ (v1.2.0). Prints every rule list from
 * inc/requirements.php as a crawlable table with its official sources;
 * the live checker under each generator uses the same lists.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$rf_purposes = rf_receipt_purposes();
?>

<article id="rf-main-content" class="rf-tool-page">

	<section class="rf-category-hero">
		<div class="rf-container rf-category-hero-inner">
			<span class="rf-category-icon" aria-hidden="true">✅</span>
			<h1>Receipt Requirements Checker</h1>
			<p class="rf-hero-sub rf-direct-answer">
				A receipt that is fine for your own records can still be rejected by an employer, an FSA plan or a tax office.
				Below is what a receipt must show for each common use, taken from the official source. Every generator on this site
				checks your receipt against these lists as you type.
			</p>
			<ul class="rf-jump-links">
				<?php foreach ( $rf_purposes as $rf_key => $rf_p ) : ?>
					<li><a href="#<?php echo esc_attr( $rf_key ); ?>"><?php echo esc_html( $rf_p['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>

	<div class="rf-container rf-narrow">
		<section class="rf-section">
			<?php
			foreach ( $rf_purposes as $rf_key => $rf_p ) :
				$rf_template_url = home_url( '/' . $rf_p['template'] . '/' );
				?>
				<div class="rf-req-block" id="<?php echo esc_attr( $rf_key ); ?>">
					<h2><?php echo esc_html( $rf_p['heading'] ); ?></h2>
					<p><?php echo esc_html( $rf_p['intro'] ); ?></p>
					<div class="rf-table-wrap">
						<table class="rf-compare-table">
							<thead>
								<tr><th>The receipt needs</th><th>When</th></tr>
							</thead>
							<tbody>
								<?php foreach ( $rf_p['checks'] as $rf_check ) : ?>
									<tr>
										<td>
											<?php echo esc_html( $rf_check['label'] ); ?>
											<?php if ( ! empty( $rf_check['note'] ) ) : ?>
												<br><span class="rf-muted"><?php echo esc_html( $rf_check['note'] ); ?></span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( rf_requirement_when_text( $rf_check, $rf_key ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<p class="rf-muted">Source<?php echo count( $rf_p['sources'] ) > 1 ? 's' : ''; ?>:</p>
					<ul class="rf-sources">
						<?php foreach ( $rf_p['sources'] as $rf_src ) : ?>
							<li>
								<?php if ( $rf_src[1] ) : ?>
									<a href="<?php echo esc_url( $rf_src[1] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( $rf_src[0] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $rf_src[0] ); ?>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
					<p class="rf-cta-row">
						<a class="rf-btn rf-btn-primary" href="<?php echo esc_url( add_query_arg( 'purpose', $rf_key, $rf_template_url ) . '#rf-generator' ); ?>">Check your receipt now</a>
					</p>
				</div>
			<?php endforeach; ?>
		</section>

		<?php if ( function_exists( 'rf_rent_receipt_laws' ) ) : ?>
			<section class="rf-section">
				<h2>Rent Receipts: Your State's Rules</h2>
				<p>Some US states require landlords to give a receipt for rent paid in cash, and some for any payment. See <a href="<?php echo esc_url( home_url( '/rent-receipt-laws/' ) ); ?>">rent receipt laws by state</a>.</p>
			</section>
		<?php endif; ?>

		<section class="rf-section rf-faq-section">
			<h2>Receipt Requirements FAQ</h2>
			<div class="rf-faq">
				<?php foreach ( rf_requirements_faqs() as $rf_faq ) : ?>
					<h3><?php echo esc_html( $rf_faq[0] ); ?></h3>
					<p><?php echo esc_html( $rf_faq[1] ); ?></p>
				<?php endforeach; ?>
			</div>
			<p class="rf-law-disclaimer">This page is general information, not legal or tax advice. Rules change, and an employer or plan can ask for more than the law does.</p>
		</section>
	</div>

</article>

<?php
get_footer();
