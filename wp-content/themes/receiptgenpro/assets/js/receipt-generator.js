/**
 * ReceiptGenPro — Receipt Generator (v1.3.0)
 *
 * 100% client-side: no fetch(), no form submission, no data ever leaves
 * the browser.
 *
 * v1.1.0 changes (Sept 2026 audit):
 *  - C3: reads the per-template config rendered by generator-embed.php
 *    (extra fields, receipt title, tip, amount paid / balance due, amount
 *    in words, signature line, statement) so each /xxx-receipt/ page
 *    produces a genuinely different receipt.
 *  - C4: JPEG export (the copy always promised PDF, PNG *or JPEG*).
 *  - M5: jsPDF + html2canvas are no longer loaded on page load; they are
 *    fetched from cdnjs the first time the visitor clicks a download
 *    button (≈550 KB of JS removed from every initial page view).
 *
 * v1.1.2 (Oct 2026 audit, Phase 1): local default date, cent-rounded
 * totals, fixed amount-in-words cents, negatives clamped to 0, SRI on the
 * cdnjs libraries.
 *
 * v1.1.3 (Oct 2026 audit, Phase 3): INR/PKR amounts in words use lakh and
 * crore, the form is kept in sessionStorage across reloads, exports render
 * an 800 px off-screen copy (sharp on phones), "Change / Credit" when the
 * amount paid exceeds the total, a "Remove logo" button, separate error
 * messages, and files named receipt-<number>.<ext>.
 *
 * v1.1.4 (Oct 2026 audit, Phase 4): the preview is no longer an aria-live
 * region; a hidden status line announces only the new total, debounced.
 *
 * v1.1.6 (Oct 2026 audit, Phase 5): optional discount (% or amount, taken
 * off before tax), a named first tax and an optional second tax (e.g.
 * CGST + SGST), and the receipt number continues from the last one you
 * downloaded in this browser (localStorage, never uploaded).
 *
 * v1.2.0: requirement checker ("Check this receipt for: an HRA claim…",
 * rules from inc/requirements.php), an optional verification QR code that
 * carries the receipt's details in the link's #fragment (never sent to
 * the server; see /verify-receipt/), a custom number label and a second
 * signature line.
 *
 * v1.3.0: nine receipt designs (rf-design-<key> on the preview, kept in
 * localStorage; "Thermal Slip" exports at 80 mm, "Office + Customer Copy"
 * prints the receipt twice) and, on the homepage, a receipt type picker
 * that rebuilds the type-specific fields in place from #rf-type-data.
 */
( function () {
	'use strict';

	if ( typeof document !== 'undefined' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	}

	var LIBS = ( typeof window !== 'undefined' && window.rfReceipt && window.rfReceipt.libs ) || {
		html2canvas: {
			url: 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
			integrity: 'sha512-BNaRQnYJYiPSqHHDb58B0yaPfCu+Wgds8Gp/gU33kqBtgNS4tSPHuGibyoeqMV/TJlSKda6FXzoEyYGjTe+vXA=='
		},
		jspdf: {
			url: 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
			integrity: 'sha512-qZvrmS2ekKPF2mSznTQsxqPgnpkI4DNTlrdUmTzrDgektczlKNRRhy5X5AAOnx5S09ydFYWWNSfcEqDTTHgtNA=='
		},
		qrcode: {
			url: 'https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js',
			integrity: 'sha512-ZDSPMa/JM1D+7kdg2x3BsruQ6T/JpJo3jWDWkCZsP+5yVyp1KfESqLI+7RqB5k24F7p2cV7i2YHh/890y6P6Sw=='
		}
	};
	var loading = {};

	function loadScript( lib ) {
		// Accept a plain URL too (older cached localize data).
		var url = typeof lib === 'string' ? lib : lib.url;
		if ( loading[ url ] ) {
			return loading[ url ];
		}
		loading[ url ] = new Promise( function ( resolve, reject ) {
			var s = document.createElement( 'script' );
			s.src = url;
			s.async = true;
			s.crossOrigin = 'anonymous';
			if ( lib.integrity ) {
				s.integrity = lib.integrity;
			}
			s.onload = resolve;
			s.onerror = function () {
				delete loading[ url ];
				reject( new Error( 'Failed to load ' + url ) );
			};
			document.head.appendChild( s );
		} );
		return loading[ url ];
	}

	function ensureLibs( needPdf ) {
		var jobs = [];
		if ( typeof window.html2canvas === 'undefined' ) {
			jobs.push( loadScript( LIBS.html2canvas ) );
		}
		if ( needPdf && ! window.jspdf ) {
			jobs.push( loadScript( LIBS.jspdf ) );
		}
		return Promise.all( jobs );
	}

	// ---- Money helpers -----------------------------------------------------
	// All totals are built from amounts rounded to whole cents, so the figures
	// printed on the receipt always add up (subtotal + tax + tip = total).
	function toCents( n ) {
		// toFixed(6) strips float noise first (1.005 * 100 = 100.49999…).
		return Math.round( parseFloat( ( n * 100 ).toFixed( 6 ) ) );
	}

	// Parse a number field; empty, invalid or negative input counts as 0.
	function nonNegative( value, fallback ) {
		var n = parseFloat( value );
		if ( isNaN( n ) ) {
			n = fallback || 0;
		}
		return n > 0 ? n : 0;
	}

	// Subtotal → discount → taxes → tip → total, all in whole cents.
	// discountType is 'percent' or 'amount'; a discount never exceeds the
	// subtotal, and every tax is charged on the discounted amount.
	function calcTotals( subtotalCents, discount, discountType, taxRates, tipCents ) {
		var discountCents = discountType === 'percent' ?
			toCents( subtotalCents * Math.min( discount, 100 ) / 10000 ) :
			toCents( discount );
		discountCents = Math.min( discountCents, subtotalCents );
		var taxableCents = subtotalCents - discountCents;
		var taxCents = taxRates.map( function ( rate ) {
			return toCents( taxableCents * rate / 10000 );
		} );
		var totalCents = taxableCents + tipCents + taxCents.reduce( function ( a, b ) {
			return a + b;
		}, 0 );
		return { discountCents: discountCents, taxCents: taxCents, totalCents: totalCents };
	}

	// Splits a receipt number into { pre, digits, post } around its counter:
	// the last run of digits, unless that run is a year (19xx/20xx) at the
	// very end after a separator and another run comes before it, as in
	// A-1/2026 or 0042-2026. null when there is no digit at all.
	function numberParts( str ) {
		var runs = [];
		var re = /\d+/g;
		var m;
		while ( ( m = re.exec( str ) ) ) {
			runs.push( { start: m.index, digits: m[0] } );
		}
		if ( ! runs.length ) {
			return null;
		}
		var run = runs[ runs.length - 1 ];
		if ( runs.length > 1 && run.start + 4 === str.length && /^(19|20)\d\d$/.test( run.digits ) && /[\/\-.\s_]$/.test( str.slice( 0, run.start ) ) ) {
			run = runs[ runs.length - 2 ];
		}
		return { pre: str.slice( 0, run.start ), digits: run.digits, post: str.slice( run.start + run.digits.length ) };
	}

	// Adds 1 to a string of digits, keeping zero padding and any length
	// (no parseInt, so 20-digit numbers don't lose precision).
	function incrementDigits( digits ) {
		var out = digits.split( '' );
		for ( var i = out.length - 1; i >= 0; i-- ) {
			if ( out[ i ] !== '9' ) {
				out[ i ] = String( +out[ i ] + 1 );
				return out.join( '' );
			}
			out[ i ] = '0';
		}
		return '1' + out.join( '' );
	}

	// The receipt number after this one: 1001 → 1002, INV-0099 → INV-0100,
	// 2026-009 → 2026-010, A-1/2026 → A-2/2026 (a year at the end is kept).
	// A number without digits gets "-2".
	function nextReceiptNumber( current ) {
		var str = String( current || '' ).trim();
		var p = numberParts( str );
		if ( ! p ) {
			return str ? str + '-2' : '1001';
		}
		return p.pre + incrementDigits( p.digits ) + p.post;
	}

	// True when `num` was already used: it equals the last downloaded number,
	// or has the same prefix and suffix and a counter that is not higher.
	function isUsedNumber( num, last ) {
		num  = String( num || '' ).trim();
		last = String( last || '' ).trim();
		if ( ! num || ! last ) {
			return false;
		}
		if ( num === last ) {
			return true;
		}
		var a = numberParts( num );
		var b = numberParts( last );
		if ( ! a || ! b || a.pre !== b.pre || a.post !== b.post ) {
			return false;
		}
		var x = a.digits.replace( /^0+(?=\d)/, '' );
		var y = b.digits.replace( /^0+(?=\d)/, '' );
		return x.length < y.length || ( x.length === y.length && x <= y );
	}

	// Today's date in the visitor's own time zone (toISOString() is UTC).
	function todayLocal() {
		var d = new Date();
		var pad = function ( n ) {
			return ( n < 10 ? '0' : '' ) + n;
		};
		return d.getFullYear() + '-' + pad( d.getMonth() + 1 ) + '-' + pad( d.getDate() );
	}

	// ---- Number to words (for cash / rent receipts) -----------------------
	var ONES = [ '', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen' ];
	var TENS = [ '', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety' ];

	function under1000( n ) {
		var out = [];
		if ( n >= 100 ) {
			out.push( ONES[ Math.floor( n / 100 ) ] + ' hundred' );
			n = n % 100;
		}
		if ( n >= 20 ) {
			out.push( TENS[ Math.floor( n / 10 ) ] + ( n % 10 ? '-' + ONES[ n % 10 ] : '' ) );
		} else if ( n > 0 ) {
			out.push( ONES[ n ] );
		}
		return out.join( ' ' );
	}

	function intToWords( n ) {
		if ( n === 0 ) {
			return 'zero';
		}
		var scales = [ '', ' thousand', ' million', ' billion', ' trillion' ];
		var parts = [];
		var i = 0;
		while ( n > 0 && i < scales.length ) {
			var chunk = n % 1000;
			if ( chunk ) {
				parts.unshift( under1000( chunk ) + scales[ i ] );
			}
			n = Math.floor( n / 1000 );
			i++;
		}
		return parts.join( ' ' );
	}

	// Indian numbering (lakh = 1,00,000; crore = 1,00,00,000) for INR and PKR.
	function intToWordsIndian( n ) {
		if ( n === 0 ) {
			return 'zero';
		}
		var parts = [];
		var crore = Math.floor( n / 10000000 );
		n = n % 10000000;
		if ( crore ) {
			parts.push( intToWordsIndian( crore ) + ' crore' );
		}
		var lakh = Math.floor( n / 100000 );
		n = n % 100000;
		if ( lakh ) {
			parts.push( under1000( lakh ) + ' lakh' );
		}
		var thousand = Math.floor( n / 1000 );
		n = n % 1000;
		if ( thousand ) {
			parts.push( under1000( thousand ) + ' thousand' );
		}
		if ( n ) {
			parts.push( under1000( n ) );
		}
		return parts.join( ' ' );
	}

	var INDIAN_GROUPING = { INR: true, PKR: true };

	var CURRENCY_WORDS = {
		USD: [ 'US dollars', 'cents' ], EUR: [ 'euros', 'cents' ], GBP: [ 'pounds', 'pence' ],
		PKR: [ 'Pakistani rupees', 'paisa' ], INR: [ 'Indian rupees', 'paise' ], CAD: [ 'Canadian dollars', 'cents' ],
		AUD: [ 'Australian dollars', 'cents' ], AED: [ 'UAE dirhams', 'fils' ], SAR: [ 'Saudi riyals', 'halalas' ]
	};

	function amountInWords( amount, code ) {
		var names = CURRENCY_WORDS[ code ] || [ code, 'cents' ];
		// Split in integer cents so 2.999 becomes "three … only", never "one hundred cents".
		var c     = toCents( amount );
		var whole = Math.floor( c / 100 );
		var cents = c % 100;
		var toWords = INDIAN_GROUPING[ code ] ? intToWordsIndian : intToWords;
		var text = toWords( whole ) + ' ' + names[0];
		if ( cents ) {
			text += ' and ' + toWords( cents ) + ' ' + names[1];
		}
		return text.charAt( 0 ).toUpperCase() + text.slice( 1 ) + ' only';
	}

	// ---- Requirement checks (rules: inc/requirements.php) -----------------
	// facts: { business, client, date, items, total, words, signature,
	//          statement: true | false | null (null = not on this template),
	//          x: { <extra field id>: true | false }, amount, cash }
	// Returns one { label, status, optional } per check, where status is
	// ok | missing | nofield | manual | na.
	function factFor( facts, key ) {
		if ( key.indexOf( 'x:' ) === 0 ) {
			var id = key.slice( 2 );
			return Object.prototype.hasOwnProperty.call( facts.x || {}, id ) ? !! facts.x[ id ] : null;
		}
		return facts[ key ] === undefined ? null : facts[ key ];
	}

	function evaluateChecks( checks, facts ) {
		return ( checks || [] ).map( function ( c ) {
			var out = { label: c.label, optional: !! c.optional, status: 'ok' };
			var when = c.when || {};
			if ( ( when.total_over !== undefined && ! ( facts.amount > when.total_over ) ) || ( when.cash && ! facts.cash ) ) {
				out.status = 'na';
				return out;
			}
			if ( c.manual ) {
				out.status = 'manual';
				return out;
			}
			var keys = [].concat( c.field || [] );
			var vals = keys.map( function ( k ) {
				return factFor( facts, k );
			} );
			var present = vals.filter( function ( v ) {
				return v !== null;
			} );
			if ( c.all ) {
				out.status = present.length < vals.length ? 'nofield' : ( present.every( Boolean ) ? 'ok' : 'missing' );
			} else {
				out.status = present.some( Boolean ) ? 'ok' : ( present.length ? 'missing' : 'nofield' );
			}
			return out;
		} );
	}

	// ---- Verification QR payload -------------------------------------------
	// The receipt's details as compact JSON, base64url-encoded, placed after
	// the # of the /verify-receipt/ link. Browsers never send the # part to
	// the server, so the details stay between the issuer and whoever scans.
	function clip( str, max ) {
		str = String( str || '' );
		return str.length > max ? str.slice( 0, max - 1 ) + '…' : str;
	}

	function b64urlEncode( str ) {
		var bytes = new TextEncoder().encode( str );
		var bin = '';
		for ( var i = 0; i < bytes.length; i++ ) {
			bin += String.fromCharCode( bytes[ i ] );
		}
		return btoa( bin ).replace( /\+/g, '-' ).replace( /\//g, '_' ).replace( /=+$/, '' );
	}

	var QR_MAX = 1200; // characters of payload; keeps the code scannable when printed

	function buildPayload( r ) {
		var p = {
			v: 1,
			t: clip( r.title, 40 ),
			b: clip( r.business, 80 ),
			c: clip( r.client, 80 ),
			n: clip( r.number, 30 ),
			d: r.date,
			cu: r.currency,
			i: ( r.items || [] ).map( function ( it ) {
				return [ clip( it.name, 60 ), it.qty, it.priceCents ];
			} ),
			tot: r.totalCents,
			pm: r.payMethod,
			x: ( r.extras || [] ).map( function ( e ) {
				return [ clip( e[0], 40 ), clip( e[1], 80 ) ];
			} )
		};
		if ( r.paidCents !== null && r.paidCents !== undefined ) {
			p.pd = r.paidCents;
		}
		var enc = b64urlEncode( JSON.stringify( p ) );
		while ( enc.length > QR_MAX && ( p.x.length || p.i.length > 3 ) ) {
			if ( p.x.length ) {
				p.x.pop();
			} else {
				p.i.pop();
				p.more = 1; // tells the verify page that not every line fit
			}
			enc = b64urlEncode( JSON.stringify( p ) );
		}
		return enc;
	}

	// ---- Receipt type fields (homepage picker) -----------------------------
	// The same markup generator-embed.php renders for a template's extra
	// fields, tip / amount paid and statement, built from its form config.
	function esc( str ) {
		return String( str === undefined || str === null ? '' : str )
			.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' ).replace( /'/g, '&#039;' );
	}

	function titleCase( str ) {
		return String( str || '' ).toLowerCase().replace( /(^|\s)([a-z])/g, function ( m, sp, ch ) {
			return sp + ch.toUpperCase();
		} );
	}

	function typeFieldsHtml( f ) {
		f = f || {};
		var out = { extra: '', tippaid: '', statement: '' };
		var extra = f.extra || [];
		if ( extra.length ) {
			out.extra = '<fieldset class="rf-template-fields"><legend>' + esc( titleCase( f.title || 'Receipt' ) ) + ' Details</legend><div class="rf-form-row rf-form-row-2col">' +
				extra.map( function ( x ) {
					var id = 'rf-x-' + String( x.id || '' ).toLowerCase().replace( /[^a-z0-9_-]/g, '' );
					var type = x.type || 'text';
					var attrs = ' id="' + esc( id ) + '" data-rf-extra data-label="' + esc( x.label ) + '"';
					var input;
					if ( type === 'select' ) {
						input = '<select' + attrs + '>' + ( x.options || [] ).map( function ( o ) {
							return '<option>' + esc( o ) + '</option>';
						} ).join( '' ) + '</select>';
					} else if ( type === 'textarea' ) {
						input = '<textarea' + attrs + ' rows="2" placeholder="' + esc( x.ph ) + '"></textarea>';
					} else {
						input = '<input type="' + esc( type ) + '"' + attrs + ' data-type="' + esc( type ) + '" placeholder="' + esc( x.ph ) + '">';
					}
					return '<div class="rf-field"><label for="' + esc( id ) + '">' + esc( x.label ) + '</label>' + input + '</div>';
				} ).join( '' ) + '</div></fieldset>';
		}
		if ( f.tip || f.paid ) {
			out.tippaid = '<div class="rf-form-row rf-form-row-2col">' +
				( f.tip ? '<div class="rf-field"><label for="rf-tip">Tip / Gratuity (amount)</label><input type="number" id="rf-tip" min="0" step="0.01" placeholder="0.00"></div>' : '' ) +
				( f.paid ? '<div class="rf-field"><label for="rf-amount-paid">Amount Paid (leave blank if paid in full)</label><input type="number" id="rf-amount-paid" min="0" step="0.01" placeholder="0.00"></div>' : '' ) +
				'</div>';
		}
		var st = f.statement;
		if ( st && st.text ) {
			out.statement = '<div class="rf-field rf-field-check"><label><input type="checkbox" id="rf-statement" data-text="' + esc( st.text ) + '"' + ( st.checked ? ' checked' : '' ) + '> ' + esc( st.label ) + '</label></div>';
		}
		return out;
	}

	// ---- Designs ---------------------------------------------------------
	// Export width in px per design (others use the 800 px default).
	var DESIGN_WIDTH = { thermal: 380 };

	// "Office + Customer Copy" prints the same receipt twice with a cut line.
	function wrapDesign( design, body ) {
		if ( design !== 'copies' ) {
			return '<div class="rf-receipt-inner">' + body + '</div>';
		}
		return '<div class="rf-receipt-inner"><p class="rf-copy-label">Office copy</p>' + body + '</div>' +
			'<div class="rf-copy-cut"><span>&#9986; cut here</span></div>' +
			'<div class="rf-receipt-inner"><p class="rf-copy-label">Customer copy</p>' + body + '</div>';
	}

	// Lets a Node check exercise the pure helpers without a browser.
	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = {
			amountInWords: amountInWords,
			intToWords: intToWords,
			intToWordsIndian: intToWordsIndian,
			toCents: toCents,
			nonNegative: nonNegative,
			calcTotals: calcTotals,
			nextReceiptNumber: nextReceiptNumber,
			isUsedNumber: isUsedNumber,
			evaluateChecks: evaluateChecks,
			buildPayload: buildPayload,
			b64urlEncode: b64urlEncode,
			typeFieldsHtml: typeFieldsHtml,
			wrapDesign: wrapDesign,
			esc: esc
		};
	}

	function init() {
		var form = document.getElementById( 'rf-receipt-form' );
		if ( ! form ) {
			return; // Tool not present on this page.
		}

		var itemsList   = document.getElementById( 'rf-items-list' );
		var addItemBtn  = document.getElementById( 'rf-add-item' );
		var currencySel = document.getElementById( 'rf-currency' );
		var dateInput   = document.getElementById( 'rf-receipt-date' );
		var preview     = document.getElementById( 'rf-receipt-preview' );
		var logoInput   = document.getElementById( 'rf-business-logo' );
		var logoRemove  = document.getElementById( 'rf-logo-remove' );
		var tipInput    = document.getElementById( 'rf-tip' );
		var paidInput   = document.getElementById( 'rf-amount-paid' );
		var statement   = document.getElementById( 'rf-statement' );
		var totalLive   = document.getElementById( 'rf-total-live' );
		var totalLiveTimer = null;

		var cfg = {
			title: form.dataset.receiptTitle || 'RECEIPT',
			words: form.dataset.words === '1',
			signature: form.dataset.signature || '',
			signature2: form.dataset.signature2 || '',
			numberLabel: form.dataset.numberLabel || 'Receipt #',
			businessLabel: form.dataset.businessLabel || 'From',
			clientLabel: form.dataset.clientLabel || 'Received From'
		};
		var itemPlaceholders = [];
		try {
			itemPlaceholders = JSON.parse( form.dataset.itemPlaceholders || '[]' );
		} catch ( e ) {
			itemPlaceholders = [];
		}

		var state = { logoDataUrl: null };

		// ---- Verification QR code ---------------------------------------------
		var qrToggle   = document.getElementById( 'rf-qr' );
		var verifyBase = ( window.rfReceipt && window.rfReceipt.verifyUrl ) || 'https://receiptgenpro.com/verify-receipt/';
		var qrCache    = { text: '', url: '' };

		function qrDataUrl( text ) {
			if ( typeof window.qrcode !== 'function' ) {
				ensureQrLib();
				return '';
			}
			if ( qrCache.text !== text ) {
				var qr = window.qrcode( 0, 'L' );
				qr.addData( text, 'Byte' );
				qr.make();
				// 4 px per module, scaled down in CSS with crisp edges.
				qrCache = { text: text, url: qr.createDataURL( 4, 0 ) };
			}
			return qrCache.url;
		}

		var qrLoading = false;
		function ensureQrLib() {
			if ( qrLoading ) {
				return;
			}
			qrLoading = true;
			loadScript( LIBS.qrcode ).then( function () {
				renderReceipt();
			}, function () {
				qrLoading = false; // try again on the next change
			} );
		}

		// ---- Requirement checker ----------------------------------------------
		var purposeSel    = document.getElementById( 'rf-purpose' );
		var checkerResult = document.getElementById( 'rf-checker-result' );
		var purposes      = ( window.rfReceipt && window.rfReceipt.purposes ) || {};
		var lastFacts     = null;

		if ( purposeSel ) {
			var urlPurpose = ( window.location.search.match( /[?&]purpose=([a-z0-9-]+)/ ) || [] )[1];
			if ( urlPurpose && purposes[ urlPurpose ] ) {
				purposeSel.value = urlPurpose;
			}
			purposeSel.addEventListener( 'change', function () {
				renderChecker( lastFacts );
			} );
		}

		function extraFacts() {
			var x = {};
			form.querySelectorAll( '[data-rf-extra]' ).forEach( function ( el ) {
				x[ el.id.replace( /^rf-x-/, '' ) ] = !! ( el.value || '' ).trim();
			} );
			return x;
		}

		var STATUS_TEXT = {
			ok: '',
			missing: 'Fill this in',
			nofield: 'Not on this template',
			manual: 'Do this on the printed receipt',
			na: 'Not needed for this amount'
		};

		function renderChecker( facts ) {
			lastFacts = facts;
			if ( ! purposeSel || ! checkerResult || ! facts ) {
				return;
			}
			var p = purposes[ purposeSel.value ];
			if ( ! p ) {
				checkerResult.innerHTML = '';
				return;
			}
			var results = evaluateChecks( p.checks, facts );
			var needed  = results.filter( function ( r ) {
				return ! r.optional && ( r.status === 'ok' || r.status === 'missing' || r.status === 'nofield' );
			} );
			var done = needed.filter( function ( r ) {
				return r.status === 'ok';
			} ).length;
			var onOtherTemplate = p.templateUrl && p.templateUrl.replace( /\/$/, '' ) !== ( window.location.origin + window.location.pathname ).replace( /\/$/, '' );
			var html = '<p class="rf-checker-summary' + ( done === needed.length ? ' rf-checker-all-done' : '' ) + '">' +
				( done === needed.length ? 'Everything required is on the receipt.' : done + ' of ' + needed.length + ' required details are on the receipt.' ) + '</p><ul>';
			results.forEach( function ( r ) {
				var status = r.status === 'missing' && r.optional ? 'optional' : r.status;
				var note = r.status === 'missing' && r.optional ? 'Recommended' : STATUS_TEXT[ r.status ];
				html += '<li class="rf-check-' + status + '"><span class="rf-check-label">' + escapeHtml( r.label ) + '</span>' +
					( note ? '<span class="rf-check-note">' + escapeHtml( note ) + '</span>' : '' ) + '</li>';
			} );
			html += '</ul>';
			if ( onOtherTemplate && results.some( function ( r ) {
				return r.status === 'nofield' && ! r.optional;
			} ) ) {
				html += '<p class="rf-checker-switch">The <a href="' + escapeHtml( p.templateUrl ) + '?purpose=' + encodeURIComponent( purposeSel.value ) + '#rf-generator">' + escapeHtml( p.templateName || 'matching template' ) + '</a> has every field this needs.</p>';
			}
			checkerResult.innerHTML = html;
		}

		// ---- Receipt type picker (homepage) ------------------------------------
		var typeData = null;
		var currentType = 'general';
		var typeDataEl = document.getElementById( 'rf-type-data' );
		if ( typeDataEl ) {
			try {
				typeData = JSON.parse( typeDataEl.textContent );
			} catch ( e ) {
				typeData = null;
			}
		}
		var typeLinks = document.querySelectorAll( '.rf-type-option' );
		var typeMore  = document.getElementById( 'rf-type-more' );

		function setLabel( inputId, text, ph ) {
			var label = form.querySelector( 'label[for="' + inputId + '"]' );
			var input = document.getElementById( inputId );
			if ( label && text ) {
				label.textContent = text;
			}
			if ( input && ph !== undefined ) {
				input.placeholder = ph;
			}
		}

		var paySel = document.getElementById( 'rf-payment-method' );
		var paymentTouched = false;
		var typeCurrency = '';
		if ( paySel ) {
			paySel.addEventListener( 'change', function () {
				paymentTouched = true;
			} );
		}

		// Swap the form to another receipt type, keeping everything the
		// types share (business, customer, number, date, items, taxes, notes).
		function applyType( slug ) {
			var t = typeData && typeData[ slug ];
			if ( ! t ) {
				return false;
			}
			var f = t.form || {};
			currentType = slug;
			cfg.title         = f.title || 'RECEIPT';
			cfg.words         = !! f.words;
			cfg.signature     = f.signature || '';
			cfg.signature2    = f.signature2 || '';
			cfg.numberLabel   = f.number_label || 'Receipt #';
			cfg.businessLabel = f.business_label || 'From';
			cfg.clientLabel   = f.client_label || 'Received From';
			itemPlaceholders  = [].concat( f.item_ph || [] );

			setLabel( 'rf-business-name', f.business_label, f.business_ph );
			setLabel( 'rf-client-name', f.client_label, f.client_ph );
			setLabel( 'rf-receipt-number', f.number_label );
			var notesEl = document.getElementById( 'rf-notes' );
			if ( notesEl ) {
				notesEl.placeholder = f.notes_ph || '';
			}
			// The payment method follows the type until the visitor picks one.
			if ( paySel && f.payment && ! paymentTouched ) {
				paySel.value = f.payment;
			}
			// A type's own currency (INR for HRA) is undone when switching to
			// a type without one, unless the visitor chose another currency.
			if ( f.currency && currencies[ f.currency ] ) {
				currencySel.value = f.currency;
				typeCurrency = f.currency;
			} else if ( typeCurrency ) {
				if ( currencySel.value === typeCurrency ) {
					currencySel.value = defaultCurrency;
				}
				typeCurrency = '';
			}

			var parts = typeFieldsHtml( f );
			[ 'extra', 'tippaid', 'statement' ].forEach( function ( key ) {
				var box = document.getElementById( 'rf-type-' + key );
				if ( box ) {
					box.innerHTML = parts[ key ];
				}
			} );
			tipInput  = document.getElementById( 'rf-tip' );
			paidInput = document.getElementById( 'rf-amount-paid' );
			statement = document.getElementById( 'rf-statement' );

			itemsList.querySelectorAll( '.rf-item-name' ).forEach( function ( input, i ) {
				input.placeholder = itemPlaceholders[ i ] || itemPlaceholders[0] || 'Item or service';
			} );

			if ( purposeSel ) {
				purposeSel.value = purposes[ t.purpose ] ? t.purpose : '';
			}

			typeLinks.forEach( function ( a ) {
				if ( a.dataset.type === slug ) {
					a.setAttribute( 'aria-current', 'true' );
				} else {
					a.removeAttribute( 'aria-current' );
				}
			} );
			if ( typeMore ) {
				if ( t.url ) {
					typeMore.innerHTML = 'Rules, tips and examples: <a href="' + esc( t.url ) + '">' + esc( t.label ) + ' guide</a>';
					typeMore.hidden = false;
				} else {
					typeMore.innerHTML = '';
					typeMore.hidden = true;
				}
			}
			return true;
		}

		typeLinks.forEach( function ( a ) {
			a.addEventListener( 'click', function ( e ) {
				if ( ! typeData || e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1 ) {
					return; // no data, or opening in a new tab: follow the link
				}
				e.preventDefault();
				if ( applyType( a.dataset.type ) ) {
					renderReceipt();
				}
			} );
		} );

		// ---- Design picker ---------------------------------------------------
		var DESIGN_KEY = 'rgp-design';
		var designRadios = document.querySelectorAll( 'input[name="rf-design"]' );
		var design = 'classic';

		function setDesign( key ) {
			var radio = null;
			designRadios.forEach( function ( r ) {
				if ( r.value === key ) {
					radio = r;
				}
			} );
			if ( ! radio ) {
				return;
			}
			design = key;
			radio.checked = true;
			designRadios.forEach( function ( r ) {
				r.parentNode.classList.toggle( 'is-checked', r === radio );
			} );
			preview.className = preview.className.replace( /\brf-design-[a-z]+\b/g, '' ).trim() + ' rf-design-' + key;
		}

		designRadios.forEach( function ( r ) {
			r.addEventListener( 'change', function () {
				setDesign( r.value );
				try {
					window.localStorage.setItem( DESIGN_KEY, r.value );
				} catch ( e ) {}
				renderReceipt();
			} );
		} );
		try {
			setDesign( window.localStorage.getItem( DESIGN_KEY ) || 'classic' );
		} catch ( e ) {
			setDesign( 'classic' );
		}

		// ---- Currency dropdown ---------------------------------------------
		var currencies = ( window.rfReceipt && window.rfReceipt.currencies ) || { USD: '$ — US Dollar' };
		Object.keys( currencies ).forEach( function ( code ) {
			var opt = document.createElement( 'option' );
			opt.value = code;
			opt.textContent = currencies[ code ];
			currencySel.appendChild( opt );
		} );

		// The page's own currency: the template default (e.g. INR for HRA) or USD.
		var defaultCurrency = form.dataset.currency && currencies[ form.dataset.currency ] ? form.dataset.currency : ( currencies.USD ? 'USD' : currencySel.value );
		currencySel.value = defaultCurrency;

		// ---- Default date = today ------------------------------------------
		if ( dateInput && ! dateInput.value ) {
			dateInput.value = todayLocal();
		}

		// ---- Item rows ------------------------------------------------------
		function addItemRow( placeholder, values ) {
			var row = document.createElement( 'div' );
			row.className = 'rf-item-row';

			var name = document.createElement( 'input' );
			name.type = 'text';
			name.className = 'rf-item-name';
			name.placeholder = placeholder || itemPlaceholders[0] || 'Item or service';
			name.setAttribute( 'aria-label', 'Item description' );

			var qty = document.createElement( 'input' );
			qty.type = 'number';
			qty.className = 'rf-item-qty';
			qty.min = '0';
			qty.step = 'any';
			qty.value = '1';
			qty.setAttribute( 'aria-label', 'Quantity' );

			var price = document.createElement( 'input' );
			price.type = 'number';
			price.className = 'rf-item-price';
			price.min = '0';
			price.step = '0.01';
			price.placeholder = '0.00';
			price.setAttribute( 'aria-label', 'Price' );

			var remove = document.createElement( 'button' );
			remove.type = 'button';
			remove.className = 'rf-item-remove';
			remove.setAttribute( 'aria-label', 'Remove item' );
			remove.innerHTML = '&times;';

			if ( values ) {
				name.value  = values.name || '';
				qty.value   = values.qty !== undefined ? values.qty : '1';
				price.value = values.price || '';
			}

			row.appendChild( name );
			row.appendChild( qty );
			row.appendChild( price );
			row.appendChild( remove );
			itemsList.appendChild( row );

			remove.addEventListener( 'click', function () {
				row.remove();
				renderReceipt();
			} );
		}

		addItemBtn.addEventListener( 'click', function () {
			addItemRow();
			renderReceipt();
		} );

		// Seed rows with the template-specific placeholders.
		var seeds = itemPlaceholders.length ? itemPlaceholders.slice( 0, 2 ) : [ '', '' ];
		if ( seeds.length < 2 ) {
			seeds.push( seeds[0] );
		}
		seeds.forEach( function ( ph ) {
			addItemRow( ph );
		} );

		// ---- Logo upload ------------------------------------------------------
		if ( logoInput ) {
			logoInput.addEventListener( 'change', function ( e ) {
				var file = e.target.files && e.target.files[0];
				if ( ! file ) {
					return;
				}
				var reader = new FileReader();
				reader.onload = function ( ev ) {
					state.logoDataUrl = ev.target.result;
					renderReceipt();
				};
				reader.readAsDataURL( file );
			} );
		}

		if ( logoRemove ) {
			logoRemove.addEventListener( 'click', function () {
				state.logoDataUrl = null;
				if ( logoInput ) {
					logoInput.value = '';
				}
				renderReceipt();
			} );
		}

		// ---- Keep entries across reloads (until the tab is closed) ------------
		// sessionStorage stays in this browser tab only; nothing is uploaded.
		var STORE_KEY = 'rgp-form:' + window.location.pathname;

		function storeFields() {
			return Array.prototype.filter.call( form.querySelectorAll( 'input[id], select[id], textarea[id]' ), function ( el ) {
				return el.type !== 'file';
			} );
		}

		function saveForm() {
			var data = { type: currentType, fields: {}, items: [], logo: state.logoDataUrl };
			storeFields().forEach( function ( el ) {
				data.fields[ el.id ] = el.type === 'checkbox' ? el.checked : el.value;
			} );
			itemsList.querySelectorAll( '.rf-item-row' ).forEach( function ( row ) {
				data.items.push( {
					name: row.querySelector( '.rf-item-name' ).value,
					qty: row.querySelector( '.rf-item-qty' ).value,
					price: row.querySelector( '.rf-item-price' ).value
				} );
			} );
			try {
				window.sessionStorage.setItem( STORE_KEY, JSON.stringify( data ) );
			} catch ( e ) {
				// A large logo can exceed the quota: keep everything else.
				try {
					data.logo = null;
					window.sessionStorage.setItem( STORE_KEY, JSON.stringify( data ) );
				} catch ( e2 ) {}
			}
		}

		function restoreForm() {
			var data;
			try {
				data = JSON.parse( window.sessionStorage.getItem( STORE_KEY ) || 'null' );
			} catch ( e ) {
				data = null;
			}
			if ( ! data || ! data.fields ) {
				return;
			}
			if ( data.type && data.type !== 'general' ) {
				applyType( data.type );
			}
			storeFields().forEach( function ( el ) {
				if ( ! Object.prototype.hasOwnProperty.call( data.fields, el.id ) ) {
					return;
				}
				if ( el.type === 'checkbox' ) {
					el.checked = !! data.fields[ el.id ];
				} else {
					el.value = data.fields[ el.id ];
				}
			} );
			if ( Array.isArray( data.items ) ) {
				itemsList.innerHTML = '';
				data.items.forEach( function ( it, i ) {
					addItemRow( itemPlaceholders[ i ] || itemPlaceholders[0], it );
				} );
			}
			if ( data.logo ) {
				state.logoDataUrl = data.logo;
			}
		}

		// ---- Receipt number continues from the last download ----------------
		// localStorage keeps only the last downloaded number, in this browser.
		var LAST_NUMBER_KEY = 'rgp-last-receipt-number';
		var numberInput = document.getElementById( 'rf-receipt-number' );
		try {
			var lastNumber = window.localStorage.getItem( LAST_NUMBER_KEY );
			if ( lastNumber && numberInput ) {
				numberInput.value = nextReceiptNumber( lastNumber );
			}
		} catch ( e ) {}

		// After a download: remember the number used and move the field on to
		// the next one, so the next receipt never repeats it.
		function rememberNumber() {
			if ( ! numberInput ) {
				return;
			}
			var used = numberInput.value.trim() || '1001';
			try {
				window.localStorage.setItem( LAST_NUMBER_KEY, used );
			} catch ( e ) {}
			numberInput.value = nextReceiptNumber( used );
			numberInput.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		}

		restoreForm();

		// A number saved in this tab that was already downloaded moves on too.
		if ( lastNumber && numberInput && isUsedNumber( numberInput.value, lastNumber ) ) {
			numberInput.value = nextReceiptNumber( lastNumber );
		}

		// Show the extra options when a restored form uses them.
		var moreOptions = document.getElementById( 'rf-more-options' );
		if ( moreOptions && ( nonNegative( ( document.getElementById( 'rf-discount' ) || {} ).value ) || nonNegative( ( document.getElementById( 'rf-tax2-rate' ) || {} ).value ) ) ) {
			moreOptions.open = true;
		}

		// ---- Live re-render on any field change ------------------------------
		form.addEventListener( 'input', renderReceipt );
		form.addEventListener( 'change', renderReceipt );

		function getCurrencySymbol( code ) {
			var map = { USD: '$', EUR: '€', GBP: '£', PKR: '₨', INR: '₹', CAD: 'CA$', AUD: 'AU$', AED: 'د.إ', SAR: '﷼' };
			return map[ code ] || code + ' ';
		}

		function escapeHtml( str ) {
			var div = document.createElement( 'div' );
			div.textContent = str || '';
			return div.innerHTML;
		}

		function fieldValue( id ) {
			var el = document.getElementById( id );
			return el ? el.value.trim() : '';
		}

		function money( symbol, n ) {
			return symbol + ( toCents( n ) / 100 ).toFixed( 2 );
		}

		function formatExtra( el ) {
			var v = ( el.value || '' ).trim();
			if ( ! v ) {
				return '';
			}
			var t = el.dataset.type;
			if ( t === 'date' && /^\d{4}-\d{2}-\d{2}$/.test( v ) ) {
				return v;
			}
			if ( t === 'month' && /^\d{4}-\d{2}$/.test( v ) ) {
				var mm = [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ];
				return mm[ parseInt( v.slice( 5 ), 10 ) - 1 ] + ' ' + v.slice( 0, 4 );
			}
			if ( t === 'datetime-local' ) {
				return v.replace( 'T', ' ' );
			}
			return v;
		}

		function collectItems() {
			var rows = itemsList.querySelectorAll( '.rf-item-row' );
			var items = [];
			rows.forEach( function ( row ) {
				var name  = row.querySelector( '.rf-item-name' ).value.trim();
				var qty   = nonNegative( row.querySelector( '.rf-item-qty' ).value, 1 );
				var price = nonNegative( row.querySelector( '.rf-item-price' ).value );
				if ( name || price ) {
					items.push( { name: name || 'Item', qty: qty, price: price, lineCents: toCents( qty * price ) } );
				}
			} );
			return items;
		}

		function renderReceipt() {
			var business  = document.getElementById( 'rf-business-name' ).value.trim() || 'Your Business Name';
			var client    = document.getElementById( 'rf-client-name' ).value.trim();
			var number    = document.getElementById( 'rf-receipt-number' ).value.trim() || '1001';
			var date      = dateInput.value || todayLocal();
			var currency  = currencySel.value || 'USD';
			var symbol    = getCurrencySymbol( currency );
			var taxRate   = nonNegative( document.getElementById( 'rf-tax-rate' ).value );
			var taxLabel  = fieldValue( 'rf-tax-label' ) || 'Tax';
			var tax2Rate  = nonNegative( fieldValue( 'rf-tax2-rate' ) );
			var tax2Label = fieldValue( 'rf-tax2-label' ) || 'Tax 2';
			var discount  = nonNegative( fieldValue( 'rf-discount' ) );
			var discType  = fieldValue( 'rf-discount-type' ) === 'amount' ? 'amount' : 'percent';
			var payMethod = document.getElementById( 'rf-payment-method' ).value;
			var notes     = document.getElementById( 'rf-notes' ).value.trim();
			var items     = collectItems();
			var tipCents  = tipInput ? toCents( nonNegative( tipInput.value ) ) : 0;
			var paidRaw   = paidInput ? paidInput.value.trim() : '';

			var subtotalCents = items.reduce( function ( sum, it ) {
				return sum + it.lineCents;
			}, 0 );
			var calc       = calcTotals( subtotalCents, discount, discType, [ taxRate, tax2Rate ], tipCents );
			var totalCents = calc.totalCents;
			var subtotal   = subtotalCents / 100;
			var tip        = tipCents / 100;
			var total      = totalCents / 100;
			var paid       = paidRaw === '' ? null : toCents( nonNegative( paidRaw ) ) / 100;

			var rowsHtml = items
				.map( function ( it ) {
					return (
						'<tr>' +
						'<td>' + escapeHtml( it.name ) + '</td>' +
						'<td class="rf-num">' + escapeHtml( String( it.qty ) ) + '</td>' +
						'<td class="rf-num">' + money( symbol, it.price ) + '</td>' +
						'<td class="rf-num">' + money( symbol, it.lineCents / 100 ) + '</td>' +
						'</tr>'
					);
				} )
				.join( '' );

			var meta = [
				'<div><strong>' + escapeHtml( cfg.numberLabel.replace( /:$/, '' ) ) + ':</strong> ' + escapeHtml( number ) + '</div>',
				'<div><strong>Date:</strong> ' + escapeHtml( date ) + '</div>'
			];
			if ( client ) {
				meta.push( '<div><strong>' + escapeHtml( cfg.clientLabel.replace( / \(optional\)$/i, '' ) ) + ':</strong> ' + escapeHtml( client ) + '</div>' );
			}
			meta.push( '<div><strong>Payment Method:</strong> ' + escapeHtml( payMethod ) + '</div>' );

			var extras = [];
			form.querySelectorAll( '[data-rf-extra]' ).forEach( function ( el ) {
				var val = formatExtra( el );
				if ( val ) {
					var label = ( el.dataset.label || '' ).replace( / \(optional\)$/i, '' );
					meta.push( '<div><strong>' + escapeHtml( label ) + ':</strong> ' + escapeHtml( val ) + '</div>' );
					extras.push( [ label, val ] );
				}
			} );

			var totalsHtml =
				'<div><span>Subtotal</span><span>' + money( symbol, subtotal ) + '</span></div>' +
				( calc.discountCents ? '<div><span>Discount' + ( discType === 'percent' ? ' (' + Math.min( discount, 100 ) + '%)' : '' ) + '</span><span>-' + money( symbol, calc.discountCents / 100 ) + '</span></div>' : '' ) +
				( taxRate ? '<div><span>' + escapeHtml( taxLabel ) + ' (' + taxRate + '%)</span><span>' + money( symbol, calc.taxCents[0] / 100 ) + '</span></div>' : '' ) +
				( tax2Rate ? '<div><span>' + escapeHtml( tax2Label ) + ' (' + tax2Rate + '%)</span><span>' + money( symbol, calc.taxCents[1] / 100 ) + '</span></div>' : '' ) +
				( tip ? '<div><span>Tip</span><span>' + money( symbol, tip ) + '</span></div>' : '' ) +
				'<div class="rf-receipt-total-final"><span>Total</span><span>' + money( symbol, total ) + '</span></div>';

			if ( paid !== null ) {
				var diffCents = totalCents - toCents( paid );
				totalsHtml += '<div><span>Amount Paid</span><span>' + money( symbol, paid ) + '</span></div>';
				if ( diffCents < 0 ) {
					totalsHtml += '<div class="rf-receipt-balance"><span>Change / Credit</span><span>' + money( symbol, -diffCents / 100 ) + '</span></div>';
				} else {
					totalsHtml += '<div class="rf-receipt-balance"><span>Balance Due</span><span>' + money( symbol, diffCents / 100 ) + '</span></div>';
				}
			}

			var wordsAmount = paid !== null ? paid : total;
			var wordsHtml = ( cfg.words && wordsAmount > 0 ) ?
				'<p class="rf-receipt-words"><strong>Amount received in words:</strong> ' + escapeHtml( amountInWords( wordsAmount, currency ) ) + '</p>' : '';

			var statementHtml = ( statement && statement.checked ) ?
				'<p class="rf-receipt-statement">' + escapeHtml( statement.dataset.text ) + '</p>' : '';

			var signatureHtml = [ cfg.signature, cfg.signature2 ].filter( Boolean ).map( function ( label ) {
				return '<div class="rf-receipt-signature"><span class="rf-sig-line"></span><span>' + escapeHtml( label ) + '</span></div>';
			} ).join( '' );
			if ( cfg.signature && cfg.signature2 ) {
				signatureHtml = '<div class="rf-receipt-signatures">' + signatureHtml + '</div>';
			}

			var qrHtml = '';
			if ( qrToggle && qrToggle.checked ) {
				var verifyLink = verifyBase + '#r=' + buildPayload( {
					title: cfg.title,
					business: document.getElementById( 'rf-business-name' ).value.trim(),
					client: client,
					number: number,
					date: date,
					currency: currency,
					items: items.map( function ( it ) {
						return { name: it.name, qty: it.qty, priceCents: toCents( it.price ) };
					} ),
					totalCents: totalCents,
					paidCents: paid === null ? null : toCents( paid ),
					payMethod: payMethod,
					extras: extras
				} );
				var qrImg = qrDataUrl( verifyLink );
				if ( qrImg ) {
					qrHtml = '<div class="rf-receipt-qr"><img src="' + qrImg + '" width="112" height="112" alt="Verification QR code"><span>Scan to verify this receipt<br>' + escapeHtml( verifyBase.replace( /^https?:\/\//, '' ) ) + '</span></div>';
				}
			}

			preview.innerHTML = wrapDesign( design,
				'<div class="rf-receipt-head">' +
				( state.logoDataUrl ? '<img src="' + state.logoDataUrl + '" class="rf-receipt-logo" alt="">' : '' ) +
				'<div><h3>' + escapeHtml( business ) + '</h3><p class="rf-receipt-label">' + escapeHtml( cfg.title ) + '</p></div>' +
				'</div>' +
				'<div class="rf-receipt-meta">' + meta.join( '' ) + '</div>' +
				'<table class="rf-receipt-table"><thead><tr><th>Description</th><th class="rf-num">Qty</th><th class="rf-num">Price</th><th class="rf-num">Total</th></tr></thead><tbody>' +
				( rowsHtml || '<tr><td colspan="4" class="rf-empty">Add items on the left to see them here</td></tr>' ) +
				'</tbody></table>' +
				'<div class="rf-receipt-totals">' + totalsHtml + '</div>' +
				wordsHtml +
				statementHtml +
				( notes ? '<div class="rf-receipt-notes">' + escapeHtml( notes ) + '</div>' : '' ) +
				signatureHtml +
				qrHtml +
				'<p class="rf-receipt-footer">Generated with ' + escapeHtml( ( window.rfReceipt && window.rfReceipt.brand ) || 'ReceiptGenPro' ) + '</p>' );

			if ( logoRemove ) {
				logoRemove.style.display = state.logoDataUrl ? '' : 'none';
			}
			saveForm();

			renderChecker( {
				business: !! document.getElementById( 'rf-business-name' ).value.trim(),
				client: !! client,
				date: !! dateInput.value,
				items: items.some( function ( it ) {
					return it.name && it.name !== 'Item';
				} ),
				total: totalCents > 0,
				words: cfg.words ? true : null,
				signature: ( cfg.signature || cfg.signature2 ) ? true : null,
				statement: statement ? statement.checked : null,
				x: extraFacts(),
				amount: total,
				cash: payMethod === 'Cash'
			} );

			// Screen readers hear only the new total, once typing pauses,
			// instead of the whole receipt on every keystroke.
			if ( totalLive ) {
				var totalText = 'Total: ' + money( symbol, total );
				clearTimeout( totalLiveTimer );
				totalLiveTimer = setTimeout( function () {
					if ( totalLive.textContent !== totalText ) {
						totalLive.textContent = totalText;
					}
				}, 800 );
			}
		}

		renderReceipt();

		// ---- Export helpers ----------------------------------------------------
		var EXPORT_WIDTH = 800; // px; at scale 2 every export is 1600 px wide.

		// Renders a copy of the preview at a fixed desktop width, off screen, so a
		// receipt made on a 375 px phone is as sharp as one made on a laptop.
		function renderCanvas() {
			var width = DESIGN_WIDTH[ design ] || EXPORT_WIDTH;
			var clone = preview.cloneNode( true );
			clone.removeAttribute( 'id' );
			clone.setAttribute( 'aria-hidden', 'true' );
			clone.style.cssText = 'position:absolute;left:-10000px;top:0;width:' + width + 'px;max-width:none;box-sizing:border-box;box-shadow:none;margin:0;';
			document.body.appendChild( clone );
			return window.html2canvas( clone, {
				scale: 2,
				backgroundColor: '#ffffff',
				width: width,
				windowWidth: Math.max( 1200, document.documentElement.clientWidth ),
				scrollX: 0,
				scrollY: 0
			} ).then( function ( canvas ) {
				clone.remove();
				return canvas;
			}, function ( err ) {
				clone.remove();
				throw err;
			} );
		}

		function withCanvas( needPdf, btn, cb ) {
			var original = btn.textContent;
			btn.disabled = true;
			btn.textContent = 'Preparing…';
			ensureLibs( needPdf )
				.then( function () {
					return renderCanvas()
						.then( cb )
						.then( rememberNumber )
						.catch( function ( err ) {
							if ( window.console ) {
								window.console.error( err );
							}
							window.alert( 'Sorry, the receipt could not be exported. Please try again, or try another format.' );
						} );
				}, function () {
					window.alert( 'The export library could not be loaded. Please check your connection and try again.' );
				} )
				.then( function () {
					btn.disabled = false;
					btn.textContent = original;
				} );
		}

		// receipt-<number>.<ext>, keeping only filename-safe characters.
		function fileName( ext ) {
			var num = document.getElementById( 'rf-receipt-number' ).value.trim().replace( /[^A-Za-z0-9_-]+/g, '-' ).replace( /^-+|-+$/g, '' );
			return 'receipt' + ( num ? '-' + num : '' ) + '.' + ext;
		}

		function download( href, filename ) {
			var link = document.createElement( 'a' );
			link.download = filename;
			link.href = href;
			link.target = '_self'; // the /embed/ page sets <base target="_blank">
			document.body.appendChild( link );
			link.click();
			link.remove();
		}

		var pngBtn = document.getElementById( 'rf-download-png' );
		if ( pngBtn ) {
			pngBtn.addEventListener( 'click', function () {
				withCanvas( false, pngBtn, function ( canvas ) {
					download( canvas.toDataURL( 'image/png' ), fileName( 'png' ) );
				} );
			} );
		}

		var jpgBtn = document.getElementById( 'rf-download-jpg' );
		if ( jpgBtn ) {
			jpgBtn.addEventListener( 'click', function () {
				withCanvas( false, jpgBtn, function ( canvas ) {
					download( canvas.toDataURL( 'image/jpeg', 0.92 ), fileName( 'jpg' ) );
				} );
			} );
		}

		var pdfBtn = document.getElementById( 'rf-download-pdf' );
		if ( pdfBtn ) {
			pdfBtn.addEventListener( 'click', function () {
				withCanvas( true, pdfBtn, function ( canvas ) {
					// JPEG inside the PDF keeps files ~10x smaller than PNG (was ~5 MB).
					var imgData   = canvas.toDataURL( 'image/jpeg', 0.92 );
					var jsPDF     = window.jspdf.jsPDF;
					if ( design === 'thermal' ) {
						// A real 80 mm till roll: 72 mm printable, page as long as the slip.
						var slipHeight = ( canvas.height * 72 ) / canvas.width;
						var slip = new jsPDF( { orientation: 'portrait', unit: 'mm', format: [ 80, slipHeight + 8 ] } );
						slip.addImage( imgData, 'JPEG', 4, 4, 72, slipHeight );
						slip.save( fileName( 'pdf' ) );
						return;
					}
					var pdfWidth  = 190; // A4 width minus 10 mm margins
					var pdfHeight = ( canvas.height * pdfWidth ) / canvas.width;
					if ( pdfHeight > 277 ) { // keep aspect ratio on very long receipts
						pdfHeight = 277;
						pdfWidth  = ( canvas.width * pdfHeight ) / canvas.height;
					}
					var pdf       = new jsPDF( { orientation: 'portrait', unit: 'mm', format: 'a4' } );
					pdf.addImage( imgData, 'JPEG', ( 210 - pdfWidth ) / 2, 10, pdfWidth, pdfHeight );
					pdf.save( fileName( 'pdf' ) );
				} );
			} );
		}
	}
} )();
