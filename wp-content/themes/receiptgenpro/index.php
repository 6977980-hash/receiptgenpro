<?php
/**
 * ReceiptGenPro — index.php
 * Required fallback template (WordPress will not activate a theme
 * without one). In practice front-page.php, page.php, single.php,
 * home.php and archive.php cover every real URL on this site, so this
 * file is a safety net for anything unmatched.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="rf-container">
	<?php if ( have_posts() ) : ?>
		<div class="rf-blog-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<a href="<?php the_permalink(); ?>" class="rf-blog-card">
					<h2><?php the_title(); ?></h2>
					<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
				</a>
				<?php
			endwhile;
			?>
		</div>
	<?php else : ?>
		<p><?php esc_html_e( 'Nothing found.', 'receiptgenpro' ); ?></p>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
