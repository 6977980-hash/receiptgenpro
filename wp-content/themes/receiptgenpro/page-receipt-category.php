<?php
/**
 * Template Name: Receipt Category Page
 *
 * Used for every flat, single-purpose landing page: /rent-receipt/,
 * /hotel-receipt/, /restaurant-receipt/, etc. Structured "quick facts"
 * come from inc/category-data.php; the long-form article body (intro,
 * H2 sections, FAQ) is the normal WordPress Page content, edited in the
 * block editor like any other page.
 *
 * Deliberately NOT reusing the homepage's H1 or title — each category
 * page targets exactly one keyword, which is the fix for the
 * cannibalization issue found on competitor sites.
 */
get_header();

while ( have_posts() ) :
	the_post();

	$cat = rf_get_category_data( get_post_field( 'post_name' ) );
	?>

	<article id="rf-main-content" class="rf-category-page">

		<section class="rf-category-hero">
			<div class="rf-container rf-category-hero-inner">
				<span class="rf-category-icon" aria-hidden="true"><?php echo esc_html( $cat['icon'] ?? '🧾' ); ?></span>
				<h1><?php echo esc_html( $cat['h1'] ?? get_the_title() ); ?></h1>
				<p class="rf-hero-sub">Free, no signup, no watermark — download as PDF, PNG or JPEG in under a minute.</p>
				<a href="#rf-generator" class="rf-btn rf-btn-primary">Create It Now ↓</a>
			</div>
		</section>

		<?php
		get_template_part(
			'template-parts/generator-embed',
			null,
			array(
				'heading' => ( $cat['h1'] ?? get_the_title() ) . ' — Fill in the Details',
				'form'    => $cat['form'] ?? array(),
			)
		);
		?>

		<div class="rf-container rf-category-body-grid">

			<div class="rf-category-content">
				<?php the_content(); ?>
			</div>

			<?php if ( ! empty( $cat['highlights'] ) || ! empty( $cat['default_fields'] ) ) : ?>
			<aside class="rf-category-sidebar">
				<?php if ( ! empty( $cat['default_fields'] ) ) : ?>
				<div class="rf-sidebar-box">
					<h3>What's Included</h3>
					<ul class="rf-check-list">
						<?php foreach ( $cat['default_fields'] as $field ) : ?>
							<li><?php echo wp_kses_post( $field ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>

				<?php if ( ! empty( $cat['highlights'] ) ) : ?>
				<div class="rf-sidebar-box">
					<h3>Why This Template</h3>
					<ul class="rf-highlight-list">
						<?php foreach ( $cat['highlights'] as $point ) : ?>
							<li><?php echo wp_kses_post( $point ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>

				<?php if ( ! empty( $cat['related'] ) ) : ?>
				<div class="rf-sidebar-box">
					<h3>Related Templates</h3>
					<ul class="rf-related-template-list">
						<?php foreach ( $cat['related'] as $related_slug ) :
							$related_cat = rf_get_category_data( $related_slug );
							if ( ! $related_cat ) {
								continue;
							}
							?>
							<li>
								<a href="<?php echo esc_url( home_url( '/' . $related_slug . '/' ) ); ?>">
									<span aria-hidden="true"><?php echo esc_html( $related_cat['icon'] ); ?></span>
									<?php echo esc_html( rf_category_label( $related_cat ) ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>

				<div class="rf-sidebar-box rf-sidebar-cta">
					<h3>Need a Different Receipt?</h3>
					<p>Browse all <a href="<?php echo esc_url( home_url( '/receipt-templates/' ) ); ?>">receipt templates</a> or start from the <a href="<?php echo esc_url( home_url( '/' ) ); ?>">general receipt generator</a>.</p>
				</div>
			</aside>
			<?php endif; ?>

		</div>

	</article>

	<?php
endwhile;

get_footer();
