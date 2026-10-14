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
