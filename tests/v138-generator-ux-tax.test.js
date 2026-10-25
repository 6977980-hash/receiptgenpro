// Theme 1.3.8 (audit of 25 Oct 2026, v2 Phase 2): prices that include tax
// and the GST bill mode (A25-M3, G-2), the "Generated with" line (A25-L1),
// the check before an empty download and the PAN hint (A25-L5), SAR / AED
// symbols (A25-L3), date formats (A25-L4), the verify page's sum check
// (A25-L6), and the phone bar, empty text and Thermal styles (A25-M5).
// The DOM tests use jsdom like tests/v136-receipt-tools.test.js; without
// it they are skipped, unless RF_REQUIRE_JSDOM is set.
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
	// jsdom fires its own DOMContentLoaded a moment later, which would
	// run init() a second time (currency menu filled twice, reset to USD).
	w.document.addEventListener( 'DOMContentLoaded', ( e ) => {
		if ( e.isTrusted ) {
			e.stopImmediatePropagation();
		}
	} );
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

const select = ( w, id, value ) => {
	const el = w.document.getElementById( id );
	el.value = value;
	el.dispatchEvent( new w.Event( 'change', { bubbles: true } ) );
};
const check = ( w, id, on ) => {
	const el = w.document.getElementById( id );
	el.checked = on;
	el.dispatchEvent( new w.Event( 'change', { bubbles: true } ) );
};
const text = ( w ) => preview( w ).textContent;
const tick = ( ms = 10 ) => new Promise( ( r ) => setTimeout( r, ms ) );

// ---- A25-M3: prices that include tax ---------------------------------------

test( 'A25-M3: £120 with 20% VAT included is £20.00 VAT and £120.00 total', () => {
	const c = rg.calcTotals( 12000, 0, 'percent', [ 20, 0 ], 0, true );
	assert.deepEqual( c.taxCents, [ 2000, 0 ] );
	assert.equal( c.totalCents, 12000 );
	assert.equal( c.netCents, 10000 );
	assert.equal( c.inclusive, true );
	// Added on top, as before.
	const add = rg.calcTotals( 12000, 0, 'percent', [ 20, 0 ], 0 );
	assert.deepEqual( add.taxCents, [ 2400, 0 ] );
	assert.equal( add.totalCents, 14400 );
	assert.equal( add.netCents, 12000 );
} );

test( 'A25-M3: ₹1,180 with CGST 9% and SGST 9% included is ₹90.00 + ₹90.00', () => {
	const c = rg.calcTotals( 118000, 0, 'percent', [ 9, 9 ], 0, true );
	assert.deepEqual( c.taxCents, [ 9000, 9000 ] );
	assert.equal( c.totalCents, 118000 );
	assert.equal( c.netCents, 100000 );
} );

test( 'A25-M3: included taxes always leave net + taxes = gross; the cent stays in the net', () => {
	const c = rg.calcTotals( 1000, 0, 'percent', [ 7.5, 7.5 ], 0, true );
	assert.deepEqual( c.taxCents, [ 65, 65 ] ); // 65.22 each
	assert.equal( c.netCents, 870 );
	for ( let gross = 1; gross < 5000; gross += 7 ) {
		for ( const rates of [ [ 20, 0 ], [ 9, 9 ], [ 2.5, 2.5 ], [ 14, 0.5 ], [ 100, 0 ] ] ) {
			const r = rg.calcTotals( gross, 10, 'percent', rates, 150, true );
			const taxable = gross - r.discountCents;
			assert.equal( r.netCents + r.taxCents[0] + r.taxCents[1], taxable, gross + ' ' + rates );
			assert.equal( r.totalCents, taxable + 150 );
		}
	}
} );

test( 'A25-M3: the form prints "Includes VAT (20%)" under the total and puts ti in the QR code', { skip }, async () => {
	const w = openGenerator( { qr: true } );
	select( w, 'rf-currency', 'GBP' );
	type( w, 'rf-business-name', 'Leeds Bakery' );
	type( w, 'rf-tax-rate', '20' );
	type( w, 'rf-tax-label', 'VAT' );
	check( w, 'rf-tax-inclusive', true );
	item( w, 0, 'Cake', '1', '120' );
	const t = text( w );
	assert.match( t, /Total£120\.00Includes VAT \(20%\)£20\.00/ );
	assert.doesNotMatch( t, /£144/ );
	assert.ok( preview( w ).querySelector( '.rf-receipt-tax-incl' ) );
	const p = await lastPayload( w );
	assert.equal( p.ti, 1 );
	assert.equal( p.tot, 12000 );
	assert.deepEqual( p.tx, [ [ 'VAT', '20', 2000 ] ] );
	assert.equal( vr.doesNotAddUp( p ), false );

	// Unticked: added on top again, no ti.
	check( w, 'rf-tax-inclusive', false );
	assert.match( text( w ), /VAT \(20%\)£24\.00Total£144\.00/ );
	assert.equal( ( await lastPayload( w ) ).ti, undefined );
} );

test( 'A25-M3: the inclusive option is not on a donation form and is ignored after switching to Donation', { skip }, () => {
	assert.equal( openGenerator( { slug: 'donation-receipt' } ).document.getElementById( 'rf-tax-inclusive' ), null );
	const w = openGenerator();
	type( w, 'rf-tax-rate', '20' );
	check( w, 'rf-tax-inclusive', true );
	item( w, 0, 'Gift', '1', '120' );
	assert.match( text( w ), /Includes Tax \(20%\)/ );
	w.document.querySelector( '.rf-type-option[data-type="donation-receipt"]' ).dispatchEvent( new w.MouseEvent( 'click', { bubbles: true, cancelable: true } ) );
	assert.doesNotMatch( text( w ), /Includes|Tax/ );
	assert.match( text( w ), /Total\$120\.00/ );
} );

// ---- G-2: GST bill mode ----------------------------------------------------

test( 'G-2: INR with tax shows HSN/SAC and place of supply; they print and go in the QR code', { skip }, async () => {
	const w = openGenerator( { qr: true } );
	const d = w.document;
	assert.equal( d.getElementById( 'rf-items-list' ).classList.contains( 'rf-gst-mode' ), false );
	assert.equal( d.getElementById( 'rf-place-of-supply-field' ).hidden, true );
	select( w, 'rf-currency', 'INR' );
	assert.equal( d.getElementById( 'rf-items-list' ).classList.contains( 'rf-gst-mode' ), false, 'INR without tax is not a GST bill' );
	type( w, 'rf-tax-rate', '18' );
	assert.equal( d.getElementById( 'rf-items-list' ).classList.contains( 'rf-gst-mode' ), true );
	assert.equal( d.getElementById( 'rf-place-of-supply-field' ).hidden, false );

	type( w, 'rf-business-name', 'Pune Repairs' );
	item( w, 0, 'Laptop repair', '1', '1000' );
	const row = d.querySelector( '.rf-item-row' );
	row.querySelector( '.rf-item-hsn' ).value = '998713';
	row.querySelector( '.rf-item-hsn' ).dispatchEvent( new w.Event( 'input', { bubbles: true } ) );
	type( w, 'rf-place-of-supply', 'Maharashtra (27)' );
	const t = text( w );
	assert.match( t, /Laptop repairHSN\/SAC: 998713/ );
	assert.match( t, /Place of supply: Maharashtra \(27\)/ );
	assert.doesNotMatch( t, /tax invoice/i );
	const p = await lastPayload( w );
	assert.equal( p.i[0][3], '998713' );
	assert.deepEqual( p.x[0], [ 'Place of supply', 'Maharashtra (27)' ] );

	// Back to USD without a GSTIN: the codes are kept in the form, not printed.
	select( w, 'rf-currency', 'USD' );
	assert.doesNotMatch( text( w ), /HSN|Place of supply/ );
	assert.equal( row.querySelector( '.rf-item-hsn' ).value, '998713' );
} );

test( 'G-2: choosing GSTIN names the taxes CGST and SGST and splits the rate', { skip }, () => {
	const w = openGenerator();
	const d = w.document;
	type( w, 'rf-tax-rate', '18' );
	select( w, 'rf-tax-id-label', 'GSTIN' );
	assert.equal( d.getElementById( 'rf-tax-label' ).value, 'CGST' );
	assert.equal( d.getElementById( 'rf-tax2-label' ).value, 'SGST' );
	assert.equal( d.getElementById( 'rf-tax-rate' ).value, '9' );
	assert.equal( d.getElementById( 'rf-tax2-rate' ).value, '9' );
	assert.equal( d.getElementById( 'rf-more-options' ).open, true );
	assert.ok( d.getElementById( 'rf-items-list' ).classList.contains( 'rf-gst-mode' ), 'a GSTIN is a GST bill in any currency' );
	item( w, 0, 'Service', '1', '100' );
	assert.match( text( w ), /CGST \(9%\)\$9\.00SGST \(9%\)\$9\.00Total\$118\.00/ );

	// Names the visitor typed are kept.
	const w2 = openGenerator();
	type( w2, 'rf-tax-label', 'IGST' );
	type( w2, 'rf-tax-rate', '18' );
	select( w2, 'rf-tax-id-label', 'GSTIN' );
	assert.equal( w2.document.getElementById( 'rf-tax-label' ).value, 'IGST' );
	assert.equal( w2.document.getElementById( 'rf-tax-rate' ).value, '18' );
} );

test( 'G-2: no GST fields on the HRA rent receipt (INR, no tax) or a donation', { skip }, () => {
	for ( const slug of [ 'hra-rent-receipt', 'donation-receipt' ] ) {
		const w = openGenerator( { slug } );
		select( w, 'rf-tax-id-label', 'GSTIN' );
		assert.equal( w.document.getElementById( 'rf-items-list' ).classList.contains( 'rf-gst-mode' ), slug === 'hra-rent-receipt', slug );
		if ( slug === 'donation-receipt' ) {
			assert.equal( w.document.getElementById( 'rf-place-of-supply-field' ).hidden, true );
		}
	}
	const hra = openGenerator( { slug: 'hra-rent-receipt' } );
	assert.equal( hra.document.getElementById( 'rf-items-list' ).classList.contains( 'rf-gst-mode' ), false );
} );

test( 'G-2: HSN codes are dropped first when the QR code is too long', () => {
	const items = Array.from( { length: 10 }, ( _, i ) => ( { name: 'Item number ' + i, qty: 1, priceCents: 100, code: '99' + String( i ).padStart( 4, '0' ) } ) );
	const enc = rg.buildPayload( { title: 'RECEIPT', business: 'B', client: 'C', number: '1', date: '2026-10-25', currency: 'INR', items, totalCents: 1200 } );
	const p = JSON.parse( Buffer.from( enc.replace( /-/g, '+' ).replace( /_/g, '/' ), 'base64' ).toString( 'utf8' ) );
	assert.ok( enc.length <= rg.QR_MAX );
	assert.equal( p.i.length, 10, 'every line still fits' );
	assert.equal( p.more, undefined );
	assert.ok( p.i.every( ( it ) => it.length === 3 ) );
	const small = rg.buildPayload( { items: [ { name: 'Tea', qty: 1, priceCents: 100, code: '0902' } ], totalCents: 100 } );
	assert.equal( JSON.parse( Buffer.from( small.replace( /-/g, '+' ).replace( /_/g, '/' ), 'base64' ).toString( 'utf8' ) ).i[0][3], '0902' );
} );

// ---- A25-L1: the "Generated with" line ----------------------------------------

test( 'A25-L1: "Generated with ReceiptGenPro" can be left out, and the choice is saved', { skip }, async () => {
	const w = openGenerator();
	assert.equal( w.document.getElementById( 'rf-show-footer' ).checked, true );
	assert.match( text( w ), /Generated with ReceiptGenPro/ );
	check( w, 'rf-show-footer', false );
	assert.doesNotMatch( text( w ), /Generated with/ );
	assert.equal( preview( w ).querySelector( '.rf-receipt-footer' ), null );

	await tick( 450 );
	const saved = w.sessionStorage.getItem( 'rgp-form:/' );
	const w2 = openGenerator( {
		before( win ) {
			win.sessionStorage.setItem( 'rgp-form:/', saved );
		}
	} );
	assert.equal( w2.document.getElementById( 'rf-show-footer' ).checked, false );
	assert.doesNotMatch( text( w2 ), /Generated with/ );
} );

// ---- A25-L5: empty receipt, empty date, PAN ---------------------------------

test( 'A25-L5: an unnamed or 0 receipt asks first; Go back, then Download anyway, once per receipt', { skip }, async () => {
	const w = openGenerator();
	const d = w.document;
	const box = d.getElementById( 'rf-export-check' );
	assert.equal( box.hidden, true );
	d.getElementById( 'rf-download-pdf' ).click();
	await tick();
	assert.equal( w.files.length, 0, 'nothing downloaded yet' );
	assert.equal( box.hidden, false );
	assert.match( box.textContent, /"Your Business \/ Name" field is empty, so it says "Your Business Name", and the total is \$0\.00/ );
	assert.equal( d.activeElement, box );

	d.getElementById( 'rf-export-back' ).click();
	assert.equal( box.hidden, true );
	assert.equal( d.activeElement, d.getElementById( 'rf-business-name' ) );

	item( w, 0, 'Tea', '1', '3' );
	d.getElementById( 'rf-download-png' ).click();
	await tick();
	assert.match( box.textContent, /field is empty/ );
	assert.doesNotMatch( box.textContent, /total is/ );
	d.getElementById( 'rf-export-anyway' ).click();
	for ( let i = 0; i < 100 && ! w.files.length; i++ ) {
		await tick( 5 );
	}
	assert.deepEqual( w.files, [ 'receipt-1001.png' ] );
	await tick();
	// The same receipt in another format doesn't ask again.
	assert.equal( await download( w, 'rf-download-pdf' ), 'receipt-1001.pdf' );
	assert.equal( box.hidden, true );

	// Typing a name hides an open notice.
	const w2 = openGenerator();
	item( w2, 0, 'Tea', '1', '3' );
	w2.document.getElementById( 'rf-download-pdf' ).click();
	await tick();
	assert.equal( w2.document.getElementById( 'rf-export-check' ).hidden, false );
	type( w2, 'rf-business-name', 'Ali Store' );
	assert.equal( w2.document.getElementById( 'rf-export-check' ).hidden, true );
	assert.equal( await download( w2, 'rf-download-pdf' ), 'receipt-1001.pdf' );
} );

test( 'A25-L5: an in-kind donation with no value downloads without asking', { skip }, async () => {
	const w = openGenerator( { slug: 'donation-receipt' } );
	type( w, 'rf-business-name', 'Food Bank' );
	item( w, 0, 'In-kind: 3 boxes of canned food', '1', '' );
	assert.equal( await download( w, 'rf-download-png' ), 'receipt-1001.png' );
} );

test( 'A25-L5: a cleared date prints "Date: —", not today', { skip }, async () => {
	const w = openGenerator( { qr: true } );
	assert.match( text( w ), /Date: \d/ );
	type( w, 'rf-receipt-date', '' );
	assert.match( text( w ), /Date: —/ );
	assert.equal( ( await lastPayload( w ) ).d, '' );
} );

test( 'A25-L5: the landlord PAN gets a hint (not a block) and is printed in capitals', { skip }, () => {
	const w = openGenerator( { slug: 'hra-rent-receipt' } );
	const d = w.document;
	const pan = d.getElementById( 'rf-x-landlord_pan' );
	type( w, 'rf-x-landlord_pan', '12345' );
	const hint = d.getElementById( 'rf-pan-hint' );
	assert.equal( hint.hidden, false );
	assert.equal( pan.getAttribute( 'aria-describedby' ), 'rf-pan-hint' );
	assert.match( text( w ), /Landlord PAN: 12345/ ); // still printed
	assert.equal( rg.isPan( 'abcde1234f' ), true );
	assert.equal( rg.isPan( 'ABCDE12345' ), false );

	pan.value = ' abcde1234f';
	pan.dispatchEvent( new w.Event( 'change', { bubbles: true } ) );
	assert.equal( pan.value, 'ABCDE1234F' );
	assert.equal( hint.hidden, true );
	assert.equal( pan.hasAttribute( 'aria-describedby' ), false );
	assert.match( text( w ), /Landlord PAN: ABCDE1234F/ );
	assert.equal( openGenerator().document.getElementById( 'rf-pan-hint' ), null );
} );

// ---- A25-L3: currency symbols -------------------------------------------------

test( 'A25-L3: SAR and AED print as letter codes with a space; CA$ and AU$ stay as they are', { skip }, () => {
	const page = render( '' );
	assert.equal( page.data.currencies.SAR, 'SAR — Saudi Riyal' );
	assert.equal( page.data.currencies.AED, 'AED — UAE Dirham' );
	assert.doesNotMatch( JSON.stringify( page.data.currencies ), /\\ufdfc|﷼|د\.إ/ );
	assert.equal( rg.symbolPrefix( 'SAR' ), 'SAR ' );
	assert.equal( rg.symbolPrefix( 'CA$' ), 'CA$' );
	assert.equal( rg.symbolPrefix( '€' ), '€' );

	const w = openGenerator();
	item( w, 0, 'Abaya', '1', '1000' );
	select( w, 'rf-currency', 'SAR' );
	assert.match( text( w ), /TotalSAR 1,000\.00/ );
	select( w, 'rf-currency', 'AED' );
	assert.match( text( w ), /TotalAED 1,000\.00/ );
	select( w, 'rf-currency', 'CAD' );
	assert.match( text( w ), /TotalCA\$1,000\.00/ );
} );

test( 'A25-L3/L6: the verify page money(): letter codes, unknown codes, no currency', () => {
	assert.equal( vr.money( { cu: 'SAR' }, 100000 ), 'SAR 1,000.00' );
	assert.equal( vr.money( { cu: 'EUR' }, 1200 ), 'EUR 12.00' ); // no rfVerify in Node
	assert.equal( vr.money( {}, 1200 ), '12.00 (no currency)' );
} );

// ---- A25-L4: date formats -----------------------------------------------------

test( 'A25-L4: formatDate and the default format per currency', () => {
	assert.equal( rg.formatDate( '2026-10-25', 'long' ), '25 Oct 2026' );
	assert.equal( rg.formatDate( '2026-01-05', 'long' ), '5 Jan 2026' );
	assert.equal( rg.formatDate( '2026-10-25', 'mdy' ), '10/25/2026' );
	assert.equal( rg.formatDate( '2026-10-25', 'dmy' ), '25/10/2026' );
	assert.equal( rg.formatDate( '2026-10-25', 'iso' ), '2026-10-25' );
	assert.equal( rg.formatDate( 'soon', 'long' ), 'soon' );
	assert.equal( rg.formatDate( '2026-13-01', 'long' ), '2026-13-01' );
	assert.equal( rg.dateFormatFor( 'USD' ), 'mdy' );
	for ( const c of [ 'INR', 'PKR', 'GBP', 'AUD', 'AED', 'SAR' ] ) {
		assert.equal( rg.dateFormatFor( c ), 'dmy', c );
	}
	assert.equal( rg.dateFormatFor( 'EUR' ), 'long' );
	assert.equal( rg.dateFormatFor( 'CAD' ), 'long' );
} );

test( 'A25-L4: the receipt date and date extras follow the format; the QR code keeps ISO', { skip }, async () => {
	const w = openGenerator( { slug: 'rent-receipt', qr: true } );
	type( w, 'rf-receipt-date', '2026-10-25' );
	type( w, 'rf-x-period_from', '2026-10-01' );
	assert.match( text( w ), /Date: 10\/25\/2026/ );
	assert.match( text( w ), /Rent Period From: 10\/01\/2026/ );
	select( w, 'rf-currency', 'GBP' );
	assert.match( text( w ), /Date: 25\/10\/2026/ );
	select( w, 'rf-currency', 'EUR' );
	assert.match( text( w ), /Date: 25 Oct 2026/ );
	assert.match( text( w ), /Rent Period From: 1 Oct 2026/ );
	select( w, 'rf-date-format', 'iso' );
	assert.match( text( w ), /Date: 2026-10-25/ );
	select( w, 'rf-date-format', 'dmy' );
	const p = await lastPayload( w );
	assert.equal( p.d, '2026-10-25' );
	assert.ok( p.x.some( ( e ) => e[1] === '2026-10-01' ) );
} );

// ---- A25-L6: verify page sum check --------------------------------------------

test( 'A25-L6: payloads whose lines do not add up to tot are flagged', () => {
	const base = { v: 1, cu: 'USD', i: [ [ 'Tea', 2, 150 ], [ 'Cake', 1, 400 ] ], tot: 700 };
	assert.equal( vr.linesTotal( base ), 700 );
	assert.equal( vr.doesNotAddUp( base ), false );
	assert.equal( vr.doesNotAddUp( { ...base, tot: 701 } ), false, 'a cent of rounding is fine' );
	assert.equal( vr.doesNotAddUp( { ...base, tot: 7000 } ), true );
	assert.equal( vr.doesNotAddUp( { ...base, tot: 7000, more: 1 } ), false, 'not every line fit' );
	const taxed = { ...base, dc: 100, tx: [ [ 'VAT', '20', 120 ] ], tp: 50, tot: 770 };
	assert.equal( vr.doesNotAddUp( taxed ), false );
	assert.equal( vr.doesNotAddUp( { ...taxed, ti: 1 } ), true );
	assert.equal( vr.doesNotAddUp( { ...taxed, ti: 1, tot: 650 } ), false );
} );

test( 'A25-L6: the verify page shows the note, "incl." taxes, HSN codes and (no currency)', { skip }, () => {
	const show = ( p ) => {
		const enc = Buffer.from( JSON.stringify( p ) ).toString( 'base64' ).replace( /\+/g, '-' ).replace( /\//g, '_' ).replace( /=+$/, '' );
		const dom = new JSDOM( '<!doctype html><body><p id="rf-verify-status"></p><div id="rf-verify-details" hidden></div></body>', {
			url: 'https://example.com/verify-receipt/#r=' + enc,
			runScripts: 'outside-only'
		} );
		dom.window.rfVerify = { symbols: { GBP: '£', SAR: 'SAR' } };
		dom.window.TextDecoder = TextDecoder;
		dom.window.eval( fs.readFileSync( path.join( JS, 'verify-receipt.js' ), 'utf8' ) );
		dom.window.document.dispatchEvent( new dom.window.Event( 'DOMContentLoaded' ) );
		return dom.window.document.getElementById( 'rf-verify-details' ).textContent;
	};
	const ok = show( { v: 1, cu: 'GBP', i: [ [ 'Cake', 1, 12000, '1905' ] ], tx: [ [ 'VAT', '20', 2000 ] ], ti: 1, tot: 12000 } );
	assert.match( ok, /VAT \(20%, incl\.\)£20\.00/ );
	assert.match( ok, /Cake \(HSN\/SAC 1905\)/ );
	assert.doesNotMatch( ok, /don't add up/ );
	const bad = show( { v: 1, cu: 'SAR', i: [ [ 'Abaya', 1, 10000 ] ], tot: 99999 } );
	assert.match( bad, /The lines in this code don't add up to the total shown\./ );
	assert.match( bad, /SAR 999\.99/ );
	assert.match( show( { v: 1, i: [ [ 'Tea', 1, 100 ] ], tot: 100 } ), /1\.00 \(no currency\)/ );
} );

// ---- A25-M5: phones and Thermal -------------------------------------------------

test( 'A25-M5: the empty preview says "in the form"; the phone bar carries the total', { skip }, () => {
	const w = openGenerator();
	assert.match( text( w ), /Add items in the form to see them here/ );
	assert.doesNotMatch( text( w ), /on the left/ );
	const bar = w.document.getElementById( 'rf-sticky-bar' );
	assert.ok( bar );
	assert.equal( bar.hidden, true, 'no IntersectionObserver here, so it stays hidden' );
	assert.equal( w.document.getElementById( 'rf-sticky-go' ).getAttribute( 'href' ), '#rf-receipt-preview' );
	item( w, 0, 'Tea', '2', '1.50' );
	assert.equal( w.document.getElementById( 'rf-sticky-total' ).textContent, 'Total: $3.00' );
} );

test( 'A25-M5: the Thermal styles wrap whole words and size the amount columns to the amounts', () => {
	const css = fs.readFileSync( path.join( THEME, 'assets/css/main.css' ), 'utf8' );
	assert.match( css, /\.rf-design-thermal \.rf-receipt-table th, \.rf-design-thermal \.rf-receipt-table td \{ overflow-wrap: anywhere; word-break: normal; hyphens: manual; \}/ );
	assert.match( css, /\.rf-design-thermal \.rf-receipt-table th:nth-child\(3\), \.rf-design-thermal \.rf-receipt-table th:nth-child\(4\) \{ width: calc\(var\(--rf-amount-ch, 9\) \* var\(--rf-thermal-fs\) \* \.6 \+ \.8rem\); \}/ );
	assert.match( css, /\.rf-design-thermal \.rf-receipt-table th \+ th, \.rf-design-thermal \.rf-receipt-table td \+ td \{ padding-left: \.5rem; \}/ );
	assert.match( css, /env\(safe-area-inset-bottom\)/ );
} );

test( 'A25-M5: the table carries the longest amount (up to 10) and amounts break only after commas', { skip }, () => {
	const w = openGenerator();
	const table = () => preview( w ).querySelector( '.rf-receipt-table' );
	assert.equal( table().style.getPropertyValue( '--rf-amount-ch' ), '5' );
	item( w, 0, 'Tea', '1', '1234.56' );
	assert.equal( table().style.getPropertyValue( '--rf-amount-ch' ), '9' );
	assert.match( table().innerHTML, /\$1,<wbr>234\.56/ );
	item( w, 0, 'Tea', '1', '123456789.00' );
	assert.equal( table().style.getPropertyValue( '--rf-amount-ch' ), '10' );
} );
