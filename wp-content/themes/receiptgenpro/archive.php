<?php
/**
 * ReceiptGenPro — archive.php
 * Category / tag archives. Same card grid as home.php for visual
 * consistency, with the archive title/description as an SEO-relevant H1.
 */
get_header();
?>

<div class="rf-container rf-blog-index">

	<header class="rf-blog-header">
		<h1><?php echo esc_html( single_term_title( '', false ) ?: wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
		<?php
		// v1.1.0 (audit H4): every category gets a real intro paragraph,
		// either its own wp-admin description or the theme default.
		$rf_term = get_queried_object();
		if ( term_description() ) :
			?>
			<div class="rf-archive-description"><?php echo wp_kses_post( term_description() ); ?></div>
		<?php elseif ( $rf_term instanceof WP_Term ) : ?>
			<div class="rf-archive-description"><p><?php echo esc_html( rf_default_term_description( $rf_term ) ); ?></p></div>
		<?php endif; ?>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="rf-blog-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<a href="<?php the_permalink(); ?>" class="rf-blog-card">
					<?php if ( has_post_thumbnail() ) : ?>
						<div class="rf-blog-card-thumb"><?php the_post_thumbnail( 'rf-card' ); ?></div>
					<?php endif; ?>
					<div class="rf-blog-card-body">
						<h2><?php the_title(); ?></h2>
						<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
						<div class="rf-blog-card-meta">
							<span><?php the_author(); ?></span>
							<span aria-hidden="true">·</span>
							<span><?php echo esc_html( get_the_date() ); ?></span>
						</div>
					</div>
				</a>
				<?php
			endwhile;
			?>
		</div>
		<nav class="rf-pagination">
			<?php echo paginate_links( array( 'prev_text' => '← Newer', 'next_text' => 'Older →' ) ); ?>
		</nav>
	<?php else : ?>
		<p><?php esc_html_e( 'No articles found.', 'receiptgenpro' ); ?></p>
	<?php endif; ?>

</div>

<?php get_footer(); ?>
