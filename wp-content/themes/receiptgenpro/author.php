<?php
/**
 * ReceiptGenPro — author.php
 *
 * A real author archive is an E-E-A-T signal in itself (Google can crawl
 * a stable URL showing everything one credentialed person has written).
 * This is the page the Person schema's "url" field points to.
 */
get_header();
$author_id = get_queried_object_id();
?>

<div class="rf-container rf-blog-index">

	<header class="rf-author-header">
		<?php echo get_avatar( $author_id, 96, '', get_the_author_meta( 'display_name', $author_id ) . ' avatar' ); ?>
		<h1><?php echo esc_html( get_the_author_meta( 'display_name', $author_id ) ); ?></h1>
		<?php $job_title = get_the_author_meta( 'rf_job_title', $author_id ); ?>
		<?php if ( $job_title ) : ?><p class="rf-author-title"><?php echo esc_html( $job_title ); ?></p><?php endif; ?>
		<?php $bio = get_the_author_meta( 'description', $author_id ); ?>
		<?php if ( $bio ) : ?><p class="rf-author-bio"><?php echo esc_html( $bio ); ?></p><?php endif; ?>
		<?php $linkedin = get_the_author_meta( 'linkedin', $author_id ); ?>
		<?php if ( $linkedin ) : ?><a href="<?php echo esc_url( $linkedin ); ?>" rel="me noopener" target="_blank">LinkedIn →</a><?php endif; ?>
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
					</div>
				</a>
				<?php
			endwhile;
			?>
		</div>
		<nav class="rf-pagination">
			<?php echo paginate_links( array( 'prev_text' => '← Newer', 'next_text' => 'Older →' ) ); ?>
		</nav>
	<?php endif; ?>

</div>

<?php get_footer(); ?>
