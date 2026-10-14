<?php
/**
 * ReceiptGenPro — Receipt category page registry
 *
 * SINGLE SOURCE OF TRUTH for every /xxx-receipt/ landing page's SEO title,
 * meta description, "quick facts" sidebar AND (since v1.1.0) the
 * template-specific generator form.
 *
 * v1.1.0 (Sept 2026 audit, C3): every category used to render the exact
 * same generic form ("Bright Star Cleaning Co." placeholder on the rent
 * page, no pickup/drop-off fields on the taxi page even though the page
 * promised them). Each entry now carries a 'form' block that the shared
 * generator (template-parts/generator-embed.php + receipt-generator.js)
 * uses to render the fields, labels, placeholders, receipt title and
 * extras (tip, amount paid / balance, amount in words, signature line,
 * legal statement) that this receipt type genuinely needs.
 *
 * 'form' keys:
 *   title          Heading printed on the receipt ("RENT RECEIPT").
 *   business_label / business_ph   Issuer field label + placeholder.
 *   client_label   / client_ph     Payer field label + placeholder.
 *   item_ph        Array of placeholders for the starter item rows.
 *   notes_ph       Placeholder for the notes box.
 *   currency       Default currency code (e.g. 'INR'), optional.
 *   payment        Default payment method.
 *   extra          Array of extra fields: id, label, type (text|date|
 *                  datetime-local|number|textarea|select), ph, options.
 *   tip            true → adds a Tip / Gratuity amount field.
 *   paid           true → adds Amount Paid + automatic Balance Due.
 *   words          true → prints the total in words ("One hundred…").
 *   signature      Label for a signature line (empty = none).
 *   statement      array( label, text, checked ) — optional checkbox
 *                  that prints a fixed statement on the receipt.
 *
 * Framing rule (audit C5): every template is written for the business or
 * person who ISSUES the receipt (landlord, driver, hotel, restaurant…),
 * never for someone recreating a receipt on behalf of a business they
 * don't own.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function rf_receipt_categories() {
	static $categories = null;
	if ( null !== $categories ) {
		return $categories;
	}

	$categories = array(

		'rent-receipt' => array(
			'h1'               => 'Free Rent Receipt Generator',
			'seo_title'        => 'Rent Receipt Generator — Free Template for Landlords',
			'meta_description' => 'Create a free rent receipt online in seconds. Landlord and tenant names, property address, rent period, amount paid and balance due. Download as PDF, PNG or JPEG.',
			'primary_keyword'  => 'rent receipt generator',
			'related'          => array( 'cash-receipt', 'construction-receipt', 'cleaning-receipt', 'donation-receipt' ),
			'icon'             => '🏠',
			'default_fields'   => array( 'Landlord & Tenant Names', 'Property Address', 'Rent Period (From – To)', 'Amount Paid', 'Balance Due', 'Landlord Signature Line' ),
			'highlights'       => array(
				'Built for landlords, property managers and roommates collecting rent',
				'Auto-calculates balance due when only part of the rent was paid',
				'Prints the amount in words and a landlord signature line',
			),
			'form'             => array(
				'title'          => 'RENT RECEIPT',
				'business_label' => 'Landlord / Property Manager',
				'business_ph'    => 'e.g. Sarah Collins',
				'client_label'   => 'Tenant Name',
				'client_ph'      => 'e.g. John Miller',
				'item_ph'        => array( 'e.g. Rent for October 2026', 'e.g. Late fee (optional)' ),
				'notes_ph'       => 'e.g. Paid in full for the period above.',
				'payment'        => 'Bank Transfer',
				'extra'          => array(
					array( 'id' => 'property', 'label' => 'Property Address', 'type' => 'text', 'ph' => 'e.g. 24 Oak Street, Apt 3B' ),
					array( 'id' => 'period_from', 'label' => 'Rent Period From', 'type' => 'date' ),
					array( 'id' => 'period_to', 'label' => 'Rent Period To', 'type' => 'date' ),
					array( 'id' => 'landlord_pan', 'label' => 'Landlord PAN / Tax ID (optional)', 'type' => 'text', 'ph' => 'Needed for HRA claims in India above ₹1 lakh/year' ),
				),
				'paid'           => true,
				'words'          => true,
				'signature'      => 'Landlord Signature',
			),
		),

		'hotel-receipt' => array(
			'h1'               => 'Free Hotel Receipt Generator',
			'seo_title'        => 'Hotel Receipt Template — Free Hotel Bill Generator',
			'meta_description' => 'Free hotel receipt and folio generator for hotels, guesthouses and B&Bs. Guest, room, check-in/check-out, nightly rate and taxes — download as PDF.',
			'primary_keyword'  => 'hotel receipt generator',
			'related'          => array( 'restaurant-receipt', 'taxi-receipt', 'cleaning-receipt', 'cash-receipt' ),
			'icon'             => '🏨',
			'default_fields'   => array( 'Guest Name', 'Room Number / Type', 'Check-in / Check-out Dates', 'Nights × Nightly Rate', 'Taxes & Fees', 'Total Paid' ),
			'highlights'       => array(
				'Made for small hotels, guesthouses, B&Bs and short-term rental hosts',
				'Nights × nightly rate as a line item, plus a tax rate or separate lines for taxes and fees',
				'Gives your guests an itemized folio they can file with their employer',
			),
			'form'             => array(
				'title'          => 'HOTEL RECEIPT',
				'business_label' => 'Hotel / Property Name',
				'business_ph'    => 'e.g. Harbor View Guesthouse',
				'client_label'   => 'Guest Name',
				'client_ph'      => 'e.g. Maria Lopez',
				'item_ph'        => array( 'e.g. Deluxe room — 3 nights (qty = nights)', 'e.g. Breakfast' ),
				'notes_ph'       => 'e.g. Thank you for staying with us.',
				'payment'        => 'Card',
				'extra'          => array(
					array( 'id' => 'room', 'label' => 'Room Number / Type', 'type' => 'text', 'ph' => 'e.g. 204 — Deluxe Double' ),
					array( 'id' => 'checkin', 'label' => 'Check-in', 'type' => 'date' ),
					array( 'id' => 'checkout', 'label' => 'Check-out', 'type' => 'date' ),
					array( 'id' => 'hotel_address', 'label' => 'Hotel Address', 'type' => 'text', 'ph' => 'e.g. 12 Marine Drive, Brighton' ),
				),
			),
		),

		'restaurant-receipt' => array(
			'h1'               => 'Free Restaurant Receipt Generator',
			'seo_title'        => 'Restaurant Receipt Generator — Itemized & Printable',
			'meta_description' => 'Free restaurant receipt generator for cafés, food trucks and caterers. Itemized food and drinks, tax, tip and total — download as PDF, PNG or JPEG.',
			'primary_keyword'  => 'restaurant receipt generator',
			'related'          => array( 'hotel-receipt', 'hardware-store-receipt', 'cash-receipt', 'salon-spa-receipt' ),
			'icon'             => '🍽️',
			'default_fields'   => array( 'Items & Quantities', 'Table / Server', 'Tax Rate', 'Tip / Gratuity', 'Payment Method', 'Total' ),
			'highlights'       => array(
				'Built for cafés, pop-ups, food trucks and caterers without a POS',
				'Separate tax and tip fields, just like a printed restaurant check',
				'Great for catering bills and private-event invoices',
			),
			'form'             => array(
				'title'          => 'RESTAURANT RECEIPT',
				'business_label' => 'Restaurant / Café Name',
				'business_ph'    => 'e.g. Olive & Thyme Café',
				'client_label'   => 'Customer (optional)',
				'client_ph'      => 'e.g. Table guest',
				'item_ph'        => array( 'e.g. Grilled chicken sandwich', 'e.g. Iced latte' ),
				'notes_ph'       => 'e.g. Thank you — see you again soon!',
				'payment'        => 'Card',
				'extra'          => array(
					array( 'id' => 'table', 'label' => 'Table No.', 'type' => 'text', 'ph' => 'e.g. 7' ),
					array( 'id' => 'server', 'label' => 'Server', 'type' => 'text', 'ph' => 'e.g. Daniel' ),
					array( 'id' => 'time', 'label' => 'Time', 'type' => 'time' ),
				),
				'tip'            => true,
			),
		),

		'cash-receipt' => array(
			'h1'               => 'Free Cash Receipt Generator',
			'seo_title'        => 'Cash Receipt Template — Free Cash Payment Receipt',
			'meta_description' => 'Create a simple cash receipt online free. Received from, amount in words, payment for, received by and balance due. Download instantly as PDF.',
			'primary_keyword'  => 'cash receipt generator',
			'related'          => array( 'rent-receipt', 'donation-receipt', 'freelance-receipt', 'construction-receipt' ),
			'icon'             => '💵',
			'default_fields'   => array( 'Received From', 'Amount (figures & words)', 'Payment For', 'Received By + Signature', 'Date', 'Balance Due' ),
			'highlights'       => array(
				'The fastest template on the site — a few fields and done',
				'Prints the amount in words, the classic cash-receipt safeguard',
				'Ideal for deposits, advances and petty-cash payments',
			),
			'form'             => array(
				'title'          => 'CASH RECEIPT',
				'business_label' => 'Received By (Your Name / Business)',
				'business_ph'    => 'e.g. Ahmed Traders',
				'client_label'   => 'Received From',
				'client_ph'      => 'e.g. John Miller',
				'item_ph'        => array( 'e.g. Advance payment for order #452', 'e.g. Delivery charge' ),
				'notes_ph'       => 'e.g. Balance to be paid on delivery.',
				'payment'        => 'Cash',
				'extra'          => array(),
				'paid'           => true,
				'words'          => true,
				'signature'      => 'Received By (Signature)',
			),
		),

		'freelance-receipt' => array(
			'h1'               => 'Free Freelance Receipt Generator',
			'seo_title'        => 'Freelance Receipt Template — Payment Receipt for Services',
			'meta_description' => 'Generate a professional freelance payment receipt online free. Client, project, hours or milestone, rate and invoice reference. Download as PDF.',
			'primary_keyword'  => 'freelance receipt generator',
			'related'          => array( 'construction-receipt', 'cleaning-receipt', 'cash-receipt', 'salon-spa-receipt' ),
			'icon'             => '💻',
			'default_fields'   => array( 'Client Name', 'Project / Service', 'Hours or Milestone (qty)', 'Rate', 'Invoice Reference', 'Amount Paid' ),
			'highlights'       => array(
				'Works for hourly (hours × rate) or fixed-price milestones',
				'Add your logo for a client-ready, branded look',
				'Invoice reference field ties the payment to your invoice',
			),
			'form'             => array(
				'title'          => 'PAYMENT RECEIPT',
				'business_label' => 'Freelancer / Studio Name',
				'business_ph'    => 'e.g. Ali Design Studio',
				'client_label'   => 'Client Name',
				'client_ph'      => 'e.g. Northwind Ltd.',
				'item_ph'        => array( 'e.g. Logo design — milestone 2', 'e.g. Web development (qty = hours)' ),
				'notes_ph'       => 'e.g. Thank you for the project!',
				'payment'        => 'Bank Transfer',
				'extra'          => array(
					array( 'id' => 'project', 'label' => 'Project Name', 'type' => 'text', 'ph' => 'e.g. Website redesign' ),
					array( 'id' => 'invoice_ref', 'label' => 'Invoice Reference', 'type' => 'text', 'ph' => 'e.g. INV-2026-014' ),
					array( 'id' => 'tax_id', 'label' => 'Your Tax ID (optional)', 'type' => 'text', 'ph' => 'e.g. NTN / EIN / VAT number' ),
				),
				'paid'           => true,
			),
		),

		'mechanic-receipt' => array(
			'h1'               => 'Free Auto Repair Receipt Generator',
			'seo_title'        => 'Auto Repair Receipt Maker — Free Mechanic Receipt',
			'meta_description' => 'Create a free auto repair receipt online. Vehicle, VIN, odometer, parts and labor lines and warranty notes. Download as a printable PDF.',
			'primary_keyword'  => 'mechanic receipt generator',
			'related'          => array( 'hardware-store-receipt', 'construction-receipt', 'cleaning-receipt', 'cash-receipt' ),
			'icon'             => '🔧',
			'default_fields'   => array( 'Vehicle (Make / Model / Year)', 'VIN & Odometer', 'Parts Lines', 'Labor Lines (hours × rate)', 'Warranty Notes', 'Total Paid' ),
			'highlights'       => array(
				'Use separate lines for parts and labor, like a real shop invoice',
				'Vehicle identification fields (make, model, VIN, odometer)',
				'Warranty / guarantee note printed on the receipt',
			),
			'form'             => array(
				'title'          => 'AUTO REPAIR RECEIPT',
				'business_label' => 'Garage / Mechanic Name',
				'business_ph'    => 'e.g. QuickFix Auto Garage',
				'client_label'   => 'Customer Name',
				'client_ph'      => 'e.g. Robert King',
				'item_ph'        => array( 'e.g. Part: brake pads (front)', 'e.g. Labor: brake service (qty = hours)' ),
				'notes_ph'       => 'e.g. 90-day warranty on parts and labor.',
				'payment'        => 'Card',
				'extra'          => array(
					array( 'id' => 'vehicle', 'label' => 'Vehicle (Make / Model / Year)', 'type' => 'text', 'ph' => 'e.g. Toyota Corolla 2019' ),
					array( 'id' => 'vin', 'label' => 'VIN / Registration No.', 'type' => 'text', 'ph' => 'e.g. 1HGBH41JXMN109186' ),
					array( 'id' => 'odometer', 'label' => 'Odometer', 'type' => 'text', 'ph' => 'e.g. 58,200 km' ),
					array( 'id' => 'warranty', 'label' => 'Warranty / Guarantee', 'type' => 'text', 'ph' => 'e.g. 90 days on parts and labor' ),
				),
				'signature'      => 'Technician Signature',
			),
		),

		'cleaning-receipt' => array(
			'h1'               => 'Free Cleaning Service Receipt Generator',
			'seo_title'        => 'Cleaning Receipt Generator — House, Carpet & Office',
			'meta_description' => 'Make a free cleaning service receipt online — house, office or carpet cleaning. Service address, service date, hours and total. Download as PDF.',
			'primary_keyword'  => 'cleaning receipt generator',
			'related'          => array( 'salon-spa-receipt', 'freelance-receipt', 'rent-receipt', 'mechanic-receipt' ),
			'icon'             => '🧹',
			'default_fields'   => array( 'Client Name', 'Service Address', 'Service Date', 'Service Type & Hours', 'Frequency', 'Total Paid' ),
			'highlights'       => array(
				'Covers house, office, carpet and move-out cleaning',
				'Frequency field for weekly or biweekly clients',
				'Simple enough for solo cleaners, detailed enough for agencies',
			),
			'form'             => array(
				'title'          => 'CLEANING SERVICE RECEIPT',
				'business_label' => 'Cleaning Business Name',
				'business_ph'    => 'e.g. Bright Star Cleaning Co.',
				'client_label'   => 'Client Name',
				'client_ph'      => 'e.g. Emma Wilson',
				'item_ph'        => array( 'e.g. Deep clean — 3-bedroom house (qty = hours)', 'e.g. Carpet shampoo' ),
				'notes_ph'       => 'e.g. Next visit: 14 November.',
				'payment'        => 'Cash',
				'extra'          => array(
					array( 'id' => 'service_address', 'label' => 'Service Address', 'type' => 'text', 'ph' => 'e.g. 8 Elm Road' ),
					array( 'id' => 'service_date', 'label' => 'Service Date', 'type' => 'date' ),
					array( 'id' => 'frequency', 'label' => 'Frequency', 'type' => 'select', 'options' => array( 'One-time', 'Weekly', 'Biweekly', 'Monthly' ) ),
				),
			),
		),

		'donation-receipt' => array(
			'h1'               => 'Free Donation Receipt Generator',
			'seo_title'        => 'Donation Receipt Template — Free 501(c)(3) Receipt',
			'meta_description' => 'Create a donation receipt for your nonprofit: organization and EIN, donor, cash or in-kind gift, and the goods-or-services statement donors need for $250+ gifts.',
			'primary_keyword'  => 'donation receipt generator',
			'related'          => array( 'cash-receipt', 'childcare-receipt', 'rent-receipt', 'freelance-receipt' ),
			'icon'             => '❤️',
			'default_fields'   => array( 'Organization Name & EIN', 'Donor Name', 'Donation Date', 'Cash Amount or In-Kind Description', 'Goods-or-Services Statement', 'Authorized Signature' ),
			'highlights'       => array(
				'Prints the "no goods or services were provided" statement',
				'Cash or in-kind: describe non-cash gifts without assigning a value',
				'Covers what the IRS expects in a written acknowledgment for $250+ gifts',
			),
			'form'             => array(
				'title'          => 'DONATION RECEIPT',
				'business_label' => 'Organization Name',
				'business_ph'    => 'e.g. Green City Food Bank',
				'client_label'   => 'Donor Name',
				'client_ph'      => 'e.g. Jennifer Adams',
				'item_ph'        => array( 'e.g. Cash donation', 'e.g. In-kind: 3 boxes of canned food (no value)' ),
				'notes_ph'       => 'e.g. Thank you for supporting our mission.',
				'payment'        => 'Online Payment',
				'extra'          => array(
					array( 'id' => 'ein', 'label' => 'EIN / Charity Registration No.', 'type' => 'text', 'ph' => 'e.g. 12-3456789' ),
					array( 'id' => 'status', 'label' => 'Tax Status', 'type' => 'text', 'ph' => 'e.g. 501(c)(3) tax-exempt organization' ),
					array( 'id' => 'gift_type', 'label' => 'Gift Type', 'type' => 'select', 'options' => array( 'Cash / cheque / card', 'In-kind (non-cash)' ) ),
				),
				'signature'      => 'Authorized Signature',
				'statement'      => array(
					'label'   => 'No goods or services were provided in exchange',
					'text'    => 'No goods or services were provided in exchange for this contribution.',
					'checked' => true,
				),
			),
		),

		'taxi-receipt' => array(
			'h1'               => 'Free Taxi Receipt Generator for Drivers',
			'seo_title'        => 'Taxi Receipt Template for Drivers — Free Generator',
			'meta_description' => 'Free taxi receipt generator for drivers and cab companies. Pickup, drop-off, trip date and time, distance, fare, tip and vehicle number — download as PDF.',
			'primary_keyword'  => 'taxi receipt generator',
			'related'          => array( 'hotel-receipt', 'restaurant-receipt', 'mechanic-receipt', 'cash-receipt' ),
			'icon'             => '🚕',
			'default_fields'   => array( 'Driver / Taxi Company', 'Vehicle / License No.', 'Pickup & Drop-off', 'Trip Date & Time', 'Distance & Fare', 'Tip' ),
			'highlights'       => array(
				'Made for taxi drivers, cab companies and private-hire operators',
				'Pickup, drop-off, distance and vehicle number on every receipt',
				'Hand passengers a proper printed or WhatsApp receipt on request',
			),
			'form'             => array(
				'title'          => 'TAXI RECEIPT',
				'business_label' => 'Driver / Taxi Company',
				'business_ph'    => 'e.g. City Cabs — driver Imran K.',
				'client_label'   => 'Passenger Name (optional)',
				'client_ph'      => 'e.g. Passenger',
				'item_ph'        => array( 'e.g. Fare — Airport to City Centre', 'e.g. Airport surcharge' ),
				'notes_ph'       => 'e.g. Thank you for riding with us.',
				'payment'        => 'Cash',
				'extra'          => array(
					array( 'id' => 'vehicle_no', 'label' => 'Vehicle / License No.', 'type' => 'text', 'ph' => 'e.g. LEB-1234' ),
					array( 'id' => 'pickup', 'label' => 'Pickup', 'type' => 'text', 'ph' => 'e.g. Terminal 1, Airport' ),
					array( 'id' => 'dropoff', 'label' => 'Drop-off', 'type' => 'text', 'ph' => 'e.g. 45 Mall Road' ),
					array( 'id' => 'trip_time', 'label' => 'Trip Date & Time', 'type' => 'datetime-local' ),
					array( 'id' => 'distance', 'label' => 'Distance', 'type' => 'text', 'ph' => 'e.g. 18 km' ),
				),
				'tip'            => true,
				'signature'      => 'Driver Signature',
			),
		),

		'childcare-receipt' => array(
			'h1'               => 'Free Childcare & Daycare Receipt Generator',
			'seo_title'        => 'Childcare Receipt Generator — Daycare & Babysitting',
			'meta_description' => 'Create a free childcare or daycare receipt online. Provider name and tax ID, child, care period and amount paid — the details parents need for tax credits.',
			'primary_keyword'  => 'childcare receipt generator',
			'related'          => array( 'donation-receipt', 'cleaning-receipt', 'cash-receipt', 'freelance-receipt' ),
			'icon'             => '🧸',
			'default_fields'   => array( 'Provider Name & Address', 'Provider Tax ID', 'Child Name', 'Care Period', 'Rate', 'Total Paid' ),
			'highlights'       => array(
				'Provider Tax ID field — parents need it for dependent-care credits (IRS Form 2441)',
				'Supports daycare centers, nannies and in-home babysitters',
				'Care-period dates for weekly or monthly billing',
			),
			'form'             => array(
				'title'          => 'CHILDCARE RECEIPT',
				'business_label' => 'Childcare Provider',
				'business_ph'    => 'e.g. Little Stars Daycare',
				'client_label'   => 'Parent / Guardian',
				'client_ph'      => 'e.g. Laura Chen',
				'item_ph'        => array( 'e.g. Full-day care — 5 days (qty = days)', 'e.g. Meals' ),
				'notes_ph'       => 'e.g. Thank you!',
				'payment'        => 'Bank Transfer',
				'extra'          => array(
					array( 'id' => 'provider_tax_id', 'label' => 'Provider Tax ID / SSN / EIN', 'type' => 'text', 'ph' => 'e.g. 12-3456789' ),
					array( 'id' => 'provider_address', 'label' => 'Provider Address', 'type' => 'text', 'ph' => 'e.g. 10 Park Lane' ),
					array( 'id' => 'child', 'label' => 'Child Name', 'type' => 'text', 'ph' => 'e.g. Mia Chen' ),
					array( 'id' => 'care_from', 'label' => 'Care Period From', 'type' => 'date' ),
					array( 'id' => 'care_to', 'label' => 'Care Period To', 'type' => 'date' ),
				),
				'signature'      => 'Provider Signature',
			),
		),

		'hardware-store-receipt' => array(
			'h1'               => 'Free Hardware Store Receipt Generator',
			'seo_title'        => 'Hardware Store Receipt Generator — Itemized Template',
			'meta_description' => 'Make a free itemized hardware store receipt online — SKU, item, quantity, price and sales tax. Download as a printable PDF, PNG or JPEG.',
			'primary_keyword'  => 'hardware store receipt generator',
			'related'          => array( 'construction-receipt', 'mechanic-receipt', 'restaurant-receipt', 'cash-receipt' ),
			'icon'             => '🔨',
			'default_fields'   => array( 'Item / SKU', 'Quantity', 'Unit Price', 'Sales Tax', 'Store Address', 'Cashier' ),
			'highlights'       => array(
				'Multi-line itemized format built for retail SKUs',
				'Sales-tax field with automatic total recalculation',
				'Store address and cashier printed like a POS receipt',
			),
			'form'             => array(
				'title'          => 'SALES RECEIPT',
				'business_label' => 'Store Name',
				'business_ph'    => 'e.g. Ace Tools & Hardware',
				'client_label'   => 'Customer (optional)',
				'client_ph'      => 'e.g. Walk-in customer',
				'item_ph'        => array( 'e.g. SKU 10442 — 2" wood screws (box)', 'e.g. SKU 20881 — Paint roller' ),
				'notes_ph'       => 'e.g. Returns accepted within 30 days with receipt.',
				'payment'        => 'Card',
				'extra'          => array(
					array( 'id' => 'store_address', 'label' => 'Store Address', 'type' => 'text', 'ph' => 'e.g. 77 Main Street' ),
					array( 'id' => 'cashier', 'label' => 'Cashier', 'type' => 'text', 'ph' => 'e.g. Tom' ),
				),
			),
		),

		'salon-spa-receipt' => array(
			'h1'               => 'Free Salon & Spa Receipt Generator',
			'seo_title'        => 'Salon & Spa Receipt Generator — Services & Tips',
			'meta_description' => 'Create a free salon or spa receipt online. Services, stylist or therapist, retail products, tip and total. Download as PDF instantly.',
			'primary_keyword'  => 'salon receipt generator',
			'related'          => array( 'cleaning-receipt', 'freelance-receipt', 'restaurant-receipt', 'cash-receipt' ),
			'icon'             => '💇',
			'default_fields'   => array( 'Client Name', 'Services', 'Stylist / Therapist', 'Retail Products', 'Tip', 'Total' ),
			'highlights'       => array(
				'Services and retail products on separate lines',
				'Stylist / therapist name for commission tracking',
				'Tip field, just like most printed salon receipts',
			),
			'form'             => array(
				'title'          => 'SALON RECEIPT',
				'business_label' => 'Salon / Spa Name',
				'business_ph'    => 'e.g. Glow Beauty Lounge',
				'client_label'   => 'Client Name',
				'client_ph'      => 'e.g. Sophie Turner',
				'item_ph'        => array( 'e.g. Haircut & blow-dry', 'e.g. Product: argan oil shampoo' ),
				'notes_ph'       => 'e.g. Next appointment: 2 December.',
				'payment'        => 'Card',
				'extra'          => array(
					array( 'id' => 'stylist', 'label' => 'Stylist / Therapist', 'type' => 'text', 'ph' => 'e.g. Hina' ),
				),
				'tip'            => true,
			),
		),

		'construction-receipt' => array(
			'h1'               => 'Free Construction & Contractor Receipt Generator',
			'seo_title'        => 'Contractor Receipt Generator — Construction Payments',
			'meta_description' => 'Generate a free contractor or construction payment receipt. Project address, phase, materials and labor lines, amount paid and balance remaining.',
			'primary_keyword'  => 'contractor receipt generator',
			'related'          => array( 'hardware-store-receipt', 'freelance-receipt', 'mechanic-receipt', 'rent-receipt' ),
			'icon'             => '🏗️',
			'default_fields'   => array( 'Client & Project Address', 'Milestone / Phase', 'Materials Lines', 'Labor Lines', 'Amount Paid', 'Balance Remaining' ),
			'highlights'       => array(
				'Milestone / phase field for progress payments',
				'Materials vs. labor lines for transparent billing',
				'Balance-remaining calculation for multi-payment contracts',
			),
			'form'             => array(
				'title'          => 'CONTRACTOR PAYMENT RECEIPT',
				'business_label' => 'Contractor / Company',
				'business_ph'    => 'e.g. Solid Build Contractors',
				'client_label'   => 'Client Name',
				'client_ph'      => 'e.g. David Brown',
				'item_ph'        => array( 'e.g. Materials: cement & steel', 'e.g. Labor: foundation work' ),
				'notes_ph'       => 'e.g. Next milestone due on completion of roofing.',
				'payment'        => 'Bank Transfer',
				'extra'          => array(
					array( 'id' => 'project_address', 'label' => 'Project Address', 'type' => 'text', 'ph' => 'e.g. Plot 14, Green Avenue' ),
					array( 'id' => 'phase', 'label' => 'Milestone / Phase', 'type' => 'text', 'ph' => 'e.g. Phase 2 of 4 — Structure' ),
					array( 'id' => 'contract_ref', 'label' => 'Contract / Invoice Ref.', 'type' => 'text', 'ph' => 'e.g. CT-2026-07' ),
				),
				'paid'           => true,
				'words'          => true,
				'signature'      => 'Contractor Signature',
			),
		),
		'hra-rent-receipt' => array(
			'h1'               => 'Rent Receipt for HRA (India)',
			'seo_title'        => 'Rent Receipt for HRA — Free Generator (India, PDF)',
			'meta_description' => 'Create a monthly rent receipt for your HRA claim: landlord and tenant names, property address, rent month, amount in rupees and words, landlord PAN and signature.',
			'primary_keyword'  => 'rent receipt for hra',
			'related'          => array( 'rent-receipt', 'cash-receipt', 'payment-receipt', 'deposit-receipt' ),
			'icon'             => '🇮🇳',
			'default_fields'   => array( 'Landlord & Tenant Names', 'Rented Property Address', 'Rent Month', 'Amount (₹ figures & words)', 'Landlord PAN', 'Landlord Signature' ),
			'highlights'       => array(
				'Formatted the way employers ask for HRA proof — one receipt per month',
				'Landlord PAN field (needed when annual rent is above ₹1 lakh)',
				'Amount in words in rupees and paise, plus a signature line for the landlord',
			),
			'form'             => array(
				'title'          => 'RENT RECEIPT',
				'currency'       => 'INR',
				'business_label' => 'Landlord Name',
				'business_ph'    => 'e.g. Ramesh Kumar',
				'client_label'   => 'Tenant Name',
				'client_ph'      => 'e.g. Priya Sharma',
				'item_ph'        => array( 'e.g. House rent for April 2026', 'e.g. Maintenance charges (if included)' ),
				'notes_ph'       => 'e.g. Received with thanks.',
				'payment'        => 'Bank Transfer',
				'extra'          => array(
					array( 'id' => 'property', 'label' => 'Rented Property Address', 'type' => 'text', 'ph' => 'e.g. Flat 302, Green Towers, Pune' ),
					array( 'id' => 'rent_month', 'label' => 'Rent Month', 'type' => 'month' ),
					array( 'id' => 'landlord_pan', 'label' => 'Landlord PAN', 'type' => 'text', 'ph' => 'e.g. ABCDE1234F' ),
					array( 'id' => 'landlord_address', 'label' => 'Landlord Address (optional)', 'type' => 'text', 'ph' => 'e.g. 12 MG Road, Pune' ),
				),
				'words'          => true,
				'signature'      => 'Landlord Signature (affix revenue stamp if required)',
			),
		),

		'payment-receipt' => array(
			'h1'               => 'Free Payment Receipt Generator',
			'seo_title'        => 'Payment Receipt Template — Free Proof of Payment',
			'meta_description' => 'Make a free payment receipt online: who paid, amount, what it was for, payment method and reference number. Download a proof of payment as PDF, PNG or JPEG.',
			'primary_keyword'  => 'payment receipt template',
			'related'          => array( 'cash-receipt', 'deposit-receipt', 'freelance-receipt', 'rent-receipt' ),
			'icon'             => '🧾',
			'default_fields'   => array( 'Received From', 'Payment For', 'Transaction / Reference No.', 'Invoice Reference', 'Amount Paid & Balance', 'Signature' ),
			'highlights'       => array(
				'A general proof-of-payment for any business or individual',
				'Transaction reference field for bank transfers and online payments',
				'Links the payment to an invoice and shows any balance left',
			),
			'form'             => array(
				'title'          => 'PAYMENT RECEIPT',
				'business_label' => 'Received By (Your Name / Business)',
				'business_ph'    => 'e.g. Northstar Supplies',
				'client_label'   => 'Received From',
				'client_ph'      => 'e.g. Daniel Evans',
				'item_ph'        => array( 'e.g. Payment for invoice INV-2026-031', 'e.g. Delivery charge' ),
				'notes_ph'       => 'e.g. Thank you for your payment.',
				'payment'        => 'Bank Transfer',
				'extra'          => array(
					array( 'id' => 'txn_ref', 'label' => 'Transaction / Reference No.', 'type' => 'text', 'ph' => 'e.g. bank ref, cheque no. or payment ID' ),
					array( 'id' => 'invoice_ref', 'label' => 'Invoice Reference (optional)', 'type' => 'text', 'ph' => 'e.g. INV-2026-031' ),
				),
				'paid'           => true,
				'words'          => true,
				'signature'      => 'Authorized Signature',
			),
		),

		'deposit-receipt' => array(
			'h1'               => 'Free Deposit Receipt Generator',
			'seo_title'        => 'Deposit Receipt Template — Security & Advance Deposits',
			'meta_description' => 'Free deposit receipt for security deposits and advance payments: who paid, what the deposit secures, refund terms, amount in words and balance due.',
			'primary_keyword'  => 'deposit receipt template',
			'related'          => array( 'rent-receipt', 'payment-receipt', 'cash-receipt', 'construction-receipt' ),
			'icon'             => '🔐',
			'default_fields'   => array( 'Received From', 'Deposit Type', 'Deposit For (property, order or booking)', 'Refund Terms', 'Amount (figures & words)', 'Signature' ),
			'highlights'       => array(
				'Covers rental security deposits, booking deposits and order advances',
				'Refund-terms field so both sides know when and how it comes back',
				'Balance-due calculation when the deposit is part of a larger price',
			),
			'form'             => array(
				'title'          => 'DEPOSIT RECEIPT',
				'business_label' => 'Received By (Your Name / Business)',
				'business_ph'    => 'e.g. Maple Lettings',
				'client_label'   => 'Received From',
				'client_ph'      => 'e.g. Aisha Khan',
				'item_ph'        => array( 'e.g. Security deposit — 24 Oak Street, Apt 3B', 'e.g. Booking deposit — 12 June event' ),
				'notes_ph'       => 'e.g. Balance due on delivery.',
				'payment'        => 'Bank Transfer',
				'extra'          => array(
					array( 'id' => 'deposit_type', 'label' => 'Deposit Type', 'type' => 'select', 'options' => array( 'Security deposit (refundable)', 'Advance / booking deposit', 'Order deposit (non-refundable)' ) ),
					array( 'id' => 'deposit_for', 'label' => 'Deposit For', 'type' => 'text', 'ph' => 'e.g. Property, order no. or booking date' ),
					array( 'id' => 'refund_terms', 'label' => 'Refund Terms', 'type' => 'text', 'ph' => 'e.g. Refundable within 30 days of move-out, less damages' ),
				),
				'paid'           => true,
				'words'          => true,
				'signature'      => 'Received By (Signature)',
			),
		),
	);

	return $categories;
}

function rf_get_category_data( $slug ) {
	$all = rf_receipt_categories();
	return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
}

/**
 * Footer fallback menus: every template from rf_receipt_categories(), split
 * into two halves ($half 0 = first column, 1 = second), so a new template
 * shows up in the footer without editing a hard-coded list.
 */
function rf_footer_category_list( $half ) {
	$all   = rf_receipt_categories();
	$split = (int) ceil( count( $all ) / 2 );
	$cats  = 0 === $half ? array_slice( $all, 0, $split, true ) : array_slice( $all, $split, null, true );
	echo '<ul class="rf-footer-menu">';
	foreach ( $cats as $slug => $cat ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( '/' . $slug . '/' ) ), esc_html( rf_category_label( $cat ) ) );
	}
	echo '</ul>';
}

/**
 * Plain-text label for a category (used in menus, cards, breadcrumbs).
 */
function rf_category_label( $cat ) {
	return html_entity_decode( str_replace( 'Free ', '', $cat['h1'] ), ENT_QUOTES, 'UTF-8' );
}
