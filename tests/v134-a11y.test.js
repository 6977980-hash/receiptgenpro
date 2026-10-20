// Tests for theme 1.3.4 (audit of 20 Oct 2026, Phase 4).
// Run: node --test tests/*.test.js
'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const rg = require( '../wp-content/themes/receiptgenpro/assets/js/receipt-generator.js' );

const theme = path.join( __dirname, '../wp-content/themes/receiptgenpro' );

test( 'only an image data URL is accepted as a logo (#60)', () => {
	assert.ok( rg.isLogoDataUrl( 'data:image/png;base64,iVBORw0KGgo=' ) );
	assert.ok( rg.isLogoDataUrl( 'data:image/jpeg;base64,/9j/4AAQ' ) );
	assert.ok( rg.isLogoDataUrl( 'data:image/webp;base64,UklGR' ) );
	assert.ok( ! rg.isLogoDataUrl( 'data:image/svg+xml;base64,PHN2Zz4=' ) );
	assert.ok( ! rg.isLogoDataUrl( 'data:image/png;base64,x" onerror="alert(1)' ) );
	assert.ok( ! rg.isLogoDataUrl( 'javascript:alert(1)' ) );
	assert.ok( ! rg.isLogoDataUrl( 'https://example.com/logo.png' ) );
	assert.ok( ! rg.isLogoDataUrl( null ) );
	assert.ok( ! rg.isLogoDataUrl( { src: 'data:image/png;base64,AA' } ) );
} );

test( 'the customer copy is hidden from screen readers (#51)', () => {
	const two = rg.wrapDesign( 'copies', 'X' );
	const parts = two.split( 'rf-copy-cut' );
	assert.ok( ! /aria-hidden/.test( parts[0] ), 'the office copy is read' );
	assert.match( parts[1], /aria-hidden="true"[^>]*><p class="rf-copy-label">Customer copy/ );
	assert.doesNotMatch( rg.wrapDesign( 'classic', 'X' ), /aria-hidden/ );
} );

test( 'green text and the Modern label pass 4.5:1 (#45)', () => {
	const css = fs.readFileSync( path.join( theme, 'assets/css/main.css' ), 'utf8' );
	const lum = ( hex ) => {
		const c = hex.match( /\w\w/g ).map( ( h ) => parseInt( h, 16 ) / 255 ).map( ( v ) => ( v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 ) ) );
		return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
	};
	const ratio = ( a, b ) => {
		const [ hi, lo ] = [ lum( a ), lum( b ) ].sort( ( x, y ) => y - x );
		return ( hi + 0.05 ) / ( lo + 0.05 );
	};
	const success = css.match( /--rf-success:\s*#([0-9a-f]{6})/i )[1];
	assert.ok( ratio( success, 'ffffff' ) >= 4.5, '--rf-success on white' );
	assert.ok( ratio( success, 'f8fafc' ) >= 4.5, '--rf-success on the checker box' );
	const label = css.match( /\.rf-design-modern \.rf-receipt-label \{ color: #([0-9a-f]{3,6})/i )[1];
	assert.ok( ratio( label.length === 3 ? label.replace( /./g, '$&$&' ) : label, '2563eb' ) >= 4.5, 'Modern label on blue' );
} );

test( 'smooth scrolling respects reduced motion (#50)', () => {
	const css = fs.readFileSync( path.join( theme, 'assets/css/main.css' ), 'utf8' );
	assert.match( css, /@media \(prefers-reduced-motion: reduce\) \{ html \{ scroll-behavior: auto; \} \}/ );
	assert.match( css, /scroll-padding-top: 80px/ );
} );
