<?php
/**
 * Shared receipt generator tool markup — used on the homepage AND every
 * /xxx-receipt/ category page (see functions.php enqueue condition).
 *
 * v1.1.0 (Sept 2026 audit, C3/C4):
 *  - Accepts a per-template 'form' config (inc/category-data.php) so the
 *    rent page asks for landlord/tenant/property/period, the taxi page for
 *    pickup/drop-off/vehicle, the donation page for EIN + the goods-or-
 *    services statement, etc. The fields are rendered server-side, so they
 *    are visible in the HTML Google crawls, not injected by JS.
 *  - Adds the JPEG export button the page copy always promised.
 *
 * Optional $args:
 *   'heading'      (string)  Form heading, defaults to "Receipt Details"
 *   'item_default' (string)  Placeholder for item rows (legacy, homepage)
 *   'form'         (array)   Template config from rf_get_category_data()
 *   'purpose'      (string)  Requirement check selected by default
 *                            (inc/requirements.php), '' for none
 *   'picker'       (bool)    v1.3.0: show the receipt type picker (homepage)
 *   'embed'        (bool)    v1.4.0: inside /embed/<slug>/ (no "Remember
 *                            my business details")
 *
 * v1.2.0: requirement checker under the preview, optional verification
 * QR code, a custom number label and a second signature line.
 *
 * v1.3.0: a design picker over the preview (rf_receipt_designs()) and, on
 * the homepage, a receipt type picker that swaps the form in place. The
 * parts that differ by type sit in #rf-type-* wrappers so the JS can
 * rebuild them from the same config this file renders.
 *
 * v1.3.3: amount inputs have max="999999999999.99" and rate inputs
 * max="100" (the script applies the same limits), and #rf-form-notice
 * says when an entry was capped or counted as 0.
 *
 * v1.3.6 (audit of 25 Oct, Phase 1): an optional "Business details" group
 * under the business name on every form (address, contact, tax ID), and
 * no tax rate or discount controls on a form with 'no_tax' (donations).
 *
 * v1.3.8 (audit of 25 Oct, v2 Phase 2): a date format menu, a place of
 * supply field (GST bill mode only; the script shows it), "Prices include
 * tax" in More options, an option to leave out the "Generated with" line,
 * the check shown before downloading an empty receipt, and the total bar
 * at the bottom of the screen on phones.
 *
 * v1.4.0 (audit of 25 Oct, v2 Phase 4): "Remember my business details on
 * this device" (off by default, never inside an embed) with a "Forget
 * saved details" button, and an "Other (type a code)" currency with its
 * code and decimals fields.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$heading = $args['heading'] ?? 'Receipt Details';
$form    = rf_generator_form( $args['form'] ?? array(), $args['item_default'] ?? 'e.g. Service or product name' );
$picker  = ! empty( $args['picker'] ); // v1.3.0: homepage receipt type picker
$embed   = ! empty( $args['embed'] ); // v1.4.0: no saved profile inside someone else's page
$designs = rf_receipt_designs();
$purpose  = (string) ( $args['purpose'] ?? '' );
$purposes = rf_receipt_purposes();
$payment_methods = array( 'Cash', 'Card', 'Bank Transfer', 'Online Payment', 'Cheque' );
?>
<section class="rf-generator" id="rf-generator">
	<?php if ( $picker ) :
		$rf_types = rf_generator_types();
		?>
		<div class="rf-container rf-type-picker">
			<h2 class="rf-picker-title" id="rf-type-picker-title">1. Choose your receipt type</h2>
			<ul class="rf-type-list" aria-labelledby="rf-type-picker-title">
				<?php foreach ( $rf_types as $rf_slug => $rf_type ) : ?>
					<li>
						<a class="rf-type-option" href="<?php echo esc_url( $rf_type['url'] ? $rf_type['url'] . '#rf-generator' : '#rf-generator' ); ?>" data-type="<?php echo esc_attr( $rf_slug ); ?>"<?php echo 'general' === $rf_slug ? ' aria-current="true"' : ''; ?>>
							<span class="rf-type-icon" aria-hidden="true"><?php echo esc_html( $rf_type['icon'] ); ?></span>
							<span class="rf-type-name"><?php echo esc_html( $rf_type['label'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="rf-type-more" id="rf-type-more" hidden></p>
			<script type="application/json" id="rf-type-data"><?php echo wp_json_encode( $rf_types, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ); ?></script>
		</div>
	<?php endif; ?>
	<div class="rf-container rf-generator-grid">

		<form class="rf-generator-form" id="rf-receipt-form" autocomplete="off"
			data-item-placeholders="<?php echo esc_attr( wp_json_encode( array_values( (array) $form['item_ph'] ) ) ); ?>"
			data-receipt-title="<?php echo esc_attr( $form['title'] ); ?>"
			data-currency="<?php echo esc_attr( $form['currency'] ?? '' ); ?>"
			data-words="<?php echo $form['words'] ? '1' : '0'; ?>"
			data-signature="<?php echo esc_attr( $form['signature'] ); ?>"
			data-signature2="<?php echo esc_attr( $form['signature2'] ); ?>"
			data-number-label="<?php echo esc_attr( $form['number_label'] ); ?>"
			data-business-label="<?php echo esc_attr( $form['business_label'] ); ?>"
			data-client-label="<?php echo esc_attr( $form['client_label'] ); ?>"
			data-no-tax="<?php echo $form['no_tax'] ? '1' : '0'; ?>"
			data-in-kind="<?php echo $form['in_kind'] ? '1' : '0'; ?>">
			<h2><?php echo esc_html( $heading ); ?></h2>

			<div class="rf-form-row rf-form-row-2col">
				<div class="rf-field">
					<label for="rf-business-name"><?php echo esc_html( $form['business_label'] ); ?></label>
					<input type="text" id="rf-business-name" placeholder="<?php echo esc_attr( $form['business_ph'] ); ?>">
				</div>
				<div class="rf-field">
					<label for="rf-business-logo">Logo (optional)</label>
					<input type="file" id="rf-business-logo" accept="image/*">
					<button type="button" class="rf-logo-remove" id="rf-logo-remove" style="display:none">Remove logo</button>
				</div>
			</div>

			<?php // Part of the shared form (not of a type's extra fields), so switching type keeps it. ?>
			<details class="rf-more-options rf-business-details" id="rf-business-details">
				<summary>Business details (optional)</summary>
				<div class="rf-field">
					<label for="rf-business-address">Business address</label>
					<textarea id="rf-business-address" rows="2" placeholder="e.g. 12 High Street, Leeds LS1 4AB"></textarea>
				</div>
				<div class="rf-field">
					<label for="rf-business-contact">Phone, email or website</label>
					<input type="text" id="rf-business-contact" placeholder="e.g. 0113 496 0000 · hello@example.com">
				</div>
				<div class="rf-form-row rf-form-row-2col">
					<div class="rf-field">
						<label for="rf-tax-id-label">Tax number type</label>
						<select id="rf-tax-id-label">
							<?php foreach ( array( 'Tax ID', 'VAT No.', 'GSTIN', 'ABN', 'EIN', 'NTN', 'TRN' ) as $rf_tax_id ) : ?>
								<option><?php echo esc_html( $rf_tax_id ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="rf-field">
						<label for="rf-tax-id">Tax number</label>
						<input type="text" id="rf-tax-id" placeholder="e.g. GB123456789">
					</div>
				</div>
				<p class="rf-field-hint">Printed under your business name. VAT and GST receipts usually need your address and registration number. The tax number is never put in the QR code.</p>
			</details>

			<?php if ( ! $embed ) : ?>
				<?php // The script ticks it when a saved profile exists and shows "Forget" then. ?>
				<div class="rf-field rf-field-check rf-remember">
					<label>
						<input type="checkbox" id="rf-remember" aria-describedby="rf-remember-hint">
						Remember my business details on this device
					</label>
					<button type="button" class="rf-forget" id="rf-forget" hidden>Forget saved details</button>
					<p class="rf-field-hint" id="rf-remember-hint">Keeps the name, address, contact, tax number, logo and currency in this browser (localStorage) for your next receipt, of any type. Nothing is uploaded.</p>
					<p class="rf-field-hint rf-field-warn" id="rf-remember-note" role="status" hidden></p>
				</div>
			<?php endif; ?>

			<div class="rf-form-row rf-form-row-2col">
				<div class="rf-field">
					<label for="rf-client-name"><?php echo esc_html( $form['client_label'] ); ?></label>
					<input type="text" id="rf-client-name" placeholder="<?php echo esc_attr( $form['client_ph'] ); ?>">
				</div>
				<div class="rf-field">
					<label for="rf-receipt-number"><?php echo esc_html( $form['number_label'] ); ?></label>
					<input type="text" id="rf-receipt-number" value="1001" aria-describedby="rf-receipt-number-hint">
					<p class="rf-field-hint" id="rf-receipt-number-hint">Downloading the same receipt again keeps this number; once you change the receipt, it moves to the next one. Next time, it starts after the last number you downloaded on this device.</p>
				</div>
			</div>

			<div class="rf-form-row rf-form-row-2col">
				<div class="rf-field">
					<label for="rf-receipt-date">Date</label>
					<input type="date" id="rf-receipt-date">
				</div>
				<div class="rf-field">
					<label for="rf-currency">Currency</label>
					<select id="rf-currency"></select>
				</div>
			</div>

			<?php // "Other (type a code)…" in the currency menu shows this (the script adds that option). ?>
			<div class="rf-form-row rf-form-row-2col" id="rf-currency-other" hidden>
				<div class="rf-field">
					<label for="rf-currency-code">Currency code</label>
					<input type="text" id="rf-currency-code" maxlength="3" placeholder="e.g. CHF" autocapitalize="characters" spellcheck="false" aria-describedby="rf-currency-code-hint">
					<p class="rf-field-hint" id="rf-currency-code-hint">Three letters (ISO 4217), printed before each amount: "CHF 1,000.00".</p>
				</div>
				<div class="rf-field">
					<label for="rf-currency-dp">Decimals</label>
					<select id="rf-currency-dp">
						<option value="2">2 (1,000.00)</option>
						<option value="0">0 (1,000)</option>
						<option value="3">3 (1,000.000)</option>
					</select>
				</div>
			</div>

			<div class="rf-form-row rf-form-row-2col">
				<div class="rf-field">
					<label for="rf-date-format">Date format</label>
					<select id="rf-date-format">
						<option value="">Automatic (follows the currency)</option>
						<option value="long">25 Oct 2026</option>
						<option value="mdy">10/25/2026 (month first)</option>
						<option value="dmy">25/10/2026 (day first)</option>
						<option value="iso">2026-10-25</option>
					</select>
				</div>
				<?php // GST bill mode only (INR with tax, or a GSTIN); the script shows it. ?>
				<div class="rf-field" id="rf-place-of-supply-field" hidden>
					<label for="rf-place-of-supply">Place of supply (optional)</label>
					<input type="text" id="rf-place-of-supply" placeholder="e.g. Maharashtra (27)" aria-describedby="rf-gst-hint">
					<p class="rf-field-hint" id="rf-gst-hint">GST bill: add the state here and an HSN/SAC code under each item if you like. A receipt from this tool is not a GST tax invoice.</p>
				</div>
			</div>

			<div id="rf-type-extra">
			<?php if ( ! empty( $form['extra'] ) ) : ?>
				<fieldset class="rf-template-fields">
					<legend><?php echo esc_html( ucwords( strtolower( $form['title'] ) ) ); ?> Details</legend>
					<div class="rf-form-row rf-form-row-2col">
						<?php foreach ( $form['extra'] as $field ) :
							$fid   = 'rf-x-' . sanitize_key( $field['id'] );
							$type  = $field['type'] ?? 'text';
							$label = $field['label'];
							?>
							<div class="rf-field">
								<label for="<?php echo esc_attr( $fid ); ?>"><?php echo esc_html( $label ); ?></label>
								<?php if ( 'select' === $type ) : ?>
									<select id="<?php echo esc_attr( $fid ); ?>" data-rf-extra data-label="<?php echo esc_attr( $label ); ?>">
										<?php foreach ( (array) ( $field['options'] ?? array() ) as $opt ) : ?>
											<option><?php echo esc_html( $opt ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php elseif ( 'textarea' === $type ) : ?>
									<textarea id="<?php echo esc_attr( $fid ); ?>" rows="2" data-rf-extra data-label="<?php echo esc_attr( $label ); ?>" placeholder="<?php echo esc_attr( $field['ph'] ?? '' ); ?>"></textarea>
								<?php else : ?>
									<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $fid ); ?>" data-rf-extra data-type="<?php echo esc_attr( $type ); ?>" data-label="<?php echo esc_attr( $label ); ?>" placeholder="<?php echo esc_attr( $field['ph'] ?? '' ); ?>">
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</fieldset>
			<?php endif; ?>
			</div>

			<h3>Items</h3>
			<div id="rf-items-list"></div>
			<button type="button" class="rf-btn rf-btn-secondary" id="rf-add-item">+ Add Item</button>

			<div class="rf-form-row rf-form-row-2col">
				<?php // The homepage picker hides it for a no-tax type; a no-tax page never has it. ?>
				<?php if ( ! $form['no_tax'] || $picker ) : ?>
					<div class="rf-field" id="rf-tax-rate-field"<?php echo $form['no_tax'] ? ' hidden' : ''; ?>>
						<label for="rf-tax-rate">Tax Rate (%)</label>
						<input type="number" id="rf-tax-rate" value="0" min="0" max="100" step="0.01">
					</div>
				<?php endif; ?>
				<div class="rf-field">
					<label for="rf-payment-method">Payment Method</label>
					<select id="rf-payment-method">
						<?php foreach ( $payment_methods as $method ) : ?>
							<option<?php selected( $form['payment'], $method ); ?>><?php echo esc_html( $method ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<p class="rf-form-notice" id="rf-form-notice" role="status" hidden></p>

			<?php if ( ! $form['no_tax'] || $picker ) : ?>
			<details class="rf-more-options" id="rf-more-options"<?php echo $form['no_tax'] ? ' hidden' : ''; ?>>
				<summary>Discount, a second tax (e.g. CGST + SGST), prices with tax included</summary>
				<div class="rf-form-row rf-form-row-2col">
					<div class="rf-field">
						<label for="rf-discount">Discount</label>
						<input type="number" id="rf-discount" min="0" max="999999999999.99" step="0.01" placeholder="0">
					</div>
					<div class="rf-field">
						<label for="rf-discount-type">Discount type</label>
						<select id="rf-discount-type">
							<option value="percent">% of subtotal</option>
							<option value="amount">Fixed amount</option>
						</select>
					</div>
				</div>
				<div class="rf-form-row rf-form-row-2col">
					<div class="rf-field">
						<label for="rf-tax-label">Name of the first tax</label>
						<input type="text" id="rf-tax-label" placeholder="Tax (e.g. CGST, VAT, GST)">
					</div>
					<div class="rf-field rf-field-check rf-field-check-inline">
						<label>
							<input type="checkbox" id="rf-tax-inclusive" aria-describedby="rf-tax-inclusive-hint">
							Prices include tax
						</label>
						<p class="rf-field-hint" id="rf-tax-inclusive-hint">For prices quoted with VAT or GST in them: the tax is worked out of the total, not added on top.</p>
					</div>
				</div>
				<div class="rf-form-row rf-form-row-2col">
					<div class="rf-field">
						<label for="rf-tax2-label">Second tax name</label>
						<input type="text" id="rf-tax2-label" placeholder="e.g. SGST">
					</div>
					<div class="rf-field">
						<label for="rf-tax2-rate">Second tax rate (%)</label>
						<input type="number" id="rf-tax2-rate" value="0" min="0" max="100" step="0.01">
					</div>
				</div>
				<p class="rf-field-hint">The discount comes off the subtotal before tax, and both taxes are charged on (or, with prices that include tax, worked out of) the discounted amount.</p>
			</details>
			<?php endif; ?>

			<div id="rf-type-tippaid">
			<?php if ( $form['tip'] || $form['paid'] ) : ?>
				<div class="rf-form-row rf-form-row-2col">
					<?php if ( $form['tip'] ) : ?>
						<div class="rf-field">
							<label for="rf-tip">Tip / Gratuity (amount)</label>
							<input type="number" id="rf-tip" min="0" max="999999999999.99" step="0.01" placeholder="0.00">
						</div>
					<?php endif; ?>
					<?php if ( $form['paid'] ) : ?>
						<div class="rf-field">
							<label for="rf-amount-paid">Amount Paid (leave blank if paid in full)</label>
							<input type="number" id="rf-amount-paid" min="0" max="999999999999.99" step="0.01" placeholder="0.00">
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			</div>

			<div id="rf-type-statement">
			<?php if ( ! empty( $form['statement'] ) ) : ?>
				<div class="rf-field rf-field-check">
					<label>
						<input type="checkbox" id="rf-statement" data-text="<?php echo esc_attr( $form['statement']['text'] ); ?>" <?php checked( ! empty( $form['statement']['checked'] ) ); ?>>
						<?php echo esc_html( $form['statement']['label'] ); ?>
					</label>
				</div>
			<?php endif; ?>
			</div>

			<div class="rf-field">
				<label for="rf-notes">Notes (optional)</label>
				<textarea id="rf-notes" rows="2" placeholder="<?php echo esc_attr( $form['notes_ph'] ); ?>"></textarea>
			</div>

			<div class="rf-field rf-field-check">
				<label>
					<input type="checkbox" id="rf-qr" checked aria-describedby="rf-qr-hint">
					Add a verification QR code
				</label>
				<p class="rf-field-hint" id="rf-qr-hint">Scanning it shows the receipt's details on a phone. It is a convenience, not proof the receipt is genuine. <a href="<?php echo esc_url( home_url( '/verify-receipt/' ) ); ?>">How it works</a></p>
			</div>

			<div class="rf-field rf-field-check">
				<label>
					<input type="checkbox" id="rf-show-footer" checked>
					Show "Generated with <?php echo esc_html( RF_BRAND_NAME ); ?>" at the bottom
				</label>
			</div>
		</form>

		<div class="rf-generator-preview">
			<fieldset class="rf-design-picker" id="rf-design-picker">
				<legend><?php echo $picker ? '2. Choose a design' : 'Choose a design'; ?></legend>
				<div class="rf-design-list">
					<?php foreach ( $designs as $rf_key => $rf_name ) : ?>
						<label class="rf-design-option rf-design-swatch-<?php echo esc_attr( $rf_key ); ?>">
							<input type="radio" name="rf-design" value="<?php echo esc_attr( $rf_key ); ?>"<?php checked( 'classic', $rf_key ); ?>>
							<span class="rf-design-swatch" aria-hidden="true"><i></i><i></i><i></i></span>
							<span class="rf-design-name"><?php echo esc_html( $rf_name ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
			<div id="rf-receipt-preview" class="rf-receipt-paper rf-design-classic"></div>
			<p id="rf-total-live" class="screen-reader-text" role="status" aria-live="polite"></p>
			<?php // Shown by the script before downloading a receipt with no business name or a zero total. ?>
			<div class="rf-export-check" id="rf-export-check" role="alert" tabindex="-1" hidden>
				<p id="rf-export-check-text"></p>
				<div class="rf-export-check-actions">
					<button type="button" class="rf-btn rf-btn-primary" id="rf-export-anyway">Download anyway</button>
					<button type="button" class="rf-btn rf-btn-secondary" id="rf-export-back">Go back</button>
				</div>
			</div>
			<?php // v1.3.9: kept with the form in sessionStorage; "Automatic" is Letter for USD and CAD, A4 for the rest. ?>
			<div class="rf-paper-size">
				<label for="rf-paper-size">PDF paper size</label>
				<select id="rf-paper-size" aria-describedby="rf-paper-size-hint">
					<option value="">Automatic (by currency)</option>
					<option value="letter">US Letter</option>
					<option value="a4">A4</option>
				</select>
				<p class="rf-field-hint" id="rf-paper-size-hint">Automatic is US Letter for USD and CAD, A4 for other currencies.</p>
			</div>
			<div class="rf-preview-actions">
				<button type="button" class="rf-btn rf-btn-primary" id="rf-download-pdf">Download PDF</button>
				<button type="button" class="rf-btn rf-btn-secondary" id="rf-download-png">Download PNG</button>
				<button type="button" class="rf-btn rf-btn-secondary" id="rf-download-jpg">Download JPEG</button>
			</div>
			<p class="rf-privacy-note">🔒 Nothing is uploaded — your receipt is generated entirely in your browser.</p>
			<div class="rf-checker" id="rf-checker">
				<label for="rf-purpose" class="rf-checker-label">Check this receipt for</label>
				<select id="rf-purpose">
					<option value="">— Choose what it's for —</option>
					<?php foreach ( $purposes as $rf_key => $rf_p ) : ?>
						<option value="<?php echo esc_attr( $rf_key ); ?>"<?php selected( $purpose, $rf_key ); ?>><?php echo esc_html( $rf_p['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<div id="rf-checker-result" class="rf-checker-result"></div>
				<p id="rf-checker-status" class="screen-reader-text" role="status"></p>
				<p class="rf-checker-more"><a href="<?php echo esc_url( home_url( '/receipt-requirements/' ) ); ?>">What each check is based on</a></p>
			</div>
			<?php // Phones: the total and a shortcut to the preview while the form fills the screen. ?>
			<div class="rf-sticky-bar" id="rf-sticky-bar" hidden>
				<span class="rf-sticky-total" id="rf-sticky-total" aria-hidden="true"></span>
				<a class="rf-btn rf-btn-primary" href="#rf-receipt-preview" id="rf-sticky-go">Preview &amp; download</a>
			</div>
			<p class="rf-usage-note">For receipts you issue for your own business or property. Never create a receipt on behalf of a business you don't own, or for a payment that didn't happen — see our <a href="<?php echo esc_url( home_url( '/terms-and-conditions/' ) ); ?>">Terms</a>.</p>
		</div>

	</div>
</section>
