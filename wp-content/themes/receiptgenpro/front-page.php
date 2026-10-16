<?php
/**
 * ReceiptGenPro — front-page.php
 *
 * The ONLY page on the site that targets "free online receipt generator",
 * "free receipt maker", "online receipt generator no signup" and
 * "receipt generator online". No other template repeats this H1 or these
 * title tags — that single rule is what prevents the keyword-
 * cannibalization problem found in the competitor audit.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<article id="rf-main-content">

	<!-- =========================================================
	     HERO + LIVE TOOL
	     ========================================================= -->
	<section class="rf-hero">
		<div class="rf-container rf-hero-grid">
			<div class="rf-hero-copy">
				<h1>Free Online Receipt Generator — Create &amp; Download Receipts in Seconds</h1>
				<p class="rf-hero-sub">
					Make a professional, itemized receipt online free — no signup, no watermark, no credit card.
					Fill in the form, preview it instantly, and download as <strong>PDF, PNG or JPEG</strong>.
					Everything runs in your browser, so your customer and payment data is never uploaded to a server.
				</p>
				<ul class="rf-trust-bar">
					<li>✅ 100% free, forever</li>
					<li>✅ No signup or account</li>
					<li>✅ No watermark</li>
					<li>✅ Works on mobile</li>
					<li>✅ Your data stays on your device</li>
				</ul>
				<a href="#rf-generator" class="rf-btn rf-btn-primary">Create Your Receipt Now ↓</a>
			</div>
		</div>
	</section>

	<!-- =========================================================
	     THE ACTUAL TOOL (client-side, see assets/js/receipt-generator.js)
	     ========================================================= -->
	<?php get_template_part( 'template-parts/generator-embed', null, array( 'heading' => 'Receipt Details', 'item_default' => 'e.g. Service or product name', 'picker' => true ) ); ?>

	<!-- =========================================================
	     HOW IT WORKS — also parsed into HowTo schema
	     ========================================================= -->
	<section class="rf-section rf-howto">
		<div class="rf-container">
			<h2>How to Use the Receipt Generator</h2>
			<ol class="rf-howto-steps">
				<li><strong>Enter your business details.</strong> Add your business name and, if you like, upload your logo so the receipt looks branded and professional.</li>
				<li><strong>Add the customer and items.</strong> Type in who the receipt is for, then add each item or service with its price — the total, tax and balance update live as you type.</li>
				<li><strong>Choose your currency and payment method.</strong> Pick from common currencies and mark how the customer paid (cash, card, bank transfer, etc.).</li>
				<li><strong>Preview instantly.</strong> Your receipt renders on the right side of the screen in real time, exactly as it will look when downloaded.</li>
				<li><strong>Download as PDF, PNG or JPEG.</strong> One click generates a print-ready file. Nothing is saved on our servers — you keep full control of the file.</li>
			</ol>
		</div>
	</section>

	<!-- =========================================================
	     WHAT IS A RECEIPT GENERATOR — AEO direct-answer block
	     ========================================================= -->
	<section class="rf-section rf-definition">
		<div class="rf-container rf-narrow">
			<h2>What Is a Receipt Generator?</h2>
			<p class="rf-direct-answer">
				A receipt generator is an online tool that lets you create a proof-of-payment document —
				a receipt — without design software or accounting experience. You type in the payer,
				the amount, and what was purchased, and the tool formats it into a professional,
				downloadable receipt. <?php echo esc_html( RF_BRAND_NAME ); ?>'s receipt generator is
				free, requires no account, and processes everything locally in your browser rather than
				on a remote server.
			</p>
			<p>
				Receipts matter for more than just handing something to a customer: they're the backbone of
				clean bookkeeping, they protect both sides of a transaction if a dispute comes up, and in
				most countries they're what you'll be asked to produce if your business is ever audited.
				A good receipt generator makes it painless to produce that paper trail every single time —
				even for a five-minute cash sale.
			</p>
		</div>
	</section>

	<!-- =========================================================
	     WHY CHOOSE US
	     ========================================================= -->
	<section class="rf-section rf-why-us">
		<div class="rf-container">
			<h2>Why Choose <?php echo esc_html( RF_BRAND_NAME ); ?>?</h2>
			<div class="rf-feature-grid">
				<div class="rf-feature-card">
					<span class="rf-feature-icon" aria-hidden="true">⚡</span>
					<h3>100% Free</h3>
					<p>No trial period, no "5 free receipts then pay," no watermark stamped across your download. The core tool is free permanently.</p>
				</div>
				<div class="rf-feature-card">
					<span class="rf-feature-icon" aria-hidden="true">🔒</span>
					<h3>Privacy by Design</h3>
					<p>Your receipt is built and rendered in your own browser. We never see, store or transmit your customer names, amounts or business details.</p>
				</div>
				<div class="rf-feature-card">
					<span class="rf-feature-icon" aria-hidden="true">🌍</span>
					<h3>Multiple Currencies</h3>
					<p>Bill in US Dollars, Euros, British Pounds, Pakistani Rupees, Indian Rupees and more — the right currency symbol, every time.</p>
				</div>
				<div class="rf-feature-card">
					<span class="rf-feature-icon" aria-hidden="true">📄</span>
					<h3>Three Export Formats</h3>
					<p>Download as a print-ready PDF, a clean PNG for emailing, or a JPEG for messaging apps like WhatsApp.</p>
				</div>
				<div class="rf-feature-card">
					<span class="rf-feature-icon" aria-hidden="true">🧾</span>
					<h3>Built for Real Use Cases</h3>
					<p>Dedicated templates for rent, freelance work, auto repair, hotels, donations and more — each with its own fields (tenant and rent period, pickup and drop-off, EIN and donor statement), not one generic form.</p>
				</div>
				<div class="rf-feature-card">
					<span class="rf-feature-icon" aria-hidden="true">📱</span>
					<h3>Works on Any Device</h3>
					<p>No app to install. Create a receipt from your phone at the counter or from your laptop at your desk.</p>
				</div>
			</div>
		</div>
	</section>

	<!-- =========================================================
	     USE CASES — internal links into every category page
	     ========================================================= -->
	<section class="rf-section rf-templates" id="templates">
		<div class="rf-container">
			<h2>Popular Receipt Templates</h2>
			<p>Every business is different — that's why each receipt type below has its own page and its own form fields. <a href="<?php echo esc_url( home_url( '/receipt-templates/' ) ); ?>">Compare all receipt templates →</a></p>
			<div class="rf-template-grid">
				<?php foreach ( rf_receipt_categories() as $slug => $cat ) : ?>
					<a href="<?php echo esc_url( home_url( '/' . $slug . '/' ) ); ?>" class="rf-template-card">
						<span class="rf-template-icon" aria-hidden="true"><?php echo esc_html( $cat['icon'] ); ?></span>
						<span class="rf-template-name"><?php echo esc_html( rf_category_label( $cat ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- =========================================================
	     WHO USES THIS
	     ========================================================= -->
	<section class="rf-section rf-who-uses">
		<div class="rf-container">
			<h2>Who Uses Our Receipt Generator?</h2>
			<div class="rf-usecase-grid">
				<div class="rf-usecase"><h3>Freelancers &amp; Contractors</h3><p>Send a clean payment receipt after every project or milestone without opening a full invoicing platform.</p></div>
				<div class="rf-usecase"><h3>Landlords</h3><p>Issue a rent receipt every month, with the rent period, property and balance due — some jurisdictions require one for cash rent, and tenants often need it as proof of payment.</p></div>
				<div class="rf-usecase"><h3>Small Retailers &amp; Shops</h3><p>Print or send a proper itemized receipt even without a point-of-sale system.</p></div>
				<div class="rf-usecase"><h3>Service Providers</h3><p>Mechanics, cleaners, salons and contractors can issue a receipt on the spot from a phone.</p></div>
				<div class="rf-usecase"><h3>Nonprofits</h3><p>Give donors a written acknowledgment with your EIN and the goods-or-services statement they need for gifts of $250 or more.</p></div>
				<div class="rf-usecase"><h3>Online Sellers</h3><p>Etsy, eBay and marketplace sellers who need a receipt their platform doesn't generate for them.</p></div>
			</div>
		</div>
	</section>

	<!-- =========================================================
	     FAQ — parsed into FAQPage schema automatically
	     ========================================================= -->
	<section class="rf-section rf-faq-section">
		<div class="rf-container rf-narrow">
			<h2>Frequently Asked Questions</h2>
			<div class="rf-faq">
				<?php foreach ( rf_home_faqs() as $rf_faq ) : ?>
					<h3><?php echo esc_html( $rf_faq[0] ); ?></h3>
					<p><?php echo wp_kses_post( $rf_faq[1] ); ?></p>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- =========================================================
	     LATEST FROM THE BLOG — pulls new posts in automatically, and
	     gives the homepage a standing, always-fresh link into the blog
	     (on top of the two contextual FAQ links above) so crawlers and
	     visitors always have a path from "/" into new content.
	     ========================================================= -->
	<?php
	$rf_latest_posts = new WP_Query(
		array(
			'posts_per_page'      => 3,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
	if ( $rf_latest_posts->have_posts() ) :
		?>
		<section class="rf-section rf-latest-blog">
			<div class="rf-container">
				<h2>Latest From Our Blog</h2>
				<p>Practical, country-specific guides on receipts, record-keeping and running a small business.</p>
				<div class="rf-blog-grid rf-latest-blog-grid">
					<?php
					while ( $rf_latest_posts->have_posts() ) :
						$rf_latest_posts->the_post();
						?>
						<a href="<?php the_permalink(); ?>" class="rf-blog-card">
							<?php if ( has_post_thumbnail() ) : ?>
								<div class="rf-blog-card-thumb"><?php the_post_thumbnail( 'rf-card' ); ?></div>
							<?php endif; ?>
							<div class="rf-blog-card-body">
								<?php
								$rf_cats = get_the_category();
								if ( $rf_cats ) :
									?>
									<span class="rf-post-category"><?php echo esc_html( $rf_cats[0]->name ); ?></span>
								<?php endif; ?>
								<h3><?php the_title(); ?></h3>
								<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18 ) ); ?></p>
							</div>
						</a>
						<?php
					endwhile;
					wp_reset_postdata();
					?>
				</div>
				<p class="rf-text-center" style="margin-top:1.5rem;">
					<a href="<?php echo esc_url( get_permalink( get_option( 'page_for_posts' ) ) ?: home_url( '/blog/' ) ); ?>" class="rf-btn rf-btn-secondary">View All Articles</a>
				</p>
			</div>
		</section>
		<?php
	endif;
	?>

	<!-- =========================================================
	     FINAL CTA
	     ========================================================= -->
	<section class="rf-section rf-final-cta">
		<div class="rf-container rf-narrow rf-text-center">
			<h2>Ready to Create a Receipt?</h2>
			<p>No signup, no watermark, no catch — just a clean, professional receipt in under a minute.</p>
			<a href="#rf-generator" class="rf-btn rf-btn-primary">Create Your Receipt Now</a>
		</div>
	</section>

</article>

<?php get_footer(); ?>
