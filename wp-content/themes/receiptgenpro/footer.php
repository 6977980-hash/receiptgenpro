<?php
/**
 * ReceiptGenPro — footer.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
</main><!-- .rf-main -->

<footer class="rf-site-footer">
	<div class="rf-container rf-footer-grid">

		<div class="rf-footer-col rf-footer-brand">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="rf-logo"><?php echo wp_kses_post( RF_LOGO_TEXT ); ?></a>
			<p><?php echo esc_html( RF_BRAND_DESCRIPTION ); ?></p>
			<div class="rf-social-links">
				<?php if ( RF_SOCIAL_TWITTER ) : ?><a href="<?php echo esc_url( RF_SOCIAL_TWITTER ); ?>" rel="me noopener" target="_blank" aria-label="Twitter / X">𝕏</a><?php endif; ?>
				<?php if ( RF_SOCIAL_FACEBOOK ) : ?><a href="<?php echo esc_url( RF_SOCIAL_FACEBOOK ); ?>" rel="me noopener" target="_blank" aria-label="Facebook">f</a><?php endif; ?>
				<?php if ( RF_SOCIAL_LINKEDIN ) : ?><a href="<?php echo esc_url( RF_SOCIAL_LINKEDIN ); ?>" rel="me noopener" target="_blank" aria-label="LinkedIn">in</a><?php endif; ?>
			</div>
		</div>

		<div class="rf-footer-col">
			<h3><?php esc_html_e( 'Receipt Tools', 'receiptgenpro' ); ?></h3>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer-tools',
					'container'      => false,
					'menu_class'     => 'rf-footer-menu',
					'fallback_cb'    => function () {
						$slugs = array( 'rent-receipt', 'hotel-receipt', 'restaurant-receipt', 'cash-receipt', 'freelance-receipt', 'mechanic-receipt' );
						echo '<ul class="rf-footer-menu">';
						foreach ( $slugs as $slug ) {
							$cat = rf_get_category_data( $slug );
							if ( $cat ) {
								printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( '/' . $slug . '/' ) ), esc_html( rf_category_label( $cat ) ) );
							}
						}
						echo '</ul>';
					},
				)
			);
			?>
		</div>

		<div class="rf-footer-col">
			<h3><?php esc_html_e( 'More Receipts', 'receiptgenpro' ); ?></h3>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer-more',
					'container'      => false,
					'menu_class'     => 'rf-footer-menu',
					'fallback_cb'    => function () {
						$slugs = array( 'cleaning-receipt', 'donation-receipt', 'taxi-receipt', 'childcare-receipt', 'hardware-store-receipt', 'salon-spa-receipt', 'construction-receipt' );
						echo '<ul class="rf-footer-menu">';
						foreach ( $slugs as $slug ) {
							$cat = rf_get_category_data( $slug );
							if ( $cat ) {
								printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( '/' . $slug . '/' ) ), esc_html( rf_category_label( $cat ) ) );
							}
						}
						echo '</ul>';
					},
				)
			);
			?>
		</div>

		<div class="rf-footer-col">
			<h3><?php esc_html_e( 'Support', 'receiptgenpro' ); ?></h3>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer-support',
					'container'      => false,
					'menu_class'     => 'rf-footer-menu',
					'fallback_cb'    => function () {
						echo '<ul class="rf-footer-menu">';
						$links = array(
							'/receipt-templates/' => 'All Receipt Templates',
							'/blog/'             => 'Blog',
							'/about-us/'         => 'About Us',
							'/contact-us/'       => 'Contact Us',
							'/privacy-policy/'   => 'Privacy Policy',
							'/terms-and-conditions/' => 'Terms & Conditions',
							'/cookie-policy/'    => 'Cookie Policy',
						);
						if ( rf_editorial_policy_url() ) {
							$links['/editorial-policy/'] = 'Editorial Policy';
						}
						foreach ( $links as $path => $label ) {
							printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( $path ) ), esc_html( $label ) );
						}
						echo '</ul>';
					},
				)
			);
			?>
		</div>

	</div>

	<div class="rf-container rf-footer-bottom">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( RF_BRAND_NAME ); ?>. <?php esc_html_e( 'All rights reserved.', 'receiptgenpro' ); ?></p>
		<p class="rf-footer-disclaimer"><?php esc_html_e( 'Receipts generated with this tool are intended for legitimate record-keeping, invoicing and reimbursement purposes only.', 'receiptgenpro' ); ?></p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
