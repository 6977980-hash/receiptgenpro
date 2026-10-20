<?php
/**
 * ReceiptGenPro — Receipt requirement checks (v1.2.0)
 *
 * SINGLE SOURCE for what a receipt needs for a given purpose (an HRA claim,
 * a dependent-care FSA claim, a $250+ donation, an expense report…). Used
 * by:
 *  - the live checker under every generator (receipt-generator.js reads
 *    this list from rfReceipt.purposes and ticks each line as you type);
 *  - the /receipt-requirements/ page, which prints the same lists as
 *    crawlable tables with their official sources.
 *
 * Check keys:
 *   label     What the receipt needs, in plain words.
 *   field     What the checker looks at:
 *               business | client | date | items | total | words |
 *               signature | statement | x:<extra field id>
 *             An array means "any one of these".
 *   all       true → every field in the 'field' array is needed.
 *   manual    true → something the person does on paper (sign, stamp);
 *             shown as a reminder, never ticked.
 *   optional  true → recommended, not required; a gap is a hint, not a warning.
 *   when      Only applies if: 'total_over' => amount (receipt currency),
 *             'cash' => true (payment method is Cash).
 *   note      Shown under the line on the requirements page.
 *
 * 'symbol' is the currency the purpose's amounts are in (default '$'), and
 * 'currency' its code (default 'USD'): a 'total_over' limit is only checked
 * on a receipt in that currency (v1.3.3), otherwise the visitor is told to
 * check it. 'per_month' => true in 'when' compares the amount per month
 * (rent receipts covering a From–To period) with the limit.
 *
 * 'sources' are array( label, url ); url may be '' when the official page
 * could not be checked (it is then printed without a link).
 *
 * Every rule must be traceable to the 'source' of its purpose. Do not add
 * a rule from memory.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function rf_receipt_purposes() {
	return array(

		'hra'       => array(
			'label'    => 'An HRA claim (India)',
			'heading'  => 'Rent receipt for an HRA claim (India)',
			'template' => 'hra-rent-receipt',
			'symbol'   => '₹',
			'currency' => 'INR',
			'intro'    => 'Employers ask salaried tenants for monthly rent receipts before they allow the House Rent Allowance exemption. The landlord PAN is mandatory once the rent paid in the year is more than ₹1,00,000.',
			'sources'  => array(
				array( 'CBDT Circular No. 8/2013 of 10 October 2013 (landlord PAN when rent is above ₹1 lakh a year)', '' ),
				array( 'Income-tax Act, 1961, section 10(13A) (HRA exemption)', '' ),
			),
			'checks'   => array(
				array( 'label' => 'Tenant name (the employee)', 'field' => 'client' ),
				array( 'label' => 'Landlord name', 'field' => 'business' ),
				array( 'label' => 'Address of the rented property', 'field' => 'x:property' ),
				array( 'label' => 'Rent month or rent period', 'field' => array( 'x:rent_month', 'x:period_from' ) ),
				array( 'label' => 'Rent amount', 'field' => 'total' ),
				array( 'label' => 'Amount in words', 'field' => 'words', 'optional' => true ),
				array( 'label' => 'Date the rent was received', 'field' => 'date' ),
				array(
					'label' => 'Landlord PAN (rent above ₹1,00,000 a year, i.e. above ₹8,333 a month)',
					'field' => 'x:landlord_pan',
					'when'  => array( 'total_over' => 8333, 'per_month' => true ),
					'note'  => 'Needed once the year\'s rent is above ₹1 lakh. If the landlord has no PAN, employers usually ask for a signed declaration from the landlord instead.',
				),
				array( 'label' => 'Landlord signature', 'field' => 'signature', 'manual' => true ),
				array(
					'label'  => 'Revenue stamp when rent above ₹5,000 is paid in cash',
					'manual' => true,
					'when'   => array( 'total_over' => 5000, 'cash' => true ),
					'note'   => 'A revenue stamp is commonly required on receipts for cash payments above ₹5,000. For rent paid by bank transfer or UPI many employers don\'t ask for one, so follow your employer\'s instructions.',
				),
			),
		),

		'rent-us'   => array(
			'label'    => 'A landlord\'s rent receipt (US)',
			'heading'  => 'Rent receipt from a landlord (US)',
			'template' => 'rent-receipt',
			'intro'    => 'Several US states require landlords to give a written receipt for rent, usually for cash payments. New York\'s law is the most detailed, so these checks follow it; see our state-by-state rent receipt laws for your state.',
			'sources'  => array(
				array( 'New York Real Property Law §235-e', 'https://www.nysenate.gov/legislation/laws/RPP/235-E' ),
			),
			'checks'   => array(
				array( 'label' => 'Date the rent was received', 'field' => 'date' ),
				array( 'label' => 'Amount received', 'field' => 'total' ),
				array( 'label' => 'Rent period the payment covers', 'field' => array( 'x:period_from', 'x:rent_month' ) ),
				array( 'label' => 'Address of the rented premises (and apartment number)', 'field' => 'x:property' ),
				array( 'label' => 'Landlord or agent name', 'field' => 'business' ),
				array( 'label' => 'Tenant name', 'field' => 'client', 'optional' => true ),
				array( 'label' => 'Signature of the person who received the payment, with their title', 'field' => 'signature', 'manual' => true ),
			),
		),

		'childcare' => array(
			'label'    => 'A dependent care FSA or child care credit claim (US)',
			'heading'  => 'Childcare receipt for a dependent care FSA or the child care credit (US)',
			'template' => 'childcare-receipt',
			'intro'    => 'To claim the child and dependent care credit, parents must give the IRS each care provider\'s name, address and taxpayer identification number on Form 2441. FSA administrators also ask for the dates of care and the dependent\'s name.',
			'sources'  => array(
				array( 'IRS Publication 503, Child and Dependent Care Expenses', 'https://www.irs.gov/publications/p503' ),
				array( 'IRS Form 2441 instructions', 'https://www.irs.gov/instructions/i2441' ),
			),
			'checks'   => array(
				array( 'label' => 'Care provider name', 'field' => 'business' ),
				array( 'label' => 'Care provider address', 'field' => 'x:provider_address' ),
				array( 'label' => 'Provider taxpayer ID (EIN or SSN)', 'field' => 'x:provider_tax_id', 'note' => 'Required on Form 2441. A tax-exempt provider such as a church-run daycare can write "Tax-Exempt" instead.' ),
				array( 'label' => 'Name of the child or dependent', 'field' => 'x:child' ),
				array( 'label' => 'Dates the care was provided', 'field' => array( 'x:care_from', 'x:care_to' ), 'all' => true ),
				array( 'label' => 'Amount paid', 'field' => 'total' ),
				array( 'label' => 'Parent or guardian who paid', 'field' => 'client' ),
				array( 'label' => 'Provider signature (many FSA plans ask for it)', 'field' => 'signature', 'manual' => true ),
			),
		),

		'donation'  => array(
			'label'    => 'A donor\'s $250+ tax deduction (US)',
			'heading'  => 'Donation receipt for a gift of $250 or more (US)',
			'template' => 'donation-receipt',
			'intro'    => 'A donor cannot deduct a single gift of $250 or more without a contemporaneous written acknowledgment from the charity. The IRS lists exactly what that acknowledgment must say.',
			'sources'  => array(
				array( 'IRS Publication 1771, Charitable Contributions: Substantiation and Disclosure Requirements', 'https://www.irs.gov/pub/irs-pdf/p1771.pdf' ),
			),
			'checks'   => array(
				array( 'label' => 'Name of the organization', 'field' => 'business' ),
				array( 'label' => 'Amount of a cash gift, or a description (no value) of a non-cash gift', 'field' => 'items' ),
				array( 'label' => 'Whether any goods or services were given in return (the statement)', 'field' => 'statement' ),
				array( 'label' => 'Date of the contribution', 'field' => 'date' ),
				array( 'label' => 'Donor name', 'field' => 'client', 'optional' => true ),
				array( 'label' => 'Organization EIN', 'field' => 'x:ein', 'optional' => true, 'note' => 'Not required by the IRS, but donors and their tax preparers often ask for it.' ),
				array(
					'label'  => 'If the donor received something for a payment over $75: a good-faith estimate of its value',
					'manual' => true,
					'when'   => array( 'total_over' => 75 ),
					'note'   => 'A quid pro quo contribution over $75 needs a written disclosure that only the amount above the value of the goods or services is deductible.',
				),
			),
		),

		'expense'   => array(
			'label'    => 'A business expense or reimbursement (US)',
			'heading'  => 'Receipt for a business expense or reimbursement (US)',
			'template' => 'payment-receipt',
			'intro'    => 'For travel, meals, lodging and similar business expenses, the IRS accepts a receipt as documentary evidence when it shows the amount, the date, the place and the essential character of the expense. Most employer expense policies ask for the same.',
			'sources'  => array(
				array( 'IRS Publication 463, Travel, Gift, and Car Expenses (chapter 5, Recordkeeping)', 'https://www.irs.gov/publications/p463' ),
			),
			'checks'   => array(
				array( 'label' => 'Name of the business that was paid', 'field' => 'business' ),
				array( 'label' => 'Date of the expense', 'field' => 'date' ),
				array( 'label' => 'What was bought (itemized)', 'field' => 'items' ),
				array( 'label' => 'Amount paid', 'field' => 'total' ),
				array( 'label' => 'Place of the expense (the business address)', 'field' => array( 'x:hotel_address', 'x:restaurant_address', 'x:business_address', 'x:store_address', 'x:pickup', 'x:service_address', 'x:project_address' ), 'note' => 'If your template has no address field, add the address under your business name or in Notes.' ),
				array( 'label' => 'Name of the customer', 'field' => 'client', 'optional' => true ),
			),
		),

		'missing'   => array(
			'label'    => 'A missing receipt declaration',
			'heading'  => 'Missing receipt declaration for an expense report',
			'template' => 'missing-receipt-form',
			'intro'    => 'When an original receipt is lost, most employers accept a signed declaration that gives the same details the receipt would have shown, plus the reason it is missing and a manager\'s approval. Check your own expense policy, as some cap how often or how much can be claimed this way.',
			'sources'  => array(
				array( 'IRS Publication 463, Recordkeeping: what to do if you have incomplete records', 'https://www.irs.gov/publications/p463' ),
			),
			'checks'   => array(
				array( 'label' => 'Employee name', 'field' => 'business' ),
				array( 'label' => 'Vendor or merchant', 'field' => 'x:vendor' ),
				array( 'label' => 'Date of the expense', 'field' => 'x:expense_date' ),
				array( 'label' => 'What was bought, and the amount', 'field' => 'items' ),
				array( 'label' => 'Business purpose', 'field' => 'x:purpose' ),
				array( 'label' => 'Why the receipt is missing', 'field' => 'x:reason' ),
				array( 'label' => 'Signed declaration by the employee', 'field' => 'statement' ),
				array( 'label' => 'Employee and approver signatures', 'field' => 'signature', 'manual' => true ),
			),
		),
	);
}

function rf_get_receipt_purpose( $key ) {
	$all = rf_receipt_purposes();
	return isset( $all[ $key ] ) ? $all[ $key ] : null;
}

/**
 * The lists in the shape receipt-generator.js expects (no sources/notes,
 * template URL resolved).
 */
function rf_receipt_purposes_for_js() {
	$out = array();
	foreach ( rf_receipt_purposes() as $key => $p ) {
		$out[ $key ] = array(
			'label'        => $p['label'],
			'currency'     => $p['currency'] ?? 'USD',
			'templateUrl'  => home_url( '/' . $p['template'] . '/' ),
			'templateName' => ( $cat = rf_get_category_data( $p['template'] ) ) ? rf_category_label( $cat ) : '',
			'checks'       => array_map(
				function ( $c ) {
					unset( $c['note'] );
					return $c;
				},
				$p['checks']
			),
		);
	}
	return $out;
}

/** The "When" column on /receipt-requirements/. */
function rf_requirement_when_text( $check, $purpose_key ) {
	$p      = rf_get_receipt_purpose( $purpose_key );
	$symbol = $p['symbol'] ?? '$';
	$parts  = array();
	if ( isset( $check['when']['total_over'] ) ) {
		$parts[] = 'Amount over ' . $symbol . number_format( $check['when']['total_over'] );
	}
	if ( ! empty( $check['when']['cash'] ) ) {
		$parts[] = 'paid in cash';
	}
	$when = $parts ? implode( ', ', $parts ) : 'Always';
	if ( ! empty( $check['optional'] ) ) {
		$when = 'Recommended';
	}
	if ( ! empty( $check['manual'] ) ) {
		$when .= ' (on the printed receipt)';
	}
	return $when;
}
