// Theme 1.4.1 (audit of 25 Oct 2026, v2 Phase 5): a year of HRA receipts
// in one PDF (G-4), a year-end donation statement (G-7) and refund
// receipts / credit notes (G-9). The DOM tests use jsdom like
// tests/v140-profile-currencies.test.js; without it they are skipped,
// unless RF_REQUIRE_JSDOM is set.
'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { execFileSync } = require( 'node:child_process' );
const rg = require( '../wp-content/themes/receiptgenpro/assets/js/receipt-generator.js' );
const vr = require( '../wp-content/themes/receiptgenpro/assets/js/verify-receipt.js' );

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
const LAST_NUMBER_KEY = 'rgp-last-receipt-number';

function render( slug ) {
	const args = [ path.join( __dirname, 'php/render-generator.php' ) ];
	if ( slug ) {
		args.push( slug );
	}
	return JSON.parse( execFileSync( 'php', args, { encoding: 'utf8' } ) );
}

// Opens the generator with stand-ins for the cdnjs libraries: each jsPDF
// document is kept in w.pdfs (its pages, page formats, invisible text and
// file name), downloads in w.files, QR code texts in w.qrTexts. `script`
// is the generator's source (default: this branch's).
function openGenerator( { slug = '', qr = false, local = {}, script } = {} ) {
	const page = render( slug );
	const dom = new JSDOM( '<!doctype html><html><head></head><body>' + page.html + '</body></html>', {
		url: 'https://example.com/' + ( slug ? slug + '/' : '' ),
		runScripts: 'outside-only',
		pretendToBeVisual: true
	} );
	const w = dom.window;
	w.rfReceipt = page.data;
	w.TextEncoder = TextEncoder;
	w.files = [];
	w.qrTexts = [];
	w.pdfs = [];
	w.document.getElementById( 'rf-qr' ).checked = qr;
	Object.keys( local ).forEach( ( k ) => w.localStorage.setItem( k, local[ k ] ) );
	w.HTMLAnchorElement.prototype.click = function () {
		w.files.push( this.download );
	};
	w.alert = ( msg ) => {
		throw new Error( 'alert: ' + msg );
	};
	w.html2canvas = () => Promise.resolve( { width: 1600, height: 900, toDataURL: () => 'data:image/png;base64,AAAA' } );
	w.jspdf = {
		jsPDF: function ( opts ) {
			const doc = {
				formats: [ opts.format ],
				texts: [],
				props: null,
				addImage() {},
				addPage( format ) {
					this.formats.push( format );
				},
				setProperties( p ) {
					this.props = p;
				},
				setFont() {},
				setFontSize() {},
				splitTextToSize: ( l ) => l,
				text( t ) {
					this.texts.push( [].concat( t ).join( '\n' ) );
				},
				save( name ) {
					this.name = name;
					w.files.push( name );
				}
			};
			w.pdfs.push( doc );
			return doc;
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
	w.document.addEventListener( 'DOMContentLoaded', ( e ) => {
		if ( e.isTrusted ) {
			e.stopImmediatePropagation();
		}
	} );
	w.eval( script || fs.readFileSync( path.join( JS, 'receipt-generator.js' ), 'utf8' ) );
	w.document.dispatchEvent( new w.Event( 'DOMContentLoaded' ) );
	return w;
}

const $ = ( w, id ) => w.document.getElementById( id );
function type( w, id, value ) {
	const el = $( w, id );
	el.value = value;
	el.dispatchEvent( new w.Event( 'input', { bubbles: true } ) );
}
function select( w, id, value ) {
	const el = $( w, id );
	el.value = value;
	el.dispatchEvent( new w.Event( 'change', { bubbles: true } ) );
}
function item( w, n, name, qty, price, date ) {
	while ( w.document.querySelectorAll( '.rf-item-row' ).length <= n ) {
		$( w, 'rf-add-item' ).click();
	}
	const row = w.document.querySelectorAll( '.rf-item-row' )[ n ];
	row.querySelector( '.rf-item-name' ).value = name;
	row.querySelector( '.rf-item-qty' ).value = qty;
	row.querySelector( '.rf-item-price' ).value = price;
	if ( date !== undefined ) {
		row.querySelector( '.rf-item-date' ).value = date;
	}
	row.querySelector( '.rf-item-price' ).dispatchEvent( new w.Event( 'input', { bubbles: true } ) );
}
async function waitFiles( w, count ) {
	for ( let i = 0; i < 400 && w.files.length < count; i++ ) {
		await new Promise( ( r ) => setTimeout( r, 5 ) );
	}
	assert.equal( w.files.length, count, 'a file was saved' );
	await new Promise( ( r ) => setTimeout( r, 10 ) );
}
function decode( text ) {
	const enc = text.split( '#r=' )[1];
	return JSON.parse( Buffer.from( enc.replace( /-/g, '+' ).replace( /_/g, '/' ), 'base64' ).toString( 'utf8' ) );
}
async function lastPayload( w ) {
	await new Promise( ( r ) => setTimeout( r, 350 ) );
	return decode( w.qrTexts[ w.qrTexts.length - 1 ] );
}
const text = ( w ) => $( w, 'rf-receipt-preview' ).textContent;
function link( p ) {
	return '#r=' + Buffer.from( JSON.stringify( p ) ).toString( 'base64' ).replace( /\+/g, '-' ).replace( /\//g, '_' ).replace( /=+$/, '' );
}
function verifyText( p ) {
	const dom = new JSDOM( '<!doctype html><body><p id="rf-verify-status"></p><div id="rf-verify-details" hidden></div></body>', {
		url: 'https://example.com/verify-receipt/' + link( p ),
		runScripts: 'outside-only'
	} );
	const w = dom.window;
	w.rfVerify = { symbols: { USD: '$', INR: '₹' }, decimals: { USD: 2, INR: 2 } };
	w.TextDecoder = TextDecoder;
	w.eval( fs.readFileSync( path.join( JS, 'verify-receipt.js' ), 'utf8' ) );
	w.document.dispatchEvent( new w.Event( 'DOMContentLoaded' ) );
	return w.document.getElementById( 'rf-verify-details' ).textContent;
}

// ---- G-4: helpers -------------------------------------------------------------

test( 'G-4: a month range, at most 12 months', () => {
	const fy = rg.monthRange( '2026-04', '2027-03' );
	assert.equal( fy.error, '' );
	assert.equal( fy.months.length, 12 );
	assert.equal( fy.months[0], '2026-04' );
	assert.equal( fy.months[8], '2026-12' );
	assert.equal( fy.months[9], '2027-01' );
	assert.equal( fy.months[11], '2027-03' );
	assert.deepEqual( rg.monthRange( '2026-10', '2026-10' ).months, [ '2026-10' ] );
	assert.deepEqual( rg.monthRange( '2026-04', '2027-04' ), { months: [], error: 'max', count: 13 } );
	assert.equal( rg.monthRange( '2026-05', '2026-04' ).error, 'invalid', 'To before From' );
	assert.equal( rg.monthRange( '', '2026-04' ).error, 'invalid' );
	assert.equal( rg.monthRange( '2026-13', '2027-01' ).error, 'invalid' );
	assert.equal( rg.MAX_MONTHS, 12 );
} );

test( 'G-4: receipt dates, labels, the financial year and the numbers', () => {
	assert.equal( rg.monthReceiptDate( '2026-04', 'first' ), '2026-04-01' );
	assert.equal( rg.monthReceiptDate( '2026-04', 'last' ), '2026-04-30' );
	assert.equal( rg.monthReceiptDate( '2027-02', 'last' ), '2027-02-28' );
	assert.equal( rg.monthReceiptDate( '2028-02', 'last' ), '2028-02-29' );
	assert.equal( rg.monthReceiptDate( '2026-12', '' ), '2026-12-01', 'the 1st by default' );
	assert.equal( rg.monthLabel( '2027-03' ), 'March 2027' );
	assert.deepEqual( rg.financialYear( '2026-10-25' ), { from: '2026-04', to: '2027-03' } );
	assert.deepEqual( rg.financialYear( '2027-02-10' ), { from: '2026-04', to: '2027-03' } );
	assert.deepEqual( rg.financialYear( '2027-04-01' ), { from: '2027-04', to: '2028-03' } );
	const nums = rg.receiptNumbers( 'RENT-0007', 12 );
	assert.equal( nums.length, 12 );
	assert.equal( nums[0], 'RENT-0007' );
	assert.equal( nums[11], 'RENT-0018' );
	assert.deepEqual( rg.receiptNumbers( 'HRA/9/2026', 3 ), [ 'HRA/9/2026', 'HRA/10/2026', 'HRA/11/2026' ] );
	assert.deepEqual( rg.receiptNumbers( '', 2 ), [ '1001', '1002' ] );
} );

// ---- G-4: the form ------------------------------------------------------------

function hraForm( opts = {} ) {
	const w = openGenerator( Object.assign( { slug: 'hra-rent-receipt', qr: true }, opts ) );
	type( w, 'rf-business-name', 'Ramesh Kumar' );
	type( w, 'rf-client-name', 'Priya Sharma' );
	type( w, 'rf-x-property', 'Flat 302, Green Towers, Pune' );
	type( w, 'rf-x-landlord_pan', 'ABCDE1234F' );
	type( w, 'rf-receipt-number', 'RENT-0007' );
	item( w, 0, 'House rent', 1, 25000 );
	return w;
}

test( 'G-4: the panel is on the HRA form only, starting at this financial year', { skip }, () => {
	const w = hraForm();
	const fy = rg.financialYear( new Date().toISOString().slice( 0, 10 ) );
	assert.equal( $( w, 'rf-months-from' ).value, fy.from );
	assert.equal( $( w, 'rf-months-to' ).value, fy.to );
	assert.equal( $( w, 'rf-months-day' ).value, 'first' );
	assert.match( $( w, 'rf-months-rent' ).placeholder, /25,000\.00/ );
	for ( const slug of [ 'rent-receipt', 'donation-receipt', 'refund-receipt' ] ) {
		assert.equal( openGenerator( { slug } ).document.getElementById( 'rf-months' ), null, slug + ' has no months panel' );
	}
} );

test( 'G-4: 12 months make one PDF with 12 pages, each month its own number, text and QR code', { skip }, async () => {
	const w = hraForm();
	type( w, 'rf-months-from', '2026-04' );
	type( w, 'rf-months-to', '2027-03' );
	select( w, 'rf-months-day', 'last' );
	const before = text( w );
	const qrBefore = w.qrTexts.length;
	$( w, 'rf-download-months' ).click();
	await waitFiles( w, 1 );
	assert.equal( w.files[0], 'hra-rent-receipts-2026-04-2027-03.pdf' );
	const pdf = w.pdfs[0];
	assert.equal( pdf.formats.length, 12, '12 pages' );
	assert.ok( pdf.formats.every( ( f ) => f === 'a4' ), 'A4 for INR' );
	assert.equal( pdf.texts.length, 12, 'a text layer on each page' );
	const months = rg.monthRange( '2026-04', '2027-03' ).months.map( rg.monthLabel );
	pdf.texts.forEach( ( t, i ) => {
		const n = 'RENT-' + String( 7 + i ).padStart( 4, '0' );
		assert.ok( t.includes( n ), 'page ' + ( i + 1 ) + ' has ' + n );
		assert.ok( t.includes( 'House rent for ' + months[ i ] ), 'page ' + ( i + 1 ) + ' item' );
		assert.ok( t.includes( 'Rent Month: ' + months[ i ] ), 'page ' + ( i + 1 ) + ' rent month' );
		assert.ok( t.includes( 'INR 25,000.00' ), 'page ' + ( i + 1 ) + ' amount' );
	} );
	assert.ok( pdf.texts[0].includes( '30/04/2026' ), 'last day of April, day first for INR' );
	assert.ok( pdf.texts[10].includes( '28/02/2027' ) );
	assert.match( pdf.props.title, /^Rent receipts April 2026 to March 2027$/ );
	// One QR code per month, with its own number, date and month.
	const codes = w.qrTexts.slice( qrBefore ).map( decode ).filter( ( p ) => /^RENT-00(0[7-9]|1[0-8])$/.test( p.n ) && p.i[0] && /House rent for/.test( p.i[0][0] ) );
	const byNumber = {};
	codes.forEach( ( p ) => {
		byNumber[ p.n ] = p;
	} );
	assert.equal( Object.keys( byNumber ).length, 12 );
	assert.equal( byNumber['RENT-0018'].d, '2027-03-31' );
	assert.equal( byNumber['RENT-0018'].tot, 2500000 );
	assert.ok( byNumber['RENT-0012'].x.some( ( e ) => e[0] === 'Rent Month' && e[1] === 'September 2026' ) );
	// The last number is remembered; the form is as it was, one number on.
	assert.equal( w.localStorage.getItem( LAST_NUMBER_KEY ), 'RENT-0018' );
	assert.equal( $( w, 'rf-receipt-number' ).value, 'RENT-0019' );
	assert.equal( text( w ), before.replace( 'RENT-0007', 'RENT-0019' ) );
	assert.match( $( w, 'rf-months-progress' ).textContent, /12 receipts .*RENT-0007 to RENT-0018/ );
} );

test( 'G-4: a monthly rent typed in the panel, and the Thermal slip one 80 mm page per month', { skip }, async () => {
	const w = hraForm( { local: { 'rgp-design': 'thermal' } } );
	type( w, 'rf-months-from', '2026-04' );
	type( w, 'rf-months-to', '2026-06' );
	type( w, 'rf-months-rent', '18000' );
	$( w, 'rf-download-months' ).click();
	await waitFiles( w, 1 );
	const pdf = w.pdfs[0];
	assert.equal( pdf.formats.length, 3 );
	pdf.formats.forEach( ( f ) => assert.equal( f[0], 80 ) );
	assert.ok( pdf.texts[2].includes( 'INR 18,000.00' ) && pdf.texts[2].includes( 'June 2026' ) );
	assert.ok( text( w ).includes( '25,000.00' ), 'the receipt on screen keeps its own amount' );
} );

test( 'G-4: notes for more than 12 months and a missing PAN; empty name or rent asks first', { skip }, async () => {
	const w = hraForm();
	type( w, 'rf-months-from', '2026-04' );
	type( w, 'rf-months-to', '2027-05' );
	const note = $( w, 'rf-months-note' );
	assert.equal( note.hidden, false );
	assert.match( note.textContent, /at most 12 months \(14 are selected\)/ );
	$( w, 'rf-download-months' ).click();
	await new Promise( ( r ) => setTimeout( r, 50 ) );
	assert.equal( w.files.length, 0, 'no download for 14 months' );
	type( w, 'rf-months-to', '2027-03' );
	assert.equal( note.hidden, true );
	// ₹9,000 a month is ₹1,08,000 a year: the PAN is needed.
	type( w, 'rf-x-landlord_pan', '' );
	type( w, 'rf-months-rent', '9000' );
	assert.equal( note.hidden, false );
	assert.match( note.textContent, /above ₹1,00,000.*landlord's PAN/ );
	type( w, 'rf-months-rent', '8000' );
	assert.equal( note.hidden, true, '₹96,000 a year: no PAN note' );
	// No landlord name: the download check asks first, and "Download anyway" goes on.
	type( w, 'rf-business-name', '' );
	$( w, 'rf-download-months' ).click();
	assert.equal( $( w, 'rf-export-check' ).hidden, false );
	assert.match( $( w, 'rf-export-check-text' ).textContent, /"Landlord Name" field is empty/ );
	$( w, 'rf-export-anyway' ).click();
	await waitFiles( w, 1 );
	assert.equal( w.pdfs[0].formats.length, 12 );
} );

test( 'G-4: on the homepage the HRA type shows the panel and the button works', { skip }, async () => {
	const w = openGenerator();
	assert.equal( $( w, 'rf-months' ), null );
	w.document.querySelector( '.rf-type-option[data-type="hra-rent-receipt"]' ).dispatchEvent( new w.MouseEvent( 'click', { bubbles: true, cancelable: true } ) );
	assert.ok( $( w, 'rf-months' ), 'panel built' );
	assert.ok( $( w, 'rf-months-from' ).value, 'range filled' );
	type( w, 'rf-business-name', 'Ramesh Kumar' );
	item( w, 0, 'House rent', 1, 20000 );
	type( w, 'rf-months-from', '2026-04' );
	type( w, 'rf-months-to', '2026-05' );
	$( w, 'rf-download-months' ).click();
	await waitFiles( w, 1 );
	assert.equal( w.files[0], 'hra-rent-receipts-2026-04-2026-05.pdf' );
	w.document.querySelector( '.rf-type-option[data-type="rent-receipt"]' ).dispatchEvent( new w.MouseEvent( 'click', { bubbles: true, cancelable: true } ) );
	assert.equal( $( w, 'rf-months' ), null, 'gone on another type' );
} );

test( 'G-4: the single PDF, PNG and JPEG downloads stay as they were', { skip }, async () => {
	const w = hraForm();
	$( w, 'rf-download-pdf' ).click();
	await waitFiles( w, 1 );
	assert.equal( w.files[0], 'receipt-RENT-0007.pdf' );
	assert.equal( w.pdfs[0].formats.length, 1 );
	assert.equal( w.pdfs[0].props.title, 'Receipt RENT-0007' );
	$( w, 'rf-download-png' ).click();
	await waitFiles( w, 2 );
	assert.equal( w.files[1], 'receipt-RENT-0007.png' );
} );

// ---- G-7: the donation statement --------------------------------------------------

test( 'G-7: the statement year', () => {
	assert.equal( rg.statementYear( '2027-01-15' ), 2026 );
	assert.equal( rg.statementYear( '2027-03-31' ), 2026 );
	assert.equal( rg.statementYear( '2026-04-01' ), 2026 );
	assert.equal( rg.statementYear( '2026-12-31' ), 2026 );
	assert.equal( rg.taxYear( '2025' ), 2025 );
	assert.equal( rg.taxYear( '25' ), null );
	assert.equal( rg.taxYear( '' ), null );
} );

function donation( annual ) {
	const w = openGenerator( { slug: 'donation-receipt', qr: true } );
	type( w, 'rf-business-name', 'Green City Food Bank' );
	type( w, 'rf-client-name', 'Jennifer Adams' );
	type( w, 'rf-x-ein', '12-3456789' );
	if ( annual ) {
		select( w, 'rf-doc-mode', 'annual' );
		type( w, 'rf-tax-year', '2025' );
		select( w, 'rf-date-format', 'mdy' );
		item( w, 0, 'Cash gift', 1, 300, '2025-03-04' );
		item( w, 1, '3 boxes of canned food', 1, '', '2025-06-10' );
		item( w, 2, 'Year-end appeal', 1, 120.5, '2025-12-12' );
	} else {
		item( w, 0, 'Cash donation', 1, 300 );
	}
	return w;
}

test( 'G-7: statement mode shows gift dates, prints them, totals cash only', { skip }, async () => {
	const w = donation( true );
	const list = $( w, 'rf-items-list' );
	assert.ok( list.classList.contains( 'rf-annual-mode' ), 'date inputs shown' );
	assert.equal( $( w, 'rf-tax-year-field' ).hidden, false );
	const t = text( w );
	assert.ok( t.includes( 'ANNUAL DONATION STATEMENT' ) );
	assert.ok( t.includes( 'Gifts received: 1 Jan to 31 Dec 2025' ) );
	assert.ok( t.includes( '03/04/2025' ) && t.includes( '06/10/2025' ) && t.includes( '12/12/2025' ), 'dates in the chosen format' );
	assert.ok( t.includes( 'Total cash contributions$420.50' ) );
	assert.ok( ! t.includes( 'Subtotal' ) );
	assert.ok( t.includes( '3 boxes of canned food' ) && t.includes( 'No value stated' ) );
	assert.ok( t.includes( 'No goods or services were provided in exchange for these contributions.' ) );
	assert.ok( ! t.includes( 'Payment Method' ) && ! t.includes( 'Gift Type' ) );
	const head = [ ...$( w, 'rf-receipt-preview' ).querySelectorAll( '.rf-receipt-table th' ) ].map( ( th ) => th.textContent );
	assert.deepEqual( head, [ 'Date', 'Description', 'Amount' ] );
	// The checker passes for a complete statement.
	assert.match( $( w, 'rf-checker-result' ).textContent, /Everything required is on the receipt/ );
	item( w, 1, '3 boxes of canned food', 1, '', '' );
	assert.doesNotMatch( $( w, 'rf-checker-result' ).textContent, /Everything required/, 'a gift without a date is missing' );
	const p = await lastPayload( w );
	assert.equal( p.st, 2025 );
	assert.equal( p.t, 'ANNUAL DONATION STATEMENT' );
	assert.equal( p.tot, 42050 );
	assert.equal( p.pm, '' );
	assert.ok( ! p.x.some( ( e ) => e[0] === 'Gift Type' ) );
} );

test( 'G-7: a gift with goods in return, and the gift dates kept with the form', { skip }, async () => {
	const w = donation( true );
	type( w, 'rf-x-qpq_desc', 'Gala dinner ticket' );
	type( w, 'rf-x-qpq_value', '60' );
	assert.ok( text( w ).includes( 'In return for these contributions the donor received Gala dinner ticket, with an estimated value of $60.00.' ) );
	await new Promise( ( r ) => setTimeout( r, 900 ) ); // the QR redraw (300 ms) re-arms the 400 ms save
	const saved = JSON.parse( w.sessionStorage.getItem( 'rgp-form:/donation-receipt/' ) );
	assert.equal( saved.items[0].date, '2025-03-04' );
	assert.equal( saved.fields['rf-doc-mode'], 'annual' );
	assert.equal( saved.fields['rf-tax-year'], '2025' );
} );

test( 'G-7: single-gift mode gives the same receipt as 1.4.0', { skip }, async () => {
	const old = execFileSync( 'git', [ 'show', 'be50059:wp-content/themes/receiptgenpro/assets/js/receipt-generator.js' ], { cwd: ROOT, encoding: 'utf8' } );
	for ( const slug of [ 'donation-receipt', 'hra-rent-receipt', 'cash-receipt', '' ] ) {
		const out = [ old, null ].map( ( script ) => {
			const w = openGenerator( { slug, script } );
			type( w, 'rf-business-name', 'Green City' );
			type( w, 'rf-client-name', 'Jennifer' );
			type( w, 'rf-receipt-date', '2026-10-25' );
			item( w, 0, 'Gift', 2, 150 );
			return $( w, 'rf-receipt-preview' ).innerHTML;
		} );
		assert.equal( out[1], out[0], ( slug || 'homepage' ) + ' renders as before' );
	}
	const w = donation( false );
	assert.equal( $( w, 'rf-doc-mode' ).value, 'single' );
	assert.ok( ! $( w, 'rf-items-list' ).classList.contains( 'rf-annual-mode' ) );
	assert.equal( $( w, 'rf-tax-year-field' ).hidden, true );
	assert.ok( text( w ).includes( 'DONATION RECEIPT' ) && text( w ).includes( 'this contribution' ) );
	const p = await lastPayload( w );
	assert.equal( p.st, undefined );
	assert.equal( p.rf, undefined );
} );

test( 'G-7: the verify page shows "Annual statement"', { skip }, () => {
	const t = verifyText( { v: 1, t: 'ANNUAL DONATION STATEMENT', b: 'Green City', cu: 'USD', i: [ [ 'Cash gift', 1, 30000 ], [ 'Food', 1, 0 ] ], tot: 30000, st: 2025 } );
	assert.ok( t.includes( 'Annual statement 2025' ) );
	assert.ok( t.includes( 'Total cash contributions$300.00' ) );
	assert.ok( ! t.includes( "don't add up" ) );
	assert.equal( vr.documentKind( { st: 'x' } ).kind, '', 'a bad st is ignored' );
	assert.ok( ! verifyText( { v: 1, cu: 'USD', i: [ [ 'A', 1, 100 ] ], tot: 100 } ).includes( 'Type' ), 'older codes as before' );
} );

// ---- G-9: refunds -------------------------------------------------------------------

function refund() {
	const w = openGenerator( { slug: 'refund-receipt', qr: true } );
	type( w, 'rf-business-name', 'Northstar Supplies' );
	type( w, 'rf-client-name', 'Daniel Evans' );
	type( w, 'rf-x-original_ref', 'INV-2026-031' );
	type( w, 'rf-x-original_date', '2026-10-01' );
	type( w, 'rf-tax-rate', '8' );
	item( w, 0, 'Wireless headphones', 1, 100 );
	return w;
}

test( 'G-9: refund labels, no payment method, no tip or amount paid', { skip }, async () => {
	const w = refund();
	const t = text( w );
	assert.ok( t.includes( 'REFUND RECEIPT' ) );
	assert.ok( t.includes( 'Refunded item' ) );
	assert.ok( t.includes( 'Tax refunded (8%)$8.00' ) );
	assert.ok( t.includes( 'Total refunded$108.00' ) );
	assert.ok( t.includes( 'Original Receipt / Invoice #: INV-2026-031' ) );
	assert.ok( t.includes( 'Refund Method: Original payment card' ) );
	assert.ok( ! t.includes( 'Payment Method' ) );
	assert.ok( ! /-\$/.test( t ), 'no minus signs' );
	assert.equal( $( w, 'rf-payment-method-field' ).hidden, true );
	assert.equal( $( w, 'rf-tip' ), null );
	assert.equal( $( w, 'rf-amount-paid' ), null );
	const p = await lastPayload( w );
	assert.equal( p.rf, 1 );
	assert.equal( p.pm, '' );
	assert.equal( p.tot, 10800 );
	assert.match( $( w, 'rf-checker-result' ).textContent, /Everything required is on the receipt/ );
	type( w, 'rf-x-original_ref', '' );
	assert.match( $( w, 'rf-checker-result' ).textContent, /Number of the original receipt or invoiceFill this in/ );
} );

test( 'G-9: credit note labels and QR flag', { skip }, async () => {
	const w = refund();
	select( w, 'rf-doc-mode', 'credit' );
	const t = text( w );
	assert.ok( t.includes( 'CREDIT NOTE' ) && ! t.includes( 'REFUND RECEIPT' ) );
	assert.ok( t.includes( 'Total credited$108.00' ) );
	const p = await lastPayload( w );
	assert.equal( p.rf, 2 );
	assert.equal( p.t, 'CREDIT NOTE' );
} );

test( 'G-9: the GST note shows in GST bill mode only, never on the receipt', { skip }, () => {
	const w = refund();
	const note = $( w, 'rf-refund-gst-note' );
	assert.equal( note.hidden, true );
	select( w, 'rf-currency', 'INR' );
	assert.equal( note.hidden, false, 'INR with tax' );
	assert.equal( note.textContent, rg.REFUND_GST_NOTE, 'the PHP and JS texts are the same' );
	assert.ok( ! text( w ).includes( 'GST credit note' ) );
	type( w, 'rf-tax-rate', '0' );
	assert.equal( note.hidden, true, 'INR without tax' );
} );

test( 'G-9: negative amounts get the refund wording', { skip }, () => {
	const w = refund();
	item( w, 0, 'Wireless headphones', 1, -100 );
	assert.match( $( w, 'rf-form-notice' ).textContent, /Enter refunded amounts as positive numbers/ );
} );

test( 'G-9: the verify page prints Refund / Credit note and Total refunded', { skip }, () => {
	const base = { v: 1, t: 'REFUND RECEIPT', b: 'Northstar', cu: 'USD', i: [ [ 'Headphones', 1, 10000 ] ], tx: [ [ 'Tax', '8', 800 ] ], tot: 10800 };
	const t = verifyText( Object.assign( {}, base, { rf: 1 } ) );
	assert.ok( t.includes( 'TypeRefund' ) );
	assert.ok( t.includes( 'Tax refunded (8%)$8.00' ) );
	assert.ok( t.includes( 'Total refunded$108.00' ) );
	assert.ok( ! t.includes( "don't add up" ) );
	const c = verifyText( Object.assign( {}, base, { rf: 2, t: 'CREDIT NOTE' } ) );
	assert.ok( c.includes( 'TypeCredit note' ) && c.includes( 'Total credited$108.00' ) );
} );

test( 'G-9: the homepage picker has the refund type and switches its fields', { skip }, () => {
	const w = openGenerator();
	const link = w.document.querySelector( '.rf-type-option[data-type="refund-receipt"]' );
	assert.ok( link, 'in the picker' );
	link.dispatchEvent( new w.MouseEvent( 'click', { bubbles: true, cancelable: true } ) );
	assert.ok( $( w, 'rf-doc-mode' ) && $( w, 'rf-x-original_ref' ) );
	assert.equal( $( w, 'rf-payment-method-field' ).hidden, true );
	assert.ok( text( w ).includes( 'Total refunded' ) );
	w.document.querySelector( '.rf-type-option[data-type="general"]' ).dispatchEvent( new w.MouseEvent( 'click', { bubbles: true, cancelable: true } ) );
	assert.equal( $( w, 'rf-payment-method-field' ).hidden, false );
	assert.equal( $( w, 'rf-doc-mode' ), null );
	assert.ok( text( w ).includes( 'Payment Method' ) && ! text( w ).includes( 'refunded' ) );
} );
