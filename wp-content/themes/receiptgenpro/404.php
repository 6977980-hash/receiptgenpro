<?php
/**
 * ReceiptGenPro — 404.php
 */
get_header();
?>
<div class="rf-container rf-narrow rf-text-center rf-404">
	<h1>404 — Page Not Found</h1>
	<p>The page you're looking for doesn't exist, or may have moved.</p>
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="rf-btn rf-btn-primary">Go to the Receipt Generator</a>
	<div class="rf-404-links">
		<h2>Receipt Templates</h2>
		<ul>
			<?php foreach ( rf_receipt_categories() as $slug => $cat ) : ?>
				<li><a href="<?php echo esc_url( home_url( '/' . $slug . '/' ) ); ?>"><?php echo esc_html( rf_category_label( $cat ) ); ?></a></li>
			<?php endforeach; ?>
			<li><a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">Blog</a></li>
		</ul>
	</div>
</div>
<?php get_footer(); ?>
