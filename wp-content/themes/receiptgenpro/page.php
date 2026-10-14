<?php
/**
 * ReceiptGenPro — page.php
 * Generic fallback for standard pages (About Us, Contact Us, Privacy
 * Policy, Terms, etc.) that don't use the Receipt Category template.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article id="rf-main-content" class="rf-generic-page">
		<div class="rf-container rf-narrow">
			<h1><?php the_title(); ?></h1>
			<div class="rf-page-content">
				<?php the_content(); ?>
			</div>
		</div>
	</article>
	<?php
endwhile;

get_footer();
