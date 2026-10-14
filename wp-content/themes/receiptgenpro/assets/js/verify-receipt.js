/**
 * ReceiptGenPro — Verify a Receipt (v1.2.0)
 *
 * Reads the receipt details that receipt-generator.js put after the # of
 * the QR code's link (/verify-receipt/#r=<base64url JSON>) and shows them.
 * Runs entirely in the browser: the # part never reaches the server.
 */
( function () {
	'use strict';

	var SYMBOLS = { USD: '$', EUR: '€', GBP: '£', PKR: '₨', INR: '₹', CAD: 'CA$', AUD: 'AU$', AED: 'د.إ', SAR: '﷼' };

	function b64urlDecode( str ) {
		str = String( str ).replace( /-/g, '+' ).replace( /_/g, '/' );
		while ( str.length % 4 ) {
			str += '=';
		}
		var bin = atob( str );
		var bytes = new Uint8Array( bin.length );
		for ( var i = 0; i < bin.length; i++ ) {
			bytes[ i ] = bin.charCodeAt( i );
		}
		return new TextDecoder().decode( bytes );
	}

	// Returns the receipt object, or null if the link holds no valid receipt.
	function decodePayload( hashOrUrl ) {
		var m = String( hashOrUrl || '' ).match( /[#&]r=([A-Za-z0-9_-]+)/ );
		if ( ! m ) {
			return null;
		}
		try {
			var p = JSON.parse( b64urlDecode( m[1] ) );
			if ( ! p || p.v !== 1 || typeof p.tot !== 'number' || ! Array.isArray( p.i ) ) {
				return null;
			}
			return p;
		} catch ( e ) {
			return null;
		}
	}

	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { decodePayload: decodePayload, b64urlDecode: b64urlDecode };
	}

	if ( typeof document === 'undefined' ) {
		return;
	}

	function esc( str ) {
		var d = document.createElement( 'div' );
		d.textContent = str === undefined || str === null ? '' : String( str );
		return d.innerHTML;
	}

	function money( p, cents ) {
		return ( SYMBOLS[ p.cu ] || ( p.cu ? p.cu + ' ' : '' ) ) + ( cents / 100 ).toFixed( 2 );
	}

	function show( hashOrUrl ) {
		var status  = document.getElementById( 'rf-verify-status' );
		var details = document.getElementById( 'rf-verify-details' );
		if ( ! status || ! details ) {
			return;
		}
		var hasLink = /[#&]r=/.test( String( hashOrUrl || '' ) );
		var p = decodePayload( hashOrUrl );
		if ( ! p ) {
			details.hidden = true;
			details.innerHTML = '';
			status.className = 'rf-verify-status ' + ( hasLink ? 'rf-verify-status-bad' : 'rf-verify-status-none' );
			status.textContent = hasLink ?
				'This link does not contain a readable receipt. It may be incomplete or damaged. Scan the QR code again.' :
				'No receipt loaded yet. Scan the QR code on the receipt with your phone camera, or paste the link below.';
			return;
		}

		status.className = 'rf-verify-status rf-verify-status-ok';
		status.textContent = 'Receipt details as issued. Compare each line with the receipt you were given.';

		var rows = [
			[ 'Document', p.t ],
			[ 'Issued by', p.b ],
			[ 'Issued to', p.c ],
			[ 'Number', p.n ],
			[ 'Date', p.d ],
			[ 'Payment method', p.pm ]
		];
		( p.x || [] ).forEach( function ( e ) {
			rows.push( [ e[0], e[1] ] );
		} );
		rows.push( [ 'Total', money( p, p.tot ) ] );
		if ( typeof p.pd === 'number' ) {
			rows.push( [ 'Amount paid', money( p, p.pd ) ] );
		}

		var html = '<dl>' + rows.filter( function ( r ) {
			return r[1] !== undefined && r[1] !== null && String( r[1] ) !== '';
		} ).map( function ( r ) {
			return '<dt>' + esc( r[0] ) + '</dt><dd>' + esc( r[1] ) + '</dd>';
		} ).join( '' ) + '</dl>';

		if ( p.i.length ) {
			html += '<div class="rf-table-wrap"><table class="rf-compare-table"><thead><tr><th>Item</th><th class="rf-num">Qty</th><th class="rf-num">Price</th></tr></thead><tbody>' +
				p.i.map( function ( it ) {
					return '<tr><td>' + esc( it[0] ) + '</td><td class="rf-num">' + esc( it[1] ) + '</td><td class="rf-num">' + esc( money( p, it[2] ) ) + '</td></tr>';
				} ).join( '' ) +
				'</tbody></table></div>';
		}
		if ( p.more ) {
			html += '<p class="rf-muted">The receipt has more lines than fit in the QR code; the first ones are shown.</p>';
		}
		html += '<p class="rf-muted">This shows what the issuer entered. It does not confirm the payment itself; only the issuer or a bank statement can.</p>';

		details.innerHTML = html;
		details.hidden = false;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		show( window.location.hash );
		window.addEventListener( 'hashchange', function () {
			show( window.location.hash );
		} );
		var form  = document.getElementById( 'rf-verify-form' );
		var input = document.getElementById( 'rf-verify-input' );
		if ( form && input ) {
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var m = input.value.match( /#(.*)$/ );
				if ( m ) {
					window.location.hash = m[1];
					show( window.location.hash ); // no hashchange when it's the same link
				} else {
					show( input.value );
				}
			} );
		}
	} );
} )();
