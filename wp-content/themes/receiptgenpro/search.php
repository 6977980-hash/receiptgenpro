<?php
/**
 * ReceiptGenPro — search.php
 */
get_header();
?>
<div class="rf-container rf-blog-index">
	<header class="rf-blog-header">
		<h1>Search results for "<?php echo esc_html( get_search_query() ); ?>"</h1>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="rf-blog-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<a href="<?php the_permalink(); ?>" class="rf-blog-card">
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
	<?php else : ?>
		<p>No results found. Try the <a href="<?php echo esc_url( home_url( '/' ) ); ?>">receipt generator</a> or browse the <a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">blog</a>.</p>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
