// Tests for theme 1.3.5 (audit of 20 Oct 2026, Phase 5): the versioned
// cookie choice and the one currency list. Run: node --test tests/
'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const consent = require( '../wp-content/themes/receiptgenpro/assets/js/consent.js' );
const rg = require( '../wp-content/themes/receiptgenpro/assets/js/receipt-generator.js' );

const THEME = path.join( __dirname, '../wp-content/themes/receiptgenpro' );

test( '#66 a cookie choice counts only for the current banner version', () => {
	assert.equal( consent.parseConsent( 'granted@' + consent.VERSION ), 'granted' );
	assert.equal( consent.parseConsent( 'declined@' + consent.VERSION ), 'declined' );
	assert.equal( consent.parseConsent( 'granted' ), null ); // saved before 1.3.5
	assert.equal( consent.parseConsent( 'granted@1' ), null );
	assert.equal( consent.parseConsent( null ), null );
} );

test( '#70 the currency symbols come from the menu labels', () => {
	assert.equal( rg.currencySymbol( '€ — Euro' ), '€' );
	assert.equal( rg.currencySymbol( 'CA$ — Canadian Dollar' ), 'CA$' );
	// No other hard-coded symbol list is left in the scripts.
	for ( const f of [ 'receipt-generator.js', 'verify-receipt.js' ] ) {
		assert.doesNotMatch( fs.readFileSync( path.join( THEME, 'assets/js', f ), 'utf8' ), /GBP: '£'/, f );
	}
} );

test( '#68 jsPDF comes from the same URL and SRI in PHP and JS', () => {
	const php = fs.readFileSync( path.join( THEME, 'inc/feature-pages.php' ), 'utf8' );
	const js = fs.readFileSync( path.join( THEME, 'assets/js/receipt-generator.js' ), 'utf8' );
	const url = /jspdf\/(\d+\.\d+\.\d+)\/jspdf\.umd\.min\.js/;
	assert.equal( php.match( url )[1], js.match( url )[1] );
	assert.notEqual( js.match( url )[1], '2.5.1' );
	const sri = ( s ) => s.slice( s.search( url ) ).match( /sha512-[A-Za-z0-9+/=]+/ )[0];
	assert.equal( sri( php ), sri( js ) );
} );
