// Unit tests for the pure money / words helpers in receipt-generator.js.
// Run: node --test tests/*.test.js
'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const rg = require( '../wp-content/themes/receiptgenpro/assets/js/receipt-generator.js' );

test( 'toCents rounds to whole cents without float noise', () => {
	assert.equal( rg.toCents( 1.005 ), 101 );
	assert.equal( rg.toCents( 0.1 + 0.2 ), 30 );
	assert.equal( rg.toCents( 19.99 ), 1999 );
	assert.equal( rg.toCents( 2.999 ), 300 );
} );

test( 'nonNegative treats empty, invalid and negative input as 0', () => {
	assert.equal( rg.nonNegative( '' ), 0 );
	assert.equal( rg.nonNegative( 'abc' ), 0 );
	assert.equal( rg.nonNegative( '-5' ), 0 );
	assert.equal( rg.nonNegative( '12.5' ), 12.5 );
	assert.equal( rg.nonNegative( '', 1 ), 1 );
} );

test( 'intToWords uses thousand / million', () => {
	assert.equal( rg.intToWords( 0 ), 'zero' );
	assert.equal( rg.intToWords( 21 ), 'twenty-one' );
	assert.equal( rg.intToWords( 1500 ), 'one thousand five hundred' );
	assert.equal( rg.intToWords( 2000001 ), 'two million one' );
} );

test( 'intToWordsIndian uses lakh / crore', () => {
	assert.equal( rg.intToWordsIndian( 120000 ), 'one lakh twenty thousand' );
	assert.equal( rg.intToWordsIndian( 12500000 ), 'one crore twenty-five lakh' );
} );

test( 'amountInWords', () => {
	assert.equal( rg.amountInWords( 100, 'USD' ), 'One hundred US dollars only' );
	assert.equal( rg.amountInWords( 12.5, 'USD' ), 'Twelve US dollars and fifty cents only' );
	// 2.999 rounds up to 3.00, never "two dollars and one hundred cents".
	assert.equal( rg.amountInWords( 2.999, 'USD' ), 'Three US dollars only' );
	assert.equal( rg.amountInWords( 120000, 'INR' ), 'One lakh twenty thousand Indian rupees only' );
	assert.equal( rg.amountInWords( 150000.5, 'PKR' ), 'One lakh fifty thousand Pakistani rupees and fifty paisa only' );
} );

test( 'calcTotals: discount comes off before tax, two taxes on the discounted amount', () => {
	// 1000.00 subtotal, 10% discount, CGST 9% + SGST 9%.
	const r = rg.calcTotals( 100000, 10, 'percent', [ 9, 9 ], 0 );
	assert.equal( r.discountCents, 10000 );
	assert.deepEqual( r.taxCents, [ 8100, 8100 ] );
	assert.equal( r.totalCents, 90000 + 16200 );
} );

test( 'calcTotals: fixed discount is capped at the subtotal, tip is added untaxed', () => {
	assert.equal( rg.calcTotals( 5000, 80, 'amount', [ 10, 0 ], 0 ).totalCents, 0 );
	const r = rg.calcTotals( 5000, 5, 'amount', [ 10, 0 ], 300 );
	assert.equal( r.discountCents, 500 );
	assert.deepEqual( r.taxCents, [ 450, 0 ] );
	assert.equal( r.totalCents, 4500 + 450 + 300 );
	// A percentage above 100 counts as 100.
	assert.equal( rg.calcTotals( 5000, 150, 'percent', [ 0, 0 ], 0 ).discountCents, 5000 );
} );

test( 'calcTotals: no discount and one tax matches the old single-tax maths', () => {
	const r = rg.calcTotals( 1999, 0, 'percent', [ 8.25, 0 ], 0 );
	assert.equal( r.taxCents[0], rg.toCents( 1999 * 8.25 / 10000 ) );
	assert.equal( r.totalCents, 1999 + r.taxCents[0] );
} );

test( 'nextReceiptNumber keeps prefixes and zero padding', () => {
	assert.equal( rg.nextReceiptNumber( '1001' ), '1002' );
	assert.equal( rg.nextReceiptNumber( 'INV-0099' ), 'INV-0100' );
	assert.equal( rg.nextReceiptNumber( '2026-009' ), '2026-010' );
	assert.equal( rg.nextReceiptNumber( '999' ), '1000' );
	assert.equal( rg.nextReceiptNumber( 'ABC' ), 'ABC-2' );
	assert.equal( rg.nextReceiptNumber( '' ), '1001' );
} );

test( 'typeFieldsHtml builds the extra fields, tip / paid and statement', () => {
	const out = rg.typeFieldsHtml( {
		title: 'RENT RECEIPT',
		extra: [
			{ id: 'property', label: 'Property Address', type: 'text', ph: 'e.g. 24 "Oak" St' },
			{ id: 'Freq', label: 'Frequency', type: 'select', options: [ 'Weekly', 'Monthly' ] },
		],
		paid: true,
		statement: { label: 'Print it', text: 'No goods <or> services', checked: true },
	} );
	assert.match( out.extra, /<legend>Rent Receipt Details<\/legend>/ );
	assert.match( out.extra, /id="rf-x-property"/ );
	assert.match( out.extra, /placeholder="e.g. 24 &quot;Oak&quot; St"/ );
	assert.match( out.extra, /<select id="rf-x-freq"[^>]*><option>Weekly<\/option><option>Monthly<\/option><\/select>/ );
	assert.match( out.tippaid, /id="rf-amount-paid"/ );
	assert.doesNotMatch( out.tippaid, /id="rf-tip"/ );
	assert.match( out.statement, /data-text="No goods &lt;or&gt; services" checked/ );
} );

test( 'typeFieldsHtml returns empty parts for the general receipt', () => {
	assert.deepEqual( rg.typeFieldsHtml( { title: 'RECEIPT', extra: [] } ), { extra: '', tippaid: '', statement: '' } );
} );

test( 'wrapDesign prints two copies only for the copies design', () => {
	assert.equal( rg.wrapDesign( 'modern', 'X' ), '<div class="rf-receipt-inner">X</div>' );
	const two = rg.wrapDesign( 'copies', 'X' );
	assert.equal( two.split( 'rf-receipt-inner' ).length - 1, 2 );
	assert.match( two, /Office copy[\s\S]*cut here[\s\S]*Customer copy/ );
} );
