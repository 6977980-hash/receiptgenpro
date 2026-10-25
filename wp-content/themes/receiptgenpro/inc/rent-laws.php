<?php
/**
 * ReceiptGenPro — Rent receipt laws by US state (v1.2.0), and since 1.4.2
 * by city and outside the US
 *
 * Feeds /rent-receipt-laws/ and one page per state, city, country and
 * province (created automatically by inc/feature-pages.php from
 * rf_rent_law_entries()). A place is listed ONLY after its statute or
 * ordinance text was read on an official or verbatim source; 'url' is the
 * text that was read and 'checked' the date. Adding one here creates its
 * page on the next setup run.
 *
 * Keys: state, citation, url (the text that was read), when_short (hub
 * table), trigger, contents (what the receipt must show, per the text),
 * records, penalty, details (extra paragraphs), summary (the page's direct
 * answer, and the meta description unless 'meta' is set), meta (optional
 * meta description of 155 characters or less), short (optional short name
 * for the SEO title, e.g. "DC"), checked, general (optional: true when
 * the only rule is a general one, not landlord-specific; such a state is
 * listed but not counted as having a landlord rule), deposit_only (1.4.2:
 * true when the only receipt rule is for a deposit; 'receipts' then lists
 * each receipt with its title and items, and the page carries the deposit
 * receipt generator).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function rf_rent_receipt_laws() {
	return array(
		'california' => array(
			'state'      => 'California',
			'citation'   => 'Cal. Civil Code § 1499',
			'url'        => 'https://california.public.law/codes/civil_code_section_1499',
			'when_short' => 'On request (general rule for paying any debt)',
			'trigger'    => 'On request (general debtor-creditor rule, not landlord-specific): a debtor may require a written receipt from the creditor.',
			'contents'   => array(  ),
			'records'    => '',
			'penalty'    => '',
			'details'    => array(
				'Civ. Code § 1962 requires the lease to disclose who rent is paid to and the accepted payment forms; § 1947.3 requires landlords to accept at least one form of payment that is neither cash nor electronic funds transfer (no receipt duty in either).',
			),
			'summary'    => 'California has no landlord-specific rent-receipt statute, but Civil Code § 1499 lets anyone who pays a debt, including a tenant paying rent, demand a written receipt.',
			'meta'       => 'California has no landlord rent-receipt statute, but Civil Code § 1499 lets anyone paying a debt, including a tenant paying rent, demand a written receipt.',
			'checked'    => 'October 2026',
			// Not counted on the hub as a landlord rule (audit 20 Oct, #65).
			'general'    => true,
		),
		'colorado' => array(
			'state'      => 'Colorado',
			'citation'   => 'C.R.S. § 38-12-802',
			'url'        => 'https://colorado.public.law/statutes/crs_38-12-802',
			'when_short' => 'In-person cash or money order; anything else on request',
			'trigger'    => 'Automatic for payments made in person with cash or money order; on request for any other payment (unless an existing procedure already gives the tenant a record showing amount, recipient and date).',
			'contents'   => array( 'Amount paid', 'Date of payment', 'Recipient (for receipts given on request)' ),
			'records'    => '',
			'penalty'    => '',
			'details'    => array(
				'Timing: Contemporaneously for in-person cash/money order; within 7 days after the request otherwise.',
				'May be electronic unless the tenant asks for paper; may be part of a billing statement.',
			),
			'summary'    => 'Colorado landlords must immediately give a receipt for in-person cash or money-order payments, and within seven days of a request for any other payment.',
			'checked'    => 'October 2026',
		),
		'connecticut' => array(
			'state'      => 'Connecticut',
			'citation'   => 'Conn. Gen. Stat. § 47a-3a(c)-(d)',
			'url'        => 'https://prdext3.cga.ct.gov/2026/sup/chap_830.htm',
			'when_short' => 'Cash payments',
			'trigger'    => 'Cash payments, from or on behalf of an occupant; any payment type is covered by the ledger request in (d).',
			'contents'   => array( 'Date of the payment', 'Amount received', 'Purpose for which the payment was made' ),
			'records'    => 'Since July 1, 2025 (subsec. (d)): on an occupant\'s request the landlord must provide a copy of the ledger or equivalent accounting for the unit showing charges assessed, payments made and any balance owed or surplus.',
			'penalty'    => '',
			'details'    => array(
				'Timing: Upon receipt of the cash payment.',
				'Public Act 25-146 (in force 1 July 2025) added the ledger-on-request rule.',
			),
			'summary'    => 'Connecticut landlords must give a receipt showing date, amount and purpose for every cash payment, and since July 2025 must provide a copy of the tenant\'s ledger on request.',
			'meta'       => 'Connecticut landlords must give a receipt with date, amount and purpose for cash payments, and since July 2025 a copy of the tenant\'s ledger on request.',
			'checked'    => 'October 2026',
		),
		'delaware' => array(
			'state'      => 'Delaware',
			'citation'   => '25 Del. C. § 5501(e)',
			'url'        => 'https://delcode.delaware.gov/title25/c055/index.html',
			'when_short' => 'Cash payments, within 15 days',
			'trigger'    => 'Cash payment for rent.',
			'contents'   => array(  ),
			'records'    => 'Landlord must keep a record of all cash receipts for rent for 3 years.',
			'penalty'    => '',
			'details'    => array(
				'Timing: Within 15 days.',
				'25 Del. C. § 5514(d): landlord must furnish a receipt for an application fee and keep application-fee records 2 years.',
			),
			'summary'    => 'Delaware landlords must give a receipt within 15 days for any cash rent payment and keep records of cash receipts for three years.',
			'checked'    => 'October 2026',
		),
		'district-of-columbia' => array(
			'state'      => 'District of Columbia',
			'short'      => 'DC',
			'citation'   => '14 DCMR § 306 (D.C. Housing Regulations)',
			'url'        => 'https://dcregs.dc.gov/Common/DCMR/SectionList.aspx?SectionNumber=14-306',
			'when_short' => 'Every payment except a personal check',
			'trigger'    => 'All money paid by the tenant (rent, security deposit or otherwise) unless paid by personal check. Even for personal-check payments, a receipt is required if a balance remains for late charges, court costs or other charges beyond rent.',
			'contents'   => array( 'Exact amount received', 'Date the money was received', 'Purpose of the payment', 'Any amounts still due for late charges, court costs or other charges in excess of rent' ),
			'records'    => '',
			'penalty'    => '',
			'details'    => array(
				'This is a D.C. housing regulation rather than a statute, but it binds landlords the same way.',
			),
			'summary'    => 'D.C. landlords must give a written receipt showing amount, date and purpose for every payment other than a personal check, and must list any outstanding fees.',
			'meta'       => 'D.C. landlords must give a written receipt showing amount, date and purpose for any payment other than a personal check, and list any outstanding fees.',
			'checked'    => 'October 2026',
		),
		'hawaii' => array(
			'state'      => 'Hawaii',
			'citation'   => 'Haw. Rev. Stat. § 521-43(e)',
			'url'        => 'https://data.capitol.hawaii.gov/hrscurrent/Vol12_Ch0501-0588/HRS0521/HRS_0521-0043.htm',
			'when_short' => 'Every payment (a canceled check counts)',
			'trigger'    => 'Any rent payment (automatic). A canceled check counts as the receipt; if rent is paid by check, a separate receipt is required only if the tenant requests one.',
			'contents'   => array(  ),
			'records'    => '',
			'penalty'    => '',
			'details'    => array(
				'Timing: At the time of payment.',
			),
			'summary'    => 'Hawaii landlords must give a written receipt for rent at the time of payment, though a canceled check counts unless the tenant asks for a receipt.',
			'checked'    => 'October 2026',
		),
		'maine' => array(
			'state'      => 'Maine',
			'citation'   => '14 M.R.S. § 6022',
			'url'        => 'https://legislature.maine.gov/statutes/14/title14sec6022.html',
			'when_short' => 'Cash payments (not small owner-occupied buildings)',
			'trigger'    => 'Each rent or security deposit payment received partly or fully in cash.',
			'contents'   => array( 'Date of payment', 'Amount paid', 'Name of the party for whom payment is made', 'Period for which payment is made', 'Statement that the payment is for rent or for security deposit', 'Signature of the person receiving payment', 'Printed name of that person' ),
			'records'    => '',
			'penalty'    => '',
			'details'    => array(
				'Timing: At the time the cash payment is accepted; a separate receipt for each installment, and separate receipts for rent and deposit paid together.',
				'Does not apply to a building with 5 or fewer units where one unit is occupied by the landlord.',
				'A rent card kept by the tenant with the same information satisfies the section.',
			),
			'summary'    => 'Maine landlords must hand over a detailed signed receipt at the time of every cash rent or deposit payment, except in owner-occupied buildings of five or fewer units.',
			'meta'       => 'Maine landlords must give a detailed signed receipt for every cash rent or deposit payment, except in owner-occupied buildings of five or fewer units.',
			'checked'    => 'October 2026',
		),
		'maryland' => array(
			'state'      => 'Maryland',
			'citation'   => 'Md. Code, Real Property § 8-205',
			'url'        => 'https://mgaleg.maryland.gov/mgawebsite/Laws/StatuteText?article=grp&section=8-205&enactments=false',
			'when_short' => 'Cash payments; any payment on request',
			'trigger'    => 'Automatic for cash payments; on request for any other payment. (Anne Arundel County: receipt required for every payment unless the tenant pays by check, or the property is commercial.)',
			'contents'   => array(  ),
			'records'    => '',
			'penalty'    => 'Landlord liable to tenant for $25 for failing to give a required written receipt, in addition to any other penalty; in Anne Arundel County, on conviction the landlord forfeits the rent for the period in question.',
			'details'    => array(
				'Anne Arundel County has a stricter local rule: a receipt for every payment unless the tenant pays by check, showing the payment and the period it covers.',
			),
			'summary'    => 'Maryland landlords must give a receipt when a tenant pays in cash or asks for one, and owe the tenant $25 if they don\'t.',
			'checked'    => 'October 2026',
		),
		// 1.4.2 (G-6): its own page; a deposit rule, not a monthly rent rule.
		'massachusetts' => array(
			'state'        => 'Massachusetts',
			'citation'     => 'M.G.L. c. 186, § 15B',
			'url'          => 'https://malegislature.gov/Laws/GeneralLaws/PartII/TitleI/Chapter186/Section15B',
			'deposit_only' => true,
			'when_short'   => 'Security deposit and last month\'s rent paid in advance',
			'trigger'      => 'Only for money paid at or before the start of a tenancy: a receipt at the time a security deposit or last month\'s rent in advance is paid, and a second receipt within 30 days after the deposit is received, naming the bank that holds it. There is no general rule for monthly rent.',
			'contents'     => array(),
			'receipts'     => array(
				array(
					'title' => 'Security deposit receipt (at the time of payment, § 15B(2)(b))',
					'items' => array( 'Amount of the security deposit', 'Name of the person receiving it and, for an agent, the name of the landlord for whom it is received', 'Date it was received', 'Description of the premises leased or rented', 'Signature of the person receiving the deposit' ),
				),
				array(
					'title' => 'Last month\'s rent paid in advance (at the time of payment, § 15B(2)(a))',
					'items' => array( 'Amount of the rent', 'Date it was received', 'That it is to be applied as rent for the last month of the tenancy', 'Name of the person receiving it and, for an agent, the name of the landlord for whom it is received', 'Description of the rented premises', 'A statement that the tenant is entitled to interest on it at 5% a year, or the lesser amount the bank paid', 'A statement that the tenant should give the landlord a forwarding address at the end of the tenancy, for the interest' ),
				),
				array(
					'title' => 'Bank receipt (within 30 days after the deposit is received, § 15B(3)(a))',
					'items' => array( 'Name and location of the bank holding the security deposit', 'Amount of the deposit', 'Account number' ),
				),
			),
			'records'      => 'A landlord who takes a security deposit must keep a record of it, including copies of every receipt and statement of condition given to the tenant, for two years after the tenancy ends, and show it to the tenant on request (§ 15B(2)(d)).',
			'penalty'      => 'If the bank receipt is not given within 30 days, the tenant is entitled to the immediate return of the security deposit (§ 15B(3)(a)); the same applies if the landlord wrongly refuses to show the deposit record (§ 15B(2)(d)). A landlord who does not hold the deposit in a separate bank account, or does not return it within 30 days after the tenancy ends, owes three times the deposit plus 5% interest, court costs and attorney\'s fees (§ 15B(6)–(7)).',
			'details'      => array(
				'At the start of a tenancy a landlord may collect no more than the first month\'s rent, the last month\'s rent, a security deposit of up to one month\'s rent and the cost of a new lock and key (§ 15B(1)(b)).',
				'A landlord who takes a security deposit must also give the tenant a signed statement of the condition of the premises, at the time of payment or within 10 days after the tenancy starts, whichever is later (§ 15B(2)(c)).',
				'Each year of the tenancy the landlord owes the tenant interest on the security deposit and on last month\'s rent paid in advance, at 5% or the lesser rate the bank paid (§ 15B(2)(a), (3)(b)).',
			),
			'summary'      => 'Massachusetts has no general rule for monthly rent receipts, but a landlord must give a signed receipt for a security deposit, a receipt for last month\'s rent paid in advance, and a bank receipt within 30 days.',
			'meta'         => 'Massachusetts landlords must give receipts for a security deposit and last month\'s rent paid in advance, and a bank receipt within 30 days.',
			'checked'      => 'October 2026',
		),
		'minnesota' => array(
			'state'      => 'Minnesota',
			'citation'   => 'Minn. Stat. § 504B.118 (subd. 2 as renumbered by 2026 Minn. Laws ch. 81, § 2)',
			'url'        => 'https://www.revisor.mn.gov/statutes/cite/504B.118',
			'when_short' => 'Cash payments',
			'trigger'    => 'Cash payments (rent or other payments).',
			'contents'   => array(  ),
			'records'    => '',
			'penalty'    => 'Effective Aug 1, 2026 (subd. 4): tenant has an affirmative defense to a non-payment eviction if the landlord violates § 504B.118; the case must be dismissed and the tenant gets reasonable attorney fees and other equitable relief.',
			'details'    => array(
				'Timing: Immediately if paid in person; within 3 business days if cash is not paid in person.',
				'Updated in 2026 (Laws 2026, ch. 81, in force 1 August 2026): the cash-receipt rule is unchanged, and a landlord who breaks this section now gives the tenant a defense to a non-payment eviction, with attorney fees.',
			),
			'summary'    => 'Minnesota landlords must give a written receipt for cash payments, right away if paid in person or within three business days otherwise.',
			'checked'    => 'October 2026',
		),
		'nevada' => array(
			'state'      => 'Nevada',
			'citation'   => 'NRS 118A.250',
			'url'        => 'https://www.leg.state.nv.us/NRS/NRS-118A.html',
			'when_short' => 'Any payment, on request',
			'trigger'    => 'On request, for any payment: security deposit, surety bond, rent, other payments, deposits or fees.',
			'contents'   => array(  ),
			'records'    => '',
			'penalty'    => 'Tenant may refuse to make rent payments until the landlord tenders the requested receipt.',
			'details'    => array(
			),
			'summary'    => 'Nevada landlords must give a signed written receipt for rent or any other payment when the tenant asks, and the tenant may withhold rent until they do.',
			'checked'    => 'October 2026',
		),
		'new-jersey' => array(
			'state'      => 'New Jersey',
			'citation'   => 'N.J.S.A. 46:8-49.2 (P.L. 2019, c. 300, § 3)',
			'url'        => 'https://pub.njleg.gov/bills/2018/PL19/300_.HTM',
			'when_short' => 'Cash payments (most buildings with 3+ units)',
			'trigger'    => 'Each cash payment for any amount due under a residential lease, renewal or extension.',
			'contents'   => array( 'Amount of the payment', 'Purpose of the payment', 'When the payment was received', 'Printed or typed names of both landlord and tenant', 'Who accepted the payment' ),
			'records'    => '',
			'penalty'    => 'Penalties under N.J.S.A. 46:8-47, plus $100 more for a second violation and $200 more for each later violation within 5 years. Violation is a valid defense to a non-payment eviction for the months in which it occurred.',
			'details'    => array(
				'\'Landlord\' (N.J.S.A. 46:8-44) excludes rental premises with 2 or fewer units, owner-occupied premises with 3 or fewer units, and hotels/motels/transient housing. Applies to leases, renewals or extensions executed on or after Jan. 13, 2020.',
				'Printed or emailed.',
			),
			'summary'    => 'New Jersey landlords covered by the law must give a printed or emailed receipt for every cash payment, and a violation can defeat a non-payment eviction.',
			'checked'    => 'October 2026',
		),
		'new-york' => array(
			'state'      => 'New York',
			'citation'   => 'N.Y. Real Property Law § 235-e',
			'url'        => 'https://www.nysenate.gov/legislation/laws/RPP/235-E',
			'when_short' => 'Cash, money order, cashier\'s check or anything but a personal check; personal checks on written request',
			'trigger'    => 'Automatically for rent paid in cash, by money order, cashier\'s check or any way other than the tenant\'s personal check. For a personal check, when the tenant asks in writing.',
			'contents'   => array( 'Date of payment', 'Amount paid', 'The premises (address and apartment) and the period the rent covers', 'Signature and title of the person who received the rent' ),
			'records'    => 'The landlord must keep a record of cash rent receipts for at least three years.',
			'penalty'    => '',
			'details'    => array(
				'A receipt must be given immediately when rent is paid in person, and within 15 days when it is paid any other way. A written request for receipts for personal-check payments lasts for the whole tenancy unless the tenant says otherwise.',
				'If rent has not been received within five days of the due date, the landlord must send the tenant a written notice by certified mail; not sending it can be used as a defense in a nonpayment case.',
			),
			'summary'    => 'Yes. New York landlords must give a written receipt for rent paid in cash or by anything other than a personal check, showing the date, amount, premises, rent period and the receiver\'s signature and title.',
			'meta'       => 'Yes. New York landlords must give a written receipt for rent paid by anything but a personal check, with date, amount, premises, period and signature.',
			'checked'    => 'October 2026',
		),
		'oregon' => array(
			'state'      => 'Oregon',
			'citation'   => 'ORS 90.140(2)',
			'url'        => 'https://www.oregonlegislature.gov/bills_laws/ors/ors090.html',
			'when_short' => 'Any payment, on request',
			'trigger'    => 'On request, for any payment (rent, deposits, fees, etc.). The tenant may condition making the payment on receiving the writing.',
			'contents'   => array( 'Amount paid', 'Date of payment', 'Information identifying the landlord or the rental property' ),
			'records'    => '',
			'penalty'    => '',
			'details'    => array(
				'Timing: For payments by mail, deposit or other non-in-person method, within a reasonable time after the request.',
				'The writing may be a receipt, a statement of the tenant\'s account or another acknowledgment.',
			),
			'summary'    => 'Oregon tenants who ask are entitled to a written receipt, account statement or other acknowledgment showing the amount, date and landlord or property for any payment.',
			'meta'       => 'Oregon tenants who ask are entitled to a written receipt or account statement showing the amount, date and landlord or property for any payment.',
			'checked'    => 'October 2026',
		),
		'texas' => array(
			'state'      => 'Texas',
			'citation'   => 'Tex. Property Code § 92.011',
			'url'        => 'https://texas.public.law/statutes/tex._prop._code_section_92.011',
			'when_short' => 'Cash payments, plus a landlord record book',
			'trigger'    => 'Cash payments. (Landlord must accept timely cash rent unless a written lease requires check, money order or other traceable/negotiable instrument.)',
			'contents'   => array(  ),
			'records'    => 'Landlord must enter the payment date and amount in a record book maintained by the landlord.',
			'penalty'    => 'Tenant (or a governmental entity/civic association on tenant\'s behalf) may sue to enjoin; prevailing tenant recovers the greater of one month\'s rent or $500 per violation, plus court costs and reasonable attorney\'s fees.',
			'details'    => array(
			),
			'summary'    => 'Texas landlords who take cash rent must give a written receipt and log the date and amount in a record book, or face at least one month\'s rent or $500 per violation.',
			'meta'       => 'Texas landlords who take cash rent must give a written receipt and log it in a record book, or face at least one month\'s rent or $500 per violation.',
			'checked'    => 'October 2026',
		),
		'virginia' => array(
			'state'      => 'Virginia',
			'citation'   => 'Va. Code § 55.1-1204(J)(1) and (D)',
			'url'        => 'https://law.lis.virginia.gov/vacode/title55.1/chapter12/section55.1-1204/',
			'when_short' => 'Cash or money order',
			'trigger'    => 'Automatic whenever rent is paid in cash or by money order. Separately, on written request the landlord must give a written statement of all charges and payments.',
			'contents'   => array(  ),
			'records'    => '',
			'penalty'    => '',
			'details'    => array(
				'On request (separate from the receipt, § 55.1-1204(D)): after a written request, the landlord must give a written statement of all charges and payments over the tenancy or the past 12 months, whichever is shorter, within 10 business days. The statute does not say when the receipt itself is due.',
				'The section has been amended several times in 2025 and 2026, and a new version takes effect on 1 July 2027; both keep the receipt and statement rules.',
			),
			'summary'    => 'Virginia landlords must give a written receipt whenever rent is paid in cash or by money order, and an account statement within 10 business days of a written request.',
			'meta'       => 'Virginia landlords must give a written receipt for rent paid in cash or money order, and an account statement within 10 business days of a written request.',
			'checked'    => 'October 2026',
		),
		'washington' => array(
			'state'      => 'Washington',
			'citation'   => 'RCW 59.18.063',
			'url'        => 'https://app.leg.wa.gov/RCW/default.aspx?cite=59.18.063',
			'when_short' => 'Cash payments; any other payment on request',
			'trigger'    => 'Automatic for any cash payment (landlord may refuse cash, but if it accepts cash it must give a receipt); on request for any non-cash payment.',
			'contents'   => array(  ),
			'records'    => '',
			'penalty'    => '',
			'details'    => array(
				'RCW 59.18.270: landlord must give a written receipt for a security deposit.',
				'Since 2022 a landlord must accept a personal check, cashier\'s check or money order and must allow rent to be paid by mail.',
			),
			'summary'    => 'Washington landlords must give a receipt for every cash payment and, if the tenant asks, a written receipt for any other payment.',
			'checked'    => 'October 2026',
		),
		'wisconsin' => array(
			'state'      => 'Wisconsin',
			'citation'   => 'Wis. Admin. Code ATCP 134.03(2)(b) (administrative rule, enforced under Wis. Stat. § 100.20)',
			'url'        => 'https://docs.legis.wisconsin.gov/code/admin_code/atcp/090/134/03',
			'when_short' => 'Cash payments',
			'trigger'    => 'Rent paid in cash. No receipt required for rent paid by check.',
			'contents'   => array( 'Nature of the payment', 'Amount of the payment' ),
			'records'    => '',
			'penalty'    => 'The rule itself sets no penalty; violations of ATCP 134 are enforced under Wis. Stat. § 100.20.',
			'details'    => array(
				'Timing: Upon receiving the cash payment.',
				'ATCP 134.03(2)(a): a written receipt is required immediately for any earnest money or security deposit (not required for a check noting its purpose unless the tenant asks).',
			),
			'summary'    => 'Wisconsin landlords must give a written receipt stating the nature and amount whenever rent is paid in cash.',
			'checked'    => 'October 2026',
		),
	);
}

/**
 * When the list was last checked against the statute texts. Shown as
 * "Last reviewed" on the hub and every law page, and used as
 * lastReviewed in their WebPage schema.
 */
function rf_rent_laws_reviewed() {
	return array(
		'label' => 'October 2026',
		'date'  => '2026-10-25',
	);
}

/**
 * States whose statute only requires a receipt for a security deposit
 * (1.4.2: the entries flagged 'deposit_only', each with its own page).
 */
function rf_rent_deposit_only_laws() {
	return array_filter(
		rf_rent_receipt_laws(),
		function ( $law ) {
			return ! empty( $law['deposit_only'] );
		}
	);
}

/**
 * 1.4.2 (G-6): city ordinances, keyed by the page path under the hub
 * ('ohio/columbus' is /rent-receipt-laws/ohio/columbus/). Same keys as
 * rf_rent_receipt_laws(), plus city and state; 'url' is the ordinance
 * text that was read. Each city's state needs an entry in
 * rf_rent_city_rule_states(), which is the parent page.
 */
function rf_rent_receipt_city_laws() {
	return array(
		'ohio/cincinnati' => array(
			'city'       => 'Cincinnati',
			'state'      => 'Ohio',
			'citation'   => 'Cincinnati Municipal Code § 871-9(a)(7)',
			'url'        => 'https://cincinnatioh.legistar.com/LegislationDetail.aspx?ID=5202599&GUID=EE754480-4BA6-4BB5-92FF-A0788EF08593',
			'when_short' => 'Deposit and every rent payment once a deposit is paid (not personal checks)',
			'trigger'    => 'When a tenant provides a security deposit: a signed receipt for the deposit and for all rental payments, at the time they are made. Payments by the tenant\'s personal check are excluded.',
			'contents'   => array(),
			'records'    => '',
			'penalty'    => '',
			'details'    => array(
				'Section 871-9 lists the obligations the city imposes on the landlord in every rental agreement; the receipt is obligation (a)(7). It is a city ordinance, so it covers rentals inside the City of Cincinnati.',
				'We read the section as restated in full by Ordinance No. 419-2021, passed by Cincinnati City Council on 10 November 2021.',
			),
			'summary'    => 'Cincinnati landlords who take a security deposit must give a signed receipt for the deposit and for every rent payment, except payments by the tenant\'s personal check.',
			'meta'       => 'Cincinnati landlords who take a security deposit must give a signed receipt for it and for every rent payment not made by personal check.',
			'checked'    => 'October 2026',
		),
		'ohio/columbus'   => array(
			'city'       => 'Columbus',
			'state'      => 'Ohio',
			'citation'   => 'Columbus City Code § 4551.05 (Ord. 0496-2021)',
			'url'        => 'https://columbus.legistar.com/LegislationDetail.aspx?ID=4811013&GUID=8993C532-2BE6-4F05-A37E-250BB0351DDB',
			'when_short' => 'Every rent payment and security deposit',
			'trigger'    => 'Every rent payment and every security deposit: a written receipt at the time the payment is received. For cash, or any other payment that does not produce an automatic written receipt, within 4 business days.',
			'contents'   => array(),
			'records'    => '',
			'penalty'    => 'Failure to provide a rent receipt is a misdemeanor of the fourth degree, and a strict liability offense (§ 4551.05(e)–(f)).',
			'details'    => array(
				'If rent or the deposit is paid through a third-party online portal or service, the tenant being able to get a written receipt from that portal counts as the landlord providing one (§ 4551.05(c)).',
				'The duty is on the "operator" of the rental. It does not apply to operators of permanent supportive housing (§ 4551.05(b)).',
				'Enacted by Ordinance 0496-2021, passed by Columbus City Council on 8 March 2021.',
			),
			'summary'    => 'Columbus landlords must give a written receipt for every rent payment and security deposit, within four business days for cash or any payment with no automatic receipt.',
			'meta'       => 'Columbus, Ohio landlords must give a written receipt for every rent payment and deposit, within four business days for cash.',
			'checked'    => 'October 2026',
		),
	);
}

/**
 * 1.4.2: states that have no statewide rule but list city rules; each is
 * the parent page of its cities. 'citation' / 'url' is the state law that
 * was read and found to have no rent receipt duty.
 */
function rf_rent_city_rule_states() {
	return array(
		'ohio' => array(
			'state'    => 'Ohio',
			'citation' => 'Ohio Rev. Code Chapter 5321',
			'url'      => 'https://codes.ohio.gov/ohio-revised-code/chapter-5321',
			'note'     => 'Ohio\'s landlord-tenant law, Revised Code Chapter 5321, sets the landlord\'s duties, the security deposit rules (§ 5321.16) and the tenant\'s right to pay rent to the court when repairs are not made (§ 5321.07), but no duty to give a rent receipt. Two cities add one by ordinance.',
			'summary'  => 'Ohio has no statewide rent receipt law, but Columbus requires a receipt for every rent payment and Cincinnati requires one once a tenant has paid a security deposit.',
			'meta'     => 'Ohio has no statewide rent receipt law, but Columbus requires a receipt for every rent payment and Cincinnati one once a deposit is paid.',
			'checked'  => 'October 2026',
		),
	);
}

/**
 * 1.4.2 (G-6): rules outside the US, keyed by the page path under the hub.
 * Keys: country, region (a province; '' for a country page), citation,
 * url, when_short, trigger, contents, records, penalty, details, summary,
 * meta, checked, currency (the generator's), general (as for the states),
 * and for a country with different rules per nation, 'sections' (each
 * with region, citation, url, trigger, contents, penalty, details).
 * A province's parent is its country hub in rf_rent_law_countries().
 */
function rf_rent_receipt_intl_laws() {
	return array(
		'uk'                     => array(
			'country'    => 'United Kingdom',
			'region'     => '',
			'name'       => 'UK',
			'citation'   => 'Landlord and Tenant Act 1985, ss. 4–7 (England); Private Residential Tenancies (Statutory Terms) (Scotland) Regulations 2017; Private Tenancies (Northern Ireland) Order 2006, art. 5',
			'url'        => 'https://www.legislation.gov.uk/ukpga/1985/70/section/4',
			'when_short' => 'England: rent book for weekly rent; Scotland and Northern Ireland: receipt for cash rent',
			'trigger'    => 'It depends on the nation. In England the landlord must provide a rent book when rent is payable weekly; in Scotland and Northern Ireland the landlord must give a written receipt whenever rent is paid in cash.',
			'contents'   => array(),
			'records'    => '',
			'penalty'    => '',
			'details'    => array(),
			'sections'   => array(
				array(
					'region'   => 'England',
					'citation' => 'Landlord and Tenant Act 1985, ss. 4–7',
					'url'      => 'https://www.legislation.gov.uk/ukpga/1985/70/section/4',
					'trigger'  => 'When rent is payable weekly, the landlord must provide a rent book or other similar document (s. 4(1)). This does not apply where the rent includes board that makes up a substantial part of the whole rent (s. 4(2)). The Act sets no rent book or receipt duty for monthly rent.',
					'contents' => array( 'The name and address of the landlord (s. 5(1))', 'For a restricted contract: the rent and the other terms and conditions of the contract', 'For a protected, statutory or assured tenancy: the notices set by regulations, in the prescribed form (s. 5(1)(b), (2))' ),
					'penalty'  => 'A landlord who does not provide the rent book, or leaves out the information it must contain, commits a summary offence with a fine of up to level 4 on the standard scale. So does anyone who demands or collects rent for the landlord while those rules are not met, unless they did not know and had no reason to suspect it (s. 7).',
					'details'  => array( 'Where the landlord is a company, the tenant can ask in writing for the names and addresses of its directors and secretary, and the landlord must give them (s. 6).' ),
				),
				array(
					'region'   => 'Wales',
					'citation' => 'Landlord and Tenant Act 1985, s. 4(4)',
					'url'      => 'https://www.legislation.gov.uk/ukpga/1985/70/section/4',
					'trigger'  => 'The rent book rule does not apply to occupation contracts under the Renting Homes (Wales) Act 2016 (s. 4(4)), and we found no rent book or rent receipt rule in that Act.',
					'contents' => array(),
					'penalty'  => '',
					'details'  => array(),
				),
				array(
					'region'   => 'Scotland',
					'citation' => 'Private Residential Tenancies (Statutory Terms) (Scotland) Regulations 2017, schedule, para. 1 (Private Housing (Tenancies) (Scotland) Act 2016, sch. 2)',
					'url'      => 'https://www.legislation.gov.uk/ssi/2017/408/schedule/made',
					'trigger'  => 'For a private residential tenancy, the type of private let under the 2016 Act (which also stopped new assured tenancies): a written receipt for every payment of rent made in cash. It is a statutory term of every such tenancy.',
					'contents' => array( 'Date of payment', 'Amount paid', 'The amount still outstanding, or confirmation that nothing more is outstanding' ),
					'penalty'  => '',
					'details'  => array( 'Older tenancies: under an assured tenancy with rent payable weekly the landlord must provide a rent book (Housing (Scotland) Act 1988, s. 30(4)), and so must the landlord of a protected or statutory tenancy with weekly rent (Rent (Scotland) Act 1984, s. 113). Not doing so is an offence with a fine of up to level 4 on the standard scale, for the landlord and anyone collecting the rent for them.' ),
				),
				array(
					'region'   => 'Northern Ireland',
					'citation' => 'Private Tenancies (Northern Ireland) Order 2006, art. 5 (as substituted by the Private Tenancies Act (Northern Ireland) 2022)',
					'url'      => 'https://www.legislation.gov.uk/nisi/2006/1459/article/5',
					'trigger'  => 'Any payment the tenant makes to the landlord in cash, for the grant, renewal or continuance of a private tenancy or under it (rent and other payments). In force since 1 April 2023.',
					'contents' => array( 'Date of payment', 'What the payment was for', 'Amount paid', 'Any amount still outstanding, or that nothing more is outstanding', 'For a single sum covering two or more payments, how it is split between them' ),
					'penalty'  => 'Not giving a proper receipt is an offence by the landlord and by anyone the landlord appointed to give it (art. 5(7)). It can be dealt with by a fixed penalty notice (art. 68A), and a failure that continues more than 14 days after a conviction or a paid fixed penalty is a further offence (art. 5ZA).',
					'details'  => array( 'The receipt must be given at the time of payment or, if that is not possible, as soon as reasonably possible afterwards, and the tenant cannot be charged for it (art. 5(5)–(6)).' ),
				),
			),
			'summary'    => 'In England a landlord must provide a rent book when rent is paid weekly. In Scotland and Northern Ireland a landlord must give a written receipt for rent paid in cash.',
			'meta'       => 'UK rent receipt rules: a rent book for weekly rent in England, and a written receipt for rent paid in cash in Scotland and Northern Ireland.',
			'checked'    => 'October 2026',
			'currency'   => 'GBP',
		),
		'canada/british-columbia' => array(
			'country'    => 'Canada',
			'region'     => 'British Columbia',
			'citation'   => 'Residential Tenancy Act, S.B.C. 2002, c. 78, s. 26(2)',
			'url'        => 'https://www.bclaws.gov.bc.ca/civix/document/id/complete/statreg/02078_01',
			'when_short' => 'Rent paid in cash',
			'trigger'    => 'Rent paid in cash.',
			'contents'   => array(),
			'records'    => '',
			'penalty'    => '',
			'details'    => array(
				'The Act does not say when the receipt is due or what it must show.',
			),
			'summary'    => 'British Columbia landlords must give the tenant a receipt for rent paid in cash, under section 26(2) of the Residential Tenancy Act.',
			'checked'    => 'October 2026',
			'currency'   => 'CAD',
		),
		'canada/manitoba'        => array(
			'country'    => 'Canada',
			'region'     => 'Manitoba',
			'citation'   => 'The Residential Tenancies Act, C.C.S.M. c. R119, s. 57',
			'url'        => 'https://web2.gov.mb.ca/laws/statutes/ccsm/r119.php',
			'when_short' => 'Rent paid in cash',
			'trigger'    => 'Rent or a tenant services charge paid in cash, by the tenant or by a person paying on the tenant\'s behalf: a written acknowledgement of receipt.',
			'contents'   => array( 'Amount received', 'Day it was received', 'Address of the rental unit and residential complex it was paid for' ),
			'records'    => '',
			'penalty'    => '',
			'details'    => array(
				'A security deposit also needs a written acknowledgement setting out the amount, the date it was received, and the rental unit and residential complex (s. 29), and the Act has the same rule for a pet damage deposit and a tenant services security deposit.',
			),
			'summary'    => 'Manitoba landlords must give a written acknowledgement for rent paid in cash, showing the amount, the day it was received and the rental unit\'s address.',
			'checked'    => 'October 2026',
			'currency'   => 'CAD',
		),
		'canada/ontario'         => array(
			'country'    => 'Canada',
			'region'     => 'Ontario',
			'citation'   => 'Residential Tenancies Act, 2006, S.O. 2006, c. 17, s. 109',
			'url'        => 'https://www.ontario.ca/laws/statute/06r17',
			'when_short' => 'Any payment, on request (free)',
			'trigger'    => 'On request, for any payment: rent, a rent deposit, arrears of rent or any other amount paid to the landlord. A former tenant can ask within 12 months after the tenancy ends.',
			'contents'   => array(),
			'records'    => '',
			'penalty'    => 'Not providing a receipt as section 109 requires is an offence (s. 234(h)). The maximum fine for an offence under the Act is $100,000 for an individual and $500,000 for a corporation (s. 238).',
			'details'    => array(
				'The receipt must be free of charge.',
			),
			'summary'    => 'Ontario landlords must give a tenant or former tenant a free receipt for rent or any other payment on request, under section 109 of the Residential Tenancies Act, 2006.',
			'meta'       => 'Ontario landlords must give a free receipt for rent or any other payment on request, under section 109 of the Residential Tenancies Act, 2006.',
			'checked'    => 'October 2026',
			'currency'   => 'CAD',
		),
		'canada/quebec'          => array(
			'country'    => 'Canada',
			'region'     => 'Quebec',
			'citation'   => 'Civil Code of Québec, art. 1568',
			'url'        => 'https://www.legisquebec.gouv.qc.ca/en/document/cs/CCQ-1991',
			'when_short' => 'On request (general rule for paying any debt)',
			'trigger'    => 'A debtor who pays a debt is entitled to an acquittance (a receipt). This is a general rule; the Civil Code\'s articles on leases add no separate rent receipt rule.',
			'contents'   => array(),
			'records'    => '',
			'penalty'    => '',
			'details'    => array(
				'Art. 1904: the landlord may not demand rent in advance for more than the first payment period (at most one month\'s rent), a deposit or any amount other than the rent, or postdated cheques.',
			),
			'summary'    => 'Quebec has no lease-specific rent receipt rule, but under article 1568 of the Civil Code a tenant who pays rent is entitled to a receipt (an acquittance).',
			'meta'       => 'Quebec has no lease-specific rent receipt rule, but under article 1568 of the Civil Code a tenant who pays rent is entitled to a receipt.',
			'checked'    => 'October 2026',
			'currency'   => 'CAD',
			'general'    => true,
		),
	);
}

/**
 * 1.4.2: country hubs (the parent page of their provinces). 'notes' are
 * places that were checked but have no page, or could not be read.
 */
function rf_rent_law_countries() {
	return array(
		'canada' => array(
			'country'  => 'Canada',
			'summary'  => 'Canada has no national rent receipt law; it depends on the province. Ontario requires a free receipt on request, and British Columbia and Manitoba a receipt for rent paid in cash.',
			'meta'     => 'Canada has no national rule: Ontario landlords must give a free receipt on request, and BC and Manitoba landlords one for rent paid in cash.',
			'notes'    => array(
				array( 'Newfoundland and Labrador', 'a written acknowledgement only for a security deposit, stating the amount, the date it was received and the premises (Residential Tenancies Act, 2018, s. 14(4)).', 'https://www.assembly.nl.ca/legislation/sr/statutes/r14-2.htm' ),
				array( 'Alberta and New Brunswick', 'we read their residential tenancies acts and found no rent receipt rule.', '' ),
				array( 'Nova Scotia, Prince Edward Island, Saskatchewan and the territories', 'not listed yet, because we list a province only after reading its law and could not read these yet.', '' ),
			),
			'checked'  => 'October 2026',
			'currency' => 'CAD',
		),
	);
}

/**
 * 1.4.2: every law page under the hub, keyed by its path below
 * /rent-receipt-laws/ ('texas', 'ohio/columbus', 'uk', 'canada/ontario'),
 * normalized for the template: kind (state, city-state, city, country,
 * country-hub, region), name (as written in headings), parent (path of
 * the parent law page, '' for the hub), template (generator template),
 * purpose (requirement check), currency, and the entry's own keys.
 * Parents come before their children.
 */
function rf_rent_law_entries() {
	static $entries = null;
	if ( null !== $entries ) {
		return $entries;
	}
	$entries = array();
	$base    = array(
		'parent'   => '',
		'template' => 'rent-receipt',
		'purpose'  => 'rent-us',
		'currency' => '',
	);
	foreach ( rf_rent_receipt_laws() as $slug => $law ) {
		$entries[ $slug ] = array_merge(
			$base,
			$law,
			array(
				'kind'     => 'state',
				'name'     => $law['state'],
				'template' => empty( $law['deposit_only'] ) ? 'rent-receipt' : 'deposit-receipt',
				'purpose'  => empty( $law['deposit_only'] ) ? 'rent-us' : '',
			)
		);
	}
	foreach ( rf_rent_city_rule_states() as $slug => $state ) {
		$entries[ $slug ] = array_merge( $base, $state, array( 'kind' => 'city-state', 'name' => $state['state'] ) );
	}
	foreach ( rf_rent_receipt_city_laws() as $path => $law ) {
		$entries[ $path ] = array_merge(
			$base,
			$law,
			array(
				'kind'   => 'city',
				'name'   => $law['city'] . ', ' . $law['state'],
				'parent' => dirname( $path ),
			)
		);
	}
	foreach ( rf_rent_law_countries() as $slug => $country ) {
		$entries[ $slug ] = array_merge( $base, $country, array( 'kind' => 'country-hub', 'name' => $country['country'], 'purpose' => '' ) );
	}
	foreach ( rf_rent_receipt_intl_laws() as $path => $law ) {
		$is_region        = '' !== $law['region'];
		$entries[ $path ] = array_merge(
			$base,
			$law,
			array(
				'kind'    => $is_region ? 'region' : 'country',
				'name'    => $is_region ? $law['region'] : ( $law['name'] ?? $law['country'] ),
				'parent'  => $is_region ? dirname( $path ) : '',
				'purpose' => '',
			)
		);
	}
	return $entries;
}

/** One law page by its path below the hub, or null. */
function rf_rent_law_entry( $sub ) {
	$entries = rf_rent_law_entries();
	return $entries[ $sub ] ?? null;
}

/** The official texts a law page relies on, citation => url (WebPage schema). */
function rf_rent_law_citations( $sub ) {
	$entry = '' === $sub ? null : rf_rent_law_entry( $sub );
	$list  = array();
	if ( ! $entry ) {
		foreach ( rf_rent_receipt_laws() as $law ) {
			$list[ $law['citation'] ] = $law['url'];
		}
		foreach ( rf_rent_receipt_city_laws() as $law ) {
			$list[ $law['citation'] ] = $law['url'];
		}
		return $list;
	}
	if ( ! empty( $entry['citation'] ) ) {
		$list[ $entry['citation'] ] = $entry['url'];
	}
	foreach ( (array) ( $entry['sections'] ?? array() ) as $section ) {
		$list[ $section['citation'] ] = $section['url'];
	}
	foreach ( rf_rent_law_children( $sub ) as $child ) {
		$list[ $child['citation'] ] = $child['url'];
	}
	return $list;
}

/** The law pages whose parent is $sub ('' for the pages under the hub). */
function rf_rent_law_children( $sub ) {
	return array_filter(
		rf_rent_law_entries(),
		function ( $entry ) use ( $sub ) {
			return $entry['parent'] === $sub;
		}
	);
}

/** Link to a law page by its path below the hub. */
function rf_rent_law_link( $sub, $text ) {
	return '<a href="' . esc_url( home_url( '/rent-receipt-laws/' . $sub . '/' ) ) . '">' . esc_html( $text ) . '</a>';
}

/** FAQ for the hub ('') or one law page (printed on the page and in FAQPage schema). */
function rf_rent_laws_faqs( $sub ) {
	$law = '' === $sub ? null : rf_rent_law_entry( $sub );
	if ( ! $law ) {
		return array(
			array( 'Do landlords have to give rent receipts?', 'There is no federal rule. Several states require a written receipt, usually for rent paid in cash, and a few for every payment or whenever the tenant asks. Some cities, such as Columbus and Cincinnati in Ohio, add their own rule. The tables on this page list each state and city we have checked, with the law.' ),
			array( 'What should a rent receipt include?', 'The date, the amount, the rent period, the property address, the tenant\'s name and the landlord\'s or agent\'s name and signature. Some states list exactly what their receipts must show; see your state\'s page.' ),
			array( 'Do other countries have rent receipt laws?', 'Some do. In England a landlord must provide a rent book when rent is paid weekly, and Scotland and Northern Ireland require a written receipt for rent paid in cash. In Canada it depends on the province: Ontario requires a receipt on request, and British Columbia and Manitoba one for cash rent.' ),
		);
	}
	$name = $law['name'];
	switch ( $law['kind'] ) {
		case 'city-state':
			$cities = array();
			foreach ( rf_rent_law_children( $sub ) as $city ) {
				$cities[] = $city['city'] . ' (' . $city['citation'] . '): ' . lcfirst( $city['when_short'] );
			}
			return array(
				array( 'Does ' . $name . ' require landlords to give rent receipts?', $law['summary'] ),
				array( 'Which ' . $name . ' cities require rent receipts?', 'We have read the ordinances of: ' . implode( '; ', $cities ) . '. Other cities may have their own rules.' ),
			);
		case 'country-hub':
			$regions = array();
			foreach ( rf_rent_law_children( $sub ) as $region ) {
				$regions[] = $region['region'] . ': ' . lcfirst( $region['when_short'] );
			}
			return array(
				array( 'Do landlords in ' . $name . ' have to give rent receipts?', $law['summary'] ),
				array( 'Which provinces have a rent receipt rule?', 'The provinces whose law we have read: ' . implode( '; ', $regions ) . '.' ),
			);
	}
	$where = 'country' === $law['kind'] ? 'the ' . $name : $name;
	$faqs  = array(
		array( 'Does a landlord have to give a rent receipt in ' . $where . '?', $law['summary'] ),
	);
	foreach ( (array) ( $law['sections'] ?? array() ) as $section ) {
		$faqs[] = array( 'What is the rule in ' . $section['region'] . '?', $section['trigger'] );
	}
	if ( ! empty( $law['deposit_only'] ) ) {
		$faqs[] = array( 'Is there a rule for monthly rent receipts in ' . $name . '?', 'No. The receipt rules in ' . $law['citation'] . ' cover only the security deposit and last month\'s rent paid in advance. A receipt for each monthly payment is still good practice for both sides.' );
		$faqs[] = array( 'What must a ' . $name . ' security deposit receipt show?', 'Under ' . $law['citation'] . ': ' . strtolower( implode( '; ', $law['receipts'][0]['items'] ) ) . '. Within 30 days the landlord must also give a receipt naming the bank, the amount and the account number.' );
	}
	if ( ! empty( $law['contents'] ) ) {
		$faqs[] = array( 'What must a ' . $name . ' rent receipt include?', 'Under ' . $law['citation'] . ': ' . strtolower( implode( '; ', $law['contents'] ) ) . '.' );
	}
	if ( ! empty( $law['records'] ) ) {
		$faqs[] = array( 'Does a ' . $name . ' landlord have to keep copies?', $law['records'] );
	}
	if ( 'city' === $law['kind'] ) {
		$faqs[] = array( 'Does ' . $law['state'] . ' state law require rent receipts?', rf_rent_law_entry( $law['parent'] )['summary'] );
	}
	return $faqs;
}
