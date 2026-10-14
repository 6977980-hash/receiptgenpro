<?php
/**
 * ReceiptGenPro — single.php
 * Blog post template. Carries the AEO/GEO/E-E-A-T signals: a direct-answer
 * intro convention, FAQ block (auto-schema'd), HowTo steps where relevant,
 * author box with real credentials, related posts for internal linking.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article id="rf-main-content" class="rf-single-post">

		<header class="rf-post-header">
			<div class="rf-container rf-narrow">
				<?php
				$cats = get_the_category();
				if ( $cats ) :
					?>
					<a class="rf-post-category" href="<?php echo esc_url( get_category_link( $cats[0]->term_id ) ); ?>"><?php echo esc_html( $cats[0]->name ); ?></a>
				<?php endif; ?>
				<h1><?php the_title(); ?></h1>
				<div class="rf-post-meta">
					<span>By <a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>"><?php the_author(); ?></a></span>
					<span aria-hidden="true">·</span>
					<span>Published <?php echo esc_html( get_the_date() ); ?></span>
					<?php if ( get_the_modified_date() !== get_the_date() ) : ?>
						<span aria-hidden="true">·</span>
						<span>Updated <?php the_modified_date(); ?></span>
					<?php endif; ?>
					<span aria-hidden="true">·</span>
					<span><?php echo esc_html( rf_reading_time() ); ?> min read</span>
				</div>
			</div>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="rf-post-thumbnail rf-container rf-narrow">
				<?php the_post_thumbnail( 'large' ); ?>
			</div>
		<?php endif; ?>

		<div class="rf-container rf-narrow">
			<div class="rf-post-content">
				<?php the_content(); ?>
			</div>

			<?php rf_render_post_sources(); // v1.1.0 (audit H2): official sources + key facts. ?>

			<?php get_template_part( 'template-parts/author-box' ); ?>
		</div>

	</article>

	<div class="rf-container rf-narrow">
		<?php get_template_part( 'template-parts/related-posts' ); ?>
	</div>

	<?php
endwhile;

get_footer();

/**
 * Simple word-count-based reading time estimate — a small but genuine
 * UX/AEO signal (answer engines favor pages that are transparent about
 * content depth and freshness).
 */
function rf_reading_time() {
	$content = get_post_field( 'post_content', get_the_ID() );
	$words   = str_word_count( wp_strip_all_tags( $content ) );
	return max( 1, (int) ceil( $words / 200 ) );
}
