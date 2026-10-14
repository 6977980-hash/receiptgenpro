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
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$heading = $args['heading'] ?? 'Receipt Details';
$form    = wp_parse_args(
	$args['form'] ?? array(),
	array(
		'title'          => 'RECEIPT',
		'business_label' => 'Your Business / Name',
		'business_ph'    => 'e.g. Bright Star Cleaning Co.',
		'client_label'   => 'Received From (Customer)',
		'client_ph'      => 'e.g. John Miller',
		'item_ph'        => array( $args['item_default'] ?? 'e.g. Service or product name' ),
		'notes_ph'       => 'Thank you for your business!',
		'payment'        => 'Cash',
		'extra'          => array(),
		'tip'            => false,
		'paid'           => false,
		'words'          => false,
		'signature'      => '',
		'statement'      => array(),
	)
);
$payment_methods = array( 'Cash', 'Card', 'Bank Transfer', 'Online Payment', 'Cheque' );
?>
<section class="rf-generator" id="rf-generator">
	<div class="rf-container rf-generator-grid">

		<form class="rf-generator-form" id="rf-receipt-form" autocomplete="off"
			data-item-placeholders="<?php echo esc_attr( wp_json_encode( array_values( (array) $form['item_ph'] ) ) ); ?>"
			data-receipt-title="<?php echo esc_attr( $form['title'] ); ?>"
			data-currency="<?php echo esc_attr( $form['currency'] ?? '' ); ?>"
			data-words="<?php echo $form['words'] ? '1' : '0'; ?>"
			data-signature="<?php echo esc_attr( $form['signature'] ); ?>"
			data-business-label="<?php echo esc_attr( $form['business_label'] ); ?>"
			data-client-label="<?php echo esc_attr( $form['client_label'] ); ?>">
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

			<div class="rf-form-row rf-form-row-2col">
				<div class="rf-field">
					<label for="rf-client-name"><?php echo esc_html( $form['client_label'] ); ?></label>
					<input type="text" id="rf-client-name" placeholder="<?php echo esc_attr( $form['client_ph'] ); ?>">
				</div>
				<div class="rf-field">
					<label for="rf-receipt-number">Receipt #</label>
					<input type="text" id="rf-receipt-number" value="1001" aria-describedby="rf-receipt-number-hint">
					<p class="rf-field-hint" id="rf-receipt-number-hint">Next time, this starts after the last number you downloaded on this device.</p>
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

			<h3>Items</h3>
			<div id="rf-items-list"></div>
			<button type="button" class="rf-btn rf-btn-secondary" id="rf-add-item">+ Add Item</button>

			<div class="rf-form-row rf-form-row-2col">
				<div class="rf-field">
					<label for="rf-tax-rate">Tax Rate (%)</label>
					<input type="number" id="rf-tax-rate" value="0" min="0" step="0.01">
				</div>
				<div class="rf-field">
					<label for="rf-payment-method">Payment Method</label>
					<select id="rf-payment-method">
						<?php foreach ( $payment_methods as $method ) : ?>
							<option<?php selected( $form['payment'], $method ); ?>><?php echo esc_html( $method ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<details class="rf-more-options" id="rf-more-options">
				<summary>Discount and a second tax (e.g. CGST + SGST)</summary>
				<div class="rf-form-row rf-form-row-2col">
					<div class="rf-field">
						<label for="rf-discount">Discount</label>
						<input type="number" id="rf-discount" min="0" step="0.01" placeholder="0">
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
				</div>
				<div class="rf-form-row rf-form-row-2col">
					<div class="rf-field">
						<label for="rf-tax2-label">Second tax name</label>
						<input type="text" id="rf-tax2-label" placeholder="e.g. SGST">
					</div>
					<div class="rf-field">
						<label for="rf-tax2-rate">Second tax rate (%)</label>
						<input type="number" id="rf-tax2-rate" value="0" min="0" step="0.01">
					</div>
				</div>
				<p class="rf-field-hint">The discount comes off the subtotal before tax, and both taxes are charged on the discounted amount.</p>
			</details>

			<?php if ( $form['tip'] || $form['paid'] ) : ?>
				<div class="rf-form-row rf-form-row-2col">
					<?php if ( $form['tip'] ) : ?>
						<div class="rf-field">
							<label for="rf-tip">Tip / Gratuity (amount)</label>
							<input type="number" id="rf-tip" min="0" step="0.01" placeholder="0.00">
						</div>
					<?php endif; ?>
					<?php if ( $form['paid'] ) : ?>
						<div class="rf-field">
							<label for="rf-amount-paid">Amount Paid (leave blank if paid in full)</label>
							<input type="number" id="rf-amount-paid" min="0" step="0.01" placeholder="0.00">
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $form['statement'] ) ) : ?>
				<div class="rf-field rf-field-check">
					<label>
						<input type="checkbox" id="rf-statement" data-text="<?php echo esc_attr( $form['statement']['text'] ); ?>" <?php checked( ! empty( $form['statement']['checked'] ) ); ?>>
						<?php echo esc_html( $form['statement']['label'] ); ?>
					</label>
				</div>
			<?php endif; ?>

			<div class="rf-field">
				<label for="rf-notes">Notes (optional)</label>
				<textarea id="rf-notes" rows="2" placeholder="<?php echo esc_attr( $form['notes_ph'] ); ?>"></textarea>
			</div>
		</form>

		<div class="rf-generator-preview">
			<div id="rf-receipt-preview" class="rf-receipt-paper"></div>
			<p id="rf-total-live" class="screen-reader-text" role="status" aria-live="polite"></p>
			<div class="rf-preview-actions">
				<button type="button" class="rf-btn rf-btn-primary" id="rf-download-pdf">Download PDF</button>
				<button type="button" class="rf-btn rf-btn-secondary" id="rf-download-png">Download PNG</button>
				<button type="button" class="rf-btn rf-btn-secondary" id="rf-download-jpg">Download JPEG</button>
			</div>
			<p class="rf-privacy-note">🔒 Nothing is uploaded — your receipt is generated entirely in your browser.</p>
			<p class="rf-usage-note">For receipts you issue for your own business or property. Never create a receipt on behalf of a business you don't own, or for a payment that didn't happen — see our <a href="<?php echo esc_url( home_url( '/terms-and-conditions/' ) ); ?>">Terms</a>.</p>
		</div>

	</div>
</section>
