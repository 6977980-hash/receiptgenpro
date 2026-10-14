<?php
/**
 * ReceiptGenPro — home.php
 *
 * Renders the site's "Posts page" (set under Settings → Reading →
 * "Posts page" once the "Blog" page from the WXR import exists). This is
 * the native WordPress blog loop, so EVERY new post you publish appears
 * here automatically, newest first, with no manual step — this directly
 * satisfies the "new posts should show up on the blog page automatically"
 * requirement.
 */
get_header();
?>

<div class="rf-container rf-blog-index">

	<header class="rf-blog-header">
		<h1>Receipt &amp; Invoice Guides</h1>
		<p>Practical, country-specific guides on making receipts, record-keeping and tax rules for small businesses — every guide links to the official tax-authority sources it is based on.<?php if ( rf_editorial_policy_url() ) : ?> See our <a href="<?php echo esc_url( rf_editorial_policy_url() ); ?>">editorial policy</a>.<?php endif; ?></p>
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
						<?php
						$cats = get_the_category();
						if ( $cats ) :
							?>
							<span class="rf-post-category"><?php echo esc_html( $cats[0]->name ); ?></span>
						<?php endif; ?>
						<h2><?php the_title(); ?></h2>
						<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
						<div class="rf-blog-card-meta">
							<span><?php the_author(); ?></span>
							<span aria-hidden="true">·</span>
							<span><?php the_date(); ?></span>
						</div>
					</div>
				</a>
				<?php
			endwhile;
			?>
		</div>

		<nav class="rf-pagination" aria-label="<?php esc_attr_e( 'Blog pagination', 'receiptgenpro' ); ?>">
			<?php
			echo paginate_links(
				array(
					'prev_text' => '← Newer',
					'next_text' => 'Older →',
				)
			);
			?>
		</nav>

	<?php else : ?>
		<p><?php esc_html_e( 'No articles published yet — check back soon.', 'receiptgenpro' ); ?></p>
	<?php endif; ?>

</div>

<?php get_footer(); ?>
