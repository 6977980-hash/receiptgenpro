<?php
/**
 * /embed/<template>/ (v1.2.0) — the receipt generator on its own, for other
 * websites to show in an iframe (see /receipt-generator-widget/).
 *
 * Deliberately NOT using wp_head()/wp_footer(): no ads, consent banner,
 * analytics or other plugin output inside someone else's page. Loaded by
 * rf_render_embed() in inc/feature-pages.php.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rf_slug  = get_query_var( 'rf_embed' );
$rf_cat   = 'receipt' === $rf_slug ? null : rf_get_category_data( $rf_slug );
$rf_label = $rf_cat ? rf_category_label( $rf_cat ) : 'Receipt Generator';
$rf_page  = $rf_cat ? home_url( '/' . $rf_slug . '/' ) : home_url( '/' );
$rf_theme = get_template_directory_uri();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $rf_label . ' — ' . RF_BRAND_NAME ); ?></title>
<meta name="robots" content="noindex, follow">
<base target="_blank">
<link rel="stylesheet" href="<?php echo esc_url( $rf_theme . '/assets/css/main.css?ver=' . rf_asset_ver( 'assets/css/main.css' ) ); ?>">
</head>
<body class="rf-embed-body">
<?php
get_template_part(
	'template-parts/generator-embed',
	null,
	array(
		'heading'      => $rf_label,
		'form'         => $rf_cat['form'] ?? array(),
		'purpose'      => $rf_cat['purpose'] ?? '',
		'item_default' => 'e.g. Service or product name',
	)
);
?>
<p class="rf-embed-credit">Free <a href="<?php echo esc_url( $rf_page ); ?>"><?php echo esc_html( strtolower( $rf_label ) ); ?></a> by <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( RF_BRAND_NAME ); ?></a></p>
<script>window.rfReceipt = <?php echo wp_json_encode( rf_receipt_script_data(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE ); ?>;</script>
<script src="<?php echo esc_url( $rf_theme . '/assets/js/receipt-generator.js?ver=' . rf_asset_ver( 'assets/js/receipt-generator.js' ) ); ?>"></script>
<script>
// Tell the page that embeds us how tall we are, so its iframe can fit (optional script in the embed code).
( function () {
	if ( window.parent === window || ! window.ResizeObserver ) { return; }
	var last = 0;
	new ResizeObserver( function () {
		var h = Math.ceil( document.body.getBoundingClientRect().height );
		if ( h !== last ) { last = h; window.parent.postMessage( { rgpHeight: h }, '*' ); }
	} ).observe( document.body );
} )();
</script>
</body>
</html>
