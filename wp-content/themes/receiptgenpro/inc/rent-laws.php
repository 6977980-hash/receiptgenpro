<?php
/**
 * ReceiptGenPro — Rent receipt laws by US state (v1.2.0)
 *
 * Feeds /rent-receipt-laws/ and one page per state (created automatically
 * by inc/feature-pages.php). A state is listed ONLY after its statute text
 * was read on an official or verbatim source; 'checked' is that date.
 * Adding a state here creates its page on the next request.
 *
 * Keys: state, citation, url (the text that was read), when_short (hub
 * table), trigger, contents (what the receipt must show, per the text),
 * records, penalty, details (extra paragraphs), summary (meta description
 * and the page's direct answer), checked.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function rf_rent_receipt_laws() {
	return array(
		'california' => array(
			'state'      => 'California',
			'citation'   => 'Cal. Civil Code § 1499',
			'url'        => 'https://california.public.law/codes/ca_civ_code_section_1499',
			'when_short' => 'On request (general rule for paying any debt)',
			'trigger'    => 'On request (general debtor-creditor rule, not landlord-specific): a debtor may require a written receipt from the creditor.',
			'contents'   => array(  ),
			'records'    => '',
			'penalty'    => '',
			'details'    => array(
				'Civ. Code § 1962 requires the lease to disclose who rent is paid to and the accepted payment forms; § 1947.3 requires landlords to accept at least one form of payment that is neither cash nor electronic funds transfer (no receipt duty in either).',
			),
			'summary'    => 'California has no landlord-specific rent-receipt statute, but Civil Code § 1499 lets anyone who pays a debt, including a tenant paying rent, demand a written receipt.',
			'checked'    => 'October 2026',
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
			'checked'    => 'October 2026',
		),
		'virginia' => array(
			'state'      => 'Virginia',
			'citation'   => 'Va. Code § 55.1-1204(J)(1) and (D)',
			'url'        => 'https://law.lis.virginia.gov/vacode/title55.1/chapter12/section55.1-1204/',
			'when_short' => 'Cash or money order',
			'trigger'    => 'Automatic whenever rent is paid in cash or by money order. Separately, on written request the landlord must give a written statement of all charges and payments.',
			'contents'   => array( 'Statement on request: all debits and credits over the tenancy or the past 12 months, whichever is shorter' ),
			'records'    => '',
			'penalty'    => '',
			'details'    => array(
				'Timing: Statement of charges and payments: within 10 business days of the written request. Receipt timing not stated.',
				'The section has been amended several times in 2025 and 2026, and a new version takes effect on 1 July 2027; both keep the receipt and statement rules.',
			),
			'summary'    => 'Virginia landlords must give a written receipt whenever rent is paid in cash or by money order, and an account statement within 10 business days of a written request.',
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

/** States whose statute only requires a receipt for a security deposit. */
function rf_rent_deposit_only_laws() {
	return array(
		array(
			'state'    => 'Massachusetts',
			'citation' => 'M.G.L. c. 186, § 15B',
			'url'      => 'https://malegislature.gov/Laws/GeneralLaws/PartII/TitleI/Chapter186/Section15B',
			'note'     => 'a signed receipt for a security deposit and for last month\'s rent collected in advance, and within 30 days a receipt naming the bank and account that holds the deposit. No general rule for monthly rent.',
		),
	);
}

/** FAQ for one state page (printed on the page and in FAQPage schema). */
function rf_rent_laws_faqs( $slug ) {
	$laws = rf_rent_receipt_laws();
	if ( ! isset( $laws[ $slug ] ) ) {
		return array(
			array( 'Do landlords have to give rent receipts?', 'There is no federal rule. Several states require a written receipt, usually for rent paid in cash, and a few for every payment or whenever the tenant asks. The table on this page lists each state we have checked, with the statute.' ),
			array( 'What should a rent receipt include?', 'The date, the amount, the rent period, the property address, the tenant\'s name and the landlord\'s or agent\'s name and signature. Some states list exactly what their receipts must show; see your state\'s page.' ),
		);
	}
	$law  = $laws[ $slug ];
	$faqs = array(
		array( 'Does a landlord have to give a rent receipt in ' . $law['state'] . '?', $law['summary'] ),
	);
	if ( ! empty( $law['contents'] ) ) {
		$faqs[] = array( 'What must a ' . $law['state'] . ' rent receipt include?', 'Under ' . $law['citation'] . ': ' . strtolower( implode( '; ', $law['contents'] ) ) . '.' );
	}
	if ( ! empty( $law['records'] ) ) {
		$faqs[] = array( 'Does a ' . $law['state'] . ' landlord have to keep copies?', $law['records'] );
	}
	return $faqs;
}
