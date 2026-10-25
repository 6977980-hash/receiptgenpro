/**
 * ReceiptGenPro — Verify a Receipt (v1.4.0)
 *
 * Reads the receipt details that receipt-generator.js put after the # of
 * the QR code's link (/verify-receipt/#r=<base64url JSON>) and shows them.
 * Runs entirely in the browser: the # part never reaches the server.
 *
 * v1.3.3: shows the discount, tax lines and tip (dc, tx, tp), amounts with
 * thousands separators, and strips bidi control characters from the text.
 *
 * v1.3.8: taxes included in the prices (ti) print as "VAT (20%, incl.)",
 * an HSN/SAC code (4th item entry) prints next to its item, a payload
 * whose lines don't add up to its total gets a neutral note, "SAR 10.00"
 * has a space, and a payload without a currency says "(no currency)".
 *
 * v1.4.0: amounts are in the currency's minor unit with dp decimals (the
 * payload's dp, else the currency's from rfVerify.decimals, else 2), so
 * yen print "¥4,072" and Kuwaiti dinars "KWD 2.625". Codes made before
 * 1.4.0 have no dp and read exactly as before.
 */
( function () {
	'use strict';

	// From rf_currencies() (functions.php, printed as rfVerify.symbols); a
	// code it doesn't know prints as "EUR 12.00".
	function symbol( code ) {
		var map = ( typeof window !== 'undefined' && window.rfVerify && window.rfVerify.symbols ) || {};
		return Object.prototype.hasOwnProperty.call( map, code ) ? map[ code ] : '';
	}

	// Decimals of the payload's amounts: dp when the code has one, else the
	// currency's (rfVerify.decimals, then yen 0 and dinars 3), else 2.
	var KNOWN_DECIMALS = { JPY: 0, KWD: 3 };
	function decimals( p ) {
		if ( p.dp === 0 || p.dp === 2 || p.dp === 3 ) {
			return p.dp;
		}
		var map = ( typeof window !== 'undefined' && window.rfVerify && window.rfVerify.decimals ) || {};
		var dp = Object.prototype.hasOwnProperty.call( map, p.cu ) ? map[ p.cu ] : KNOWN_DECIMALS[ p.cu ];
		dp = parseInt( dp, 10 );
		return dp === 0 || dp === 3 ? dp : 2;
	}

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

	function isCents( n ) {
		return typeof n === 'number' && isFinite( n ) && Math.floor( n ) === n;
	}

	// An array whose every entry is itself an array (item lines, extra fields).
	function isRows( a ) {
		return Array.isArray( a ) && a.every( Array.isArray );
	}

	// Returns the receipt object, or null if the link holds no valid receipt.
	function decodePayload( hashOrUrl ) {
		var m = String( hashOrUrl || '' ).match( /[#&]r=([A-Za-z0-9_-]+)/ );
		if ( ! m ) {
			return null;
		}
		try {
			var p = JSON.parse( b64urlDecode( m[1] ) );
			if ( ! p || typeof p !== 'object' || p.v !== 1 || ! isCents( p.tot ) || ! isRows( p.i ) ) {
				return null;
			}
			if ( p.x !== undefined && ! isRows( p.x ) ) {
				return null;
			}
			if ( p.pd !== undefined && p.pd !== null && ! isCents( p.pd ) ) {
				return null;
			}
			if ( ( p.dc !== undefined && ! isCents( p.dc ) ) || ( p.tp !== undefined && ! isCents( p.tp ) ) ) {
				return null;
			}
			if ( p.tx !== undefined && ! ( isRows( p.tx ) && p.tx.every( function ( t ) {
				return isCents( t[2] );
			} ) ) ) {
				return null;
			}
			for ( var k = 0; k < p.i.length; k++ ) {
				if ( ! isCents( p.i[ k ][2] ) ) {
					return null;
				}
			}
			return p;
		} catch ( e ) {
			return null;
		}
	}

	// Bidirectional overrides could make "1000" read as "0001".
	function stripBidi( str ) {
		return String( str === undefined || str === null ? '' : str ).replace( /[\u202A-\u202E\u2066-\u2069]/g, '' );
	}

	// "$1,234.50"; INR and PKR group in lakh and crore ("₹12,34,567.00");
	// a symbol of letters, or a code without a symbol, gets a space
	// ("SAR 1,000.00"); no currency at all, "1,000.00 (no currency)".
	// `cents` is in the minor unit: "¥4,072", "KWD 2.625".
	function money( p, cents ) {
		var locale = p.cu === 'INR' || p.cu === 'PKR' ? 'en-IN' : 'en-US';
		var dp = decimals( p );
		var num = new Intl.NumberFormat( locale, { minimumFractionDigits: dp, maximumFractionDigits: dp } ).format( cents / ( dp === 0 ? 1 : ( dp === 3 ? 1000 : 100 ) ) );
		if ( ! p.cu ) {
			return num + ' (no currency)';
		}
		var sym = symbol( p.cu ) || stripBidi( p.cu );
		return sym + ( /^[A-Za-z]{2,}$/.test( sym ) ? ' ' : '' ) + num;
	}

	// The lines − discount + taxes added on top + tip, in cents or the
	// currency's minor unit (taxes included in the prices, ti, are already
	// in the lines).
	function linesTotal( p ) {
		var cents = p.i.reduce( function ( sum, it ) {
			return sum + Math.round( ( parseFloat( it[1] ) || 0 ) * it[2] );
		}, 0 ) - ( p.dc || 0 ) + ( p.tp || 0 );
		if ( ! p.ti ) {
			( p.tx || [] ).forEach( function ( t ) {
				cents += t[2];
			} );
		}
		return cents;
	}

	// True when the payload's lines don't come to its total (more than one
	// minor unit off: a cent, a yen, a fils), unless not every line fit in
	// the code (more).
	function doesNotAddUp( p ) {
		return ! p.more && Math.abs( linesTotal( p ) - p.tot ) > 1;
	}

	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { decodePayload: decodePayload, b64urlDecode: b64urlDecode, money: money, decimals: decimals, stripBidi: stripBidi, linesTotal: linesTotal, doesNotAddUp: doesNotAddUp };
	}

	if ( typeof document === 'undefined' ) {
		return;
	}

	function esc( str ) {
		var d = document.createElement( 'div' );
		d.textContent = stripBidi( str );
		return d.innerHTML;
	}

	function show( hashOrUrl ) {
		var status  = document.getElementById( 'rf-verify-status' );
		var details = document.getElementById( 'rf-verify-details' );
		if ( ! status || ! details ) {
			return;
		}
		var hasLink = /[#&]r=/.test( String( hashOrUrl || '' ) );
		var p = decodePayload( hashOrUrl );
		var html = '';
		if ( p ) {
			try {
				html = detailsHtml( p );
			} catch ( e ) {
				p = null;
			}
		}
		if ( ! p ) {
			details.hidden = true;
			details.innerHTML = '';
			status.className = 'rf-verify-status ' + ( hasLink ? 'rf-verify-status-bad' : 'rf-verify-status-none' );
			status.textContent = hasLink ?
				'This link does not contain a readable receipt. It may be incomplete or damaged. Scan the QR code again.' :
				'No receipt loaded yet. Scan the QR code on the receipt with your phone camera, or paste the link below.';
			return;
		}

		details.innerHTML = html;
		details.hidden = false;
		// Set only after the details rendered, so a bad link never shows a result.
		// Neutral, not green: the code is not signed, so anyone can make one.
		status.className = 'rf-verify-status rf-verify-status-info';
		status.textContent = 'These are the details stored in this QR code. Anyone can create a code, so this does not prove who issued the receipt or that it was paid.';
	}

	function detailsHtml( p ) {
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
		if ( p.dc ) {
			rows.push( [ 'Discount', '-' + money( p, p.dc ) ] );
		}
		( p.tx || [] ).forEach( function ( t ) {
			var rate = t[1] !== undefined && t[1] !== '' ? t[1] + '%' : '';
			var incl = p.ti ? ( rate ? ', incl.' : 'incl.' ) : '';
			rows.push( [ String( t[0] || 'Tax' ) + ( rate || incl ? ' (' + rate + incl + ')' : '' ), money( p, t[2] ) ] );
		} );
		if ( p.tp ) {
			rows.push( [ 'Tip', money( p, p.tp ) ] );
		}
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
					var code = typeof it[3] === 'string' && it[3] ? ' (HSN/SAC ' + it[3] + ')' : '';
					return '<tr><td>' + esc( String( it[0] ) + code ) + '</td><td class="rf-num">' + esc( it[1] ) + '</td><td class="rf-num">' + esc( money( p, it[2] ) ) + '</td></tr>';
				} ).join( '' ) +
				'</tbody></table></div>';
		}
		if ( p.more ) {
			html += '<p class="rf-muted">The receipt has more lines than fit in the QR code; the first ones are shown.</p>';
		}
		if ( doesNotAddUp( p ) ) {
			html += '<p class="rf-verify-note">The lines in this code don\'t add up to the total shown.</p>';
		}
		html += '<p class="rf-muted">This shows what was typed when the code was made. It does not confirm who issued the receipt or that it was paid; only the person who received the money, or a bank statement, can.</p>';
		return html;
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
