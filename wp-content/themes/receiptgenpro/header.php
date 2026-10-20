<?php
/**
 * ReceiptGenPro — header.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="google-site-verification" content="Luens6h079hldVCHg7m22YIj9p7xiF6ehZ8ns3zhhw8" />
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="rf-skip-link screen-reader-text" href="#rf-main"><?php esc_html_e( 'Skip to content', 'receiptgenpro' ); ?></a>

<header class="rf-site-header">
	<div class="rf-container rf-header-inner">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="rf-logo" rel="home">
			<?php echo wp_kses_post( RF_LOGO_TEXT ); ?>
		</a>

		<nav class="rf-primary-nav" aria-label="<?php esc_attr_e( 'Primary', 'receiptgenpro' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'rf-nav-menu',
					'fallback_cb'    => 'rf_default_primary_menu',
				)
			);
			?>
		</nav>

		<button class="rf-nav-toggle" id="rf-nav-toggle" aria-expanded="false" aria-controls="rf-primary-nav-mobile">
			<span></span><span></span><span></span>
			<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'receiptgenpro' ); ?></span>
		</button>
	</div>

	<div class="rf-nav-mobile" id="rf-primary-nav-mobile">
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'rf-nav-menu-mobile',
				'fallback_cb'    => 'rf_default_primary_menu',
			)
		);
		?>
	</div>
</header>

<?php if ( ! is_front_page() ) : ?>
	<?php get_template_part( 'template-parts/breadcrumbs' ); ?>
<?php endif; ?>

<main id="rf-main" class="rf-main">
<?php
/**
 * Fallback menu so the theme looks correct even before the site owner
 * has built a Primary Menu in wp-admin.
 *
 * wp_nav_menu() passes its own $args, so the desktop call gets the
 * horizontal `rf-nav-menu` list and the mobile call gets the vertical
 * `rf-nav-menu-mobile` list (it used to print `rf-nav-menu` for both,
 * which made the open mobile menu one wide row off the side of a phone).
 */
function rf_default_primary_menu( $args = array() ) {
	$args       = (array) $args;
	$menu_class = ! empty( $args['menu_class'] ) ? $args['menu_class'] : 'rf-nav-menu';

	$items = array(
		home_url( '/' )                 => 'Receipt Generator',
		home_url( '/receipt-templates/' ) => 'Templates',
		rf_blog_url() => 'Blog',
		home_url( '/about-us/' )        => 'About Us',
		home_url( '/contact-us/' )      => 'Contact',
	);
	$html = '<ul class="' . esc_attr( $menu_class ) . '">';
	foreach ( $items as $url => $label ) {
		$html .= sprintf( '<li><a href="%s">%s</a></li>', esc_url( $url ), esc_html( $label ) );
	}
	$html .= '</ul>';

	if ( isset( $args['echo'] ) && ! $args['echo'] ) {
		return $html;
	}
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
}
