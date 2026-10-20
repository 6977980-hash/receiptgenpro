// Tests for the generator fixes of theme 1.3.3 (audit of 20 Oct 2026,
// Phase 3). Run: node --test tests/*.test.js
'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const rg = require( '../wp-content/themes/receiptgenpro/assets/js/receipt-generator.js' );
const vr = require( '../wp-content/themes/receiptgenpro/assets/js/verify-receipt.js' );
const wb = require( '../wp-content/themes/receiptgenpro/assets/js/widget-builder.js' );

const decode = ( enc ) => JSON.parse( Buffer.from( enc, 'base64url' ).toString( 'utf8' ) );

test( '#23 huge, exponent and Infinity inputs are capped', () => {
	assert.equal( rg.nonNegative( '1e400' ), 0 );
	assert.equal( rg.nonNegative( 'Infinity' ), 0 );
	assert.equal( rg.capped( '1e21', rg.MAX_AMOUNT ), 999999999999.99 );
	assert.equal( rg.capped( '1e300', 100 ), 100 );
	assert.equal( rg.capped( '-3', 100 ), 0 );
	assert.equal( rg.lineCents( 1e6, 999999999999.99 ), 99999999999999 );
	assert.equal( rg.formatRate( 8.875 ), '8.875' );
	assert.equal( rg.formatRate( 5 ), '5' );
	assert.equal( rg.formatRate( 0.1 + 0.2 ), '0.3' );
	// The largest total the form can make (capped subtotal, 2 × 100 % tax,
	// capped tip) still has words.
	const max = rg.calcTotals( 99999999999999, 0, 'percent', [ 100, 100 ], 99999999999999 ).totalCents;
	assert.match( rg.amountInWords( max / 100, 'USD' ), /^Three trillion nine hundred ninety-nine billion/ );
	assert.match( rg.amountInWords( 1e15 / 100, 'USD' ), /^Ten trillion/ );
} );

test( '#28 the unit price is rounded to cents before × quantity', () => {
	assert.equal( rg.lineCents( 3, 0.333 ), 99 );
	assert.equal( rg.lineCents( 2, 1.005 ), 202 );
	assert.equal( rg.lineCents( 1.5, 10 ), 1500 );
} );

test( '#29 singular currency words, and no "zero … and"', () => {
	assert.equal( rg.amountInWords( 1, 'USD' ), 'One US dollar only' );
	assert.equal( rg.amountInWords( 1.01, 'USD' ), 'One US dollar and one cent only' );
	assert.equal( rg.amountInWords( 0.5, 'USD' ), 'Fifty cents only' );
	assert.equal( rg.amountInWords( 0.01, 'GBP' ), 'One penny only' );
	assert.equal( rg.amountInWords( 2.02, 'GBP' ), 'Two pounds and two pence only' );
	assert.equal( rg.amountInWords( 1.01, 'INR' ), 'One Indian rupee and one paisa only' );
	assert.equal( rg.amountInWords( 0.25, 'INR' ), 'Twenty-five paise only' );
	assert.equal( rg.amountInWords( 0, 'USD' ), 'Zero US dollars only' );
} );

test( '#30 thousands separators, lakh grouping for INR / PKR', () => {
	assert.equal( rg.formatAmount( 123456789, 'USD' ), '1,234,567.89' );
	assert.equal( rg.formatAmount( 123456789, 'INR' ), '12,34,567.89' );
	assert.equal( rg.formatAmount( 5, 'PKR' ), '0.05' );
	// The symbols come from the page (rfVerify, printed by functions.php).
	globalThis.window = { rfVerify: { symbols: { USD: '$' } } };
	try {
		assert.equal( vr.money( { cu: 'USD' }, 123456789 ), '$1,234,567.89' );
	} finally {
		delete globalThis.window;
	}
	assert.equal( vr.money( { cu: 'USD' }, 123456789 ), 'USD 1,234,567.89' );
} );

test( '#31 a discount larger than the subtotal is flagged', () => {
	assert.equal( rg.calcTotals( 5000, 80, 'amount', [ 0, 0 ], 0 ).discountClamped, true );
	assert.equal( rg.calcTotals( 5000, 20, 'amount', [ 0, 0 ], 0 ).discountClamped, false );
} );

test( '#27 QR payload stays short for long CJK / Hindi receipts, cut by code point', () => {
	const name = '東京都渋谷区神南一丁目株式会社サンプル商事'.repeat( 3 );
	const items = Array.from( { length: 12 }, ( _, i ) => ( { name: 'पंक्ति संख्या ' + i + ' अतिरिक्त विवरण के साथ', qty: 1, priceCents: 1000 } ) );
	const enc = rg.buildPayload( {
		title: 'किराया रसीद', business: name, client: name, number: 'A-1/2026', date: '2026-10-20', currency: 'INR',
		items, totalCents: 12000, payMethod: 'Cash',
		extras: [ [ 'संपत्ति का पता', 'फ्लैट 302, ग्रीन टावर्स, पुणे, महाराष्ट्र'.repeat( 2 ), 'property' ] ]
	} );
	assert.ok( enc.length <= rg.QR_MAX, 'payload is ' + enc.length + ' characters' );
	const p = decode( enc );
	assert.equal( p.more, 1 );
	assert.ok( p.i.length >= 1, 'keeps at least the first item' );
	assert.equal( p.tot, 12000 );
	// No lone surrogates: an emoji is kept whole or dropped whole.
	assert.equal( rg.clip( 'ab😀cd', 3 ), 'ab…' );
	assert.equal( rg.clip( '😀😀😀😀', 3 ), '😀😀…' );
	// A short receipt is left as it is.
	const small = decode( rg.buildPayload( { title: 'RECEIPT', business: 'Shop', items: [ { name: 'Tea', qty: 1, priceCents: 250 } ], totalCents: 250 } ) );
	assert.equal( small.i[0][0], 'Tea' );
	assert.equal( small.more, undefined );
	// Whole px per module, at least 112 px.
	assert.equal( rg.qrPixels( 25 ), 125 );
	assert.equal( rg.qrPixels( 77 ), 154 );
} );

test( '#37 #38 discount, taxes and tip are in the QR; tax IDs are not', () => {
	const p = decode( rg.buildPayload( {
		title: 'RECEIPT', business: 'Shop', items: [ { name: 'Tea', qty: 1, priceCents: 1000 } ], totalCents: 1170,
		discountCents: 100, tipCents: 90,
		taxes: [ { label: 'CGST', rate: 9, cents: 81 }, { label: 'SGST', rate: 9, cents: 81 } ],
		extras: [ [ 'Landlord PAN', 'ABCDE1234F', 'landlord_pan' ], [ 'EIN', '12-3456789', 'ein' ], [ 'Room', '204', 'room' ] ]
	} ) );
	assert.equal( p.dc, 100 );
	assert.equal( p.tp, 90 );
	assert.deepEqual( p.tx, [ [ 'CGST', '9', 81 ], [ 'SGST', '9', 81 ] ] );
	assert.deepEqual( p.x, [ [ 'Room', '204' ] ] );
	const enc = ( o ) => '#r=' + rg.b64urlEncode( JSON.stringify( o ) );
	assert.ok( vr.decodePayload( enc( { v: 1, tot: 100, i: [], dc: 5, tp: 1, tx: [ [ 'VAT', '20', 10 ] ] } ) ) );
	assert.equal( vr.decodePayload( enc( { v: 1, tot: 100, i: [], tx: [ [ 'VAT', '20', 'x' ] ] } ) ), null );
	assert.equal( vr.decodePayload( enc( { v: 1, tot: 100, i: [], dc: 1.5 } ) ), null );
} );

test( '#39 bidi controls are stripped', () => {
	assert.equal( rg.stripBidi( 'Total ‮0001‬' ), 'Total 0001' );
	assert.equal( rg.esc( 'a⁦b⁩' ), 'ab' );
	assert.equal( vr.stripBidi( '‭x' ), 'x' );
} );

test( '#33 checker limits apply only in their own currency, and per month', () => {
	const checks = [ { label: 'Landlord PAN', field: 'x:landlord_pan', when: { total_over: 8333, per_month: true } } ];
	const base = { x: { landlord_pan: false }, currency: 'INR', months: 1 };
	assert.equal( rg.evaluateChecks( checks, { ...base, amount: 9000 }, 'INR' )[0].status, 'missing' );
	assert.equal( rg.evaluateChecks( checks, { ...base, amount: 8000 }, 'INR' )[0].status, 'na' );
	// Three months of ₹8,000 is ₹8,000 a month: below the limit.
	assert.equal( rg.evaluateChecks( checks, { ...base, amount: 24000, months: 3 }, 'INR' )[0].status, 'na' );
	assert.equal( rg.evaluateChecks( checks, { ...base, amount: 30000, months: 3 }, 'INR' )[0].status, 'missing' );
	// A $9,000 receipt is not compared with a rupee limit.
	const usd = rg.evaluateChecks( checks, { ...base, amount: 9000, currency: 'USD' }, 'INR' )[0];
	assert.equal( usd.status, 'currency' );
	assert.equal( usd.currency, 'INR' );
	// The $75 donation rule with a USD purpose (the default) still works.
	const donation = [ { label: 'Estimate', manual: true, when: { total_over: 75 } } ];
	assert.equal( rg.evaluateChecks( donation, { amount: 100, currency: 'USD' } )[0].status, 'manual' );
	assert.equal( rg.monthsBetween( '2026-01-01', '2026-03-31' ), 3 );
	assert.equal( rg.monthsBetween( '2026-10-01', '2026-10-31' ), 1 );
	assert.equal( rg.monthsBetween( '', '2026-10-31' ), 1 );
} );

test( '#34 long receipts are split into pages between rows', () => {
	assert.deepEqual( rg.pageSlices( 1000, 1200, [] ), [ [ 0, 1000 ] ] );
	assert.deepEqual( rg.pageSlices( 2500, 1000, [ 300, 700, 950, 1100, 1800, 2400 ] ), [ [ 0, 950 ], [ 950, 1800 ], [ 1800, 2500 ] ] );
	// No break in the lower half of a page: cut at the page edge.
	assert.deepEqual( rg.pageSlices( 2100, 1000, [ 100 ] ), [ [ 0, 1000 ], [ 1000, 2000 ], [ 2000, 2100 ] ] );
} );

test( '#41 widget script uses the site origin as a JSON string and no URL selector', () => {
	const s = wb.heightScript( 'https://receiptgenpro.com/sub/' );
	assert.match( s, /e\.origin!=="https:\/\/receiptgenpro\.com"/ );
	assert.doesNotMatch( s, /iframe\[src/ );
	assert.match( wb.heightScript( 'https://a.example:8443/' ), /"https:\/\/a\.example:8443"/ );
} );
