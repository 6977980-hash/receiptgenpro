<?php
/**
 * Template Name: Verify a Receipt
 *
 * /verify-receipt/ (v1.2.0). The QR code on a receipt links here with the
 * receipt's details after the # (assets/js/verify-receipt.js decodes them
 * in the browser; the # part is never sent to the server).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<article id="rf-main-content" class="rf-tool-page">

	<section class="rf-category-hero">
		<div class="rf-container rf-category-hero-inner">
			<span class="rf-category-icon" aria-hidden="true">🔍</span>
			<h1>Verify a Receipt</h1>
			<p class="rf-hero-sub rf-direct-answer">
				Receipts made with <?php echo esc_html( RF_BRAND_NAME ); ?> can carry a QR code. Scan it and this page shows the details
				stored in the code. Anyone can make a code, so it is a quick reference, not proof of who issued the receipt or that it was paid.
			</p>
		</div>
	</section>

	<div class="rf-container rf-narrow">
		<section class="rf-section">
			<div class="rf-verify-box" id="rf-verify">
				<p class="rf-verify-status rf-verify-status-none" id="rf-verify-status" role="status">No receipt loaded yet. Scan the QR code on the receipt with your phone camera, or paste the link below.</p>
				<div class="rf-verify-details" id="rf-verify-details" hidden></div>
				<form class="rf-verify-paste" id="rf-verify-form">
					<label for="rf-verify-input" class="screen-reader-text">Verification link</label>
					<input type="url" id="rf-verify-input" placeholder="Paste the link from the QR code" autocomplete="off">
					<button type="submit" class="rf-btn rf-btn-primary">Check</button>
				</form>
			</div>
			<noscript><p>This check runs in your browser, so it needs JavaScript.</p></noscript>
		</section>

		<section class="rf-section">
			<h2>How to Check a Receipt</h2>
			<ol>
				<li>Point your phone camera at the QR code printed on the receipt and open the link.</li>
				<li>Compare every line on this page with the receipt: the amount, the date, the receipt number and both names.</li>
				<li>If anything differs, or if you need to be sure, ask the person or business named on the receipt to confirm it.</li>
			</ol>
			<h2>What This Proves, and What It Doesn't</h2>
			<p>The QR code holds a copy of the details that were typed when the receipt was made. It is not signed or registered anywhere, so anyone can make a new code with any details, including a code that matches an edited receipt.</p>
			<p>So a matching code does not prove who issued the receipt, that it was not changed, or that the money was paid. Only the person or business that received the payment, or a bank statement, can confirm that.</p>
			<h2>Your Privacy</h2>
			<p>The details travel in the part of the link after the # sign. Browsers never send that part to a website, so the receipt is never uploaded to us; this page reads it inside your own browser.</p>
			<h2>Add a QR Code to Your Own Receipts</h2>
			<p>Every <a href="<?php echo esc_url( home_url( '/receipt-templates/' ) ); ?>">receipt template</a> on this site adds a verification QR code by default. Your customers, tenants or employer can scan it to read the details on their phone. You can switch it off with the "Add a verification QR code" box.</p>
		</section>

		<section class="rf-section rf-faq-section">
			<h2>Receipt Verification FAQ</h2>
			<div class="rf-faq">
				<?php foreach ( rf_verify_faqs() as $rf_faq ) : ?>
					<h3><?php echo esc_html( $rf_faq[0] ); ?></h3>
					<p><?php echo esc_html( $rf_faq[1] ); ?></p>
				<?php endforeach; ?>
			</div>
		</section>
	</div>

</article>

<?php
get_footer();
