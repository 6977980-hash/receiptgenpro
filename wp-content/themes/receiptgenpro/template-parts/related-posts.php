<?php
/**
 * Related posts — internal linking is one of the strongest, cheapest SEO
 * levers and was flagged in the audit as under-used. Shown at the bottom
 * of every blog post, pulled from shared categories first.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$categories = wp_get_post_categories( get_the_ID() );
$related    = new WP_Query(
	array(
		'category__in'        => $categories,
		'post__not_in'        => array( get_the_ID() ),
		'posts_per_page'      => 3,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);

if ( $related->have_posts() ) :
	?>
	<aside class="rf-related-posts">
		<h2><?php esc_html_e( 'Related Guides', 'receiptgenpro' ); ?></h2>
		<div class="rf-related-grid">
			<?php
			while ( $related->have_posts() ) :
				$related->the_post();
				?>
				<a href="<?php the_permalink(); ?>" class="rf-related-card">
					<?php if ( has_post_thumbnail() ) : ?>
						<?php
						// Three cards of ~235 px in the post column; one full-width card below 800 px.
						the_post_thumbnail( 'rf-card', array( 'sizes' => '(max-width: 800px) calc(100vw - 2.5rem), 240px' ) );
						?>
					<?php endif; ?>
					<h3><?php the_title(); ?></h3>
					<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 16 ) ); ?></p>
				</a>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
		</div>
	</aside>
	<?php
endif;
