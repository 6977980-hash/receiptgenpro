<?php
/**
 * Visible breadcrumb trail — kept in exact sync with BreadcrumbList schema
 * via the shared rf_get_breadcrumbs() data source (see functions.php +
 * inc/schema.php). This 1:1 match matters: Google treats a mismatch
 * between visible breadcrumbs and schema as a structured-data violation.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$trail = rf_get_breadcrumbs();
if ( count( $trail ) < 2 ) {
	return;
}
?>
<nav class="rf-breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'receiptgenpro' ); ?>">
	<div class="rf-container">
		<ol>
			<?php foreach ( $trail as $i => $crumb ) : ?>
				<li>
					<?php if ( ! empty( $crumb['url'] ) && $i !== count( $trail ) - 1 ) : ?>
						<a href="<?php echo esc_url( $crumb['url'] ); ?>"><?php echo esc_html( $crumb['title'] ); ?></a>
					<?php else : ?>
						<span aria-current="page"><?php echo esc_html( $crumb['title'] ); ?></span>
					<?php endif; ?>
				</li>
				<?php if ( $i !== count( $trail ) - 1 ) : ?><li class="rf-crumb-sep" aria-hidden="true">/</li><?php endif; ?>
			<?php endforeach; ?>
		</ol>
	</div>
</nav>
