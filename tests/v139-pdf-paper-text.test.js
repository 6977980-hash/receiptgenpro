// Theme 1.3.9 (audit of 25 Oct 2026, v2 Phase 3): the PDF paper size
// (US Letter for USD and CAD, A4 otherwise, or the visitor's choice) and
// its invisible text layer (A25-M4, G-3), and the size of the empty QR
// box (A25-L11). The DOM tests use jsdom like tests/v138-generator-ux-tax.test.js;
// without it they are skipped, unless RF_REQUIRE_JSDOM is set.
'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { execFileSync } = require( 'node:child_process' );
const rg = require( '../wp-content/themes/receiptgenpro/assets/js/receipt-generator.js' );

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

// Opens the generator with stand-ins for the cdnjs libraries. Each PDF
// made lands in w.pdfs: { format, pages, props, texts: [ { lines, x, y,
// opts, page } ], name }.
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
	w.pdfs = [];
	w.document.getElementById( 'rf-qr' ).checked = qr;
	w.HTMLAnchorElement.prototype.click = function () {
		w.files.push( this.download );
	};
	w.alert = ( msg ) => {
		throw new Error( 'alert: ' + msg );
	};
	w.canvasHeight = 900;
	w.html2canvas = () => Promise.resolve( { width: 1600, height: w.canvasHeight, toDataURL: () => 'data:image/png;base64,AAAA' } );
	w.jspdf = {
		jsPDF: function ( opts ) {
			const doc = { format: opts.format, pages: 1, props: null, texts: [], images: [], fontSize: 16 };
			w.pdfs.push( doc );
			return {
				addImage( data, type, x, y, width, height ) {
					doc.images.push( { x, y, width, height, page: doc.pages } );
				},
				addPage( format ) {
					assert.equal( format, doc.format, 'every page has the same size' );
					doc.pages++;
				},
				setProperties( p ) {
					doc.props = p;
				},
				setFont() {},
				setFontSize( s ) {
					doc.fontSize = s;
				},
				splitTextToSize: ( lines ) => lines,
				text( lines, x, y, o ) {
					doc.texts.push( { lines, x, y, opts: o, page: doc.pages, size: doc.fontSize } );
				},
				save( name ) {
					doc.name = name;
					w.files.push( name );
				}
			};
		}
	};
	w.qrcode = () => ( { addData() {}, make() {}, getModuleCount: () => 41, createDataURL: () => 'data:image/gif;base64,AAAA' } );
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

function type( w, id, value ) {
	const el = w.document.getElementById( id );
	el.value = value;
	el.dispatchEvent( new w.Event( 'input', { bubbles: true } ) );
}

const select = ( w, id, value ) => {
	const el = w.document.getElementById( id );
	el.value = value;
	el.dispatchEvent( new w.Event( 'change', { bubbles: true } ) );
};

function item( w, n, name, qty, price ) {
	const row = w.document.querySelectorAll( '.rf-item-row' )[ n ];
	row.querySelector( '.rf-item-name' ).value = name;
	row.querySelector( '.rf-item-qty' ).value = qty;
	row.querySelector( '.rf-item-price' ).value = price;
	row.querySelector( '.rf-item-price' ).dispatchEvent( new w.Event( 'input', { bubbles: true } ) );
}

async function downloadPdf( w ) {
	const count = w.pdfs.length;
	w.document.getElementById( 'rf-download-pdf' ).click();
	for ( let i = 0; i < 100 && ! ( w.pdfs.length > count && w.pdfs[ count ].name ); i++ ) {
		await new Promise( ( r ) => setTimeout( r, 5 ) );
	}
	assert.ok( w.pdfs[ count ] && w.pdfs[ count ].name, 'a PDF was saved' );
	await new Promise( ( r ) => setTimeout( r, 5 ) );
	return w.pdfs[ count ];
}

const allText = ( doc ) => doc.texts.map( ( t ) => t.lines.join( '\n' ) ).join( '\n' );

// ---- Paper size --------------------------------------------------------------

test( 'A25-M4: A4 is 190 x 277 mm inside 10 mm margins, Letter 195.9 x 259.4 mm', () => {
	assert.deepEqual( rg.paperSize( 'a4' ), { format: 'a4', width: 210, height: 297, margin: 10, contentWidth: 190, pageHeight: 277 } );
	assert.deepEqual( rg.paperSize( 'letter' ), { format: 'letter', width: 215.9, height: 279.4, margin: 10, contentWidth: 195.9, pageHeight: 259.4 } );
	assert.equal( rg.paperSize( 'legal' ).format, 'a4' );
	assert.equal( rg.paperSize( '' ).format, 'a4' );
} );

test( 'A25-M4: USD and CAD default to Letter, every other currency to A4', () => {
	assert.equal( rg.paperFor( 'USD' ), 'letter' );
	assert.equal( rg.paperFor( 'CAD' ), 'letter' );
	for ( const c of [ 'INR', 'GBP', 'EUR', 'AUD', 'PKR', 'SAR', 'AED', undefined ] ) {
		assert.equal( rg.paperFor( c ), 'a4', String( c ) );
	}
} );

// ---- Text layer helpers --------------------------------------------------------

test( 'G-3: text outside Latin-1 becomes the currency code or is left out', () => {
	assert.equal( rg.pdfText( 'Total  ₹1,18,000.00', '₹', 'INR' ), 'Total INR 1,18,000.00' );
	assert.equal( rg.pdfText( '-€5.00', '€', 'EUR' ), '-EUR 5.00' );
	assert.equal( rg.pdfText( '£20.00', '£', 'GBP' ), '£20.00' );
	assert.equal( rg.pdfText( 'SAR 1,000.00', 'SAR', 'SAR' ), 'SAR 1,000.00' );
	assert.equal( rg.pdfText( 'Café Müller', '$', 'USD' ), 'Café Müller' );
	assert.equal( rg.pdfText( 'किराया', '₹', 'INR' ), '' );
	assert.equal( rg.pdfText( 'مطعم', 'SAR', 'SAR' ), '' );
	assert.equal( rg.pdfText( null ), '' );
} );

test( 'A25-L11: the empty QR box has the size of the code it waits for', () => {
	assert.equal( rg.qrModulesFor( 17 ), 21 );
	assert.equal( rg.qrModulesFor( 18 ), 25 );
	assert.equal( rg.qrModulesFor( 271 ), 57 );
	assert.equal( rg.qrModulesFor( 272 ), 61 );
	assert.equal( rg.qrModulesFor( 586 ), 81 );
	assert.equal( rg.qrPixels( rg.qrModulesFor( 300 ) ), 122 ); // 61 modules x 2 px
} );

// ---- In the generator ----------------------------------------------------------

test( 'A25-M4: a USD receipt downloads as Letter, with its number and total as hidden text', { skip }, async () => {
	const w = openGenerator();
	type( w, 'rf-business-name', 'Austin Plumbing' );
	type( w, 'rf-client-name', 'Jane Roe' );
	type( w, 'rf-receipt-number', 'INV-2044' );
	item( w, 0, 'Pipe repair', '2', '62.50' );
	const doc = await downloadPdf( w );
	assert.equal( doc.format, 'letter' );
	assert.deepEqual( { ...doc.images[0] }, { x: 10, y: 10, width: 195.9, height: 900 * 195.9 / 1600, page: 1 } );
	assert.equal( doc.texts.length, 1 );
	assert.equal( doc.texts[0].page, 1 );
	assert.equal( doc.texts[0].opts.renderingMode, 'invisible' );
	const t = allText( doc );
	for ( const s of [ 'Austin Plumbing', 'INV-2044', 'Jane Roe', 'Pipe repair  2  $62.50  $125.00', 'Subtotal  $125.00', 'Total  $125.00' ] ) {
		assert.ok( t.includes( s ), s + ' in:\n' + t );
	}
	assert.deepEqual( { ...doc.props }, { title: 'Receipt INV-2044', subject: 'Austin Plumbing', creator: 'ReceiptGenPro' } );
	assert.equal( doc.name, 'receipt-INV-2044.pdf' );
} );

test( 'A25-M4: an INR receipt is A4; ₹ is written INR and Hindi is left out', { skip }, async () => {
	const w = openGenerator( { slug: 'hra-rent-receipt' } );
	assert.equal( w.document.getElementById( 'rf-currency' ).value, 'INR' );
	type( w, 'rf-business-name', 'Ramesh Kumar' );
	type( w, 'rf-receipt-number', 'HRA-07' );
	item( w, 0, 'मकान किराया', '1', '25000' );
	type( w, 'rf-notes', 'Rent for July' );
	const doc = await downloadPdf( w );
	assert.equal( doc.format, 'a4' );
	assert.equal( doc.images[0].width, 190 );
	const t = allText( doc );
	assert.ok( t.includes( 'HRA-07' ), t );
	assert.ok( t.includes( 'Total  INR 25,000.00' ), t );
	assert.ok( t.includes( '1  INR 25,000.00  INR 25,000.00' ), 'the row keeps its numbers: ' + t );
	assert.doesNotMatch( t, /₹|मकान/ );
	assert.match( t, /Amount received in words: Twenty-five thousand Indian rupees only/ );
} );

test( 'A25-M4: the paper size menu overrides the currency and is kept with the form, not the receipt', { skip }, async () => {
	const w = openGenerator();
	type( w, 'rf-business-name', 'Austin Plumbing' );
	item( w, 0, 'Pipe repair', '1', '80' );
	select( w, 'rf-paper-size', 'a4' );
	const a4 = await downloadPdf( w );
	assert.equal( a4.format, 'a4' );
	const num = w.document.getElementById( 'rf-receipt-number' ).value;
	select( w, 'rf-paper-size', 'letter' );
	assert.equal( w.document.getElementById( 'rf-receipt-number' ).value, num, 'a new paper size is the same receipt' );
	const letter = await downloadPdf( w );
	assert.equal( letter.format, 'letter' );
	assert.equal( letter.name, a4.name );

	// Automatic follows the currency.
	select( w, 'rf-paper-size', '' );
	select( w, 'rf-currency', 'EUR' );
	assert.equal( ( await downloadPdf( w ) ).format, 'a4' );

	// Saved in sessionStorage with the rest of the form.
	select( w, 'rf-paper-size', 'letter' );
	await new Promise( ( r ) => setTimeout( r, 450 ) );
	const saved = w.sessionStorage.getItem( 'rgp-form:/' );
	assert.equal( JSON.parse( saved ).fields[ 'rf-paper-size' ], 'letter' );
	const w2 = openGenerator( { before: ( win ) => win.sessionStorage.setItem( 'rgp-form:/', saved ) } );
	assert.equal( w2.document.getElementById( 'rf-paper-size' ).value, 'letter' );
	assert.deepEqual( Object.keys( w2.localStorage ).filter( ( k ) => /paper/i.test( k ) ), [], 'no new storage key' );
} );

test( 'A25-M4: a long receipt continues on Letter pages; the text is all on page 1, shrunk to fit', { skip }, async () => {
	const w = openGenerator();
	type( w, 'rf-business-name', 'Austin Plumbing' );
	for ( let i = 1; i < 60; i++ ) {
		w.document.getElementById( 'rf-add-item' ).click();
	}
	w.document.querySelectorAll( '.rf-item-row' ).forEach( ( row, i ) => {
		row.querySelector( '.rf-item-name' ).value = 'Part ' + ( i + 1 );
		row.querySelector( '.rf-item-price' ).value = '1';
	} );
	w.document.querySelector( '.rf-item-price' ).dispatchEvent( new w.Event( 'input', { bubbles: true } ) );
	// 1600 px wide = 195.9 mm, so a page holds 1600 x 259.4 / 195.9 = 2118 px.
	w.canvasHeight = 5000;
	w.HTMLCanvasElement.prototype.getContext = () => ( { fillRect() {}, drawImage() {} } );
	w.HTMLCanvasElement.prototype.toDataURL = () => 'data:image/jpeg;base64,AAAA';
	const doc = await downloadPdf( w );
	assert.equal( doc.format, 'letter' );
	assert.equal( doc.pages, 3 );
	assert.equal( doc.texts.length, 1 );
	assert.equal( doc.texts[0].page, 1 );
	const lines = doc.texts[0].lines;
	assert.ok( lines.includes( 'Part 60  1  $1.00  $1.00' ) );
	assert.ok( lines.length * doc.texts[0].size * ( 25.4 / 72 ) * 1.15 <= 259.4 + 1e-9, 'fits on the page' );
} );

test( 'A25-M4: the Thermal slip stays 80 mm wide and gets the text too', { skip }, async () => {
	const w = openGenerator();
	type( w, 'rf-business-name', 'Taco Stand' );
	type( w, 'rf-receipt-number', 'T-5' );
	item( w, 0, 'Tacos', '3', '4' );
	const radio = w.document.querySelector( 'input[name="rf-design"][value="thermal"]' );
	radio.checked = true;
	radio.dispatchEvent( new w.Event( 'change', { bubbles: true } ) );
	const doc = await downloadPdf( w );
	assert.equal( doc.format[0], 80 );
	assert.equal( doc.images[0].width, 72 );
	assert.ok( allText( doc ).includes( 'T-5' ) );
	assert.ok( allText( doc ).includes( 'Total  $12.00' ) );
} );

test( 'A25-L11: before the QR library loads, its box is as big as the code will be', { skip }, () => {
	const w = openGenerator( { qr: true, before: ( win ) => {
		delete win.qrcode;
		win.document.head.appendChild = () => {}; // the library never arrives
	} } );
	const box = w.document.querySelector( '.rf-qr-pending' );
	assert.ok( box );
	const px = parseInt( box.style.width, 10 );
	assert.equal( box.style.height, box.style.width );
	assert.ok( px >= 112 && px <= 170, String( px ) );
} );

test( 'A25-L11: the embed preview holds its place until the first receipt is drawn', () => {
	const css = fs.readFileSync( path.join( THEME, 'assets/css/main.css' ), 'utf8' );
	assert.match( css, /\.rf-embed-body #rf-receipt-preview:empty \{ min-height: 640px; \}/ );
} );
