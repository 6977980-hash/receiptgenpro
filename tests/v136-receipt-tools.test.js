// Theme 1.3.6 (audit of 25 Oct 2026, Phase 1): receipt numbering across
// downloads (A25-H1), the seller's address / contact / tax number (A25-H2),
// donation receipts without tax and with a quid pro quo line (A25-M2) and
// the QR caption (A25-M1). Real markup and script in jsdom, like
// tests/v135-dom.test.js. Without jsdom the tests are skipped, unless
// RF_REQUIRE_JSDOM is set.
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
const THEME = path.join( ROOT, 'wp-content/themes/receiptgenpro' );
const JS = path.join( THEME, 'assets/js' );

function render( slug ) {
	const args = [ path.join( __dirname, 'php/render-generator.php' ) ];
	if ( slug ) {
		args.push( slug );
	}
	return JSON.parse( execFileSync( 'php', args, { encoding: 'utf8' } ) );
}

// Opens the generator with stand-ins for the cdnjs libraries: downloads
// land in w.files (file names), QR code texts in w.qrTexts.
function openGenerator( { slug = '', before, qr = false } = {} ) {
	const page = render( slug );
	const dom = new JSDOM( '<!doctype html><html><head></head><body>' + page.html + '</body></html>', {
		url: 'https://example.com/' + ( slug ? slug + '/' : '' ),
		runScripts: 'outside-only',
		pretendToBeVisual: true
	} );
	const w = dom.window;
	w.rfReceipt = page.data;
	w.TextEncoder = TextEncoder; // every browser has it; jsdom doesn't
	w.files = [];
	w.qrTexts = [];
	w.document.getElementById( 'rf-qr' ).checked = qr;
	w.HTMLAnchorElement.prototype.click = function () {
		w.files.push( this.download );
	};
	w.alert = ( msg ) => {
		throw new Error( 'alert: ' + msg );
	};
	const canvas = { width: 1600, height: 900, toDataURL: () => 'data:image/png;base64,AAAA' };
	w.html2canvas = () => Promise.resolve( canvas );
	w.jspdf = {
		jsPDF: function () {
			return { addImage() {}, addPage() {}, setProperties() {}, setFont() {}, setFontSize() {}, splitTextToSize: ( l ) => l, text() {}, save( name ) {
				w.files.push( name );
			} };
		}
	};
	w.qrcode = () => ( {
		addData( text ) {
			w.qrTexts.push( text );
		},
		make() {},
		getModuleCount: () => 41,
		createDataURL: () => 'data:image/gif;base64,AAAA'
	} );
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

function item( w, n, name, qty, price ) {
	const row = w.document.querySelectorAll( '.rf-item-row' )[ n ];
	row.querySelector( '.rf-item-name' ).value = name;
	row.querySelector( '.rf-item-qty' ).value = qty;
	row.querySelector( '.rf-item-price' ).value = price;
	row.querySelector( '.rf-item-price' ).dispatchEvent( new w.Event( 'input', { bubbles: true } ) );
}

// Clicks a download button and waits for its file.
async function download( w, id ) {
	const count = w.files.length;
	w.document.getElementById( id ).click();
	for ( let i = 0; i < 100 && w.files.length === count; i++ ) {
		await new Promise( ( r ) => setTimeout( r, 5 ) );
	}
	assert.equal( w.files.length, count + 1, id + ' gave a file' );
	await new Promise( ( r ) => setTimeout( r, 5 ) ); // the number is stored after the file
	return w.files[ w.files.length - 1 ];
}

const preview = ( w ) => w.document.getElementById( 'rf-receipt-preview' );
const number = ( w ) => w.document.getElementById( 'rf-receipt-number' ).value;

// The QR code is redrawn at most every 300 ms while typing; the last
// change lands after that.
async function lastPayload( w ) {
	await new Promise( ( r ) => setTimeout( r, 350 ) );
	const text = w.qrTexts[ w.qrTexts.length - 1 ];
	const enc = text.split( '#r=' )[1];
	return JSON.parse( Buffer.from( enc.replace( /-/g, '+' ).replace( /_/g, '/' ), 'base64' ).toString( 'utf8' ) );
}

test( 'A25-H1: PDF, PNG and JPEG of the same receipt carry one number', { skip }, async () => {
	const w = openGenerator();
	type( w, 'rf-business-name', 'Ali Store' );
	item( w, 0, 'Tea', '2', '1.50' );
	assert.equal( number( w ), '1001' );

	assert.equal( await download( w, 'rf-download-pdf' ), 'receipt-1001.pdf' );
	assert.equal( await download( w, 'rf-download-png' ), 'receipt-1001.png' );
	assert.equal( await download( w, 'rf-download-jpg' ), 'receipt-1001.jpg' );
	assert.equal( number( w ), '1001' );
	assert.match( preview( w ).textContent, /Receipt #: 1001/ );
	assert.equal( w.localStorage.getItem( 'rgp-last-receipt-number' ), '1001' );

	// A change of design or an empty new row is not a new receipt.
	w.document.querySelector( 'input[name="rf-design"][value="modern"]' ).click();
	w.document.getElementById( 'rf-add-item' ).click();
	assert.equal( number( w ), '1001' );

	// The first real change moves the number on, once.
	type( w, 'rf-client-name', 'Sara' );
	assert.equal( number( w ), '1002' );
	assert.match( preview( w ).textContent, /Receipt #: 1002/ );
	type( w, 'rf-client-name', 'Sara Khan' );
	assert.equal( number( w ), '1002' );
	assert.equal( await download( w, 'rf-download-png' ), 'receipt-1002.png' );

	// A reload after a download starts at the next number.
	const last = w.localStorage.getItem( 'rgp-last-receipt-number' );
	const w2 = openGenerator( {
		before( win ) {
			win.localStorage.setItem( 'rgp-last-receipt-number', last );
		}
	} );
	assert.equal( number( w2 ), '1003' );
} );

test( 'A25-H1: a number the visitor typed after a download is kept', { skip }, async () => {
	const w = openGenerator();
	type( w, 'rf-business-name', 'Ali Store' ); // 1.3.8: an unnamed receipt asks first
	item( w, 0, 'Tea', '1', '3' );
	await download( w, 'rf-download-png' );
	type( w, 'rf-receipt-number', 'INV-500' );
	assert.equal( number( w ), 'INV-500' ); // the number alone is not a change
	type( w, 'rf-client-name', 'Sara' );
	assert.equal( number( w ), 'INV-500' );
} );

test( 'A25-H2: business details print under the name; the tax number stays out of the QR code', { skip }, async () => {
	for ( const slug of [ '', 'rent-receipt', 'donation-receipt' ] ) {
		const w = openGenerator( { slug, qr: true } );
		const d = w.document;
		assert.ok( d.getElementById( 'rf-business-details' ), slug + ': has Business details' );
		type( w, 'rf-business-name', 'Ali Store' );
		type( w, 'rf-business-address', '12 High Street\nLeeds LS1 4AB' );
		type( w, 'rf-business-contact', '0113 496 0000 · hi@example.com' );
		d.getElementById( 'rf-tax-id-label' ).value = 'VAT No.';
		type( w, 'rf-tax-id', 'GB123456789' );
		const head = preview( w ).querySelector( '.rf-receipt-head' );
		assert.match( head.innerHTML, /12 High Street<br>Leeds LS1 4AB/, slug );
		assert.match( head.textContent, /0113 496 0000 · hi@example\.com/ );
		assert.match( head.textContent, /VAT No\.: GB123456789/ );

		const p = await lastPayload( w );
		assert.doesNotMatch( JSON.stringify( p ), /GB123456789/, slug + ': no tax number in the QR' );
		assert.deepEqual( p.x[0], [ 'Business address', '12 High Street, Leeds LS1 4AB' ] );
	}
} );

test( 'A25-H2: escaped, bidi stripped, address clipped to 60 in the QR, restored on reload', { skip }, async () => {
	const w = openGenerator( { qr: true } );
	type( w, 'rf-business-address', '<b>Main</b> Road ‮1234 ' + 'x'.repeat( 80 ) );
	const head = preview( w ).querySelector( '.rf-receipt-head' );
	assert.doesNotMatch( head.innerHTML, /<b>/ );
	assert.doesNotMatch( head.textContent, /‮/ );
	assert.equal( Array.from( ( await lastPayload( w ) ).x[0][1] ).length, 60 );

	await new Promise( ( r ) => setTimeout( r, 450 ) );
	const saved = w.sessionStorage.getItem( 'rgp-form:/' );
	const w2 = openGenerator( {
		before( win ) {
			win.sessionStorage.setItem( 'rgp-form:/', saved );
		}
	} );
	assert.match( w2.document.getElementById( 'rf-business-address' ).value, /Main<\/b> Road/ );
	assert.equal( w2.document.getElementById( 'rf-business-details' ).open, true );
} );

test( 'A25-H2: the expense check passes on the restaurant receipt with a business address', { skip }, () => {
	const w = openGenerator( { slug: 'restaurant-receipt' } );
	w.document.getElementById( 'rf-purpose' ).value = 'expense';
	w.document.getElementById( 'rf-purpose' ).dispatchEvent( new w.Event( 'change' ) );
	const place = () => Array.from( w.document.querySelectorAll( '#rf-checker-result li' ) ).find( ( li ) => /Place of the expense/.test( li.textContent ) );
	type( w, 'rf-business-address', '5 Market Square' );
	assert.equal( place().className, 'rf-check-ok' );
} );

test( 'A25-M2: a donation has no tax or discount, totals equal the gifts, QPQ line and in-kind lines', { skip }, () => {
	const w = openGenerator( { slug: 'donation-receipt' } );
	const d = w.document;
	assert.equal( d.getElementById( 'rf-tax-rate' ), null );
	assert.equal( d.getElementById( 'rf-discount' ), null );
	assert.equal( d.getElementById( 'rf-tip' ), null );

	item( w, 0, 'Cash donation', '1', '300' );
	item( w, 1, 'In-kind: 3 boxes of canned food', '1', '' );
	const text = preview( w ).textContent;
	assert.match( text, /Total\$300\.00/ );
	assert.doesNotMatch( text, /Tax|Discount/ );
	assert.match( text, /canned food1No value statedNo value stated/ );
	assert.match( text, /No goods or services were provided/ );

	type( w, 'rf-x-qpq_desc', 'a gala dinner ticket' );
	type( w, 'rf-x-qpq_value', '60' );
	const t2 = preview( w ).textContent;
	assert.match( t2, /In return for this contribution the donor received a gala dinner ticket, with an estimated value of \$60\.00\. Only the amount above this value may be tax-deductible\./ );
	assert.doesNotMatch( t2, /No goods or services were provided/ );
	assert.doesNotMatch( t2, /Their estimated value:/ ); // not also a "Label: value" line
} );

test( 'A25-M2: the picker hides the tax controls for Donation and shows them again', { skip }, () => {
	const w = openGenerator();
	const d = w.document;
	const click = ( slug ) => d.querySelector( '.rf-type-option[data-type="' + slug + '"]' ).dispatchEvent( new w.MouseEvent( 'click', { bubbles: true, cancelable: true } ) );
	item( w, 0, 'Gift', '1', '300' );
	type( w, 'rf-tax-rate', '7' );
	assert.match( preview( w ).textContent, /Total\$321\.00/ );

	click( 'donation-receipt' );
	assert.equal( d.getElementById( 'rf-tax-rate-field' ).hidden, true );
	assert.equal( d.getElementById( 'rf-more-options' ).hidden, true );
	assert.match( preview( w ).textContent, /Total\$300\.00/ );
	assert.ok( d.getElementById( 'rf-x-qpq_desc' ) );

	click( 'general' );
	assert.equal( d.getElementById( 'rf-tax-rate-field' ).hidden, false );
	assert.equal( d.getElementById( 'rf-more-options' ).hidden, false );
	assert.match( preview( w ).textContent, /Total\$321\.00/ );
} );

test( 'A25-M1: the printed QR caption does not say "verify"', { skip }, () => {
	const w = openGenerator( { qr: true } );
	const qr = preview( w ).querySelector( '.rf-receipt-qr' );
	assert.match( qr.textContent, /Scan to view the details in this code/ );
	assert.doesNotMatch( qr.textContent.replace( /\S*verify-receipt\S*/, '' ), /verif/i );
	assert.equal( qr.querySelector( 'img' ).alt, 'QR code with this receipt\'s details' );
} );
