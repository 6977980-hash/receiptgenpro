<?php
/**
 * Template Name: Receipt Generator Widget
 *
 * /receipt-generator-widget/ (v1.2.0). Embed code for other websites; the
 * iframe loads /embed/<template>/ (inc/feature-pages.php). The credit link
 * in the code sits outside the iframe so it counts as a normal link.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$rf_templates = rf_embed_templates();
$rf_default   = 'rent-receipt';
?>

<article id="rf-main-content" class="rf-tool-page">

	<section class="rf-category-hero">
		<div class="rf-container rf-category-hero-inner">
			<span class="rf-category-icon" aria-hidden="true">🧩</span>
			<h1>Free Receipt Generator Widget</h1>
			<p class="rf-hero-sub rf-direct-answer">
				Put a working receipt generator on your own website. Pick a template, copy the code and paste it into any page:
				your visitors fill it in and download a PDF, PNG or JPEG without leaving your site. Free, no signup, no ads, and nothing they type is uploaded.
			</p>
		</div>
	</section>

	<div class="rf-container">
		<section class="rf-section">
			<div class="rf-widget-builder" id="rf-widget-builder" data-embed-base="<?php echo esc_url( home_url( '/embed/' ) ); ?>" data-site="<?php echo esc_url( home_url( '/' ) ); ?>" data-brand="<?php echo esc_attr( RF_BRAND_NAME ); ?>">
				<div>
					<h2>1. Choose a template</h2>
					<div class="rf-field">
						<label for="rf-widget-template">Template</label>
						<select id="rf-widget-template">
							<?php foreach ( $rf_templates as $rf_slug => $rf_label ) : ?>
								<option value="<?php echo esc_attr( $rf_slug ); ?>" data-page="<?php echo esc_url( 'receipt' === $rf_slug ? home_url( '/' ) : home_url( '/' . $rf_slug . '/' ) ); ?>"<?php selected( $rf_default, $rf_slug ); ?>><?php echo esc_html( $rf_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="rf-field">
						<label for="rf-widget-height">Height in pixels (used if your site blocks the auto-height script)</label>
						<input type="number" id="rf-widget-height" value="1600" min="600" max="4000" step="50">
					</div>
					<h2>2. Copy the code</h2>
					<div class="rf-field">
						<label for="rf-widget-code">Embed code</label>
						<?php
						$rf_page_url = home_url( '/' . $rf_default . '/' );
						$rf_code     = '<iframe src="' . esc_url( home_url( '/embed/' . $rf_default . '/' ) ) . '" title="' . esc_attr( $rf_templates[ $rf_default ] ) . '" width="100%" height="1600" style="border:0;width:100%;max-width:1180px" loading="lazy"></iframe>' . "\n"
							. '<p style="font-size:13px">Free <a href="' . esc_url( $rf_page_url ) . '">' . esc_html( strtolower( $rf_templates[ $rf_default ] ) ) . '</a> by ' . esc_html( RF_BRAND_NAME ) . '</p>';
						?>
						<textarea id="rf-widget-code" readonly><?php echo esc_textarea( $rf_code ); ?></textarea>
					</div>
					<button type="button" class="rf-btn rf-btn-primary" id="rf-widget-copy">Copy code</button>
					<span id="rf-widget-copied" class="rf-muted" role="status"></span>
					<h2 style="margin-top:2rem">3. Paste it into your site</h2>
					<ul>
						<li><strong>WordPress:</strong> add a "Custom HTML" block and paste the code.</li>
						<li><strong>Wix:</strong> Add → Embed Code → Embed HTML, then paste.</li>
						<li><strong>Squarespace / Webflow:</strong> add a Code or Embed block and paste.</li>
						<li><strong>Any other site:</strong> paste it into the page's HTML where the generator should appear.</li>
					</ul>
				</div>
				<div class="rf-widget-preview">
					<h2>Preview</h2>
					<iframe id="rf-widget-preview" src="<?php echo esc_url( home_url( '/embed/' . $rf_default . '/' ) ); ?>" title="Widget preview" loading="lazy"></iframe>
				</div>
			</div>
		</section>

		<section class="rf-section rf-narrow">
			<h2>Who Is the Widget For?</h2>
			<p>Property managers can let tenants print their own rent receipts, accountants and bookkeepers can offer clients a receipt maker on their resources page, and trade associations, coworking spaces and community groups can give members a free tool. Every template on <?php echo esc_html( RF_BRAND_NAME ); ?> can be embedded, from <a href="<?php echo esc_url( home_url( '/rent-receipt/' ) ); ?>">rent receipts</a> to <a href="<?php echo esc_url( home_url( '/missing-receipt-form/' ) ); ?>">missing receipt forms</a>.</p>
			<h2>Rules for Using the Widget</h2>
			<p>Keep the credit line under the widget. Don't use it to create receipts for a business you don't own or for payments that didn't happen; our <a href="<?php echo esc_url( home_url( '/terms-and-conditions/' ) ); ?>">Terms</a> apply to the widget as they do to the site.</p>
		</section>

		<section class="rf-section rf-faq-section rf-narrow">
			<h2>Widget FAQ</h2>
			<div class="rf-faq">
				<?php foreach ( rf_widget_faqs() as $rf_faq ) : ?>
					<h3><?php echo esc_html( $rf_faq[0] ); ?></h3>
					<p><?php echo esc_html( $rf_faq[1] ); ?></p>
				<?php endforeach; ?>
			</div>
		</section>
	</div>

</article>

<?php
get_footer();
