<?php
/**
 * Template Name: Receipt Templates Hub
 *
 * /receipt-templates/ — the hub page every template breadcrumb points to
 * (Sept 2026 audit, H7). Before v1.1.0 the breadcrumb pointed at
 * /#templates and /receipt-templates/ was redirected by WordPress's 404
 * guesser to an unrelated blog post.
 *
 * The page is created automatically (inc/seo-fixes.php) with this template.
 * Anything typed into the page in the editor is printed below the grid.
 */
get_header();

$rf_groups = array(
	'By type of business' => array( 'rent-receipt', 'hotel-receipt', 'restaurant-receipt', 'taxi-receipt', 'mechanic-receipt', 'cleaning-receipt', 'salon-spa-receipt', 'hardware-store-receipt', 'construction-receipt', 'childcare-receipt', 'freelance-receipt' ),
	'By kind of payment'  => array( 'payment-receipt', 'cash-receipt', 'deposit-receipt', 'hra-rent-receipt', 'donation-receipt' ),
);
?>

<article id="rf-main-content" class="rf-hub-page">

	<section class="rf-category-hero">
		<div class="rf-container rf-category-hero-inner">
			<h1>Free Receipt Templates</h1>
			<p class="rf-hero-sub rf-direct-answer">
				Pick the receipt template that matches your business. Each one has its own fields — a rent receipt asks for the
				tenant, property and rent period; a donation receipt adds your EIN and the goods-or-services statement — and every
				template is free, needs no signup and downloads as PDF, PNG or JPEG.
			</p>
		</div>
	</section>

	<div class="rf-container">
		<?php foreach ( $rf_groups as $rf_group_title => $rf_slugs ) : ?>
			<section class="rf-section rf-hub-group">
				<h2><?php echo esc_html( $rf_group_title ); ?></h2>
				<div class="rf-hub-grid">
					<?php
					foreach ( $rf_slugs as $rf_slug ) :
						$rf_cat = rf_get_category_data( $rf_slug );
						if ( ! $rf_cat ) {
							continue;
						}
						?>
						<a class="rf-hub-card" href="<?php echo esc_url( home_url( '/' . $rf_slug . '/' ) ); ?>">
							<span class="rf-template-icon" aria-hidden="true"><?php echo esc_html( $rf_cat['icon'] ); ?></span>
							<h3><?php echo esc_html( rf_category_label( $rf_cat ) ); ?></h3>
							<p><?php echo esc_html( $rf_cat['meta_description'] ); ?></p>
							<ul class="rf-hub-fields">
								<?php foreach ( array_slice( (array) $rf_cat['default_fields'], 0, 4 ) as $rf_field ) : ?>
									<li><?php echo esc_html( $rf_field ); ?></li>
								<?php endforeach; ?>
							</ul>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>

		<section class="rf-section rf-narrow">
			<h2>Which Receipt Template Should I Use?</h2>
			<div class="rf-table-wrap">
				<table class="rf-compare-table">
					<thead>
						<tr><th>If you are…</th><th>Use this template</th><th>Key fields it adds</th></tr>
					</thead>
					<tbody>
						<tr><td>A landlord collecting rent</td><td><a href="<?php echo esc_url( home_url( '/rent-receipt/' ) ); ?>">Rent receipt</a></td><td>Tenant, property address, rent period, balance due, signature</td></tr>
						<tr><td>A salaried employee in India claiming HRA</td><td><a href="<?php echo esc_url( home_url( '/hra-rent-receipt/' ) ); ?>">Rent receipt for HRA</a></td><td>Rent month, amount in rupees and words, landlord PAN and signature</td></tr>
						<tr><td>Confirming any payment you received</td><td><a href="<?php echo esc_url( home_url( '/payment-receipt/' ) ); ?>">Payment receipt</a></td><td>Transaction reference, invoice reference, balance</td></tr>
						<tr><td>Taking a security or booking deposit</td><td><a href="<?php echo esc_url( home_url( '/deposit-receipt/' ) ); ?>">Deposit receipt</a></td><td>Deposit type, what it secures, refund terms</td></tr>
						<tr><td>Taking a cash payment</td><td><a href="<?php echo esc_url( home_url( '/cash-receipt/' ) ); ?>">Cash receipt</a></td><td>Amount in words, received-by signature, balance due</td></tr>
						<tr><td>A nonprofit thanking a donor</td><td><a href="<?php echo esc_url( home_url( '/donation-receipt/' ) ); ?>">Donation receipt</a></td><td>EIN, tax status, cash or in-kind, goods-or-services statement</td></tr>
						<tr><td>A freelancer who was just paid</td><td><a href="<?php echo esc_url( home_url( '/freelance-receipt/' ) ); ?>">Freelance receipt</a></td><td>Project, invoice reference, amount paid</td></tr>
						<tr><td>A contractor taking a progress payment</td><td><a href="<?php echo esc_url( home_url( '/construction-receipt/' ) ); ?>">Contractor receipt</a></td><td>Project address, phase, balance remaining</td></tr>
						<tr><td>A taxi driver or cab company</td><td><a href="<?php echo esc_url( home_url( '/taxi-receipt/' ) ); ?>">Taxi receipt</a></td><td>Vehicle no., pickup, drop-off, distance, tip</td></tr>
						<tr><td>A hotel, guesthouse or B&amp;B</td><td><a href="<?php echo esc_url( home_url( '/hotel-receipt/' ) ); ?>">Hotel receipt</a></td><td>Room, check-in / check-out, nightly rate</td></tr>
						<tr><td>A café, food truck or caterer</td><td><a href="<?php echo esc_url( home_url( '/restaurant-receipt/' ) ); ?>">Restaurant receipt</a></td><td>Table, server, tip</td></tr>
					</tbody>
				</table>
			</div>
		</section>

		<section class="rf-section rf-faq-section rf-narrow">
			<h2>Receipt Template FAQ</h2>
			<div class="rf-faq">
				<?php foreach ( rf_hub_faqs() as $rf_faq ) : ?>
					<h3><?php echo esc_html( $rf_faq[0] ); ?></h3>
					<p><?php echo wp_kses_post( $rf_faq[1] ); ?></p>
				<?php endforeach; ?>
			</div>
		</section>

		<?php
		while ( have_posts() ) :
			the_post();
			if ( trim( get_the_content() ) ) :
				?>
				<section class="rf-section rf-narrow rf-page-content"><?php the_content(); ?></section>
				<?php
			endif;
		endwhile;
		?>
	</div>

</article>

<?php
get_footer();
