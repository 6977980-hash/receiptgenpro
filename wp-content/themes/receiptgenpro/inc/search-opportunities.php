<?php
/**
 * ReceiptGenPro — theme 1.4.4: work from the Search Console queries of
 * 1 Nov 2026 (receiptgenpro.com, 15 Sep to 30 Oct 2026, 232 queries).
 *
 * 1. The two VAT guides competed for "what is a vat receipt" (380 of the
 *    site's 2.78K impressions, position 58): /what-is-a-vat-receipt/ is
 *    now the answer page (title, meta, three more FAQs people search for)
 *    and /vat-receipts-in-the-uk/ is the HMRC checklist (title, meta and
 *    a direct answer about HMRC's rules, not a definition).
 * 2. Guides on Google's first page with no clicks get a clearer title and
 *    description.
 * 3. Receipt template pages get a short section and FAQs for the words
 *    people use that the pages lacked (cab, folio, form, itemized, cash
 *    advance, proof of payment, lost receipt affidavit…). Brand names and
 *    "fake" searches are deliberately not targeted.
 *
 * Nothing here writes to the database: the post changes are applied when
 * the page is shown (the_content, rf_seo_data), so they can't half-apply
 * and go away if this file is removed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * New SEO title and meta description for guides (posts), by slug.
 * Titles ≤ 60 characters, descriptions ≤ 155 (tests/php/v144-test.php).
 *
 * @return array slug => array( title, description )
 */
function rf_v144_post_seo() {
	return array(
		'what-is-a-vat-receipt'                               => array(
			'What Is a VAT Receipt? Meaning, Example & What It Must Show',
			'A VAT receipt is proof of purchase that shows the VAT rate and amount on their own lines. What it must show, an example, and how to make one free.',
		),
		'vat-receipts-in-the-uk'                              => array(
			'UK VAT Invoice Rules (HMRC): Full vs Simplified Checklist',
			'HMRC\'s checklist for UK VAT invoices: what a full invoice must show, when a simplified one is allowed (£250 or less), and how long to keep records.',
		),
		'receipt-numbering-system-how-to-number-receipts'     => array(
			'How to Number Receipts: 3 Simple Systems (With Examples)',
			'Number receipts the easy way: straight sequential, year-prefixed (2026-001) or by category. Rules for gaps and voids, and how to start from scratch.',
		),
		'what-must-be-on-a-receipt-legal-requirements-by-country' => array(
			'What Must Be on a Receipt? US, UK, Canada, Australia, India',
			'The details a receipt must show in the US, UK, Canada, Australia and India, the extra fields for tax invoices, and a checklist that works anywhere.',
		),
		'how-to-make-a-receipt-on-your-phone'                 => array(
			'How to Make a Receipt on Your Phone — Free, No App Needed',
			'Make a receipt on your iPhone or Android phone in the browser: no app, no signup, no watermark. Step-by-step for both, then share it as a PDF.',
		),
		'digital-receipts-vs-paper-receipts'                  => array(
			'Are Digital Receipts Legal? Digital vs Paper Receipts',
			'Are emailed and PDF receipts as valid as paper ones? What tax authorities accept, the pros and cons of each, and how to keep digital receipts safe.',
		),
		'what-happens-if-youre-audited-and-dont-have-receipts' => array(
			'Audited Without Receipts? What Happens and What Counts',
			'Missing receipts in a tax audit: what happens, what other evidence auditors accept (bank statements, invoices, vendor letters) and how to protect yourself.',
		),
		'receipt-requirements-in-australia'                   => array(
			'ATO Tax Invoice & Receipt Requirements in Australia',
			'What an Australian tax invoice must show for the ATO (ABN, GST), when a plain receipt is enough, and how long to keep business records.',
		),
		'receipt-rules-for-businesses-in-canada'              => array(
			'CRA Invoice & Receipt Requirements in Canada (GST/HST)',
			'What a receipt or invoice must show for the CRA, when to add your GST/HST number, the GST, HST and PST basics, and how long to keep records.',
		),
	);
}

function rf_v144_post_seo_filter( $data ) {
	if ( ! is_singular( 'post' ) ) {
		return $data;
	}
	$seo = rf_v144_post_seo()[ get_post_field( 'post_name' ) ] ?? null;
	if ( $seo ) {
		$data['title']       = $seo[0];
		$data['description'] = $seo[1];
	}
	return $data;
}
add_filter( 'rf_seo_data', 'rf_v144_post_seo_filter', 20 );

/**
 * FAQs added to a guide's own FAQ block (the first .rf-faq), by slug.
 *
 * @return array slug => list of array( question, answer )
 */
function rf_v144_post_faqs() {
	return array(
		'what-is-a-vat-receipt' => array(
			array( 'Can you claim VAT on supermarket receipts?', 'Only for business purchases, and only on items that carried VAT. Most food is zero-rated in the UK, so a supermarket receipt often shows VAT on just a few lines, such as cleaning products or stationery. Keep the till receipt: if it shows the store\'s VAT number and the VAT for each rate, it works as a simplified VAT invoice for purchases of £250 or less.' ),
			array( 'Where is the VAT number on a receipt?', 'Usually under the seller\'s name and address at the top, or in the footer, labelled "VAT No." or "VAT Reg No." A UK VAT number starts with GB followed by 9 digits. No VAT number means the receipt can\'t be used to reclaim VAT.' ),
			array( 'How long should you keep VAT receipts?', 'In the UK, HMRC asks VAT-registered businesses to keep VAT records, including the receipts and invoices behind them, for at least 6 years. Other countries set their own periods; see our guide to how long to keep business receipts.' ),
		),
	);
}

/**
 * Text replaced in a guide as it is shown, by slug. Each 'find' is a
 * regular expression matched against the content as WordPress renders it
 * (tests/php/fixtures/v144/ holds the live text of 1 Nov 2026).
 *
 * @return array slug => list of array( find, replace )
 */
function rf_v144_post_replacements() {
	return array(
		'vat-receipts-in-the-uk' => array(
			array(
				'#<p class="rf-direct-answer">.*?</p>#s',
				'<p class="rf-direct-answer">Under HMRC rules, a VAT-registered UK business must give a VAT invoice showing a unique invoice number, the date, its name, address and VAT registration number, the customer\'s name and address, a description of what was sold, the price excluding VAT, the VAT rate and the VAT amount. For a retail sale of £250 or less (including VAT) it can give a simplified invoice instead, with fewer details.</p>',
			),
		),
	);
}

/** HTML for a list of FAQ pairs as .rf-faq children (h3 + p). */
function rf_v144_faq_items_html( $pairs ) {
	$html = '';
	foreach ( $pairs as $pair ) {
		$html .= "\n<h3>" . esc_html( $pair[0] ) . "</h3>\n<p>" . esc_html( $pair[1] ) . '</p>';
	}
	return $html;
}

/**
 * Applies the 1.4.4 changes to a guide's content: replacements, then the
 * extra FAQs before the end of its first .rf-faq block (or as a new block
 * at the end when the guide has none). Used for the page and for the
 * FAQPage schema, so the two always match.
 */
function rf_v144_post_content( $content, $slug ) {
	foreach ( rf_v144_post_replacements()[ $slug ] ?? array() as $rule ) {
		$content = preg_replace( $rule[0], str_replace( array( '\\', '$' ), array( '\\\\', '\$' ), $rule[1] ), $content, 1 );
	}
	$faqs = rf_v144_post_faqs()[ $slug ] ?? array();
	if ( $faqs && false === strpos( $content, esc_html( $faqs[0][0] ) ) ) {
		$items = rf_v144_faq_items_html( $faqs );
		if ( preg_match( '#<div class="rf-faq">.*?(</div>)#s', $content, $m, PREG_OFFSET_CAPTURE ) ) {
			$at      = $m[1][1];
			$content = substr( $content, 0, $at ) . $items . "\n" . substr( $content, $at );
		} else {
			$content .= "\n<div class=\"rf-faq\">" . $items . "\n</div>";
		}
	}
	return $content;
}

function rf_v144_the_content( $content ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	return rf_v144_post_content( $content, get_post_field( 'post_name' ) );
}
add_filter( 'the_content', 'rf_v144_the_content', 99 );

/**
 * Extra sections and FAQs for receipt template pages, by category slug.
 * Every template is written for the business that issues the receipt
 * (category-data.php, audit C5).
 *
 * @return array slug => array( 'sections' => list of array( h2, html ), 'faqs' => list of array( q, a ) )
 */
function rf_v144_category_extras() {
	$link = function ( $path, $text ) {
		return '<a href="' . esc_url( home_url( $path ) ) . '">' . esc_html( $text ) . '</a>';
	};
	return array(
		'taxi-receipt'         => array(
			'sections' => array(
				array( 'Taxi Receipt Form, Template or Generator?', '<p>Drivers search for a taxi receipt form, a cab receipt template or a taxi receipt maker; they all mean the same thing: a receipt with the driver or company, the vehicle number, pickup and drop-off, the date and time, and the fare. The generator above is all three. Fill it in on your phone after a trip and send the PDF, or print a few and hand them to passengers who ask.</p>' ),
			),
			'faqs'     => array(
				array( 'Can a passenger get a receipt from a taxi?', 'Yes, ask the driver or the cab company. Licensed taxis and most cab companies can give a printed or emailed receipt on request. If you drive, this generator lets you give one even without a meter printer.' ),
				array( 'Is a cab receipt the same as a taxi receipt?', 'Yes. "Cab" and "taxi" mean the same, so a cab receipt shows the same details: who drove, the vehicle, the trip and the fare. Private-hire and airport-transfer drivers can use the same template.' ),
			),
		),
		'hotel-receipt'        => array(
			'sections' => array(
				array( 'Hotel Folio vs Hotel Receipt', '<p>A <strong>folio</strong> is the hotel\'s running bill for one stay: each night\'s room rate, taxes, and extras such as meals, parking or minibar, posted day by day. The <strong>receipt</strong> confirms what the guest paid at checkout. Many hotels give one document that does both, which is what this generator makes: list each night or charge as its own line, add the taxes, and record the payment and any balance.</p>' ),
			),
			'faqs'     => array(
				array( 'What is a hotel folio?', 'The itemized bill for a guest\'s stay: the room charge for each night, taxes and fees, and any extras charged to the room. Business travelers usually need it, not just the card slip, for an expense report.' ),
				array( 'Can a motel, lodge or B&B use this hotel receipt?', 'Yes. Any place that rents rooms by the night can: put the property name in the hotel field, then the room, the dates and the nightly rate. For a short-term rental of a whole home, the same fields work.' ),
			),
		),
		'cleaning-receipt'     => array(
			'sections' => array(
				array( 'House, Carpet and Office Cleaning Receipts', '<p>The same template covers a house cleaning, a carpet cleaning job or a recurring office contract. For carpets, list each room or area as its own line with the square footage or price; for a house, list the type of clean (standard, deep, move-out) and any extras such as oven or windows. Put the service address and date so the client can match the receipt to the visit.</p>' ),
			),
			'faqs'     => array(
				array( 'Do I need a receipt or an invoice for a cleaning job?', 'An invoice asks for payment; a receipt confirms it was paid. If the client pays on the day, give a receipt. If they pay later, send an invoice first, then a receipt once the money arrives.' ),
				array( 'Can I use this for carpet cleaning?', 'Yes. List each room, staircase or rug as its own line, add stain treatment or deodorizing as extras, and the generator totals them with any tax.' ),
			),
		),
		'construction-receipt' => array(
			'sections' => array(
				array( 'Receipt for a Cash Payment to a Contractor', '<p>When a client pays a contractor in cash, a receipt is the only record that the money changed hands. Write the amount in figures and words (the generator adds the words), the project address, what the payment covers (a deposit, a milestone or the final payment), the balance still owed, and sign it. Give the client a copy and keep one for your books.</p>' ),
			),
			'faqs'     => array(
				array( 'How do I write a receipt for a cash payment to a contractor?', 'Put the contractor\'s business name and license number, the client, the project address, the date, what the payment is for, the amount in figures and words, the balance remaining and a signature. The generator above has a field for each.' ),
			),
		),
		'mechanic-receipt'     => array(
			'sections' => array(
				array( 'Listing Parts Like an Alternator or Engine Rebuild', '<p>Put each part on its own line with the part name and number, for example "Alternator, remanufactured, part no. ..." or "Engine rebuild kit", then labor as a separate line with the hours and rate. Note whether a part is new, used or remanufactured and any warranty. A clear parts-and-labor receipt is what customers need for a warranty claim or to show a buyer when they sell the car.</p>' ),
			),
			'faqs'     => array(
				array( 'Is a mechanic receipt the same as an auto repair receipt?', 'Yes. Mobile mechanics, garages and body shops can all use it: the vehicle (make, model, VIN, mileage), each part, labor hours, tax and the total.' ),
			),
		),
		'restaurant-receipt'   => array(
			'sections' => array(
				array( 'How to Make an Itemized Receipt', '<p>An itemized receipt lists every item on its own line with the quantity and price, then the subtotal, each tax, the tip and the total, instead of one lump sum. Employers and tax authorities usually ask for itemized receipts for meal expenses because they show what was bought. In the generator, add one row per dish or drink and turn on the tip field; the totals are worked out for you.</p>' ),
			),
			'faqs'     => array(
				array( 'What makes a restaurant receipt itemized?', 'Each dish and drink on its own line with quantity and price, followed by the subtotal, tax, tip and total. A card slip showing only the total is not itemized.' ),
			),
		),
		'cash-receipt'         => array(
			'sections' => array(
				array( 'Cash Advance Receipt', '<p>When you hand someone cash before the work or purchase (an advance to an employee for travel, or to a supplier before delivery), give or get a receipt for the advance. Write "Cash advance" in the description, the purpose, the amount in figures and words, and the date. When the advance is settled, a second receipt or the expense report shows how it was spent and any balance returned.</p>' ),
			),
			'faqs'     => array(
				array( 'How do I write a cash advance receipt?', 'Name who received the cash and who gave it, write "Cash advance for ..." with the purpose, the amount in figures and words, the date, and both signatures. Note when and how it will be settled.' ),
				array( 'What is the format of a cash receipt?', 'Receipt number and date at the top; received from; the amount in figures and words; what it was for; the payment method (cash); the balance due if any; and the name and signature of the person who received it.' ),
			),
		),
		'payment-receipt'      => array(
			'sections' => array(),
			'faqs'     => array(
				array( 'Is a payment receipt proof of payment?', 'Yes. A receipt signed or issued by the person who was paid, with the date, amount, method and what it was for, is the usual proof of payment. A bank transfer record shows money left an account; the receipt shows the payee accepted it for a specific bill.' ),
			),
		),
		'missing-receipt-form' => array(
			'sections' => array(
				array( 'Lost Receipt Form, Affidavit or Declaration?', '<p>Companies call it a lost receipt form, a missing receipt declaration or a lost receipt affidavit. For an expense report they mean the same: a signed statement of the vendor, date, amount, business purpose and why the receipt is missing. An <strong>affidavit</strong> in the strict sense is sworn before a notary; most employers only need the signed declaration this page makes. Check your expense policy, and attach a bank or card statement line if you have one.</p>' ),
			),
			'faqs'     => array(
				array( 'Can I use this for a lost receipt on an expense report?', 'Yes, that is what it is for. Fill in one line per missing receipt (a fuel stop, a taxi, a meal), sign it, and attach it to the expense report with any card statement that shows the charge.' ),
				array( 'What if I lost several gas receipts?', 'List each fill-up as its own line with the date, station, amount and trip. A mileage log or card statement for the same dates makes the declaration much stronger.' ),
			),
		),
	);
}

/** The FAQ block for a template page's extras ('' when it has none). */
function rf_v144_category_faq_html( $slug ) {
	$faqs = rf_v144_category_extras()[ $slug ]['faqs'] ?? array();
	return $faqs ? '<div class="rf-faq">' . rf_v144_faq_items_html( $faqs ) . "\n</div>" : '';
}

/** Prints a template page's extra sections and FAQs (page-receipt-category.php). */
function rf_v144_category_extras_html( $slug ) {
	$extra = rf_v144_category_extras()[ $slug ] ?? null;
	if ( ! $extra ) {
		return '';
	}
	$html = '';
	foreach ( $extra['sections'] as $section ) {
		$html .= '<h2>' . esc_html( $section[0] ) . '</h2>' . "\n" . $section[1] . "\n";
	}
	if ( $extra['faqs'] ) {
		$html .= "<h2>More Questions</h2>\n" . rf_v144_category_faq_html( $slug ) . "\n";
	}
	return '<div class="rf-v144-extra">' . $html . '</div>';
}
