<?php
/**
 * Author / E-E-A-T box — shown at the bottom of every blog post.
 *
 * This directly targets the "no author bio / no E-E-A-T signal" finding
 * from the competitor audit. It pulls the two custom profile fields
 * registered in functions.php (rf_job_title, rf_expertise) so real
 * credentials show up both here AND in the Person schema (inc/schema.php)
 * — visible content and structured data always match.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$author_id    = get_the_author_meta( 'ID' );
$job_title    = get_the_author_meta( 'rf_job_title', $author_id );
$expertise    = get_the_author_meta( 'rf_expertise', $author_id );
$linkedin     = get_the_author_meta( 'linkedin', $author_id );
$description  = get_the_author_meta( 'description', $author_id );
?>
<div class="rf-author-box">
	<div class="rf-author-avatar">
		<?php echo get_avatar( $author_id, 96, '', get_the_author_meta( 'display_name', $author_id ) . ' avatar' ); ?>
	</div>
	<div class="rf-author-body">
		<?php
		// v1.1.0 (audit H2): the box used to say "Written & reviewed by"
		// with nobody actually reviewing. It now says "Written by", and a
		// "Reviewed by …" line appears only when the post has a real
		// reviewer in the custom field "rf_reviewed_by" (e.g.
		// "Jane Doe, CPA").
		$rf_reviewer = get_post_meta( get_the_ID(), 'rf_reviewed_by', true );
		?>
		<p class="rf-author-label"><?php esc_html_e( 'Written by', 'receiptgenpro' ); ?></p>
		<h3><a href="<?php echo esc_url( get_author_posts_url( $author_id ) ); ?>" rel="author"><?php the_author(); ?></a></h3>
		<?php if ( $job_title ) : ?>
			<p class="rf-author-title"><?php echo esc_html( $job_title ); ?></p>
		<?php endif; ?>
		<?php if ( $expertise ) : ?>
			<p class="rf-author-expertise"><?php echo esc_html( $expertise ); ?></p>
		<?php elseif ( $description ) : ?>
			<p class="rf-author-expertise"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<?php if ( $rf_reviewer ) : ?>
			<p class="rf-author-reviewer"><?php echo esc_html( 'Reviewed by ' . $rf_reviewer ); ?></p>
		<?php endif; ?>
		<?php if ( $linkedin ) : ?>
			<a class="rf-author-linkedin" href="<?php echo esc_url( $linkedin ); ?>" rel="me noopener" target="_blank"><?php esc_html_e( 'View LinkedIn profile →', 'receiptgenpro' ); ?></a>
		<?php endif; ?>
	</div>
</div>
