/**
 * ReceiptGenPro — Receipt Generator (v1.3.3)
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
 *
 * v1.3.3 (audit of 20 Oct, Phase 3): amounts capped at 999,999,999,999.99
 * and rates at 100 % (no more "Infinity"), a note under the form for
 * negative or capped entries and a discount larger than the subtotal,
 * unit prices rounded to cents before × quantity, thousands separators,
 * singular currency words, a QR payload kept under ~500 characters (and
 * without tax IDs, with discount / taxes / tip) drawn at whole px per
 * module, long A4 PDFs split into pages, a canvas scale that stays under
 * iOS's limit, logos shrunk to 400 px, values carried over between
 * receipt types, ?type= in the address bar, bidi controls stripped, and
 * checker limits applied only in their own currency (HRA PAN per month).
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
			url: 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/4.2.1/jspdf.umd.min.js',
			integrity: 'sha512-plOdviVmws4Y3JAvbnpfKb2hVxKM1lCwsi3vmElYRj+tiDLffZ4FVUj5a8vyKJ9pIgl8JCAHEJ4D1iUKBecswg=='
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
	// "€ — Euro" → "€" (the currency menu label, from rf_currencies()).
	function currencySymbol( label ) {
		return String( label ).split( ' — ' )[0];
	}

	// All totals are built from amounts rounded to whole cents, so the figures
	// printed on the receipt always add up (subtotal + tax + tip = total).
	function toCents( n ) {
		// toFixed(6) strips float noise first (1.005 * 100 = 100.49999…).
		return Math.round( parseFloat( ( n * 100 ).toFixed( 6 ) ) );
	}

	// Highest amount and rate the form accepts (also the inputs' HTML max).
	// Capping keeps every figure printable: 1e21 or 1e400 printed as
	// "999999999999999868928.00" or "Infinity" before.
	var MAX_AMOUNT = 999999999999.99;
	var MAX_CENTS  = 99999999999999;
	var MAX_RATE   = 100;

	// Parse a number field; empty, invalid, non-finite or negative input
	// counts as 0 (or `fallback` when the field is empty or invalid).
	function nonNegative( value, fallback ) {
		var n = parseFloat( value );
		if ( isNaN( n ) || ! isFinite( n ) ) {
			n = fallback || 0;
		}
		return n > 0 ? n : 0;
	}

	// nonNegative() limited to `max`.
	function capped( value, max, fallback ) {
		return Math.min( nonNegative( value, fallback ), max );
	}

	// A tax or discount rate for display: 8.875 → "8.875", 5 → "5".
	function formatRate( rate ) {
		return String( parseFloat( Number( rate ).toFixed( 4 ) ) );
	}

	// One line's total in cents. The unit price is rounded to cents first,
	// so the printed price × quantity is what the line shows (3 × 0.33).
	function lineCents( qty, price ) {
		return Math.min( toCents( qty * ( toCents( price ) / 100 ) ), MAX_CENTS );
	}

	// Subtotal → discount → taxes → tip → total, all in whole cents.
	// discountType is 'percent' or 'amount'; a discount never exceeds the
	// subtotal (discountClamped says when it was cut), and every tax is
	// charged on the discounted amount.
	function calcTotals( subtotalCents, discount, discountType, taxRates, tipCents ) {
		var discountCents = discountType === 'percent' ?
			toCents( subtotalCents * Math.min( discount, 100 ) / 10000 ) :
			toCents( discount );
		var discountClamped = discountCents > subtotalCents;
		discountCents = Math.min( discountCents, subtotalCents );
		var taxableCents = subtotalCents - discountCents;
		var taxCents = taxRates.map( function ( rate ) {
			return toCents( taxableCents * rate / 10000 );
		} );
		var totalCents = taxableCents + tipCents + taxCents.reduce( function ( a, b ) {
			return a + b;
		}, 0 );
		return { discountCents: discountCents, discountClamped: discountClamped, taxCents: taxCents, totalCents: totalCents };
	}

	// 1234567.89 → "1,234,567.89"; INR and PKR group in lakh and crore
	// ("12,34,567.89").
	var INDIAN_GROUPING = { INR: true, PKR: true };
	var amountFormats = {};
	function formatAmount( cents, code ) {
		var locale = INDIAN_GROUPING[ code ] ? 'en-IN' : 'en-US';
		if ( ! amountFormats[ locale ] ) {
			amountFormats[ locale ] = new Intl.NumberFormat( locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 } );
		}
		return amountFormats[ locale ].format( cents / 100 );
	}

	// Bidirectional overrides (U+202A–202E, U+2066–2069) can make a printed
	// "1000" read as "0001", so they never reach the receipt.
	function stripBidi( str ) {
		return String( str === undefined || str === null ? '' : str ).replace( /[‪-‮⁦-⁩]/g, '' );
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

	// [ plural, plural of the cent, singular, singular of the cent ]
	var CURRENCY_WORDS = {
		USD: [ 'US dollars', 'cents', 'US dollar', 'cent' ], EUR: [ 'euros', 'cents', 'euro', 'cent' ],
		GBP: [ 'pounds', 'pence', 'pound', 'penny' ], PKR: [ 'Pakistani rupees', 'paisa', 'Pakistani rupee', 'paisa' ],
		INR: [ 'Indian rupees', 'paise', 'Indian rupee', 'paisa' ], CAD: [ 'Canadian dollars', 'cents', 'Canadian dollar', 'cent' ],
		AUD: [ 'Australian dollars', 'cents', 'Australian dollar', 'cent' ], AED: [ 'UAE dirhams', 'fils', 'UAE dirham', 'fils' ],
		SAR: [ 'Saudi riyals', 'halalas', 'Saudi riyal', 'halala' ]
	};

	function amountInWords( amount, code ) {
		var names = CURRENCY_WORDS[ code ] || [ code, 'cents', code, 'cent' ];
		// Split in integer cents so 2.999 becomes "three … only", never "one hundred cents".
		var c     = toCents( amount );
		var whole = Math.floor( c / 100 );
		var cents = c % 100;
		var toWords = INDIAN_GROUPING[ code ] ? intToWordsIndian : intToWords;
		var parts = [];
		// "Fifty cents only", not "Zero US dollars and fifty cents only".
		if ( whole || ! cents ) {
			parts.push( toWords( whole ) + ' ' + names[ whole === 1 ? 2 : 0 ] );
		}
		if ( cents ) {
			parts.push( toWords( cents ) + ' ' + names[ cents === 1 ? 3 : 1 ] );
		}
		var text = parts.join( ' and ' );
		return text.charAt( 0 ).toUpperCase() + text.slice( 1 ) + ' only';
	}

	// ---- Requirement checks (rules: inc/requirements.php) -----------------
	// facts: { business, client, date, items, total, words, signature,
	//          statement: true | false | null (null = not on this template),
	//          x: { <extra field id>: true | false }, amount, cash,
	//          currency, months (rent months the receipt covers, default 1) }
	// `currency` is the one the purpose's amounts are in (default USD).
	// Returns one { label, status, optional } per check, where status is
	// ok | missing | nofield | manual | na | currency (an amount limit in
	// another currency than the receipt's, which the visitor must judge).
	function factFor( facts, key ) {
		if ( key.indexOf( 'x:' ) === 0 ) {
			var id = key.slice( 2 );
			return Object.prototype.hasOwnProperty.call( facts.x || {}, id ) ? !! facts.x[ id ] : null;
		}
		return facts[ key ] === undefined ? null : facts[ key ];
	}

	function evaluateChecks( checks, facts, currency ) {
		currency = currency || 'USD';
		return ( checks || [] ).map( function ( c ) {
			var out = { label: c.label, optional: !! c.optional, status: 'ok' };
			var when = c.when || {};
			if ( when.total_over !== undefined && facts.currency && facts.currency !== currency ) {
				out.status = 'currency';
				out.currency = currency;
				return out;
			}
			// A monthly limit is compared with the amount per month.
			var amount = when.per_month ? facts.amount / Math.max( 1, facts.months || 1 ) : facts.amount;
			if ( ( when.total_over !== undefined && ! ( amount > when.total_over ) ) || ( when.cash && ! facts.cash ) ) {
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
	// Rent months between two YYYY-MM-DD dates (1 Jan – 31 Mar = 3), at least
	// 1; 1 when either date is missing.
	function monthsBetween( from, to ) {
		var a = Date.parse( from );
		var b = Date.parse( to );
		if ( isNaN( a ) || isNaN( b ) || b < a ) {
			return 1;
		}
		return Math.max( 1, Math.round( ( ( b - a ) / 86400000 + 1 ) / 30.44 ) );
	}

	// Cuts by code point, so an emoji or a CJK surrogate pair is never split.
	function clip( str, max ) {
		var chars = Array.from( stripBidi( str ) );
		return chars.length > max ? chars.slice( 0, max - 1 ).join( '' ) + '…' : chars.join( '' );
	}

	function b64urlEncode( str ) {
		var bytes = new TextEncoder().encode( str );
		var bin = '';
		for ( var i = 0; i < bytes.length; i++ ) {
			bin += String.fromCharCode( bytes[ i ] );
		}
		return btoa( bin ).replace( /\+/g, '-' ).replace( /\//g, '_' ).replace( /=+$/, '' );
	}

	// Characters of encoded payload (≈375 bytes of JSON). With the link
	// around it the code stays near version 15 (77 modules), which prints
	// sharp at 2 px a module; 1200 gave 145 modules for Hindi or CJK text.
	var QR_MAX = 500;

	// Tax IDs (PAN, EIN, SSN) are left out of the code: anyone who scans a
	// receipt could read them.
	var TAX_ID_FIELDS = { landlord_pan: true, ein: true, provider_tax_id: true, tax_id: true };

	// r.extras are [ label, value, field id ]; r.taxes [ { label, rate, cents } ].
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
			x: ( r.extras || [] ).filter( function ( e ) {
				return ! TAX_ID_FIELDS[ e[2] ];
			} ).map( function ( e ) {
				return [ clip( e[0], 40 ), clip( e[1], 80 ) ];
			} )
		};
		if ( r.discountCents ) {
			p.dc = r.discountCents;
		}
		var taxes = ( r.taxes || [] ).filter( function ( t ) {
			return t.rate;
		} );
		if ( taxes.length ) {
			p.tx = taxes.map( function ( t ) {
				return [ clip( t.label, 16 ), formatRate( t.rate ), t.cents ];
			} );
		}
		if ( r.tipCents ) {
			p.tp = r.tipCents;
		}
		if ( r.paidCents !== null && r.paidCents !== undefined ) {
			p.pd = r.paidCents;
		}

		// Over the limit: shorten the texts, then drop extra fields, then
		// shorten item names, then drop items from the end (p.more tells the
		// verify page that not every line fit) down to one, then shorten
		// the business and customer names and drop the last item. Each step runs only while
		// the code is still too long.
		var steps = [
			function () {
				p.t = clip( p.t, 24 );
				p.b = clip( p.b, 40 );
				p.c = clip( p.c, 40 );
				p.n = clip( p.n, 20 );
			},
			function () {
				p.x = p.x.map( function ( e ) {
					return [ clip( e[0], 20 ), clip( e[1], 30 ) ];
				} );
			},
			function () {
				return p.x.length ? !! p.x.pop() : false;
			},
			function () {
				p.i = p.i.map( function ( it ) {
					return [ clip( it[0], 24 ), it[1], it[2] ];
				} );
			},
			function () {
				p.i = p.i.map( function ( it ) {
					return [ clip( it[0], 12 ), it[1], it[2] ];
				} );
			},
			function () {
				if ( p.i.length <= 1 ) {
					return false;
				}
				p.i.pop();
				p.more = 1;
				return true;
			},
			function () {
				p.t = clip( p.t, 16 );
				p.b = clip( p.b, 20 );
				p.c = clip( p.c, 20 );
			},
			function () {
				if ( ! p.i.length ) {
					return false;
				}
				p.i.pop();
				p.more = 1;
				return true;
			}
		];
		var enc = b64urlEncode( JSON.stringify( p ) );
		for ( var s = 0; s < steps.length && enc.length > QR_MAX; s++ ) {
			var again;
			do {
				again = steps[ s ]();
				enc = b64urlEncode( JSON.stringify( p ) );
			} while ( again && enc.length > QR_MAX );
		}
		return enc;
	}

	// Display size of a QR code: a whole number of px per module, at least 2
	// and at least 112 px in all, so a printed code stays scannable.
	function qrPixels( modules ) {
		return modules * Math.max( 2, Math.ceil( 112 / modules ) );
	}

	// ---- Receipt type fields (homepage picker) -----------------------------
	// The same markup generator-embed.php renders for a template's extra
	// fields, tip / amount paid and statement, built from its form config.
	function esc( str ) {
		return stripBidi( str )
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
				( f.tip ? '<div class="rf-field"><label for="rf-tip">Tip / Gratuity (amount)</label><input type="number" id="rf-tip" min="0" max="' + MAX_AMOUNT + '" step="0.01" placeholder="0.00"></div>' : '' ) +
				( f.paid ? '<div class="rf-field"><label for="rf-amount-paid">Amount Paid (leave blank if paid in full)</label><input type="number" id="rf-amount-paid" min="0" max="' + MAX_AMOUNT + '" step="0.01" placeholder="0.00"></div>' : '' ) +
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
			'<div class="rf-copy-cut" aria-hidden="true"><span>&#9986; cut here</span></div>' +
			// Screen readers read the receipt once; the copy is for paper.
			'<div class="rf-receipt-inner" aria-hidden="true"><p class="rf-copy-label">Customer copy</p>' + body + '</div>';
	}

	// Splits a canvas `total` px tall into pages of at most `page` px:
	// [ [ from, to ], … ]. Each page ends at the last row break in its lower
	// half, or at the page edge when there is none.
	function pageSlices( total, page, breaks ) {
		var out = [];
		var start = 0;
		while ( total - start > page ) {
			var end = start + page;
			var cut = 0;
			( breaks || [] ).forEach( function ( b ) {
				if ( b > start + page / 2 && b <= end ) {
					cut = b;
				}
			} );
			cut = cut || end;
			out.push( [ start, cut ] );
			start = cut;
		}
		out.push( [ start, total ] );
		return out;
	}

	// Only an image data URL from the logo upload (or its saved copy) is
	// ever put on the receipt; anything else in sessionStorage is dropped.
	var LOGO_RE = /^data:image\/(png|jpeg|gif|webp);base64,[A-Za-z0-9+\/=]+$/;
	function isLogoDataUrl( url ) {
		return typeof url === 'string' && LOGO_RE.test( url );
	}

	// Lets a Node check exercise the pure helpers without a browser.
	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = {
			currencySymbol: currencySymbol,
			amountInWords: amountInWords,
			intToWords: intToWords,
			intToWordsIndian: intToWordsIndian,
			toCents: toCents,
			nonNegative: nonNegative,
			capped: capped,
			formatRate: formatRate,
			formatAmount: formatAmount,
			lineCents: lineCents,
			stripBidi: stripBidi,
			monthsBetween: monthsBetween,
			clip: clip,
			qrPixels: qrPixels,
			pageSlices: pageSlices,
			MAX_AMOUNT: MAX_AMOUNT,
			QR_MAX: QR_MAX,
			calcTotals: calcTotals,
			nextReceiptNumber: nextReceiptNumber,
			isUsedNumber: isUsedNumber,
			evaluateChecks: evaluateChecks,
			buildPayload: buildPayload,
			b64urlEncode: b64urlEncode,
			typeFieldsHtml: typeFieldsHtml,
			wrapDesign: wrapDesign,
			isLogoDataUrl: isLogoDataUrl,
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

		// Says a short message once in #rf-total-live (a type switch); the
		// next total update follows it.
		function announce( text ) {
			if ( ! totalLive ) {
				return;
			}
			clearTimeout( totalLiveTimer );
			totalLive.textContent = text;
		}

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
		var qrCache    = { text: '', url: '', size: 112 };

		// Re-encoding on every keystroke is slow on phones: while typing,
		// the code is redrawn at most every QR_WAIT ms (the last one always
		// lands). An export redraws it first (flushQr).
		var QR_WAIT  = 300;
		var qrLastAt = 0;
		var qrTimer  = null;
		var qrForce  = false;

		function qrDataUrl( text ) {
			if ( typeof window.qrcode !== 'function' ) {
				ensureQrLib();
				return null;
			}
			if ( qrCache.text !== text && qrCache.text && ! qrForce && Date.now() - qrLastAt < QR_WAIT ) {
				if ( ! qrTimer ) {
					qrTimer = setTimeout( function () {
						qrTimer = null;
						renderReceipt();
					}, QR_WAIT );
				}
				return qrCache;
			}
			if ( qrCache.text !== text ) {
				qrLastAt = Date.now();
				var qr = window.qrcode( 0, 'L' );
				qr.addData( text, 'Byte' );
				qr.make();
				// 4 px per module, shown at a whole number of px per module.
				qrCache = { text: text, url: qr.createDataURL( 4, 0 ), size: qrPixels( qr.getModuleCount() ) };
			}
			return qrCache;
		}

		function flushQr() {
			clearTimeout( qrTimer );
			qrTimer = null;
			qrForce = true;
			renderReceipt();
			qrForce = false;
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
		var checkerStatus = document.getElementById( 'rf-checker-status' );
		var checkerTimer  = null;
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
			na: 'Not needed for this amount',
			currency: 'The limit is in %s, so check this yourself'
		};

		function renderChecker( facts ) {
			lastFacts = facts;
			if ( ! purposeSel || ! checkerResult || ! facts ) {
				return;
			}
			var p = purposes[ purposeSel.value ];
			if ( ! p ) {
				checkerResult.innerHTML = '';
				setCheckerStatus( '' );
				return;
			}
			var results = evaluateChecks( p.checks, facts, p.currency );
			var needed  = results.filter( function ( r ) {
				return ! r.optional && ( r.status === 'ok' || r.status === 'missing' || r.status === 'nofield' );
			} );
			var done = needed.filter( function ( r ) {
				return r.status === 'ok';
			} ).length;
			var onOtherTemplate = p.templateUrl && p.templateUrl.replace( /\/$/, '' ) !== ( window.location.origin + window.location.pathname ).replace( /\/$/, '' );
			var summary = done === needed.length ? 'Everything required is on the receipt.' : done + ' of ' + needed.length + ' required details are on the receipt.';
			var html = '<p class="rf-checker-summary' + ( done === needed.length ? ' rf-checker-all-done' : '' ) + '">' + summary + '</p><ul>';
			results.forEach( function ( r ) {
				var status = r.status === 'missing' && r.optional ? 'optional' : r.status;
				var note = r.status === 'missing' && r.optional ? 'Recommended' : STATUS_TEXT[ r.status ].replace( '%s', r.currency || '' );
				html += '<li class="rf-check-' + ( status === 'currency' ? 'manual' : status ) + '"><span class="rf-check-label">' + escapeHtml( r.label ) + '</span>' +
					( note ? '<span class="rf-check-note">' + escapeHtml( note ) + '</span>' : '' ) + '</li>';
			} );
			html += '</ul>';
			if ( onOtherTemplate && results.some( function ( r ) {
				return r.status === 'nofield' && ! r.optional;
			} ) ) {
				html += '<p class="rf-checker-switch">The <a href="' + escapeHtml( p.templateUrl ) + '?purpose=' + encodeURIComponent( purposeSel.value ) + '#rf-generator">' + escapeHtml( p.templateName || 'matching template' ) + '</a> has every field this needs.</p>';
			}
			checkerResult.innerHTML = html;
			setCheckerStatus( summary );
		}

		// The summary is read out once typing pauses, only when it changes.
		function setCheckerStatus( text ) {
			if ( ! checkerStatus ) {
				return;
			}
			clearTimeout( checkerTimer );
			checkerTimer = setTimeout( function () {
				if ( checkerStatus.textContent !== text ) {
					checkerStatus.textContent = text;
				}
			}, 800 );
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

			// Values typed into fields both types have (Property Address,
			// Amount Paid, Tip…) carry over to the new type's fields.
			var carry = {};
			form.querySelectorAll( '#rf-type-extra [id], #rf-type-tippaid [id]' ).forEach( function ( el ) {
				if ( el.value ) {
					carry[ el.id ] = el.value;
				}
			} );
			var parts = typeFieldsHtml( f );
			[ 'extra', 'tippaid', 'statement' ].forEach( function ( key ) {
				var box = document.getElementById( 'rf-type-' + key );
				if ( box ) {
					box.innerHTML = parts[ key ];
				}
			} );
			Object.keys( carry ).forEach( function ( id ) {
				var el = document.getElementById( id );
				if ( ! el ) {
					return;
				}
				var before = el.value;
				el.value = carry[ id ];
				if ( el.tagName === 'SELECT' && el.value !== carry[ id ] ) {
					el.value = before; // not one of this type's options
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

		// ?type=<slug> in the address bar, so a reload or a shared link opens
		// the same receipt type.
		function rememberType( slug ) {
			if ( ! window.history || ! window.history.replaceState ) {
				return;
			}
			var search = window.location.search.replace( /([?&])type=[^&#]*&?/, '$1' ).replace( /[?&]$/, '' );
			if ( slug !== 'general' ) {
				search += ( search ? '&' : '?' ) + 'type=' + encodeURIComponent( slug );
			}
			try {
				window.history.replaceState( window.history.state, '', window.location.pathname + search + window.location.hash );
			} catch ( e ) {}
		}

		typeLinks.forEach( function ( a ) {
			a.addEventListener( 'click', function ( e ) {
				if ( ! typeData || e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1 ) {
					return; // no data, or opening in a new tab: follow the link
				}
				e.preventDefault();
				if ( applyType( a.dataset.type ) ) {
					renderReceipt();
					rememberType( a.dataset.type );
					announce( 'Form switched to ' + ( ( typeData[ a.dataset.type ] && typeData[ a.dataset.type ].label ) || 'this receipt type' ) + '.' );
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

		// Printing (Ctrl+P): main.css prints only the receipt on A4. The
		// Thermal Slip prints on an 80 mm page as long as the slip.
		var printPage = null;
		window.addEventListener( 'beforeprint', function () {
			if ( design !== 'thermal' ) {
				return;
			}
			// The slip is 360 px wide on screen and 72 mm (272 px) on paper,
			// so its text wraps a little more when printed.
			var mm = Math.ceil( preview.offsetHeight * 25.4 / 96 * 1.15 ) + 10;
			printPage = document.createElement( 'style' );
			printPage.textContent = '@page { size: 80mm ' + mm + 'mm; margin: 4mm; }';
			document.head.appendChild( printPage );
		} );
		window.addEventListener( 'afterprint', function () {
			if ( printPage ) {
				printPage.remove();
				printPage = null;
			}
		} );

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
			price.max = String( MAX_AMOUNT );
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
				labelRows();
				renderReceipt();
			} );
			labelRows();
		}

		// "Item 2 price" etc., so a screen reader knows which row a field is in.
		function labelRows() {
			itemsList.querySelectorAll( '.rf-item-row' ).forEach( function ( row, i ) {
				var n = i + 1;
				row.querySelector( '.rf-item-name' ).setAttribute( 'aria-label', 'Item ' + n + ' description' );
				row.querySelector( '.rf-item-qty' ).setAttribute( 'aria-label', 'Item ' + n + ' quantity' );
				row.querySelector( '.rf-item-price' ).setAttribute( 'aria-label', 'Item ' + n + ' price' );
				row.querySelector( '.rf-item-remove' ).setAttribute( 'aria-label', 'Remove item ' + n );
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
					shrinkLogo( ev.target.result, function ( url ) {
						state.logoDataUrl = isLogoDataUrl( url ) ? url : null;
						renderReceipt();
					} );
				};
				reader.readAsDataURL( file );
			} );
		}

		// A phone photo can be 12 MB: keep at most 400 px on the longer side
		// (the receipt shows it at 48 px), so it fits in sessionStorage and
		// the export stays light. PNG keeps transparency.
		var LOGO_MAX = 400;
		function shrinkLogo( dataUrl, done ) {
			var img = new Image();
			img.onload = function () {
				var w = img.naturalWidth;
				var h = img.naturalHeight;
				var k = Math.min( 1, LOGO_MAX / Math.max( w, h, 1 ) );
				// Other types (an SVG…) are redrawn as PNG, even at full size.
				if ( ( k === 1 && isLogoDataUrl( dataUrl ) ) || ! w || ! h ) {
					done( dataUrl );
					return;
				}
				try {
					var c = document.createElement( 'canvas' );
					c.width = Math.round( w * k );
					c.height = Math.round( h * k );
					c.getContext( '2d' ).drawImage( img, 0, 0, c.width, c.height );
					done( c.toDataURL( 'image/png' ) );
				} catch ( e ) {
					done( dataUrl );
				}
			};
			img.onerror = function () {
				done( dataUrl );
			};
			img.src = dataUrl;
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

		// Saved once typing pauses, not on every keystroke.
		var saveTimer = null;
		function saveFormSoon() {
			clearTimeout( saveTimer );
			saveTimer = setTimeout( saveForm, 400 );
		}
		window.addEventListener( 'pagehide', function () {
			if ( saveTimer ) {
				clearTimeout( saveTimer );
				saveForm();
			}
		} );

		function saveForm() {
			saveTimer = null;
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
			if ( isLogoDataUrl( data.logo ) ) {
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

		var urlType = ( window.location.search.match( /[?&]type=([a-z0-9-]+)/ ) || [] )[1];
		if ( urlType && urlType !== currentType ) {
			applyType( urlType );
		}

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

		// The menu labels ("€ — Euro", from rf_currencies()) carry the symbol.
		function getCurrencySymbol( code ) {
			return currencies[ code ] ? currencySymbol( currencies[ code ] ) : code + ' ';
		}

		function escapeHtml( str ) {
			var div = document.createElement( 'div' );
			div.textContent = stripBidi( str );
			return div.innerHTML;
		}

		function fieldValue( id ) {
			var el = document.getElementById( id );
			return el ? el.value.trim() : '';
		}

		function money( symbol, n ) {
			return symbol + formatAmount( toCents( n ), currencySel.value );
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

		// Notes under the form when an entry was changed: a negative number,
		// an amount over the maximum, a rate over 100 %, a cut discount.
		var formNotice = document.getElementById( 'rf-form-notice' );
		if ( ! formNotice ) {
			formNotice = document.createElement( 'p' );
			formNotice.id = 'rf-form-notice';
			formNotice.className = 'rf-form-notice';
			formNotice.hidden = true;
			var moreBox = document.getElementById( 'rf-more-options' );
			form.insertBefore( formNotice, moreBox || addItemBtn.nextSibling );
		}

		function isNegative( value ) {
			return parseFloat( value ) < 0;
		}

		function isOver( value, max ) {
			var n = parseFloat( value );
			return n > max || n === Infinity;
		}

		function collectItems( notes ) {
			var rows = itemsList.querySelectorAll( '.rf-item-row' );
			var items = [];
			rows.forEach( function ( row ) {
				var name     = row.querySelector( '.rf-item-name' ).value.trim();
				var qtyRaw   = row.querySelector( '.rf-item-qty' ).value;
				var priceRaw = row.querySelector( '.rf-item-price' ).value;
				var qty      = nonNegative( qtyRaw, 1 );
				var price    = toCents( capped( priceRaw, MAX_AMOUNT ) ) / 100;
				if ( isNegative( qtyRaw ) || isNegative( priceRaw ) ) {
					notes.negative = true;
				}
				if ( isOver( priceRaw, MAX_AMOUNT ) || toCents( qty * price ) > MAX_CENTS ) {
					notes.max = true;
				}
				if ( name || price ) {
					items.push( { name: name || 'Item', qty: qty, price: price, lineCents: lineCents( qty, price ) } );
				}
			} );
			return items;
		}

		function showNotices( notes, symbol ) {
			var text = [];
			if ( notes.negative ) {
				text.push( 'Negative numbers count as 0. For a refund or a reduction, use the discount.' );
			}
			if ( notes.max ) {
				text.push( 'Amounts are limited to ' + money( symbol, MAX_AMOUNT ) + '.' );
			}
			if ( notes.rate ) {
				text.push( 'Tax and discount rates are limited to 100%.' );
			}
			if ( notes.discount ) {
				text.push( 'The discount is more than the subtotal, so only ' + money( symbol, notes.discount ) + ' is taken off.' );
			}
			var html = text.join( ' ' );
			if ( formNotice.textContent !== html ) {
				formNotice.textContent = html;
			}
			formNotice.hidden = ! html;
		}

		function renderReceipt() {
			var business  = document.getElementById( 'rf-business-name' ).value.trim() || 'Your Business Name';
			var client    = document.getElementById( 'rf-client-name' ).value.trim();
			var number    = document.getElementById( 'rf-receipt-number' ).value.trim() || '1001';
			var date      = dateInput.value || todayLocal();
			var currency  = currencySel.value || 'USD';
			var symbol    = getCurrencySymbol( currency );
			var fix       = {};
			var taxRaw    = fieldValue( 'rf-tax-rate' );
			var tax2Raw   = fieldValue( 'rf-tax2-rate' );
			var discRaw   = fieldValue( 'rf-discount' );
			var discType  = fieldValue( 'rf-discount-type' ) === 'amount' ? 'amount' : 'percent';
			var taxRate   = capped( taxRaw, MAX_RATE );
			var taxLabel  = fieldValue( 'rf-tax-label' ) || 'Tax';
			var tax2Rate  = capped( tax2Raw, MAX_RATE );
			var tax2Label = fieldValue( 'rf-tax2-label' ) || 'Tax 2';
			var discount  = capped( discRaw, discType === 'percent' ? MAX_RATE : MAX_AMOUNT );
			var payMethod = document.getElementById( 'rf-payment-method' ).value;
			var notes     = document.getElementById( 'rf-notes' ).value.trim();
			var items     = collectItems( fix );
			var tipRaw    = tipInput ? tipInput.value : '';
			var tipCents  = toCents( capped( tipRaw, MAX_AMOUNT ) );
			var paidRaw   = paidInput ? paidInput.value.trim() : '';

			[ taxRaw, tax2Raw, discRaw, tipRaw, paidRaw ].forEach( function ( v ) {
				if ( isNegative( v ) ) {
					fix.negative = true;
				}
			} );
			if ( isOver( taxRaw, MAX_RATE ) || isOver( tax2Raw, MAX_RATE ) || ( discType === 'percent' && isOver( discRaw, MAX_RATE ) ) ) {
				fix.rate = true;
			}
			if ( ( discType === 'amount' && isOver( discRaw, MAX_AMOUNT ) ) || isOver( tipRaw, MAX_AMOUNT ) || isOver( paidRaw, MAX_AMOUNT ) ) {
				fix.max = true;
			}

			var subtotalCents = items.reduce( function ( sum, it ) {
				return sum + it.lineCents;
			}, 0 );
			if ( subtotalCents > MAX_CENTS ) {
				subtotalCents = MAX_CENTS;
				fix.max = true;
			}
			var calc       = calcTotals( subtotalCents, discount, discType, [ taxRate, tax2Rate ], tipCents );
			var totalCents = calc.totalCents;
			var subtotal   = subtotalCents / 100;
			var tip        = tipCents / 100;
			var total      = totalCents / 100;
			var paid       = paidRaw === '' ? null : toCents( capped( paidRaw, MAX_AMOUNT ) ) / 100;
			if ( calc.discountClamped && subtotalCents ) {
				fix.discount = calc.discountCents / 100;
			}
			showNotices( fix, symbol );

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
					extras.push( [ label, val, el.id.replace( /^rf-x-/, '' ) ] );
				}
			} );

			var totalsHtml =
				'<div><span>Subtotal</span><span>' + money( symbol, subtotal ) + '</span></div>' +
				( calc.discountCents ? '<div><span>Discount' + ( discType === 'percent' ? ' (' + formatRate( Math.min( discount, 100 ) ) + '%)' : '' ) + '</span><span>-' + money( symbol, calc.discountCents / 100 ) + '</span></div>' : '' ) +
				( taxRate ? '<div><span>' + escapeHtml( taxLabel ) + ' (' + formatRate( taxRate ) + '%)</span><span>' + money( symbol, calc.taxCents[0] / 100 ) + '</span></div>' : '' ) +
				( tax2Rate ? '<div><span>' + escapeHtml( tax2Label ) + ' (' + formatRate( tax2Rate ) + '%)</span><span>' + money( symbol, calc.taxCents[1] / 100 ) + '</span></div>' : '' ) +
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
					discountCents: calc.discountCents,
					taxes: [
						{ label: taxLabel, rate: taxRate, cents: calc.taxCents[0] },
						{ label: tax2Label, rate: tax2Rate, cents: calc.taxCents[1] }
					],
					tipCents: tipCents,
					totalCents: totalCents,
					paidCents: paid === null ? null : toCents( paid ),
					payMethod: payMethod,
					extras: extras
				} );
				var qr = qrDataUrl( verifyLink );
				var qrText = '<span>Scan to verify this receipt<br>' + escapeHtml( verifyBase.replace( /^https?:\/\//, '' ) ) + '</span>';
				// While the QR library loads, an empty box holds its place so
				// the receipt (and an embedding iframe) doesn't jump.
				qrHtml = qr ?
					'<div class="rf-receipt-qr"><img src="' + qr.url + '" width="' + qr.size + '" height="' + qr.size + '" style="width:' + qr.size + 'px;height:' + qr.size + 'px" alt="Verification QR code">' + qrText + '</div>' :
					'<div class="rf-receipt-qr"><span class="rf-qr-pending" aria-hidden="true"></span>' + qrText + '</div>';
			}

			preview.innerHTML = wrapDesign( design,
				'<div class="rf-receipt-head">' +
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

			// The logo is added as an element, never as HTML text.
			if ( isLogoDataUrl( state.logoDataUrl ) ) {
				preview.querySelectorAll( '.rf-receipt-head' ).forEach( function ( head ) {
					var img = document.createElement( 'img' );
					img.className = 'rf-receipt-logo';
					img.alt = '';
					img.src = state.logoDataUrl;
					head.insertBefore( img, head.firstChild );
				} );
			}
			if ( logoRemove ) {
				logoRemove.style.display = state.logoDataUrl ? '' : 'none';
			}
			saveFormSoon();

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
				cash: payMethod === 'Cash',
				currency: currency,
				months: monthsBetween( fieldValue( 'rf-x-period_from' ), fieldValue( 'rf-x-period_to' ) )
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

		// iOS Safari draws nothing on a canvas over ~16.7 million pixels.
		var CANVAS_MAX_PIXELS = 16000000;

		// Renders a copy of the preview at a fixed desktop width, off screen, so a
		// receipt made on a 375 px phone is as sharp as one made on a laptop.
		// canvas.rfBreaks lists the y positions (canvas px) between rows, where
		// a long receipt can be split into PDF pages.
		function renderCanvas() {
			var width = DESIGN_WIDTH[ design ] || EXPORT_WIDTH;
			var clone = preview.cloneNode( true );
			clone.removeAttribute( 'id' );
			clone.setAttribute( 'aria-hidden', 'true' );
			clone.style.cssText = 'position:absolute;left:-10000px;top:0;width:' + width + 'px;max-width:none;box-sizing:border-box;box-shadow:none;margin:0;';
			document.body.appendChild( clone );
			var height = clone.offsetHeight;
			var scale  = Math.min( 2, Math.sqrt( CANVAS_MAX_PIXELS / Math.max( 1, width * height ) ) );
			var top    = clone.getBoundingClientRect().top;
			var breaks = [];
			clone.querySelectorAll( '.rf-receipt-head, .rf-receipt-meta, tr, .rf-receipt-totals > div, .rf-receipt-words, .rf-receipt-statement, .rf-receipt-notes, .rf-receipt-signature, .rf-receipt-qr, .rf-copy-cut, .rf-receipt-inner' ).forEach( function ( el ) {
				breaks.push( Math.round( ( el.getBoundingClientRect().bottom - top ) * scale ) );
			} );
			return window.html2canvas( clone, {
				scale: scale,
				backgroundColor: '#ffffff',
				width: width,
				windowWidth: Math.max( 1200, document.documentElement.clientWidth ),
				scrollX: 0,
				scrollY: 0
			} ).then( function ( canvas ) {
				clone.remove();
				canvas.rfBreaks = breaks.sort( function ( a, b ) {
					return a - b;
				} );
				return canvas;
			}, function ( err ) {
				clone.remove();
				throw err;
			} );
		}

		// The button stays focusable while busy (disabling it would drop
		// keyboard focus); a second click is ignored until the export ends.
		function withCanvas( needPdf, btn, cb ) {
			if ( btn.getAttribute( 'aria-busy' ) === 'true' ) {
				return;
			}
			var original = btn.textContent;
			btn.setAttribute( 'aria-busy', 'true' );
			btn.setAttribute( 'aria-disabled', 'true' );
			btn.textContent = 'Preparing…';
			flushQr();
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
					btn.removeAttribute( 'aria-busy' );
					btn.removeAttribute( 'aria-disabled' );
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
					// A4 at full width (190 mm inside 10 mm margins); a receipt
					// taller than one page (277 mm) continues on the next
					// pages, cut between rows, instead of shrinking.
					var pdf    = new jsPDF( { orientation: 'portrait', unit: 'mm', format: 'a4' } );
					var pageMm = 277;
					var pagePx = Math.floor( canvas.width * pageMm / 190 );
					var slices = pageSlices( canvas.height, pagePx, canvas.rfBreaks || [] );
					slices.forEach( function ( sl, n ) {
						var part = canvas;
						if ( slices.length > 1 ) {
							part = document.createElement( 'canvas' );
							part.width = canvas.width;
							part.height = sl[1] - sl[0];
							var ctx = part.getContext( '2d' );
							ctx.fillStyle = '#ffffff';
							ctx.fillRect( 0, 0, part.width, part.height );
							ctx.drawImage( canvas, 0, sl[0], canvas.width, part.height, 0, 0, canvas.width, part.height );
						}
						if ( n ) {
							pdf.addPage( 'a4', 'portrait' );
						}
						pdf.addImage( slices.length > 1 ? part.toDataURL( 'image/jpeg', 0.92 ) : imgData, 'JPEG', 10, 10, 190, ( part.height * 190 ) / canvas.width );
					} );
					pdf.save( fileName( 'pdf' ) );
				} );
			} );
		}
	}
} )();
