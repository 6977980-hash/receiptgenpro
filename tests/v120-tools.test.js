// Unit tests for the v1.2.0 helpers: requirement checks, the verification
// QR payload (encode in receipt-generator.js, decode in verify-receipt.js)
// and the widget embed code.
// Run: node --test tests/*.test.js
'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const rg = require( '../wp-content/themes/receiptgenpro/assets/js/receipt-generator.js' );
const vr = require( '../wp-content/themes/receiptgenpro/assets/js/verify-receipt.js' );
const wb = require( '../wp-content/themes/receiptgenpro/assets/js/widget-builder.js' );

const base = { business: true, client: true, date: true, items: true, total: true, words: true, signature: true, statement: null, x: { property: true, landlord_pan: false }, amount: 12000, cash: true };

test( 'evaluateChecks: ok, missing, not on template, manual, conditional', () => {
	const r = rg.evaluateChecks( [
		{ label: 'a', field: 'business' },
		{ label: 'b', field: 'x:landlord_pan', when: { total_over: 8333 } },
		{ label: 'c', field: 'x:child' },
		{ label: 'd', manual: true },
		{ label: 'e', field: 'x:landlord_pan', when: { total_over: 20000 } },
		{ label: 'f', manual: true, when: { total_over: 5000, cash: true } },
		{ label: 'g', field: 'statement' },
		{ label: 'h', field: [ 'x:rent_month', 'x:property' ] },
		{ label: 'i', field: [ 'x:property', 'x:missing' ], all: true }
	], base ).map( ( x ) => x.status );
	assert.deepEqual( r, [ 'ok', 'missing', 'nofield', 'manual', 'na', 'manual', 'nofield', 'ok', 'nofield' ] );
} );

test( 'evaluateChecks: cash-only rule does not apply to a bank transfer', () => {
	const r = rg.evaluateChecks( [ { label: 'stamp', manual: true, when: { total_over: 5000, cash: true } } ], Object.assign( {}, base, { cash: false } ) );
	assert.equal( r[0].status, 'na' );
} );

test( 'QR payload round-trips, including non-ASCII text', () => {
	const enc = rg.buildPayload( {
		title: 'RENT RECEIPT', business: 'Ramesh Kumar', client: 'Priyā Śharma', number: '1001', date: '2026-10-14',
		currency: 'INR', items: [ { name: 'Rent — April', qty: 1, priceCents: 1200000 } ], totalCents: 1200000,
		paidCents: null, payMethod: 'Cash', extras: [ [ 'Rent Month', 'April 2026' ] ]
	} );
	assert.match( enc, /^[A-Za-z0-9_-]+$/ );
	const p = vr.decodePayload( 'https://receiptgenpro.com/verify-receipt/#r=' + enc );
	assert.equal( p.c, 'Priyā Śharma' );
	assert.equal( p.tot, 1200000 );
	assert.deepEqual( p.i, [ [ 'Rent — April', 1, 1200000 ] ] );
	assert.equal( p.pd, undefined );
} );

test( 'QR payload stays under the size limit by dropping lines', () => {
	const items = Array.from( { length: 40 }, ( _, i ) => ( { name: 'Line item number ' + i + ' with a long description', qty: 1, priceCents: 100 } ) );
	const enc = rg.buildPayload( { title: 'RECEIPT', business: 'B', client: 'C', number: '1', date: '2026-01-01', currency: 'USD', items, totalCents: 4000, payMethod: 'Card', extras: [] } );
	assert.ok( enc.length <= 1200, String( enc.length ) );
	const p = vr.decodePayload( '#r=' + enc );
	assert.equal( p.more, 1 );
	assert.equal( p.tot, 4000 );
} );

test( 'decodePayload rejects links without a valid receipt', () => {
	assert.equal( vr.decodePayload( '' ), null );
	assert.equal( vr.decodePayload( '#r=not-json' ), null );
	assert.equal( vr.decodePayload( '#r=' + rg.b64urlEncode( JSON.stringify( { v: 2 } ) ) ), null );
} );

test( 'widget code has the iframe, the credit link and an origin-checked listener', () => {
	const code = wb.buildCode( { embedBase: 'https://receiptgenpro.com/embed/', site: 'https://receiptgenpro.com/', brand: 'ReceiptGenPro', slug: 'rent-receipt', label: 'Rent Receipt Generator', page: 'https://receiptgenpro.com/rent-receipt/', height: 1600 } );
	assert.match( code, /<iframe src="https:\/\/receiptgenpro\.com\/embed\/rent-receipt\/"/ );
	assert.match( code, /<a href="https:\/\/receiptgenpro\.com\/rent-receipt\/">rent receipt generator<\/a> by ReceiptGenPro/ );
	assert.match( code, /e\.origin!=="https:\/\/receiptgenpro\.com"/ );
} );

test( 'decodePayload rejects malformed lines, extras and amounts', () => {
	const enc = ( o ) => '#r=' + rg.b64urlEncode( JSON.stringify( o ) );
	assert.equal( vr.decodePayload( enc( { v: 1, tot: 1, i: [ null ], x: 'ab' } ) ), null );
	assert.equal( vr.decodePayload( enc( { v: 1, tot: 1, i: [ [ 'a', 1, 100 ] ], x: 'ab' } ) ), null );
	assert.equal( vr.decodePayload( enc( { v: 1, tot: 1, i: [ [ 'a', 1, 100 ] ], x: [ 'ab' ] } ) ), null );
	assert.equal( vr.decodePayload( enc( { v: 1, tot: 1.5, i: [] } ) ), null );
	assert.equal( vr.decodePayload( enc( { v: 1, tot: 1, i: [ [ 'a', 1, '100' ] ] } ) ), null );
	assert.equal( vr.decodePayload( enc( { v: 1, tot: 1, i: [], pd: 'x' } ) ), null );
	assert.equal( vr.decodePayload( enc( { v: 1, tot: 1e400, i: [] } ) ), null );
	assert.equal( vr.decodePayload( enc( null ) ), null );
	const ok = vr.decodePayload( enc( { v: 1, tot: 100, i: [ [ 'a', 1, 100 ] ], x: [ [ 'Rent Month', 'April' ] ], pd: 100 } ) );
	assert.equal( ok.tot, 100 );
} );
