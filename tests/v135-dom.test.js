// DOM tests for the generator and the verify page (theme 1.3.5, audit of
// 20 Oct 2026, #69): the real markup (rendered by
// tests/php/render-generator.php) and the real scripts, run in jsdom.
// Needs jsdom (CI installs it; locally: npm i --no-save jsdom) and php.
// Without jsdom the tests are skipped, unless RF_REQUIRE_JSDOM is set.
'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { execFileSync } = require( 'node:child_process' );

let JSDOM = null;
try {
	( { JSDOM } = require( 'jsdom' ) );
} catch ( e ) {
	if ( process.env.RF_REQUIRE_JSDOM ) {
		throw e;
	}
}
const skip = JSDOM ? false : 'jsdom is not installed';

const ROOT = path.join( __dirname, '..' );
const JS = path.join( ROOT, 'wp-content/themes/receiptgenpro/assets/js' );
const rg = require( path.join( JS, 'receipt-generator.js' ) );

function render( slug ) {
	const args = [ path.join( __dirname, 'php/render-generator.php' ) ];
	if ( slug ) {
		args.push( slug );
	}
	return JSON.parse( execFileSync( 'php', args, { encoding: 'utf8' } ) );
}

// Opens the generator. `before( window )` runs before the script, e.g. to
// seed the tab's sessionStorage or the browser's localStorage.
function openGenerator( { slug = '', before, url = 'https://example.com/' } = {} ) {
	const page = render( slug );
	const dom = new JSDOM( '<!doctype html><html><head></head><body>' + page.html + '</body></html>', {
		url,
		runScripts: 'outside-only',
		pretendToBeVisual: true
	} );
	const w = dom.window;
	w.rfReceipt = page.data;
	// The QR library would be fetched from cdnjs; these tests don't need it.
	w.document.getElementById( 'rf-qr' ).checked = false;
	if ( before ) {
		before( w );
	}
	w.eval( fs.readFileSync( path.join( JS, 'receipt-generator.js' ), 'utf8' ) );
	w.document.dispatchEvent( new w.Event( 'DOMContentLoaded' ) );
	return w;
}

function type( w, id, value ) {
	const el = w.document.getElementById( id );
	el.value = value;
	el.dispatchEvent( new w.Event( 'input', { bubbles: true } ) );
}

function firstItem( w, name, qty, price ) {
	const row = w.document.querySelector( '.rf-item-row' );
	row.querySelector( '.rf-item-name' ).value = name;
	row.querySelector( '.rf-item-qty' ).value = qty;
	row.querySelector( '.rf-item-price' ).value = price;
	row.querySelector( '.rf-item-price' ).dispatchEvent( new w.Event( 'input', { bubbles: true } ) );
}

const preview = ( w ) => w.document.getElementById( 'rf-receipt-preview' );

test( 'renderReceipt draws what is typed, with escaped text and totals', { skip }, () => {
	const w = openGenerator();
	type( w, 'rf-business-name', 'Ali <b>Store</b>' );
	type( w, 'rf-client-name', 'Sara' );
	firstItem( w, 'Notebook', '3', '2.50' );
	type( w, 'rf-tax-rate', '10' );
	const html = preview( w ).innerHTML;
	const text = preview( w ).textContent;
	assert.match( text, /Ali <b>Store<\/b>/ ); // shown as text…
	assert.doesNotMatch( html, /<b>Store/ ); // …not as markup
	assert.match( text, /Sara/ );
	assert.match( text, /Notebook/ );
	assert.match( text, /\$7\.50/ ); // 3 × 2.50
	assert.match( text, /\$8\.25/ ); // + 10 % tax
} );

test( 'applyType: HRA switches to INR and back to USD (FUN-01)', { skip }, () => {
	const w = openGenerator();
	const link = ( slug ) => w.document.querySelector( '.rf-type-option[data-type="' + slug + '"]' );
	const currency = () => w.document.getElementById( 'rf-currency' ).value;
	type( w, 'rf-business-name', 'Kept Business' );

	link( 'hra-rent-receipt' ).dispatchEvent( new w.MouseEvent( 'click', { bubbles: true, cancelable: true } ) );
	assert.equal( currency(), 'INR' );
	assert.equal( link( 'hra-rent-receipt' ).getAttribute( 'aria-current' ), 'true' );
	assert.ok( w.document.getElementById( 'rf-landlord_pan' ) || w.document.querySelector( '#rf-type-extra [id*="pan"]' ), 'HRA has a landlord PAN field' );
	assert.match( w.location.search, /type=hra-rent-receipt/ );
	assert.match( preview( w ).textContent, /₹/ );

	link( 'cash-receipt' ).dispatchEvent( new w.MouseEvent( 'click', { bubbles: true, cancelable: true } ) );
	assert.equal( currency(), 'USD' );
	assert.equal( w.document.getElementById( 'rf-business-name' ).value, 'Kept Business' );
} );

test( 'the form is saved in the tab and restored on reload', { skip }, async () => {
	const w = openGenerator();
	type( w, 'rf-business-name', 'Saved Shop' );
	firstItem( w, 'Widget', '2', '4' );
	await new Promise( ( r ) => setTimeout( r, 450 ) ); // saved once typing pauses
	const saved = JSON.parse( w.sessionStorage.getItem( 'rgp-form:/' ) );
	assert.equal( saved.fields[ 'rf-business-name' ], 'Saved Shop' );
	assert.deepEqual( saved.items[0], { name: 'Widget', qty: '2', price: '4', hsn: '' } );

	const w2 = openGenerator( {
		before( win ) {
			win.sessionStorage.setItem( 'rgp-form:/', JSON.stringify( saved ) );
		}
	} );
	assert.equal( w2.document.getElementById( 'rf-business-name' ).value, 'Saved Shop' );
	assert.equal( w2.document.querySelector( '.rf-item-name' ).value, 'Widget' );
	assert.match( preview( w2 ).textContent, /\$8\.00/ );
} );

test( 'the receipt number continues from the last download (FUN-02)', { skip }, () => {
	const w = openGenerator( {
		before( win ) {
			win.localStorage.setItem( 'rgp-last-receipt-number', 'INV-0099' );
		}
	} );
	assert.equal( w.document.getElementById( 'rf-receipt-number' ).value, 'INV-0100' );

	// A number saved in the tab that was already downloaded moves on too.
	const w2 = openGenerator( {
		before( win ) {
			win.localStorage.setItem( 'rgp-last-receipt-number', '2026-009' );
			win.sessionStorage.setItem( 'rgp-form:/', JSON.stringify( { type: 'general', fields: { 'rf-receipt-number': '2026-009' }, items: [] } ) );
		}
	} );
	assert.equal( w2.document.getElementById( 'rf-receipt-number' ).value, '2026-010' );
} );

test( 'verify show(): a good link shows the details, a damaged one says so', { skip }, () => {
	const link = rg.buildPayload( {
		title: 'CASH RECEIPT',
		business: 'Ali <i>Store</i>',
		client: 'Sara',
		number: '1001',
		date: '2026-10-20',
		currency: 'EUR',
		items: [ { name: 'Tea', qty: 2, priceCents: 150 } ],
		discountCents: 0,
		taxes: [],
		tipCents: 0,
		totalCents: 300,
		paidCents: null,
		payMethod: 'Cash',
		extras: []
	} );
	const markup = '<p id="rf-verify-status" role="status"></p><div id="rf-verify-details" hidden></div>' +
		'<form id="rf-verify-form"><input id="rf-verify-input"></form>';

	function open( hash ) {
		const dom = new JSDOM( '<!doctype html><body>' + markup + '</body>', {
			url: 'https://example.com/verify-receipt/' + hash,
			runScripts: 'outside-only'
		} );
		const w = dom.window;
		w.TextDecoder = TextDecoder; // every browser has it; jsdom doesn't
		w.rfVerify = { symbols: { EUR: '€' } };
		w.eval( fs.readFileSync( path.join( JS, 'verify-receipt.js' ), 'utf8' ) );
		w.document.dispatchEvent( new w.Event( 'DOMContentLoaded' ) );
		return w;
	}

	const w = open( '#r=' + link );
	const details = w.document.getElementById( 'rf-verify-details' );
	assert.equal( details.hidden, false );
	assert.match( details.textContent, /Ali <i>Store<\/i>/ );
	assert.doesNotMatch( details.innerHTML, /<i>Store/ );
	assert.match( details.textContent, /€3\.00/ );
	assert.match( w.document.getElementById( 'rf-verify-status' ).className, /rf-verify-status-info/ );

	const bad = open( '#r=' + link.slice( 0, 20 ) );
	assert.equal( bad.document.getElementById( 'rf-verify-details' ).hidden, true );
	assert.match( bad.document.getElementById( 'rf-verify-status' ).className, /rf-verify-status-bad/ );
} );
