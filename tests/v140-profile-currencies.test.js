// Theme 1.4.0 (audit of 25 Oct 2026, v2 Phase 4): "Remember my business
// details on this device" (G-1) and more currencies with 0 or 3 decimals
// and a typed code (G-5). The DOM tests use jsdom like
// tests/v138-generator-ux-tax.test.js; without it they are skipped, unless
// RF_REQUIRE_JSDOM is set.
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
const PROFILE_KEY = 'rgp-business-profile';
const LOGO = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

function render( slug, embed ) {
	const args = [ path.join( __dirname, 'php/render-generator.php' ) ];
	if ( slug ) {
		args.push( slug );
	}
	if ( embed ) {
		args.push( 'embed' );
	}
	return JSON.parse( execFileSync( 'php', args, { encoding: 'utf8' } ) );
}

// Opens the generator with stand-ins for the cdnjs libraries: downloads
// land in w.files (file names), QR code texts in w.qrTexts. `local` and
// `session` are put in the page's storage first, as a browser that already
// has them (a tab closed and opened again keeps only `local`).
function openGenerator( { slug = '', embed = false, before, qr = false, local = {}, session = {} } = {} ) {
	const page = render( slug, embed );
	const at = embed ? 'embed/' + slug + '/' : ( slug ? slug + '/' : '' );
	const dom = new JSDOM( '<!doctype html><html><head></head><body>' + page.html + '</body></html>', {
		url: 'https://example.com/' + at,
		runScripts: 'outside-only',
		pretendToBeVisual: true
	} );
	const w = dom.window;
	w.rfReceipt = page.data;
	w.TextEncoder = TextEncoder; // every browser has it; jsdom doesn't
	w.files = [];
	w.qrTexts = [];
	w.document.getElementById( 'rf-qr' ).checked = qr;
	Object.keys( local ).forEach( ( k ) => w.localStorage.setItem( k, local[ k ] ) );
	Object.keys( session ).forEach( ( k ) => w.sessionStorage.setItem( k, session[ k ] ) );
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
	// run init() a second time.
	w.document.addEventListener( 'DOMContentLoaded', ( e ) => {
		if ( e.isTrusted ) {
			e.stopImmediatePropagation();
		}
	} );
	w.eval( fs.readFileSync( path.join( JS, 'receipt-generator.js' ), 'utf8' ) );
	w.document.dispatchEvent( new w.Event( 'DOMContentLoaded' ) );
	return w;
}

const $ = ( w, id ) => w.document.getElementById( id );
function type( w, id, value ) {
	const el = $( w, id );
	el.value = value;
	el.dispatchEvent( new w.Event( 'input', { bubbles: true } ) );
}
const select = ( w, id, value ) => {
	const el = $( w, id );
	el.value = value;
	el.dispatchEvent( new w.Event( 'change', { bubbles: true } ) );
};
const check = ( w, id, on ) => {
	const el = $( w, id );
	el.checked = on;
	el.dispatchEvent( new w.Event( 'change', { bubbles: true } ) );
};
function item( w, n, name, qty, price ) {
	const row = w.document.querySelectorAll( '.rf-item-row' )[ n ];
	row.querySelector( '.rf-item-name' ).value = name;
	row.querySelector( '.rf-item-qty' ).value = qty;
	row.querySelector( '.rf-item-price' ).value = price;
	row.querySelector( '.rf-item-price' ).dispatchEvent( new w.Event( 'input', { bubbles: true } ) );
}
async function download( w, id ) {
	const count = w.files.length;
	$( w, id ).click();
	for ( let i = 0; i < 100 && w.files.length === count; i++ ) {
		await new Promise( ( r ) => setTimeout( r, 5 ) );
	}
	assert.equal( w.files.length, count + 1, id + ' gave a file' );
	await new Promise( ( r ) => setTimeout( r, 5 ) );
	return w.files[ w.files.length - 1 ];
}
async function lastPayload( w ) {
	await new Promise( ( r ) => setTimeout( r, 350 ) );
	const enc = w.qrTexts[ w.qrTexts.length - 1 ].split( '#r=' )[1];
	return JSON.parse( Buffer.from( enc.replace( /-/g, '+' ).replace( /_/g, '/' ), 'base64' ).toString( 'utf8' ) );
}
const text = ( w ) => $( w, 'rf-receipt-preview' ).textContent;
const saved = ( w ) => JSON.parse( w.localStorage.getItem( PROFILE_KEY ) || 'null' );
const tick = ( ms = 450 ) => new Promise( ( r ) => setTimeout( r, ms ) ); // saveFormSoon waits 400 ms
const profile = ( extra ) => JSON.stringify( Object.assign( { v: 1, name: 'Ali Traders', address: '12 Mall Road\nLahore', contact: '0300 1234567', taxIdLabel: 'NTN', taxId: '1234567-8', logo: LOGO, currency: 'PKR' }, extra ) );

// ---- G-5: minor units ------------------------------------------------------

test( 'G-5: toMinor, formatAmount and amountInWords follow the decimals; 2 is unchanged', () => {
	assert.equal( rg.toMinor( 1.005, 2 ), rg.toCents( 1.005 ) );
	assert.equal( rg.toMinor( 1234.4, 0 ), 1234 );
	assert.equal( rg.toMinor( 1.2505, 3 ), 1251 );
	assert.equal( rg.formatAmount( 407200, 'USD' ), '4,072.00' );
	assert.equal( rg.formatAmount( 407200, 'USD', 2 ), '4,072.00' );
	assert.equal( rg.formatAmount( 4072, 'JPY', 0 ), '4,072' );
	assert.equal( rg.formatAmount( 2625, 'KWD', 3 ), '2.625' );
	assert.equal( rg.formatAmount( 123456789, 'INR', 2 ), '12,34,567.89' );
	assert.equal( rg.amountInWords( 4072, 'JPY', 0 ), 'Four thousand seventy-two yen only' );
	assert.equal( rg.amountInWords( 1, 'JPY', 0 ), 'One yen only' );
	// 2.625 dinars are 2 dinars and 625 fils (the handoff's "one hundred
	// twenty-five" was the 0.125 tax line).
	assert.equal( rg.amountInWords( 2.625, 'KWD', 3 ), 'Two Kuwaiti dinars and six hundred twenty-five fils only' );
	assert.equal( rg.amountInWords( 2.125, 'KWD', 3 ), 'Two Kuwaiti dinars and one hundred twenty-five fils only' );
	assert.equal( rg.amountInWords( 0.5, 'KWD', 3 ), 'Five hundred fils only' );
	assert.equal( rg.amountInWords( 1000, 'CHF', 2 ), 'One thousand CHF only' );
	assert.equal( rg.amountInWords( 1000.5, 'CHF', 2 ), 'One thousand CHF and fifty cents only' );
	assert.equal( rg.amountInWords( 7.125, 'BHD', 3 ), 'Seven BHD and 125/1000 only' );
	assert.equal( rg.amountInWords( 12.5, 'MYR' ), 'Twelve ringgit and fifty sen only' );
	assert.equal( rg.amountInWords( 1, 'NGN' ), 'One naira only' );
	assert.equal( rg.amountInWords( 2.01, 'PHP' ), 'Two Philippine pesos and one centavo only' );
	// Existing currencies, default decimals: as before.
	assert.equal( rg.amountInWords( 1234.5, 'USD' ), 'One thousand two hundred thirty-four US dollars and fifty cents only' );
} );

test( 'G-5: lines and totals in yen and in fils', () => {
	assert.equal( rg.lineCents( 3, 1234, 0 ), 3702 );
	assert.equal( rg.lineCents( 3, 0.33 ), 99 );
	assert.equal( rg.lineCents( 2, 1.25, 3 ), 2500 );
	const yen = rg.calcTotals( 3702, 0, 'percent', [ 10, 0 ], 0, false, 0 );
	assert.deepEqual( yen.taxCents, [ 370, 0 ] );
	assert.equal( yen.totalCents, 4072 );
	const kwd = rg.calcTotals( 2500, 0, 'percent', [ 5, 0 ], 0, false, 3 );
	assert.deepEqual( kwd.taxCents, [ 125, 0 ] );
	assert.equal( kwd.totalCents, 2625 );
	// A fixed discount is typed in whole units.
	assert.equal( rg.calcTotals( 3702, 100, 'amount', [ 0, 0 ], 0, false, 0 ).discountCents, 100 );
	assert.equal( rg.calcTotals( 2500, 0.5, 'amount', [ 0, 0 ], 0, false, 3 ).discountCents, 500 );
	assert.equal( rg.calcTotals( 2500, 0.5, 'amount', [ 0, 0 ], 0 ).discountCents, 50 );
	// The cap in each unit stays a safe integer.
	assert.ok( Number.isSafeInteger( rg.lineCents( 1, rg.MAX_AMOUNT, 3 ) ) );
	assert.equal( rg.lineCents( 1000, rg.MAX_AMOUNT, 3 ), 999999999999990 );
} );

test( 'G-5: decimals, codes, paper and date defaults', () => {
	assert.equal( rg.currencyDecimals( 'JPY', { JPY: 0 } ), 0 );
	assert.equal( rg.currencyDecimals( 'KWD', {} ), 3 );
	assert.equal( rg.currencyDecimals( 'USD', { USD: 2 } ), 2 );
	assert.equal( rg.currencyDecimals( 'XYZ', { XYZ: 7 } ), 2 );
	assert.equal( rg.customCode( ' chf ' ), 'CHF' );
	assert.equal( rg.customCode( 'CH' ), '' );
	assert.equal( rg.customCode( 'C1F' ), '' );
	assert.equal( rg.customCode( 'ÇHF' ), '' );
	for ( const c of [ 'NZD', 'SGD', 'ZAR', 'NGN', 'PHP', 'MYR', 'JPY', 'KWD', 'CHF' ] ) {
		assert.equal( rg.paperFor( c ), 'a4', c );
	}
	assert.equal( rg.paperFor( 'USD' ), 'letter' );
	assert.equal( rg.paperFor( 'CAD' ), 'letter' );
	for ( const c of [ 'NZD', 'SGD', 'ZAR', 'NGN', 'MYR', 'KWD' ] ) {
		assert.equal( rg.dateFormatFor( c ), 'dmy', c );
	}
	assert.equal( rg.dateFormatFor( 'JPY' ), 'iso' );
	assert.equal( rg.dateFormatFor( 'PHP' ), 'mdy' );
	assert.equal( rg.isGstMode( { currency: 'JPY', taxed: true } ), false );
	// ₦ and ₱ are outside Latin-1: the PDF text says the code.
	assert.equal( rg.pdfText( 'Total ₦1,500.00', '₦', 'NGN' ), 'Total NGN 1,500.00' );
	assert.equal( rg.pdfText( 'Total ₱1,500.00', '₱', 'PHP' ), 'Total PHP 1,500.00' );
	assert.equal( rg.pdfText( 'Total ¥4,072', '¥', 'JPY' ), 'Total ¥4,072' );
	assert.equal( rg.symbolPrefix( 'RM' ), 'RM ' );
	assert.equal( rg.symbolPrefix( 'R' ), 'R' );
} );

test( 'G-5: the QR payload has dp only when it is not 2', () => {
	const base = { title: 'RECEIPT', business: 'B', client: 'C', number: '1', date: '2026-10-25', currency: 'USD', items: [ { name: 'A', qty: 1, priceCents: 100 } ], totalCents: 100, payMethod: 'Cash' };
	const dec = ( enc ) => JSON.parse( Buffer.from( enc.replace( /-/g, '+' ).replace( /_/g, '/' ), 'base64' ).toString( 'utf8' ) );
	const old = rg.buildPayload( base );
	assert.equal( rg.buildPayload( Object.assign( {}, base, { dp: 2 } ) ), old, 'dp 2 gives the same code as before' );
	assert.equal( 'dp' in dec( old ), false );
	assert.deepEqual( Object.keys( dec( old ) ).slice( 0, 8 ), [ 'v', 't', 'b', 'c', 'n', 'd', 'cu', 'i' ] );
	assert.equal( dec( rg.buildPayload( Object.assign( {}, base, { currency: 'JPY', dp: 0 } ) ) ).dp, 0 );
	assert.equal( dec( rg.buildPayload( Object.assign( {}, base, { currency: 'KWD', dp: 3 } ) ) ).dp, 3 );
} );

// ---- G-5: verify page --------------------------------------------------------

function link( p ) {
	return '#r=' + Buffer.from( JSON.stringify( p ) ).toString( 'base64' ).replace( /\+/g, '-' ).replace( /\//g, '_' ).replace( /=+$/, '' );
}

test( 'G-5: verify reads dp; codes without it read as before', () => {
	const old = vr.decodePayload( link( { v: 1, cu: 'USD', i: [ [ 'A', 2, 1050 ] ], tot: 2100 } ) );
	assert.equal( vr.decimals( old ), 2 );
	assert.equal( vr.money( old, 210000 ), 'USD 2,100.00' ); // no rfVerify in Node: the code
	assert.equal( vr.money( { cu: 'PKR' }, 12345678 ), 'PKR 1,23,456.78' );
	assert.equal( vr.money( { cu: 'JPY', dp: 0 }, 4072 ), 'JPY 4,072' );
	assert.equal( vr.money( { cu: 'JPY' }, 4072 ), 'JPY 4,072', 'yen without dp: the known table' );
	assert.equal( vr.money( { cu: 'KWD', dp: 3 }, 2625 ), 'KWD 2.625' );
	assert.equal( vr.money( { cu: 'CHF', dp: 2 }, 100000 ), 'CHF 1,000.00' );
	assert.equal( vr.money( { cu: 'BHD', dp: 3 }, 7125 ), 'BHD 7.125' );
	assert.equal( vr.money( { cu: 'USD', dp: 9 }, 100 ), 'USD 1.00', 'a bad dp is ignored' );
	// The sum check is per minor unit: 1 fils off is fine, 2 is not.
	const kwd = { v: 1, cu: 'KWD', dp: 3, i: [ [ 'Tea', 2, 1250 ] ], tx: [ [ 'VAT', '5', 125 ] ], tot: 2625 };
	assert.equal( vr.doesNotAddUp( kwd ), false );
	assert.equal( vr.doesNotAddUp( Object.assign( {}, kwd, { tot: 2626 } ) ), false );
	assert.equal( vr.doesNotAddUp( Object.assign( {}, kwd, { tot: 2627 } ) ), true );
	const yen = { v: 1, cu: 'JPY', dp: 0, i: [ [ 'Ramen', 3, 1234 ] ], tx: [ [ 'Tax', '10', 370 ] ], tot: 4072 };
	assert.equal( vr.doesNotAddUp( yen ), false );
	assert.equal( vr.doesNotAddUp( Object.assign( {}, yen, { tot: 4080 } ) ), true );
} );

test( 'G-5: the verify page prints yen without decimals and dinars with 3', { skip }, () => {
	for ( const [ p, want, never ] of [
		[ { v: 1, cu: 'JPY', dp: 0, i: [ [ 'Ramen', 3, 1234 ] ], tx: [ [ 'Tax', '10', 370 ] ], tot: 4072 }, [ '¥4,072', '¥1,234', '¥370' ], /\.\d\d/ ],
		[ { v: 1, cu: 'KWD', dp: 3, i: [ [ 'Tea', 2, 1250 ] ], tx: [ [ 'VAT', '5', 125 ] ], tot: 2625 }, [ 'KWD 2.625', 'KWD 1.250', 'KWD 0.125' ], /don't add up/ ],
		[ { v: 1, cu: 'USD', i: [ [ 'Pen', 2, 150 ] ], tot: 300 }, [ '$3.00', '$1.50' ], /\$3\b(?!\.)/ ]
	] ) {
		const dom = new JSDOM( '<!doctype html><body><p id="rf-verify-status"></p><div id="rf-verify-details" hidden></div></body>', {
			url: 'https://example.com/verify-receipt/' + link( p ),
			runScripts: 'outside-only'
		} );
		const w = dom.window;
		w.rfVerify = { symbols: { JPY: '¥', KWD: 'KWD', USD: '$' }, decimals: { JPY: 0, KWD: 3, USD: 2 } };
		w.TextDecoder = TextDecoder;
		w.eval( fs.readFileSync( path.join( JS, 'verify-receipt.js' ), 'utf8' ) );
		w.document.dispatchEvent( new w.Event( 'DOMContentLoaded' ) );
		const t = w.document.getElementById( 'rf-verify-details' ).textContent;
		want.forEach( ( s ) => assert.ok( t.includes( s ), p.cu + ': ' + s + ' in ' + t ) );
		assert.doesNotMatch( t, never );
	}
} );

// ---- G-5: the form -----------------------------------------------------------

test( 'G-5: JPY ¥1,234 × 3 with 10% tax is ¥4,072, with no ".00" anywhere', { skip }, async () => {
	const w = openGenerator( { slug: 'cash-receipt', qr: true } );
	select( w, 'rf-currency', 'JPY' );
	type( w, 'rf-business-name', 'Tokyo Ramen' );
	type( w, 'rf-tax-rate', '10' );
	item( w, 0, 'Ramen', '3', '1234' );
	const t = text( w );
	assert.match( t, /Subtotal¥3,702/ );
	assert.match( t, /Tax \(10%\)¥370/ );
	assert.match( t, /Total¥4,072/ );
	assert.match( t, /Four thousand seventy-two yen only/ );
	assert.doesNotMatch( t, /\.00|cents/ );
	assert.equal( $( w, 'rf-receipt-date' ).value.length, 10 );
	assert.match( t, /Date: \d{4}-\d\d-\d\d/, 'JPY dates are year first' );
	assert.equal( w.document.querySelector( '.rf-item-price' ).step, '1' );
	const p = await lastPayload( w );
	assert.equal( p.cu, 'JPY' );
	assert.equal( p.dp, 0 );
	assert.equal( p.tot, 4072 );
	assert.deepEqual( p.i[0], [ 'Ramen', 3, 1234 ] );
	assert.equal( vr.doesNotAddUp( p ), false );
	assert.equal( await download( w, 'rf-download-pdf' ), 'receipt-1001.pdf' );
	assert.equal( await download( w, 'rf-download-png' ), 'receipt-1001.png' );
} );

test( 'G-5: KWD 1.250 × 2 with 5% is 2.500 + 0.125 = 2.625, words and dp 3', { skip }, async () => {
	const w = openGenerator( { slug: 'cash-receipt', qr: true } );
	select( w, 'rf-currency', 'KWD' );
	type( w, 'rf-business-name', 'Kuwait Tea House' );
	type( w, 'rf-tax-rate', '5' );
	item( w, 0, 'Tea', '2', '1.250' );
	const t = text( w );
	assert.match( t, /SubtotalKWD 2\.500/ );
	assert.match( t, /Tax \(5%\)KWD 0\.125/ );
	assert.match( t, /TotalKWD 2\.625/ );
	assert.match( t, /Two Kuwaiti dinars and six hundred twenty-five fils only/ );
	assert.equal( w.document.querySelector( '.rf-item-price' ).step, '0.001' );
	assert.equal( $( w, 'rf-amount-paid' ).step, '0.001' );
	const p = await lastPayload( w );
	assert.equal( p.dp, 3 );
	assert.equal( p.tot, 2625 );
	assert.deepEqual( p.tx, [ [ 'Tax', '5', 125 ] ] );
	assert.deepEqual( p.i[0], [ 'Tea', 2, 1250 ] );
	// Paid 3 dinars: change 0.375.
	type( w, 'rf-amount-paid', '3' );
	assert.match( text( w ), /Change \/ CreditKWD 0\.375/ );
	// Back to USD: two decimals and the old step.
	select( w, 'rf-currency', 'USD' );
	assert.match( text( w ), /Total\$2\.63/ );
	assert.equal( w.document.querySelector( '.rf-item-price' ).step, '0.01' );
} );

test( 'G-5: "Other (type a code)" makes chf into CHF 1,000.00 with 2 decimals', { skip }, async () => {
	const w = openGenerator( { slug: 'cash-receipt', qr: true } );
	const box = $( w, 'rf-currency-other' );
	assert.equal( box.hidden, true );
	const last = $( w, 'rf-currency' ).options[ $( w, 'rf-currency' ).options.length - 1 ];
	assert.equal( last.value, 'OTHER' );
	assert.match( last.textContent, /^Other \(type a code\)/ );
	select( w, 'rf-currency', 'OTHER' );
	assert.equal( box.hidden, false );
	type( w, 'rf-currency-code', 'chf' );
	type( w, 'rf-business-name', 'Zurich Watches' );
	item( w, 0, 'Strap', '1', '1000' );
	assert.match( text( w ), /TotalCHF 1,000\.00/ );
	assert.match( text( w ), /One thousand CHF only/ );
	let p = await lastPayload( w );
	assert.equal( p.cu, 'CHF' );
	assert.equal( 'dp' in p, false );
	select( w, 'rf-currency-dp', '3' );
	assert.match( text( w ), /TotalCHF 1,000\.000/ );
	p = await lastPayload( w );
	assert.equal( p.dp, 3 );
	assert.equal( p.tot, 1000000 );
	// Kept with the form in this tab.
	await tick();
	const draft = JSON.parse( w.sessionStorage.getItem( 'rgp-form:/cash-receipt/' ) );
	assert.equal( draft.fields[ 'rf-currency' ], 'OTHER' );
	assert.equal( draft.fields[ 'rf-currency-code' ], 'chf' );
	assert.equal( draft.fields[ 'rf-currency-dp' ], '3' );
	const again = openGenerator( { slug: 'cash-receipt', session: { 'rgp-form:/cash-receipt/': JSON.stringify( draft ) } } );
	assert.match( text( again ), /TotalCHF 1,000\.000/ );
	assert.equal( $( again, 'rf-currency-other' ).hidden, false );
} );

test( 'G-5: an existing currency prints exactly as before', { skip }, () => {
	const w = openGenerator( { slug: 'cash-receipt' } );
	select( w, 'rf-currency', 'INR' );
	item( w, 0, 'Rent', '1', '123456.785' );
	assert.match( text( w ), /Total₹1,23,456\.79/ );
	assert.match( text( w ), /One lakh twenty-three thousand four hundred fifty-six Indian rupees and seventy-nine paise only/ );
} );

// ---- G-1: remember my business details ---------------------------------------

test( 'G-1: off by default; ticking saves the profile, which fills another receipt type later', { skip }, async () => {
	const w = openGenerator( { slug: 'rent-receipt' } );
	const box = $( w, 'rf-remember' );
	assert.equal( box.checked, false );
	assert.equal( $( w, 'rf-forget' ).hidden, true );
	type( w, 'rf-business-name', 'Oak Street Lettings' );
	type( w, 'rf-business-address', '24 Oak Street' );
	await tick();
	assert.equal( w.localStorage.getItem( PROFILE_KEY ), null, 'nothing saved until ticked' );
	select( w, 'rf-currency', 'GBP' );
	check( w, 'rf-remember', true );
	let p = saved( w );
	assert.equal( p.v, 1 );
	assert.equal( p.name, 'Oak Street Lettings' );
	assert.equal( p.address, '24 Oak Street' );
	assert.equal( p.currency, 'GBP' );
	assert.equal( p.logo, null );
	assert.equal( $( w, 'rf-forget' ).hidden, false );
	// Typing goes on updating it, once typing pauses.
	type( w, 'rf-business-contact', 'hello@oak.example' );
	await tick();
	p = saved( w );
	assert.equal( p.contact, 'hello@oak.example' );

	// The tab is closed: a new page, another receipt type, with the same
	// localStorage and no draft.
	const later = openGenerator( { slug: 'donation-receipt', local: { [ PROFILE_KEY ]: w.localStorage.getItem( PROFILE_KEY ) } } );
	assert.equal( $( later, 'rf-business-name' ).value, 'Oak Street Lettings' );
	assert.equal( $( later, 'rf-business-address' ).value, '24 Oak Street' );
	assert.equal( $( later, 'rf-business-contact' ).value, 'hello@oak.example' );
	assert.equal( $( later, 'rf-currency' ).value, 'GBP' );
	assert.equal( $( later, 'rf-remember' ).checked, true );
	assert.equal( $( later, 'rf-forget' ).hidden, false );
	assert.equal( $( later, 'rf-business-details' ).open, true );
	assert.match( text( later ), /Oak Street Lettings/ );
} );

test( 'G-1: the logo and tax number come back; the HRA page keeps INR', { skip }, async () => {
	const w = openGenerator( { slug: 'hra-rent-receipt', local: { [ PROFILE_KEY ]: profile() } } );
	assert.equal( $( w, 'rf-business-name' ).value, 'Ali Traders' );
	assert.equal( $( w, 'rf-tax-id-label' ).value, 'NTN' );
	assert.equal( $( w, 'rf-tax-id' ).value, '1234567-8' );
	assert.equal( $( w, 'rf-currency' ).value, 'INR', 'a page with its own currency keeps it' );
	// ...and doesn't save INR into the profile.
	type( w, 'rf-business-name', 'Ali Traders & Sons' );
	await tick();
	assert.equal( saved( w ).name, 'Ali Traders & Sons' );
	assert.equal( saved( w ).currency, 'PKR' );
	assert.equal( w.document.querySelector( '.rf-receipt-logo' ).getAttribute( 'src' ), LOGO );
	// On the homepage the profile's currency is used.
	const home = openGenerator( { local: { [ PROFILE_KEY ]: profile() } } );
	assert.equal( $( home, 'rf-currency' ).value, 'PKR' );
	// A typed code comes back too.
	const other = openGenerator( { local: { [ PROFILE_KEY ]: profile( { currency: 'OTHER', code: 'CHF', dp: '0' } ) } } );
	assert.equal( $( other, 'rf-currency' ).value, 'OTHER' );
	assert.equal( $( other, 'rf-currency-code' ).value, 'CHF' );
	assert.equal( $( other, 'rf-currency-dp' ).value, '0' );
	assert.equal( $( other, 'rf-currency-other' ).hidden, false );
} );

test( 'G-1: a draft in this tab beats the profile', { skip }, () => {
	const draft = JSON.stringify( { type: 'general', fields: { 'rf-business-name': 'Draft Co', 'rf-currency': 'EUR' }, items: [], logo: null } );
	const w = openGenerator( { local: { [ PROFILE_KEY ]: profile() }, session: { 'rgp-form:/': draft } } );
	assert.equal( $( w, 'rf-business-name' ).value, 'Draft Co' );
	assert.equal( $( w, 'rf-currency' ).value, 'EUR' );
	assert.equal( $( w, 'rf-business-address' ).value, '' );
	assert.equal( w.document.querySelector( '.rf-receipt-logo' ), null );
	assert.equal( $( w, 'rf-remember' ).checked, true, 'the box shows that a profile exists' );
} );

test( 'G-1: unticking deletes the key; "Forget" removes it and keeps the fields', { skip }, () => {
	const w = openGenerator( { local: { [ PROFILE_KEY ]: profile() } } );
	check( w, 'rf-remember', false );
	assert.equal( w.localStorage.getItem( PROFILE_KEY ), null );
	assert.equal( $( w, 'rf-forget' ).hidden, true );
	assert.equal( $( w, 'rf-business-name' ).value, 'Ali Traders' );

	const f = openGenerator( { local: { [ PROFILE_KEY ]: profile() } } );
	$( f, 'rf-forget' ).click();
	assert.equal( f.localStorage.getItem( PROFILE_KEY ), null );
	assert.equal( $( f, 'rf-remember' ).checked, false );
	assert.equal( $( f, 'rf-forget' ).hidden, true );
	assert.equal( $( f, 'rf-business-name' ).value, 'Ali Traders' );
	assert.equal( $( f, 'rf-business-address' ).value, '12 Mall Road\nLahore' );
} );

test( 'G-1: filling from the profile does not move the receipt number on', { skip }, async () => {
	const w = openGenerator( { slug: 'cash-receipt', local: { [ PROFILE_KEY ]: profile(), 'rgp-last-receipt-number': '1005' } } );
	assert.equal( $( w, 'rf-receipt-number' ).value, '1006' );
	item( w, 0, 'Advance', '1', '500' );
	assert.equal( await download( w, 'rf-download-pdf' ), 'receipt-1006.pdf' );
	// Ticking or unticking is not a change to the receipt either.
	check( w, 'rf-remember', false );
	check( w, 'rf-remember', true );
	assert.equal( await download( w, 'rf-download-png' ), 'receipt-1006.png' );
	assert.equal( $( w, 'rf-receipt-number' ).value, '1006' );
	// A real change still moves it on.
	item( w, 0, 'Advance', '1', '600' );
	assert.equal( $( w, 'rf-receipt-number' ).value, '1007' );
} );

test( 'G-1: a logo over the quota is left out with a note; no storage at all only shows a note', { skip }, async () => {
	const draft = JSON.stringify( { type: 'general', fields: { 'rf-business-name': 'Big Logo Ltd' }, items: [], logo: LOGO } );
	const w = openGenerator( {
		session: { 'rgp-form:/': draft },
		before( win ) {
			const set = win.Storage.prototype.setItem;
			win.Storage.prototype.setItem = function ( k, v ) {
				if ( k === PROFILE_KEY && String( v ).includes( 'data:image' ) ) {
					throw new win.DOMException( 'full', 'QuotaExceededError' );
				}
				return set.call( this, k, v );
			};
		}
	} );
	check( w, 'rf-remember', true );
	const p = saved( w );
	assert.equal( p.name, 'Big Logo Ltd' );
	assert.equal( p.logo, null );
	assert.equal( $( w, 'rf-remember-note' ).hidden, false );
	assert.match( $( w, 'rf-remember-note' ).textContent, /logo is too large/ );
	assert.ok( w.document.querySelector( '.rf-receipt-logo' ), 'the logo stays on the receipt' );

	const none = openGenerator( {
		before( win ) {
			Object.defineProperty( win, 'localStorage', { get() {
				throw new win.DOMException( 'denied', 'SecurityError' );
			} } );
		}
	} );
	type( none, 'rf-business-name', 'Private Mode Co' );
	check( none, 'rf-remember', true );
	await tick();
	assert.match( $( none, 'rf-remember-note' ).textContent, /doesn't allow saving/ );
	assert.equal( $( none, 'rf-forget' ).hidden, true );
	assert.match( text( none ), /Private Mode Co/ );
} );

test( 'G-1: the embed has no checkbox and never reads or writes the profile', { skip }, async () => {
	const stored = profile();
	const w = openGenerator( { slug: 'rent-receipt', embed: true, local: { [ PROFILE_KEY ]: stored } } );
	assert.equal( $( w, 'rf-remember' ), null );
	assert.equal( $( w, 'rf-forget' ), null );
	assert.equal( $( w, 'rf-business-name' ).value, '' );
	type( w, 'rf-business-name', 'Someone Else' );
	await tick();
	assert.equal( w.localStorage.getItem( PROFILE_KEY ), stored );
	// The site's own pages have it.
	assert.ok( $( openGenerator( { slug: 'rent-receipt' } ), 'rf-remember' ) );
	assert.ok( $( openGenerator(), 'rf-remember' ) );
} );
