<?php
/**
 * ReceiptGenPro — Database content patches (Sept 2026 audit C1, C5, H8)
 *
 * The exact text below was read from the live pages on 26 Sept 2026.
 * inc/admin-fixes.php shows a preview and applies these with
 * wp_update_post(), so every change creates a normal WordPress revision
 * (Pages → Edit → Revisions to roll back).
 *
 * A patch only runs when its "find" text is still present, so running the
 * tool twice, or after you have edited a page by hand, is safe.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param array $o Owner details from the admin form:
 *                 owner, role, location, law, reply.
 * @return array slug => list of array( 'find' => string|regex, 'replace' => string, 'regex' => bool, 'label' => string )
 */
function rf_content_patches( $o ) {
	$o = wp_parse_args(
		$o,
		array(
			'owner'    => '',
			'role'     => 'web developer',
			'location' => '',
			'law'      => '',
			'reply'    => '2 business days',
		)
	);
	$today = date_i18n( 'F j, Y' );
	$owner = esc_html( $o['owner'] );
	$loc   = esc_html( $o['location'] );

	// ---------- About Us -------------------------------------------------
	$about_new = '';
	if ( $o['owner'] ) {
		$about_new = "<h2>Who Runs ReceiptGenPro</h2>\n<p>ReceiptGenPro is built and maintained by " . $owner
			. ( $o['role'] ? ', a ' . esc_html( $o['role'] ) : '' )
			. ( $loc ? ' based in ' . $loc : '' )
			. ". I build and update the receipt generator and write the guides on this site, and every guide links to the official tax-authority pages it is based on.</p>\n"
			. '<p>Found a mistake or want a new template? Email <a href="mailto:' . esc_attr( RF_SUPPORT_EMAIL ) . '">' . esc_html( RF_SUPPORT_EMAIL ) . '</a> — I read every message.</p>';
	}

	// ---------- Contact Us -----------------------------------------------
	$contact_new = '';
	if ( $o['owner'] ) {
		$contact_new = "<h2>Who You're Contacting</h2>\n<p>ReceiptGenPro is run by " . $owner . ( $loc ? ' (' . $loc . ')' : '' )
			. '. We usually reply within ' . esc_html( $o['reply'] ) . '. Please don\'t email receipt contents or payment details — the generator works entirely in your browser, so we never need them.</p>';
	}

	$law = $o['law']
		? '<p>These Terms are governed by the laws of ' . esc_html( $o['law'] ) . '. Any dispute will be handled by the competent courts of that jurisdiction, unless the consumer-protection law of your own country gives you the right to bring a claim where you live.</p>'
		: '';

	return array(

		'about-us' => array(
			array(
				'label'   => 'Replace the "[Site owner: …]" note with a real "Who Runs ReceiptGenPro" section',
				'find'    => "<p><em>[Site owner: replace this section with your real founding story, team, and company details before launch.]</em></p>",
				'replace' => $about_new,
			),
			array(
				'label'   => 'Link to the new templates hub',
				'find'    => '<a href="/#templates">purpose-built receipt templates</a>',
				'replace' => '<a href="/receipt-templates/">purpose-built receipt templates</a>',
			),
			array(
				'since'   => '1.1.5',
				'label'   => 'Add "What We Never Store" (how the generator handles your data)',
				'regex'   => true,
				'find'    => '#\z#',
				'unless'  => 'What We Never Store',
				'replace' => rf_about_never_store_content(),
			),
		),

		'contact-us' => array(
			array(
				'label'   => 'Replace the "[Site owner: …]" note with who runs the site + reply time',
				'find'    => '<p><em>[Site owner: add a real business name, and a physical mailing address here if your jurisdiction requires one for a functioning business contact page.]</em></p>',
				'replace' => $contact_new,
			),
			array(
				'since'   => '1.1.5',
				'label'   => 'Add "What to Include in Your Email" and our time zone',
				'regex'   => true,
				'find'    => '#\z#',
				'unless'  => 'What to Include in Your Email',
				'replace' => "\n<h2>What to Include in Your Email</h2>\n<ul>\n<li>The link to the page or template you were using</li>\n<li>If something isn't working: your device and browser (for example \"Android, Chrome\"), and what you clicked</li>\n<li>For a new template: the type of business and the fields its receipt needs</li>\n</ul>\n<p>We're based in " . ( $loc ? $loc : 'Pakistan' ) . ' (Pakistan Standard Time, UTC+5).</p>',
			),
		),

		'terms-and-conditions' => array(
			array(
				'label'   => 'Real "Last updated" date; remove the "have a lawyer review" note',
				'find'    => '<p><em>Last updated: [add the date you publish this page]. Standard-practice boilerplate — have a lawyer review before launch.</em></p>',
				'replace' => '<p><em>Last updated: ' . esc_html( $today ) . '.</em></p>',
			),
			array(
				'label'   => 'Governing law',
				'find'    => '<p><em>[Site owner: state the governing jurisdiction for these terms here.]</em></p>',
				'replace' => $law,
				'skip'    => ! $o['law'],
			),
			array(
				'label'   => 'Acceptable use: say plainly who may issue a receipt',
				'find'    => '<li>Impersonate another person or business without authorization</li>',
				'replace' => "<li>Impersonate another person or business without authorization, including creating a receipt that appears to come from a business you do not own or represent</li>\n<li>Create a receipt to claim a reimbursement, refund, deduction or warranty you are not entitled to</li>",
			),
		),

		'privacy-policy' => array(
			array(
				'label'   => 'Real "Last updated" date; remove the boilerplate / lawyer note',
				'regex'   => true,
				'find'    => '#<p><em>Last updated: \[add the date you publish this page\][^<]*</em></p>#',
				'replace' => '<p><em>Last updated: ' . esc_html( $today ) . '.</em></p>',
			),
			array(
				'label'   => 'Replace the "[Site owner: list every ad network…]" note with an accurate statement',
				'find'    => '<p><em>[Site owner: list every ad network and analytics provider you actually use here once you enable them — for example "Google AdSense" and "Google Analytics" by name — so this section accurately reflects your site.]</em></p>',
				'replace' => '<p>At the moment no advertising network or analytics service is active on this site. If we enable Google AdSense or Google Analytics, we will name them here and they will only load after you accept cookies.</p>',
			),
		),

		'rent-receipt' => array(
			array(
				'label'   => '"Who this is for": tenants prepare, landlord signs',
				'find'    => "and tenants who want to request a receipt from a landlord who doesn't normally provide one — this template covers all four cases with the same simple form.</p>",
				'replace' => "and tenants who prepare a receipt for their landlord to check and sign (for example for an HRA claim in India) — the landlord's signature is what makes it the landlord's receipt.</p>",
			),
			array(
				'label'   => 'FAQ: past-month receipts are issued/signed by the landlord',
				'find'    => 'This generator works just as well for a receipt covering a past month as it does for the current one.</p>',
				'replace' => 'Set the rent period to that month; the landlord should check the details and sign it, since the receipt is their confirmation that the payment was received.</p>',
			),
			array(
				'since'   => '1.1.5',
				'label'   => 'First paragraph: who this page is for + link to the HRA rent receipt (Oct 2026, CNT-05)',
				'regex'   => true,
				'backrefs' => true,
				'find'    => '#(<p class="rf-direct-answer"[^>]*>[\s\S]*?)(</p>)#',
				'unless'  => 'href="/hra-rent-receipt/"',
				'replace' => '$1 This template is for landlords, property managers and roommates in any country. Salaried employees in India claiming House Rent Allowance should use the <a href="/hra-rent-receipt/">HRA rent receipt generator</a> instead, which adds the landlord\'s PAN and prints the amount in rupees and in words.$2',
			),
		),

		'hra-rent-receipt' => array(
			array(
				'since'   => '1.1.5',
				'label'   => 'First paragraph: who this page is for + link to the general rent receipt (Oct 2026, CNT-05)',
				'regex'   => true,
				'backrefs' => true,
				'find'    => '#(<p class="rf-direct-answer"[^>]*>[\s\S]*?)(</p>)#',
				'unless'  => 'Not claiming HRA, or renting outside India?',
				'replace' => '$1 Not claiming HRA, or renting outside India? Use the general <a href="/rent-receipt/">rent receipt generator</a>, made for landlords and property managers in any country.$2',
			),
		),

		'cookie-policy' => array(
			array(
				'label'   => 'Real "Last updated" date',
				'find'    => '<p><em>Last updated: [add the date you publish this page].</em></p>',
				'replace' => '<p><em>Last updated: ' . esc_html( $today ) . '.</em></p>',
			),
			array(
				'label'   => 'Replace the "[Site owner: …]" cookie note with an accurate statement',
				'find'    => '<p><em>[Site owner: once you add Google Analytics/AdSense, list the specific cookies they set here — most ad/analytics providers publish a ready-made list you can copy.]</em></p>',
				'replace' => '<p>At the moment this site does not run analytics or advertising scripts. If we add Google Analytics or Google AdSense, they will only load after you accept cookies, and we will list the specific cookies they set on this page. Google explains its own cookies at <a href="https://policies.google.com/technologies/cookies" rel="noopener" target="_blank">policies.google.com/technologies/cookies</a>.</p>',
			),
		),

		// ---------- C5: issuer framing on the taxi / hotel / restaurant pages
		'taxi-receipt' => array(
			array(
				'label'   => 'Rewrite the page for drivers and cab companies (removes "reconstructing a lost or never-issued receipt" and "use your best estimate")',
				'regex'   => true,
				'find'    => '#^[\s\S]*Business travelers reconstructing a lost or never-issued taxi receipt[\s\S]*$#',
				'replace' => rf_taxi_page_content(),
			),
		),

		'hotel-receipt' => array(
			array(
				'label'   => 'Intro: written for hotels issuing folios, not for guests recreating lost ones',
				'find'    => "<p>Most hotels email or print a folio automatically at checkout, but there are common situations where you need to recreate or generate one yourself: a paper receipt was lost before an expense report was filed, a booking was made through a third party that didn't provide an itemized breakdown, or a small guesthouse or short-term rental simply doesn't have a billing system that produces one. Whatever the reason, an itemized hotel receipt is usually required for business travel reimbursement and often for tax deduction of travel expenses.</p>",
				'replace' => "<p>Large hotels print a folio from their property-management system at checkout. Small guesthouses, B&amp;Bs, homestays and short-term rental hosts often don't have one — yet business guests need an itemized receipt showing the nights, rate and taxes to claim their stay. This template lets you, the host, issue that folio in a minute. (In the US, for example, the IRS expects a receipt for lodging expenses regardless of the amount.)</p>",
			),
			array(
				'label'   => '"Who this is for": hosts only',
				'find'    => "<p>Business travelers who need to recreate a lost receipt for expense reporting, small guesthouses, B&amp;Bs and short-term rental hosts who don't have a property-management system, and travel coordinators preparing documentation for a finance team all use this template to produce a corporate-expense-ready receipt in one pass.</p>",
				'replace' => "<p>Small hotels, guesthouses, B&amp;Bs, homestays and short-term rental hosts who don't have a property-management system, and hotel front-desk staff who need to reissue a folio for a guest. If you are a guest who lost a hotel receipt, ask the hotel to email you a copy of your folio — they keep it on file.</p>",
			),
			array(
				'label'   => 'FAQ: accepted for reimbursement → issued by the hotel',
				'find'    => "<h3>Will this hotel receipt be accepted for expense reimbursement?</h3>\n<p>Most corporate expense systems accept any itemized receipt showing the hotel name, dates, room charges, taxes and total — this generator is formatted to include all of those fields. Always check your specific employer's expense policy for any additional requirements.</p>",
				'replace' => "<h3>What should a hotel include on a guest's receipt?</h3>\n<p>The hotel's name and address, the guest's name, room number, check-in and check-out dates, nights × nightly rate, each tax or fee on its own line, the total, and how it was paid. This template has a field for each, so your guests can file the receipt with their employer without coming back to you for a corrected copy.</p>",
			),
		),

		'restaurant-receipt' => array(
			array(
				'label'   => 'Intro: remove "reconstructing a lost receipt for an expense claim"',
				'find'    => "<p>A printed or POS-generated receipt covers most situations, but there are times you need to create one manually: reconstructing a lost receipt for an expense claim, a small café or pop-up stand that doesn't have a point-of-sale system yet, or a caterer who needs to issue a formal itemized bill after an event. This template produces the same line-by-line format diners expect from a printed restaurant check.</p>",
				'replace' => "<p>A POS system prints receipts automatically, but plenty of food businesses don't have one: a small café or pop-up stand that is just starting, a food truck at a weekend market, or a caterer who needs to issue a formal itemized bill after a private event. This template lets you issue the same line-by-line receipt diners expect from a printed restaurant check, with tax and tip on their own lines.</p>",
			),
			array(
				'label'   => '"Who this is for": remove diners/employees recreating lost receipts',
				'find'    => "<p>Independent restaurants and food trucks without a full POS system, caterers issuing a formal bill after private events, and diners or employees who need to recreate a lost receipt for an expense report all use this template to produce a properly itemized restaurant receipt.</p>",
				'replace' => "<p>Independent restaurants, cafés and food trucks without a full POS system, caterers issuing a formal bill after private events, and restaurant staff who need to reissue a copy of a customer's bill. If you are a diner who lost a receipt, ask the restaurant for a duplicate — most POS systems can reprint one.</p>",
			),
			array(
				'label'   => 'How-to step: tip now has its own field',
				'find'    => '<li><strong>Note the tip, if applicable.</strong> Add it as a line item or in the notes field, depending on how your business records gratuities.</li>',
				'replace' => '<li><strong>Add the tip, if any.</strong> Enter it in the Tip field — it prints on its own line between tax and the total, like a printed restaurant check.</li>',
			),
			array(
				'label'   => 'FAQ: tax and tip are separate fields now',
				'find'    => '<p>Yes — tax is calculated automatically from the rate you set, and you can note the tip either as its own item line or in the notes section, whichever matches how your business usually presents it.</p>',
				'replace' => '<p>Yes — tax is calculated automatically from the rate you set, and the tip has its own field, so both print as separate lines above the total.</p>',
			),
			array(
				'label'   => 'FAQ: server / table fields exist now',
				'find'    => "<p>You can add either in the notes field if it's relevant to your record-keeping — most restaurants use this for tip reconciliation at shift end.</p>",
				'replace' => '<p>Yes — the form has Table No., Server and Time fields, which print in the receipt header. Most restaurants use them for tip reconciliation at the end of a shift.</p>',
			),
			array(
				'label'   => 'FAQ: "Can I recreate a receipt for a meal I already paid for?" → reissuing a copy',
				'find'    => "<h3>Can I recreate a receipt for a meal I already paid for?</h3>\n<p>Yes, as long as you have the details (items, prices, date). This is commonly used to reconstruct a lost restaurant receipt needed for an expense claim.</p>",
				'replace' => "<h3>A customer lost their receipt — can I give them a copy?</h3>\n<p>Yes. As the restaurant, you can reissue a copy from your own order records: use the original date, items and prices, and write \"Duplicate copy\" in the notes. Only the business that was paid should issue a receipt for a meal.</p>",
			),
		),
	);
}

/**
 * Full replacement body for /taxi-receipt/ — written for the driver or cab
 * company issuing the receipt (audit C5). Uses the theme's conventions
 * (.rf-direct-answer, .rf-howto-steps, .rf-faq) so schema still works.
 */
function rf_taxi_page_content() {
	return <<<'HTML'
<p class="rf-direct-answer">A taxi receipt is the proof of payment a driver or cab company gives a passenger after a ride: pickup and drop-off points, date and time, distance, fare, any tip or surcharge, and the vehicle or license number. Fill in the form above to issue one in under a minute and hand it over as a PDF, an image on WhatsApp, or a printout.</p>

<h2>Why Drivers and Cab Companies Should Offer Receipts</h2>
<p>Ride-hailing apps email receipts automatically, but street-hail taxis, airport transfer services, private-hire drivers and small cab companies often don't have a system that does. Business passengers usually need an itemized receipt to claim the fare from their employer, and many will choose — and re-book — the driver who can provide one on the spot. A clear receipt with your vehicle number and contact details is also free advertising.</p>

<h2>How to Issue a Taxi Receipt</h2>
<ol class="rf-howto-steps">
<li><strong>Enter your name or company.</strong> Add your taxi company name or your own name as the driver, and your logo if you have one.</li>
<li><strong>Add the vehicle and trip details.</strong> Vehicle or license number, pickup, drop-off, date and time, and distance.</li>
<li><strong>Enter the fare as a line item.</strong> Put surcharges (airport, tolls, night rate) on separate lines and any tip in the Tip field.</li>
<li><strong>Choose the payment method.</strong> Cash, card or online payment, exactly as the passenger paid.</li>
<li><strong>Send or print it.</strong> Download as PDF, PNG or JPEG — JPEG is the easiest to send over WhatsApp.</li>
</ol>

<h2>What Should a Taxi Receipt Include?</h2>
<ul>
<li>Driver name or taxi company name and contact details</li>
<li>Vehicle registration or license number</li>
<li>Pickup and drop-off locations</li>
<li>Trip date and time, and distance travelled</li>
<li>Fare, surcharges and tip on separate lines</li>
<li>Total paid and payment method</li>
</ul>

<h2>Who This Template Is For</h2>
<p>Independent taxi drivers, private-hire and chauffeur services, airport transfer operators and small cab companies that want to hand passengers a proper receipt on request. If you are a passenger who didn't get a receipt, ask the driver or taxi company for one, or use your employer's missing-receipt process — a passenger shouldn't create a receipt on the driver's behalf.</p>
<p>Keeping your own copies for your accounts? See <a href="/how-long-should-you-keep-business-receipts/">how long to keep business receipts</a>.</p>

<div class="rf-faq">
<h3>Do I have to give a passenger a receipt?</h3>
<p>Rules vary by city and licensing authority — many taxi licenses require drivers to provide a receipt when the passenger asks. Check your local licensing conditions; offering one every time is good practice either way.</p>

<h3>How do I send a taxi receipt on WhatsApp?</h3>
<p>Fill in the form, click Download JPEG, and share the image from your phone's gallery in the chat. See our guide on <a href="/how-to-send-receipts-to-customers-via-whatsapp/">sending receipts via WhatsApp</a>.</p>

<h3>Should I number my taxi receipts?</h3>
<p>Yes. A simple sequence such as 2026-001, 2026-002 makes your own bookkeeping easier and helps if a passenger or their employer asks you to confirm a trip. See our <a href="/receipt-numbering-system-how-to-number-receipts/">receipt numbering guide</a>.</p>

<h3>Does this work for private car services and airport transfers?</h3>
<p>Yes — the same fields work for private-hire cars, chauffeur and limo services, and airport transfers, not just metered taxis.</p>
</div>
HTML;
}

/**
 * Applies one patch to a post body. Returns array( new content, status ),
 * status being 'applied', 'not-found' or 'skipped'.
 */
function rf_fixes_apply_patch( $content, $patch ) {
	if ( ! empty( $patch['skip'] ) ) {
		return array( $content, 'skipped' );
	}
	// 'unless': the patch has already been applied (or written by hand).
	if ( ! empty( $patch['unless'] ) && false !== strpos( $content, $patch['unless'] ) ) {
		return array( $content, 'not-found' );
	}
	if ( ! empty( $patch['regex'] ) ) {
		if ( ! preg_match( $patch['find'], $content ) ) {
			return array( $content, 'not-found' );
		}
		// 'backrefs' keeps $1 etc. in the replacement working.
		$replace = empty( $patch['backrefs'] ) ? str_replace( '$', '\$', $patch['replace'] ) : $patch['replace'];
		return array( preg_replace( $patch['find'], $replace, $content, 1 ), 'applied' );
	}
	if ( false === strpos( $content, $patch['find'] ) ) {
		return array( $content, 'not-found' );
	}
	return array( str_replace( $patch['find'], $patch['replace'], $content ), 'applied' );
}

/**
 * One-time migration (Oct 2026 audit, Phase 5). The owner approved these
 * DB changes going live with the deploy instead of being clicked in
 * Tools → ReceiptGenPro Fixes. It applies only the patches marked
 * 'since' => '1.1.5' plus the shorter SEO titles, through wp_update_post()
 * (so each page keeps a revision), and runs once.
 *
 * Audit 20 Oct (#47): it runs from inc/setup-tasks.php (admin or WP-Cron),
 * not in a visitor request. Each finished slug is recorded, so a run that
 * died part-way (a 'started' older than 10 minutes) resumes where it
 * stopped instead of blocking for good or patching a page twice.
 */
function rf_migration_done() {
	$state = get_option( 'rf_migration_phase5' );
	return is_array( $state ) && ! empty( $state['done'] );
}

function rf_migrate_phase5_content() {
	if ( rf_migration_done() || wp_installing() ) {
		return;
	}
	$state = get_option( 'rf_migration_phase5' );
	if ( is_array( $state ) ) {
		$started = isset( $state['started'] ) ? strtotime( $state['started'] ) : 0;
		if ( $started && time() - $started < 10 * MINUTE_IN_SECONDS ) {
			return; // another run is still going
		}
		$state['started'] = gmdate( 'c' );
		update_option( 'rf_migration_phase5', $state, true );
	} else {
		$state = array( 'started' => gmdate( 'c' ), 'slugs' => array(), 'log' => array() );
		if ( ! add_option( 'rf_migration_phase5', $state, '', true ) ) {
			return;
		}
	}
	$state += array( 'slugs' => array(), 'log' => array() );
	$log    = $state['log'];
	$record = function ( $slug ) use ( &$state, &$log ) {
		$state['slugs'][] = $slug;
		$state['log']     = $log;
		update_option( 'rf_migration_phase5', $state, true );
	};

	// The visitor who triggers this may not have unfiltered_html; the HTML
	// below is the theme's own, so kses must not rewrite it.
	$kses = (bool) has_filter( 'content_save_pre', 'wp_filter_post_kses' );
	kses_remove_filters();

	foreach ( rf_content_patches( get_option( 'rf_owner_details', array() ) ) as $slug => $list ) {
		$list = array_filter(
			$list,
			function ( $patch ) {
				return isset( $patch['since'] ) && '1.1.5' === $patch['since'];
			}
		);
		if ( ! $list || in_array( $slug, $state['slugs'], true ) ) {
			continue;
		}
		$found = get_posts( array( 'name' => $slug, 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1 ) );
		if ( ! $found ) {
			$log[] = "/$slug/: page not found";
			$record( $slug );
			continue;
		}
		$content = $found[0]->post_content;
		foreach ( $list as $patch ) {
			list( $content, $status ) = rf_fixes_apply_patch( $content, $patch );
			$log[] = "/$slug/: {$patch['label']}: $status";
		}
		if ( $content !== $found[0]->post_content ) {
			wp_update_post( array( 'ID' => $found[0]->ID, 'post_content' => wp_slash( $content ) ) );
		}
		$record( $slug );
	}

	// Setting a meta value twice is harmless, so the titles need no record.
	foreach ( rf_seo_title_patches() as $slug => $title ) {
		$found = get_posts( array( 'name' => $slug, 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1 ) );
		if ( $found ) {
			update_post_meta( $found[0]->ID, 'rf_seo_title', $title );
			$log[] = "/$slug/: SEO title set";
		} else {
			$log[] = "/$slug/: post not found";
		}
	}

	if ( $kses ) {
		kses_init_filters();
	}
	update_option( 'rf_migration_phase5', array( 'done' => gmdate( 'c' ), 'log' => $log ), true );
	do_action( 'litespeed_purge_all' );
}

/**
 * Shorter SEO titles (Oct 2026 audit, SEO-07): each is 60 characters or
 * less so search results show it in full. Stored in the rf_seo_title
 * custom field by Tools → ReceiptGenPro Fixes → 6; the on-page H1 stays.
 *
 * @return array post slug => SEO title
 */
function rf_seo_title_patches() {
	return array(
		'how-long-should-you-keep-business-receipts'                        => 'How Long to Keep Business Receipts: Rules by Country',
		'digital-receipts-vs-paper-receipts'                                => 'Digital vs Paper Receipts: Pros, Cons & Legal Validity',
		'receipt-templates-word-vs-excel-vs-google-docs-vs-online-generator' => 'Receipt Templates: Word vs Excel vs Google Docs vs Online',
		// Audit 20 Oct (#12). seo-meta.php also reads this list when a post
		// has no rf_seo_title of its own, so these work without a DB write.
		'receipt-requirements-for-small-businesses-in-the-usa'              => 'Receipt Requirements for US Small Businesses (IRS Rules)',
		'how-to-create-a-donation-receipt-for-nonprofits'                   => 'How to Create a Nonprofit Donation Receipt (+ Tax Rules)',
	);
}

/**
 * "What We Never Store" section appended to About Us (Oct 2026 audit,
 * CNT-06). Every sentence matches what receipt-generator.js actually does.
 */
function rf_about_never_store_content() {
	return <<<'HTML'

<h2>What We Never Store</h2>
<p>There are no accounts on ReceiptGenPro, so there is nothing to sign up for and no receipt history kept on our side. Here is what happens to what you type:</p>
<ul>
<li><strong>Your receipt details</strong> (names, items, prices, notes) are used only to draw the preview in your browser. They are never sent to our server.</li>
<li><strong>Your logo</strong> is read by your browser and shown in the preview. It is not uploaded.</li>
<li><strong>A reload doesn't lose your work:</strong> the form, and your logo, are kept in your browser tab's session storage, which is cleared when you close the tab.</li>
HTML
	. "\n" . rf_about_downloads_item() . "\n</ul>\n";
}

/**
 * The "Your downloads" line of "What We Never Store" (audit 20 Oct, #62).
 * The 1.1.5 text said two libraries load only on download, but the QR
 * library loads as soon as the preview draws its verification QR code,
 * which is on by default. Pages that already have the old line get this
 * one through rf_migrate_v135_content().
 */
function rf_about_downloads_item() {
	return '<li><strong>Your downloads</strong> (PDF, PNG or JPEG) and the receipt\'s QR code are created on your own device, with three open-source libraries from the cdnjs CDN. qrcode-generator loads with the generator while "Add a verification QR code" is ticked (it is by default) and draws the code in your browser. html2canvas and jsPDF load only when you click a download button, and build the file locally. We never see or keep a copy.</li>';
}

/** The 1.1.5 line that rf_about_downloads_item() replaces. */
function rf_about_downloads_item_v115() {
	return '<li><strong>Your downloads</strong> (PDF, PNG or JPEG) are created on your own device. When you click a download button, your browser fetches two open-source libraries, html2canvas and jsPDF, from the cdnjs CDN and builds the file locally. We never see or keep a copy.</li>';
}

/**
 * One-time patch of theme 1.3.5 (audit 20 Oct, #62): replaces the old
 * "Your downloads" line on About Us. Same rules as the Phase 5 migration
 * above: runs from inc/setup-tasks.php (admin or WP-Cron), through
 * wp_update_post() so the page keeps a revision, and records itself in its
 * own option, rf_migration_v135, so it never runs twice.
 */
function rf_migration_v135_done() {
	$state = get_option( 'rf_migration_v135' );
	return is_array( $state ) && ! empty( $state['done'] );
}

function rf_migrate_v135_content() {
	if ( rf_migration_v135_done() || wp_installing() ) {
		return;
	}
	$state = get_option( 'rf_migration_v135' );
	if ( is_array( $state ) && isset( $state['started'] ) && time() - strtotime( $state['started'] ) < 10 * MINUTE_IN_SECONDS ) {
		return; // another run is still going
	}
	if ( is_array( $state ) ) {
		update_option( 'rf_migration_v135', array( 'started' => gmdate( 'c' ) ), true );
	} elseif ( ! add_option( 'rf_migration_v135', array( 'started' => gmdate( 'c' ) ), '', true ) ) {
		return;
	}

	$log   = array();
	$found = get_posts( array( 'name' => 'about-us', 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1 ) );
	if ( ! $found ) {
		$log[] = '/about-us/: page not found';
	} else {
		list( $content, $status ) = rf_fixes_apply_patch(
			$found[0]->post_content,
			array(
				'find'    => rf_about_downloads_item_v115(),
				'unless'  => 'three open-source libraries',
				'replace' => rf_about_downloads_item(),
			)
		);
		$log[] = "/about-us/: name the three libraries: $status";
		if ( $content !== $found[0]->post_content ) {
			$kses = (bool) has_filter( 'content_save_pre', 'wp_filter_post_kses' );
			kses_remove_filters();
			wp_update_post( array( 'ID' => $found[0]->ID, 'post_content' => wp_slash( $content ) ) );
			if ( $kses ) {
				kses_init_filters();
			}
		}
	}
	update_option( 'rf_migration_v135', array( 'done' => gmdate( 'c' ), 'log' => $log ), true );
	do_action( 'litespeed_purge_all' );
}

/**
 * Editorial Policy page (audit H8). Created as a DRAFT by the admin tool so
 * the owner can read it before publishing — it describes commitments the
 * site then has to keep.
 */
function rf_editorial_policy_content( $owner = '' ) {
	$by = $owner ? esc_html( $owner ) : 'the ReceiptGenPro team';
	return <<<HTML
<p class="rf-direct-answer">ReceiptGenPro's guides explain receipts, record-keeping and the basic tax rules around them for small businesses. This page explains who writes them, which sources we rely on, how often we check them, and how to report a mistake.</p>

<h2>Who Writes Our Guides</h2>
<p>Guides are researched and written by {$by}. We are not a law or accounting firm, and our guides are general information, not tax, legal or accounting advice. When a guide has been reviewed by a qualified professional, their name and qualification are shown on the guide; we never display a reviewer who has not actually reviewed it.</p>

<h2>Sources We Use</h2>
<p>Every rule, threshold or deadline we state comes from the official source that sets it — for example the IRS (United States), HMRC and GOV.UK (United Kingdom), the Canada Revenue Agency, the Australian Taxation Office, and the GST authorities in India. Each guide ends with a "Key facts &amp; official sources" box linking to the exact pages we used and the date we last checked them.</p>

<h2>How We Keep Guides Up to Date</h2>
<ul>
<li>We re-check every guide's sources at least once a year, and sooner when a tax authority announces a change.</li>
<li>The "Updated" date on a guide changes only when we actually change its content.</li>
<li>If a rule has changed and we haven't updated a guide yet, the official source linked on the page always takes priority.</li>
</ul>

<h2>How the Receipt Generator Is Built</h2>
<p>The generator runs entirely in your browser: what you type is never uploaded to our servers. Each template's fields are based on what that type of receipt normally needs to show (for example the tenant, property and rent period on a rent receipt, or the goods-or-services statement on a donation receipt).</p>

<h2>Responsible Use</h2>
<p>ReceiptGenPro is for receipts a business or person issues for its own real transactions. We don't publish templates that imitate specific brands, and our <a href="/terms-and-conditions/">Terms</a> prohibit creating a receipt on behalf of a business you don't own or for a payment that didn't happen.</p>

<h2>Report an Error</h2>
<p>Spotted something wrong or out of date? Email <a href="mailto:support@receiptgenpro.com">support@receiptgenpro.com</a> with the page link and the official source, and we'll review it and correct the guide if needed.</p>
HTML;
}
