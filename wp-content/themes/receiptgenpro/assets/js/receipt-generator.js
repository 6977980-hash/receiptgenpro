/**
 * ReceiptGenPro — Receipt Generator (v1.4.0)
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
 *
 * v1.3.6 (audit of 25 Oct, Phase 1): downloading the same receipt again
 * keeps its number (it moves on once the receipt changes); an optional
 * business address, contact and tax number under the business name (the
 * tax number never goes in the QR code); no tax, discount or tip on a
 * no-tax type (donations), a quid pro quo line and "No value stated" for
 * an in-kind gift; the QR caption no longer says "verify".
 *
 * v1.3.9 (audit of 25 Oct, v2 Phase 3): a PDF paper size (US Letter for
 * USD and CAD, A4 otherwise, or the visitor's choice) and an invisible
 * text layer, so the PDF can be searched and copied; the empty QR box has
 * the code's final size.
 *
 * v1.3.8 (audit of 25 Oct, v2 Phase 2): prices that include tax (the tax
 * is worked back out and printed as "Includes VAT (20%)"), a GST bill
 * mode (HSN/SAC per item, place of supply), a choice of date format, an
 * option to leave out the "Generated with" line, a check before
 * downloading a receipt with no business name or a zero total, a PAN
 * hint on the HRA form, "SAR" and "AED" as symbols, and on phones a bar
 * at the bottom with the total and a shortcut to the preview.
 *
 * v1.4.0 (audit of 25 Oct, v2 Phase 4): "Remember my business details on
 * this device" (opt-in; the seller fields, logo and currency in
 * localStorage rgp-business-profile, filled in on every generator page
 * unless this tab already has a draft; never inside an embed), eight more
 * currencies, amounts in the currency's own minor unit (JPY 0 decimals,
 * KWD 3; the QR code says dp when it isn't 2), and "Other (type a code)".
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

	// What goes before an amount: a symbol made of letters ("SAR", "AED")
	// gets a space ("SAR 1,000.00"); "$", "€" and "CA$" don't.
	function symbolPrefix( symbol ) {
		symbol = String( symbol || '' );
		return /^[A-Za-z]{2,}$/.test( symbol ) ? symbol + ' ' : symbol;
	}

	// All totals are built from amounts rounded to whole cents, so the figures
	// printed on the receipt always add up (subtotal + tax + tip = total).
	function toCents( n ) {
		// toFixed(6) strips float noise first (1.005 * 100 = 100.49999…).
		return Math.round( parseFloat( ( n * 100 ).toFixed( 6 ) ) );
	}

	// The same in the currency's minor unit: `dp` decimals (2 cents, 0 for
	// yen, 3 for Kuwaiti fils). toMinor( n, 2 ) is toCents( n ).
	function minorFactor( dp ) {
		return dp === 0 ? 1 : ( dp === 3 ? 1000 : 100 );
	}

	function toMinor( n, dp ) {
		return Math.round( parseFloat( ( n * minorFactor( dp ) ).toFixed( 6 ) ) );
	}

	// Decimals of a currency: the list from rf_currencies() (rfReceipt
	// .decimals), else the built-in ones, else 2. Only 0, 2 and 3 exist.
	var KNOWN_DECIMALS = { JPY: 0, KWD: 3 };
	function currencyDecimals( code, map ) {
		var dp = map && Object.prototype.hasOwnProperty.call( map, code ) ? map[ code ] : KNOWN_DECIMALS[ code ];
		dp = parseInt( dp, 10 );
		return dp === 0 || dp === 3 ? dp : 2;
	}

	// "Other (type a code)": three letters, upper-cased; '' when not valid.
	function customCode( value ) {
		var v = String( value || '' ).trim();
		return /^[A-Za-z]{3}$/.test( v ) ? v.toUpperCase() : '';
	}

	// Highest amount and rate the form accepts (also the inputs' HTML max).
	// Capping keeps every figure printable: 1e21 or 1e400 printed as
	// "999999999999999868928.00" or "Infinity" before.
	var MAX_AMOUNT = 999999999999.99;
	var MAX_CENTS  = 99999999999999;
	var MAX_RATE   = 100;
	// MAX_AMOUNT in each minor unit; 3 decimals stay far below 2^53.
	var MAX_MINOR  = { 0: 999999999999, 2: MAX_CENTS, 3: 999999999999990 };
	function maxMinor( dp ) {
		return MAX_MINOR[ dp ] || MAX_CENTS;
	}

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

	// One line's total in cents (or the currency's minor unit, `dp`
	// decimals). The unit price is rounded to cents first, so the printed
	// price × quantity is what the line shows (3 × 0.33).
	function lineCents( qty, price, dp ) {
		dp = dp === undefined ? 2 : dp;
		return Math.min( toMinor( qty * ( toMinor( price, dp ) / minorFactor( dp ) ), dp ), maxMinor( dp ) );
	}

	// Subtotal → discount → taxes → tip → total, all in whole cents.
	// discountType is 'percent' or 'amount'; a discount never exceeds the
	// subtotal (discountClamped says when it was cut), and every tax is
	// charged on the discounted amount.
	// With `inclusive` the prices already include the taxes: each tax is
	// worked back out of the discounted amount (taxable × rate / (100 + all
	// rates)) and not added on top. netCents is what is left without tax,
	// so any rounding cent stays in the net and net + taxes = gross.
	// Every amount is in the currency's minor unit; `dp` (default 2) only
	// converts a fixed discount, typed in whole units, into it.
	function calcTotals( subtotalCents, discount, discountType, taxRates, tipCents, inclusive, dp ) {
		var discountCents = discountType === 'percent' ?
			toCents( subtotalCents * Math.min( discount, 100 ) / 10000 ) :
			toMinor( discount, dp === undefined ? 2 : dp );
		var discountClamped = discountCents > subtotalCents;
		discountCents = Math.min( discountCents, subtotalCents );
		var taxableCents = subtotalCents - discountCents;
		var rateSum = taxRates.reduce( function ( a, b ) {
			return a + b;
		}, 0 );
		var taxCents = taxRates.map( function ( rate ) {
			return inclusive ?
				toCents( taxableCents * rate / ( 100 * ( 100 + rateSum ) ) ) :
				toCents( taxableCents * rate / 10000 );
		} );
		var taxSum = taxCents.reduce( function ( a, b ) {
			return a + b;
		}, 0 );
		var totalCents = taxableCents + tipCents + ( inclusive ? 0 : taxSum );
		return {
			discountCents: discountCents,
			discountClamped: discountClamped,
			taxCents: taxCents,
			totalCents: totalCents,
			inclusive: !! inclusive,
			netCents: inclusive ? taxableCents - taxSum : taxableCents
		};
	}

	// 1234567.89 → "1,234,567.89"; INR and PKR group in lakh and crore
	// ("12,34,567.89"). `dp` decimals (default 2): yen 4,072, dinars 2.625.
	var INDIAN_GROUPING = { INR: true, PKR: true };
	var amountFormats = {};
	function formatAmount( cents, code, dp ) {
		dp = dp === undefined ? 2 : dp;
		var locale = INDIAN_GROUPING[ code ] ? 'en-IN' : 'en-US';
		var key = locale + dp;
		if ( ! amountFormats[ key ] ) {
			amountFormats[ key ] = new Intl.NumberFormat( locale, { minimumFractionDigits: dp, maximumFractionDigits: dp } );
		}
		return amountFormats[ key ].format( cents / minorFactor( dp ) );
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

	// ---- Date formats --------------------------------------------------------
	// 'long' 25 Oct 2026, 'mdy' 10/25/2026, 'dmy' 25/10/2026, 'iso' 2026-10-25.
	// The QR code always keeps ISO dates.
	var MONTHS_SHORT = [ 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' ];
	var DATE_FORMATS = { long: true, mdy: true, dmy: true, iso: true };
	var DMY_CURRENCIES = { INR: true, PKR: true, GBP: true, AUD: true, AED: true, SAR: true, NZD: true, SGD: true, ZAR: true, NGN: true, MYR: true, KWD: true };

	// The format a currency's receipts usually use: USD (and the
	// Philippines, which writes the month first too) month first, Japan
	// year first, the day-first countries day first, the rest "25 Oct
	// 2026" (no doubt about which number is the month).
	function dateFormatFor( currency ) {
		if ( currency === 'USD' || currency === 'PHP' ) {
			return 'mdy';
		}
		if ( currency === 'JPY' ) {
			return 'iso';
		}
		return DMY_CURRENCIES[ currency ] ? 'dmy' : 'long';
	}

	// A YYYY-MM-DD date in `fmt`; anything else comes back unchanged.
	function formatDate( iso, fmt ) {
		var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec( String( iso || '' ) );
		if ( ! m || ! MONTHS_SHORT[ +m[2] - 1 ] ) {
			return String( iso || '' );
		}
		if ( fmt === 'mdy' ) {
			return m[2] + '/' + m[3] + '/' + m[1];
		}
		if ( fmt === 'dmy' ) {
			return m[3] + '/' + m[2] + '/' + m[1];
		}
		if ( fmt === 'long' ) {
			return +m[3] + ' ' + MONTHS_SHORT[ +m[2] - 1 ] + ' ' + m[1];
		}
		return iso;
	}

	// An Indian PAN: five letters, four digits, one letter (ABCDE1234F).
	function isPan( value ) {
		return /^[A-Z]{5}[0-9]{4}[A-Z]$/i.test( String( value || '' ).trim() );
	}

	// GST bill mode (HSN/SAC per item, place of supply): an INR receipt that
	// charges tax, or a seller whose tax number is a GSTIN. Never on a
	// no-tax type (donations), and not on an INR receipt without tax (an
	// HRA rent receipt).
	function isGstMode( o ) {
		return ! o.noTax && ( o.taxIdLabel === 'GSTIN' || ( o.currency === 'INR' && o.taxed ) );
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
		SAR: [ 'Saudi riyals', 'halalas', 'Saudi riyal', 'halala' ],
		NZD: [ 'New Zealand dollars', 'cents', 'New Zealand dollar', 'cent' ], SGD: [ 'Singapore dollars', 'cents', 'Singapore dollar', 'cent' ],
		ZAR: [ 'rand', 'cents', 'rand', 'cent' ], NGN: [ 'naira', 'kobo', 'naira', 'kobo' ],
		PHP: [ 'Philippine pesos', 'centavos', 'Philippine peso', 'centavo' ], MYR: [ 'ringgit', 'sen', 'ringgit', 'sen' ],
		JPY: [ 'yen', '', 'yen', '' ], KWD: [ 'Kuwaiti dinars', 'fils', 'Kuwaiti dinar', 'fils' ]
	};

	// `dp` is the currency's decimals (default 2): yen have no subunit, and
	// 1,000 fils make a Kuwaiti dinar. A typed code ("CHF") is its own name:
	// "One thousand CHF and fifty cents only", or "… and 125/1000" with 3.
	function amountInWords( amount, code, dp ) {
		dp = dp === undefined ? 2 : dp;
		var unit  = minorFactor( dp );
		var names = CURRENCY_WORDS[ code ] || [ code, 'cents', code, 'cent' ];
		// Split in integer cents so 2.999 becomes "three … only", never "one hundred cents".
		var c     = toMinor( amount, dp );
		var whole = Math.floor( c / unit );
		var cents = c % unit;
		var toWords = INDIAN_GROUPING[ code ] ? intToWordsIndian : intToWords;
		var parts = [];
		var name = function ( str ) {
			return str ? ' ' + str : '';
		};
		// "Fifty cents only", not "Zero US dollars and fifty cents only".
		if ( whole || ! cents ) {
			parts.push( toWords( whole ) + name( names[ whole === 1 ? 2 : 0 ] ) );
		}
		if ( cents ) {
			parts.push( CURRENCY_WORDS[ code ] || unit === 100 ? toWords( cents ) + name( names[ cents === 1 ? 3 : 1 ] ) : cents + '/' + unit );
		}
		// A code alone ("CHF") follows the whole amount: "fifty cents CHF".
		var text = parts.join( ' and ' ) + ( ! whole && cents && ! CURRENCY_WORDS[ code ] && code ? ' ' + code : '' );
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

	// r.extras are [ label, value, field id ]; r.taxes [ { label, rate, cents } ];
	// an item's optional `code` (HSN/SAC) is its 4th entry, dropped first
	// when the code would be too long. r.inclusive sets ti: 1 (the taxes
	// are inside the prices, not added to the total). Amounts are integers
	// in the currency's minor unit; r.dp (its decimals) is added as dp only
	// when it isn't 2, so every older code still means 2.
	function buildPayload( r ) {
		var p = {
			v: 1,
			t: clip( r.title, 40 ),
			b: clip( r.business, 80 ),
			c: clip( r.client, 80 ),
			n: clip( r.number, 30 ),
			d: r.date,
			cu: r.currency
		};
		if ( r.dp === 0 || r.dp === 3 ) {
			p.dp = r.dp;
		}
		Object.assign( p, {
			i: ( r.items || [] ).map( function ( it ) {
				var row = [ clip( it.name, 60 ), it.qty, it.priceCents ];
				if ( it.code ) {
					row.push( clip( it.code, 12 ) );
				}
				return row;
			} ),
			tot: r.totalCents,
			pm: r.payMethod,
			x: ( r.extras || [] ).filter( function ( e ) {
				return ! TAX_ID_FIELDS[ e[2] ];
			} ).map( function ( e ) {
				return [ clip( e[0], 40 ), clip( e[1], 80 ) ];
			} )
		} );
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
		if ( taxes.length && r.inclusive ) {
			p.ti = 1;
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
				p.i = p.i.map( function ( it ) {
					return it.slice( 0, 3 ); // HSN/SAC codes only if space allows
				} );
			},
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

	// Bytes a QR code holds at error level L, versions 1-20 (qrcode(0, 'L')
	// picks the smallest that fits). The empty box shown while the QR
	// library loads gets the size the code will have, so nothing moves.
	var QR_CAPACITY_L = [ 17, 32, 53, 78, 106, 134, 154, 192, 230, 271, 321, 367, 425, 458, 520, 586, 644, 718, 792, 858 ];
	function qrModulesFor( bytes ) {
		for ( var v = 0; v < QR_CAPACITY_L.length; v++ ) {
			if ( bytes <= QR_CAPACITY_L[ v ] ) {
				return 21 + 4 * v;
			}
		}
		return 21 + 4 * ( QR_CAPACITY_L.length - 1 );
	}

	// ---- PDF paper and text --------------------------------------------------
	// Sizes in mm. The receipt fills the width inside 10 mm margins and a
	// long one continues on the next page. Letter is what printers in the
	// US and Canada hold, so USD and CAD receipts default to it.
	var PAPER = { a4: { width: 210, height: 297 }, letter: { width: 215.9, height: 279.4 } };
	var PAPER_MARGIN = 10;
	var LETTER_CURRENCIES = { USD: true, CAD: true }; // all others, typed codes too: A4

	function paperFor( currency ) {
		return LETTER_CURRENCIES[ currency ] ? 'letter' : 'a4';
	}

	// 'a4' | 'letter' → { format, width, height, margin, contentWidth,
	// pageHeight } (mm); anything else is A4.
	function paperSize( name ) {
		var format = PAPER[ name ] ? name : 'a4';
		var p = PAPER[ format ];
		var round = function ( mm ) {
			return Math.round( mm * 10 ) / 10;
		};
		return {
			format: format,
			width: p.width,
			height: p.height,
			margin: PAPER_MARGIN,
			contentWidth: round( p.width - 2 * PAPER_MARGIN ),
			pageHeight: round( p.height - 2 * PAPER_MARGIN )
		};
	}

	// The PDF's hidden text is in the built-in Helvetica, which has only
	// Latin-1: a symbol outside it is written as the currency code
	// ("₹1,000.00" → "INR 1,000.00"), and text that still can't be encoded
	// (Hindi, Arabic) is left out instead of printed as junk.
	var LATIN1_RE = /^[\x20-\x7e\xa0-\xff]*$/;
	function pdfText( str, symbol, code ) {
		var s = String( str === undefined || str === null ? '' : str ).replace( /\s+/g, ' ' ).trim();
		if ( symbol && code && ! LATIN1_RE.test( symbol ) ) {
			s = s.split( symbol ).join( code + ' ' );
		}
		return LATIN1_RE.test( s ) ? s : '';
	}

	// The receipt's text, line by line, from the rendered preview (`root`):
	// business, title, number, date and the other details, each item row,
	// the totals, the amount in words and the statement. Only the first copy
	// of "Office + Customer Copy" is read.
	function pdfTextLines( root, symbol, code ) {
		var inner = root.querySelector( '.rf-receipt-inner' ) || root;
		var lines = [];
		var add = function ( parts ) {
			var out = parts.map( function ( p ) {
				return pdfText( p, symbol, code );
			} ).filter( Boolean );
			if ( out.length ) {
				lines.push( out.join( '  ' ) );
			}
		};
		var texts = function ( el, sel ) {
			return Array.prototype.map.call( el.querySelectorAll( sel ), function ( c ) {
				return c.textContent;
			} );
		};
		var each = function ( sel, fn ) {
			Array.prototype.forEach.call( inner.querySelectorAll( sel ), fn );
		};
		each( '.rf-receipt-head h3, .rf-receipt-seller, .rf-receipt-label, .rf-receipt-meta > div', function ( el ) {
			add( [ el.textContent ] );
		} );
		each( '.rf-receipt-table tr', function ( tr ) {
			add( texts( tr, 'th, td' ) );
		} );
		each( '.rf-receipt-totals > div', function ( div ) {
			add( texts( div, 'span' ) );
		} );
		each( '.rf-receipt-words, .rf-receipt-statement', function ( el ) {
			add( [ el.textContent ] );
		} );
		return lines;
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

	// Donation extras printed as one sentence, not as "Label: value" lines.
	var QPQ_FIELDS = { qpq_desc: true, qpq_value: true };

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
			symbolPrefix: symbolPrefix,
			formatDate: formatDate,
			dateFormatFor: dateFormatFor,
			isPan: isPan,
			isGstMode: isGstMode,
			amountInWords: amountInWords,
			intToWords: intToWords,
			intToWordsIndian: intToWordsIndian,
			toCents: toCents,
			toMinor: toMinor,
			currencyDecimals: currencyDecimals,
			customCode: customCode,
			nonNegative: nonNegative,
			capped: capped,
			formatRate: formatRate,
			formatAmount: formatAmount,
			lineCents: lineCents,
			stripBidi: stripBidi,
			monthsBetween: monthsBetween,
			clip: clip,
			qrPixels: qrPixels,
			qrModulesFor: qrModulesFor,
			paperFor: paperFor,
			paperSize: paperSize,
			pdfText: pdfText,
			pdfTextLines: pdfTextLines,
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
		var footerToggle = document.getElementById( 'rf-show-footer' );
		var stickyTotal  = document.getElementById( 'rf-sticky-total' );
		var exportFacts  = null; // set by renderReceipt() for the download check

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
			clientLabel: form.dataset.clientLabel || 'Received From',
			noTax: form.dataset.noTax === '1',
			inKind: form.dataset.inKind === '1'
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
			cfg.noTax         = !! f.no_tax;
			cfg.inKind        = !! f.in_kind;
			itemPlaceholders  = [].concat( f.item_ph || [] );
			showTaxControls();

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

		// A no-tax type (donation) has no tax rate or discount controls.
		function showTaxControls() {
			[ 'rf-tax-rate-field', 'rf-more-options' ].forEach( function ( id ) {
				var el = document.getElementById( id );
				if ( el ) {
					el.hidden = cfg.noTax;
				}
			} );
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
		var decimalsMap = ( window.rfReceipt && window.rfReceipt.decimals ) || {};
		Object.keys( currencies ).forEach( function ( code ) {
			var opt = document.createElement( 'option' );
			opt.value = code;
			opt.textContent = currencies[ code ];
			currencySel.appendChild( opt );
		} );
		// Last: a currency that isn't listed, typed as its 3-letter code.
		var OTHER = 'OTHER';
		var otherBox = document.getElementById( 'rf-currency-other' );
		if ( otherBox ) {
			var otherOpt = document.createElement( 'option' );
			otherOpt.value = OTHER;
			otherOpt.textContent = 'Other (type a code)…';
			currencySel.appendChild( otherOpt );
		}

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

			// GST bill mode only (CSS shows it under the description).
			var hsn = document.createElement( 'input' );
			hsn.type = 'text';
			hsn.className = 'rf-item-hsn';
			hsn.maxLength = 12;
			hsn.placeholder = 'HSN/SAC code (optional)';
			hsn.setAttribute( 'aria-label', 'HSN/SAC code' );

			var remove = document.createElement( 'button' );
			remove.type = 'button';
			remove.className = 'rf-item-remove';
			remove.setAttribute( 'aria-label', 'Remove item' );
			remove.innerHTML = '&times;';

			if ( values ) {
				name.value  = values.name || '';
				qty.value   = values.qty !== undefined ? values.qty : '1';
				price.value = values.price || '';
				hsn.value   = values.hsn || '';
			}

			row.appendChild( name );
			row.appendChild( qty );
			row.appendChild( price );
			row.appendChild( remove );
			row.appendChild( hsn );
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
				row.querySelector( '.rf-item-hsn' ).setAttribute( 'aria-label', 'Item ' + n + ' HSN/SAC code' );
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

		// The PDF paper size sits by the download buttons, outside the form,
		// and is kept with it. "Remember my business details" is not: it
		// shows whether a saved profile exists.
		var paperSel = document.getElementById( 'rf-paper-size' );
		function storeFields() {
			return Array.prototype.filter.call( form.querySelectorAll( 'input[id], select[id], textarea[id]' ), function ( el ) {
				return el.type !== 'file' && el.id !== 'rf-remember';
			} ).concat( paperSel ? [ paperSel ] : [] );
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
					price: row.querySelector( '.rf-item-price' ).value,
					hsn: row.querySelector( '.rf-item-hsn' ).value
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
			if ( rememberBox && rememberBox.checked ) {
				saveProfile();
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
				return false;
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
			return true;
		}

		// ---- Remember my business details (opt-in) ---------------------------
		// localStorage keeps the seller's details on this device for the next
		// receipt, on any generator page; nothing is uploaded. The checkbox
		// is not in an embed (another site's frame), so there the key is
		// never read or written.
		var PROFILE_KEY  = 'rgp-business-profile';
		var PROFILE_TEXT = { name: 'rf-business-name', address: 'rf-business-address', contact: 'rf-business-contact', taxIdLabel: 'rf-tax-id-label', taxId: 'rf-tax-id' };
		var rememberBox  = document.getElementById( 'rf-remember' );
		var forgetBtn    = document.getElementById( 'rf-forget' );
		var rememberNote = document.getElementById( 'rf-remember-note' );

		function readProfile() {
			if ( ! rememberBox ) {
				return null;
			}
			try {
				var p = JSON.parse( window.localStorage.getItem( PROFILE_KEY ) || 'null' );
				return p && typeof p === 'object' && p.v === 1 ? p : null;
			} catch ( e ) {
				return null;
			}
		}

		function showProfileState( saved, note ) {
			if ( forgetBtn ) {
				forgetBtn.hidden = ! saved;
			}
			if ( rememberNote ) {
				rememberNote.textContent = note || '';
				rememberNote.hidden = ! note;
			}
		}

		// Saves { v, name, address, contact, taxIdLabel, taxId, logo,
		// currency [, code, dp] }. A logo over the quota is left out (with a
		// note); private mode or a full storage only shows a note.
		function saveProfile() {
			var p = { v: 1 };
			Object.keys( PROFILE_TEXT ).forEach( function ( key ) {
				var el = document.getElementById( PROFILE_TEXT[ key ] );
				p[ key ] = el ? el.value : '';
			} );
			p.logo = isLogoDataUrl( state.logoDataUrl ) ? state.logoDataUrl : null;
			// A page or type with its own currency (INR for HRA) keeps the
			// saved one, as applyProfile() leaves it alone there.
			var before = ( form.dataset.currency || typeCurrency ) && readProfile();
			if ( before ) {
				[ 'currency', 'code', 'dp' ].forEach( function ( key ) {
					if ( before[ key ] !== undefined ) {
						p[ key ] = before[ key ];
					}
				} );
			} else {
				p.currency = currencySel.value;
				if ( p.currency === OTHER ) {
					p.code = fieldValue( 'rf-currency-code' );
					p.dp = fieldValue( 'rf-currency-dp' );
				}
			}
			try {
				window.localStorage.setItem( PROFILE_KEY, JSON.stringify( p ) );
				showProfileState( true, '' );
				return;
			} catch ( e ) {}
			if ( p.logo ) {
				try {
					p.logo = null;
					window.localStorage.setItem( PROFILE_KEY, JSON.stringify( p ) );
					showProfileState( true, 'Your logo is too large to remember on this device; the other details are saved.' );
					return;
				} catch ( e2 ) {}
			}
			showProfileState( !! readProfile(), 'This browser doesn\'t allow saving here (private mode, or its storage is full), so your details will not be remembered.' );
		}

		function deleteProfile() {
			try {
				window.localStorage.removeItem( PROFILE_KEY );
			} catch ( e ) {}
			showProfileState( false, '' );
		}

		// Fills the seller fields, logo and currency from the profile. The
		// currency is kept on a page with its own (INR on the HRA form), and
		// saveProfile() doesn't save that one.
		function applyProfile( p ) {
			Object.keys( PROFILE_TEXT ).forEach( function ( key ) {
				var el = document.getElementById( PROFILE_TEXT[ key ] );
				if ( ! el || typeof p[ key ] !== 'string' ) {
					return;
				}
				var before = el.value;
				el.value = p[ key ];
				if ( el.tagName === 'SELECT' && el.value !== p[ key ] ) {
					el.value = before; // not one of the options
				}
			} );
			if ( isLogoDataUrl( p.logo ) ) {
				state.logoDataUrl = p.logo;
			}
			if ( ! form.dataset.currency && ( currencies[ p.currency ] || ( p.currency === OTHER && otherBox ) ) ) {
				currencySel.value = p.currency;
				defaultCurrency = p.currency; // what a type without its own currency goes back to
				if ( p.currency === OTHER ) {
					var codeEl = document.getElementById( 'rf-currency-code' );
					var dpEl = document.getElementById( 'rf-currency-dp' );
					if ( codeEl && typeof p.code === 'string' ) {
						codeEl.value = p.code;
					}
					if ( dpEl && /^[023]$/.test( String( p.dp ) ) ) {
						dpEl.value = String( p.dp );
					}
				}
			}
		}

		if ( rememberBox ) {
			rememberBox.addEventListener( 'change', function () {
				if ( rememberBox.checked ) {
					saveProfile();
				} else {
					deleteProfile();
				}
			} );
		}
		if ( forgetBtn ) {
			// Removes the saved copy; what is on screen now stays.
			forgetBtn.addEventListener( 'click', function () {
				deleteProfile();
				if ( rememberBox ) {
					rememberBox.checked = false;
					rememberBox.focus();
				}
			} );
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

		// What the receipt says, apart from its number and its look (design,
		// logo, QR code, footer line, date format, paper size): a download of the same receipt in another format
		// keeps the number, and the first real change moves it on.
		var NOT_CONTENT = { 'rf-receipt-number': true, 'rf-qr': true, 'rf-show-footer': true, 'rf-date-format': true, 'rf-paper-size': true };
		function receiptPrint() {
			var data = { type: currentType, fields: {}, items: [] };
			storeFields().forEach( function ( el ) {
				if ( ! NOT_CONTENT[ el.id ] ) {
					data.fields[ el.id ] = el.type === 'checkbox' ? el.checked : el.value;
				}
			} );
			itemsList.querySelectorAll( '.rf-item-row' ).forEach( function ( row ) {
				var it = [ row.querySelector( '.rf-item-name' ).value, row.querySelector( '.rf-item-qty' ).value, row.querySelector( '.rf-item-price' ).value, row.querySelector( '.rf-item-hsn' ).value ];
				if ( it[0].trim() || it[2].trim() ) {
					data.items.push( it ); // an empty row changes nothing
				}
			} );
			return JSON.stringify( data );
		}

		// The number of the last download in this page view and what that
		// receipt said; null once the field has moved on.
		var usedNumber = null;
		var usedPrint  = '';

		// After a download: remember the number used. The field keeps it, so
		// a PDF and a PNG of the same receipt carry the same number.
		function rememberNumber() {
			if ( ! numberInput ) {
				return;
			}
			var used = numberInput.value.trim() || '1001';
			try {
				window.localStorage.setItem( LAST_NUMBER_KEY, used );
			} catch ( e ) {}
			usedNumber = used;
			usedPrint  = receiptPrint();
		}

		// On the first change after a download, the field moves to the next
		// number, once; a number the visitor typed themselves is kept.
		function advanceNumber() {
			if ( usedNumber === null || receiptPrint() === usedPrint ) {
				return;
			}
			if ( numberInput.value.trim() === usedNumber ) {
				numberInput.value = nextReceiptNumber( usedNumber );
			}
			usedNumber = null;
		}

		// A draft in this tab is newer than the profile, so it wins.
		var hadDraft = restoreForm();
		var profile = readProfile();
		if ( profile ) {
			if ( ! hadDraft ) {
				applyProfile( profile );
			}
			rememberBox.checked = true;
			showProfileState( true, '' );
		}

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
		var businessDetails = document.getElementById( 'rf-business-details' );
		if ( businessDetails && ( fieldValue( 'rf-business-address' ) || fieldValue( 'rf-business-contact' ) || fieldValue( 'rf-tax-id' ) ) ) {
			businessDetails.open = true;
		}
		if ( moreOptions && ( nonNegative( ( document.getElementById( 'rf-discount' ) || {} ).value ) || nonNegative( ( document.getElementById( 'rf-tax2-rate' ) || {} ).value ) || ( document.getElementById( 'rf-tax-inclusive' ) || {} ).checked ) ) {
			moreOptions.open = true;
		}

		// A PAN is printed in capitals.
		form.addEventListener( 'change', function ( e ) {
			if ( e.target.id === 'rf-x-landlord_pan' ) {
				e.target.value = e.target.value.trim().toUpperCase();
			}
		} );

		// Choosing GSTIN as the tax number names the taxes CGST and SGST
		// (when they have no names yet) and splits a single rate between them.
		var taxIdLabelSel = document.getElementById( 'rf-tax-id-label' );
		if ( taxIdLabelSel ) {
			taxIdLabelSel.addEventListener( 'change', function () {
				var l1 = document.getElementById( 'rf-tax-label' );
				var l2 = document.getElementById( 'rf-tax2-label' );
				var r1 = document.getElementById( 'rf-tax-rate' );
				var r2 = document.getElementById( 'rf-tax2-rate' );
				if ( taxIdLabelSel.value !== 'GSTIN' || cfg.noTax || ! l1 || ! l2 || l1.value.trim() || l2.value.trim() ) {
					return;
				}
				l1.value = 'CGST';
				l2.value = 'SGST';
				var rate = r1 ? capped( r1.value, MAX_RATE ) : 0;
				if ( rate && r2 && ! nonNegative( r2.value ) ) {
					r1.value = r2.value = formatRate( rate / 2 );
				}
				if ( moreOptions ) {
					moreOptions.open = true;
				}
			} );
		}

		// ---- Live re-render on any field change ------------------------------
		form.addEventListener( 'input', renderReceipt );
		form.addEventListener( 'change', renderReceipt );

		// The menu labels ("€ — Euro", from rf_currencies()) carry the symbol.
		function getCurrencySymbol( code ) {
			return currencies[ code ] ? currencySymbol( currencies[ code ] ) : code + ' ';
		}

		// The receipt's currency: { code, symbol, dp }. A typed code is its
		// own symbol ("CHF 1,000.00"); until it is 3 letters, code and
		// symbol are ''.
		function activeCurrency() {
			var v = currencySel.value || 'USD';
			if ( v !== OTHER ) {
				return { code: v, symbol: getCurrencySymbol( v ), dp: currencyDecimals( v, decimalsMap ) };
			}
			var code = customCode( fieldValue( 'rf-currency-code' ) );
			var dpSel = parseInt( fieldValue( 'rf-currency-dp' ), 10 );
			return { code: code, symbol: code, dp: dpSel === 0 || dpSel === 3 ? dpSel : 2 };
		}

		// The active currency's decimals and code, set by renderReceipt().
		var dp = 2;
		var unit = 100;
		var curCode = 'USD';

		// Amount inputs step by the currency's minor unit (1 yen, 0.001
		// dinar); a % discount keeps 0.01.
		var STEPS = { 0: [ '1', '0' ], 2: [ '0.01', '0.00' ], 3: [ '0.001', '0.000' ] };
		function applySteps() {
			var st = STEPS[ dp ];
			var amountInputs = Array.prototype.slice.call( itemsList.querySelectorAll( '.rf-item-price' ) );
			[ 'rf-tip', 'rf-amount-paid', 'rf-x-qpq_value' ].forEach( function ( id ) {
				var el = document.getElementById( id );
				if ( el ) {
					amountInputs.push( el );
				}
			} );
			amountInputs.forEach( function ( el ) {
				if ( el.step !== st[0] ) {
					el.step = st[0];
					el.placeholder = st[1];
				}
			} );
			var disc = document.getElementById( 'rf-discount' );
			var discStep = fieldValue( 'rf-discount-type' ) === 'amount' ? st[0] : '0.01';
			if ( disc && disc.step !== discStep ) {
				disc.step = discStep;
			}
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
			return symbolPrefix( symbol ) + formatAmount( toMinor( n, dp ), curCode, dp );
		}

		// The visitor's date format, or the one their currency usually uses.
		function dateFormat( currency ) {
			var v = fieldValue( 'rf-date-format' );
			return DATE_FORMATS[ v ] ? v : dateFormatFor( currency );
		}

		// An extra field's value as printed; dates in `fmt` ('iso' for the QR).
		function formatExtra( el, fmt ) {
			var v = ( el.value || '' ).trim();
			if ( ! v ) {
				return '';
			}
			var t = el.dataset.type;
			if ( t === 'date' && /^\d{4}-\d{2}-\d{2}$/.test( v ) ) {
				return formatDate( v, fmt );
			}
			if ( t === 'month' && /^\d{4}-\d{2}$/.test( v ) ) {
				var mm = [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ];
				return mm[ parseInt( v.slice( 5 ), 10 ) - 1 ] + ' ' + v.slice( 0, 4 );
			}
			if ( t === 'datetime-local' ) {
				var dt = v.split( 'T' );
				return formatDate( dt[0], fmt ) + ( dt[1] ? ' ' + dt[1] : '' );
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

		// `gst`: GST bill mode, so each item keeps its HSN/SAC code.
		function collectItems( notes, gst ) {
			var rows = itemsList.querySelectorAll( '.rf-item-row' );
			var items = [];
			rows.forEach( function ( row ) {
				var name     = row.querySelector( '.rf-item-name' ).value.trim();
				var qtyRaw   = row.querySelector( '.rf-item-qty' ).value;
				var priceRaw = row.querySelector( '.rf-item-price' ).value;
				var code     = gst ? row.querySelector( '.rf-item-hsn' ).value.trim() : '';
				var qty      = nonNegative( qtyRaw, 1 );
				var price    = toMinor( capped( priceRaw, MAX_AMOUNT ), dp ) / unit;
				if ( isNegative( qtyRaw ) || isNegative( priceRaw ) ) {
					notes.negative = true;
				}
				if ( isOver( priceRaw, MAX_AMOUNT ) || toMinor( qty * price, dp ) > maxMinor( dp ) ) {
					notes.max = true;
				}
				if ( name || price ) {
					// An in-kind gift is described, not valued (IRS Pub. 1771).
					items.push( { name: name || 'Item', qty: qty, price: price, lineCents: lineCents( qty, price, dp ), noValue: cfg.inKind && ! price, code: code } );
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

		// GST bill mode: an HSN/SAC box under each item, a place of supply.
		var gstOn = null;
		function showGstFields( gst ) {
			if ( gst === gstOn ) {
				return;
			}
			gstOn = gst;
			itemsList.classList.toggle( 'rf-gst-mode', gst );
			var field = document.getElementById( 'rf-place-of-supply-field' );
			if ( field ) {
				field.hidden = ! gst;
			}
		}

		// HRA form: a hint (never a block) when the landlord PAN doesn't
		// look like one. The field is rebuilt on a type switch, so is its hint.
		function updatePanHint() {
			var pan = document.getElementById( 'rf-x-landlord_pan' );
			if ( ! pan ) {
				return;
			}
			var hint = document.getElementById( 'rf-pan-hint' );
			if ( ! hint ) {
				hint = document.createElement( 'p' );
				hint.id = 'rf-pan-hint';
				hint.className = 'rf-field-hint rf-field-warn';
				hint.textContent = 'This doesn\'t look like a PAN: 5 letters, 4 digits, then a letter (e.g. ABCDE1234F). Check it before you print.';
				pan.parentNode.appendChild( hint );
			}
			var bad = !! pan.value.trim() && ! isPan( pan.value );
			hint.hidden = ! bad;
			if ( bad ) {
				pan.setAttribute( 'aria-describedby', 'rf-pan-hint' );
			} else {
				pan.removeAttribute( 'aria-describedby' );
			}
		}

		// ---- Check before downloading an empty receipt ------------------------
		// No business name, or a total of 0: a notice above the buttons asks
		// first (no alert / confirm). "Download anyway" holds for this
		// receipt, so its other formats don't ask again.
		var exportCheck     = document.getElementById( 'rf-export-check' );
		var exportCheckText = document.getElementById( 'rf-export-check-text' );
		var exportOkPrint   = null;
		var pendingExport   = null;

		function hideExportCheck() {
			if ( exportCheck ) {
				exportCheck.hidden = true;
			}
			pendingExport = null;
		}

		function checkBeforeExport( run ) {
			var f = exportFacts;
			if ( ! exportCheck || ! f || ( f.business && ! f.zero ) || exportOkPrint === receiptPrint() ) {
				hideExportCheck();
				run();
				return;
			}
			var label = cfg.businessLabel.replace( / \(optional\)$/i, '' );
			var problems = [];
			if ( ! f.business ) {
				problems.push( 'the "' + label + '" field is empty, so it says "Your Business Name"' );
			}
			if ( f.zero ) {
				problems.push( 'the total is ' + f.total );
			}
			exportCheckText.textContent = 'Before you download: ' + problems.join( ', and ' ) + '.';
			pendingExport = run;
			exportCheck.hidden = false;
			exportCheck.focus();
		}

		if ( exportCheck ) {
			document.getElementById( 'rf-export-anyway' ).addEventListener( 'click', function () {
				var run = pendingExport;
				exportOkPrint = receiptPrint();
				hideExportCheck();
				if ( run ) {
					run();
				}
			} );
			document.getElementById( 'rf-export-back' ).addEventListener( 'click', function () {
				var f = exportFacts || {};
				hideExportCheck();
				var target = ! f.business ? document.getElementById( 'rf-business-name' ) : itemsList.querySelector( '.rf-item-price' );
				if ( target ) {
					target.focus();
				}
			} );
		}

		function renderReceipt() {
			advanceNumber();
			var business  = document.getElementById( 'rf-business-name' ).value.trim() || 'Your Business Name';
			var client    = document.getElementById( 'rf-client-name' ).value.trim();
			var number    = document.getElementById( 'rf-receipt-number' ).value.trim() || '1001';
			// A cleared date prints a dash, not today's date.
			var isoDate   = dateInput.value;
			var cur       = activeCurrency();
			var currency  = cur.code;
			dp            = cur.dp;
			unit          = minorFactor( dp );
			curCode       = currency;
			var date      = isoDate ? formatDate( isoDate, dateFormat( currency ) ) : '—';
			var symbol    = cur.symbol;
			var fix       = {};
			// A no-tax type (donation) ignores whatever the hidden controls hold.
			var taxRaw    = cfg.noTax ? '' : fieldValue( 'rf-tax-rate' );
			var tax2Raw   = cfg.noTax ? '' : fieldValue( 'rf-tax2-rate' );
			var discRaw   = cfg.noTax ? '' : fieldValue( 'rf-discount' );
			var discType  = fieldValue( 'rf-discount-type' ) === 'amount' ? 'amount' : 'percent';
			var taxRate   = capped( taxRaw, MAX_RATE );
			var taxLabel  = fieldValue( 'rf-tax-label' ) || 'Tax';
			var tax2Rate  = capped( tax2Raw, MAX_RATE );
			var tax2Label = fieldValue( 'rf-tax2-label' ) || 'Tax 2';
			var discount  = capped( discRaw, discType === 'percent' ? MAX_RATE : MAX_AMOUNT );
			var inclusive = ! cfg.noTax && !! ( document.getElementById( 'rf-tax-inclusive' ) || {} ).checked;
			var gst       = isGstMode( { noTax: cfg.noTax, currency: currency, taxIdLabel: fieldValue( 'rf-tax-id-label' ), taxed: taxRate > 0 || tax2Rate > 0 } );
			var place     = gst ? fieldValue( 'rf-place-of-supply' ) : '';
			var payMethod = document.getElementById( 'rf-payment-method' ).value;
			var notes     = document.getElementById( 'rf-notes' ).value.trim();
			var items     = collectItems( fix, gst );
			showGstFields( gst );
			updatePanHint();
			applySteps();
			if ( otherBox ) {
				otherBox.hidden = currencySel.value !== OTHER;
			}
			var tipRaw    = tipInput && ! cfg.noTax ? tipInput.value : '';
			var tipCents  = toMinor( capped( tipRaw, MAX_AMOUNT ), dp );
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
			if ( subtotalCents > maxMinor( dp ) ) {
				subtotalCents = maxMinor( dp );
				fix.max = true;
			}
			// Amounts in "cents" are in the currency's minor unit (`unit` a whole one).
			var calc       = calcTotals( subtotalCents, discount, discType, [ taxRate, tax2Rate ], tipCents, inclusive, dp );
			var totalCents = calc.totalCents;
			var subtotal   = subtotalCents / unit;
			var tip        = tipCents / unit;
			var total      = totalCents / unit;
			var paid       = paidRaw === '' ? null : toMinor( capped( paidRaw, MAX_AMOUNT ), dp ) / unit;
			if ( calc.discountClamped && subtotalCents ) {
				fix.discount = calc.discountCents / unit;
			}
			showNotices( fix, symbol );

			// Amounts in the table may wrap only after a thousands comma, and
			// the Thermal Slip sizes its Price and Total columns to the longest
			// one, up to 10 characters (--rf-amount-ch), so words in the
			// description stay whole.
			var amountCh = 5; // "PRICE"
			var cellMoney = function ( n ) {
				var str = money( symbol, n );
				amountCh = Math.max( amountCh, Array.from( str ).length );
				return str.replace( /,/g, ',<wbr>' );
			};
			var rowsHtml = items
				.map( function ( it ) {
					return (
						'<tr>' +
						'<td>' + escapeHtml( it.name ) + ( it.code ? '<span class="rf-item-code">HSN/SAC: ' + escapeHtml( it.code ) + '</span>' : '' ) + '</td>' +
						'<td class="rf-num">' + escapeHtml( String( it.qty ) ) + '</td>' +
						( it.noValue ?
							'<td class="rf-num rf-no-value">No value stated</td><td class="rf-num rf-no-value">No value stated</td>' :
							'<td class="rf-num">' + cellMoney( it.price ) + '</td>' +
							'<td class="rf-num">' + cellMoney( it.lineCents / unit ) + '</td>' ) +
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
			if ( place ) {
				meta.push( '<div><strong>Place of supply:</strong> ' + escapeHtml( place ) + '</div>' );
			}

			// extras keep ISO dates: they go in the QR code.
			var extras = place ? [ [ 'Place of supply', place, 'place_of_supply' ] ] : [];
			var fmt = dateFormat( currency );
			form.querySelectorAll( '[data-rf-extra]' ).forEach( function ( el ) {
				var val = formatExtra( el, 'iso' );
				if ( val ) {
					var label = ( el.dataset.label || '' ).replace( / \(optional\)$/i, '' );
					var id = el.id.replace( /^rf-x-/, '' );
					if ( ! QPQ_FIELDS[ id ] ) { // printed in their own sentence
						meta.push( '<div><strong>' + escapeHtml( label ) + ':</strong> ' + escapeHtml( formatExtra( el, fmt ) ) + '</div>' );
					}
					extras.push( [ label, val, id ] );
				}
			} );

			// The seller's details under the business name. The tax number
			// stays off the QR code; the address goes in it, clipped.
			var bizAddress = fieldValue( 'rf-business-address' );
			var bizContact = fieldValue( 'rf-business-contact' );
			var taxId      = fieldValue( 'rf-tax-id' );
			var sellerHtml =
				( bizAddress ? '<p class="rf-receipt-seller">' + escapeHtml( bizAddress ).replace( /\s*\n\s*/g, '<br>' ) + '</p>' : '' ) +
				( bizContact ? '<p class="rf-receipt-seller">' + escapeHtml( bizContact ) + '</p>' : '' ) +
				( taxId ? '<p class="rf-receipt-seller rf-receipt-taxid">' + escapeHtml( fieldValue( 'rf-tax-id-label' ) || 'Tax ID' ) + ': ' + escapeHtml( taxId ) + '</p>' : '' );
			var qrExtras = bizAddress ? [ [ 'Business address', clip( bizAddress.replace( /\s*\n\s*/g, ', ' ), 60 ), 'business_address' ] ].concat( extras ) : extras;

			// Quid pro quo: what the donor received replaces the "no goods or
			// services" statement.
			var qpqDesc  = fieldValue( 'rf-x-qpq_desc' );
			var qpqValue = fieldValue( 'rf-x-qpq_value' );
			var qpqHtml  = '';
			if ( qpqDesc || qpqValue ) {
				qpqHtml = '<p class="rf-receipt-statement">In return for this contribution the donor received ' + escapeHtml( qpqDesc || 'goods or services' ) +
					( qpqValue ? ', with an estimated value of ' + money( symbol, capped( qpqValue, MAX_AMOUNT ) ) + '. Only the amount above this value may be tax-deductible.' :
						'. Only the amount above their value may be tax-deductible.' ) + '</p>';
			}

			// The tax lines: added ones before the total, or with prices that
			// include tax, "Includes VAT (20%)" lines after it.
			var taxLines = function ( prefix ) {
				var cls = prefix ? ' class="rf-receipt-tax-incl"' : '';
				return [ [ taxRate, taxLabel, 0 ], [ tax2Rate, tax2Label, 1 ] ].map( function ( t ) {
					return t[0] ? '<div' + cls + '><span>' + prefix + escapeHtml( t[1] ) + ' (' + formatRate( t[0] ) + '%)</span><span>' + money( symbol, calc.taxCents[ t[2] ] / unit ) + '</span></div>' : '';
				} ).join( '' );
			};
			var totalsHtml =
				'<div><span>Subtotal</span><span>' + money( symbol, subtotal ) + '</span></div>' +
				( calc.discountCents ? '<div><span>Discount' + ( discType === 'percent' ? ' (' + formatRate( Math.min( discount, 100 ) ) + '%)' : '' ) + '</span><span>-' + money( symbol, calc.discountCents / unit ) + '</span></div>' : '' ) +
				( inclusive ? '' : taxLines( '' ) ) +
				( tip ? '<div><span>Tip</span><span>' + money( symbol, tip ) + '</span></div>' : '' ) +
				'<div class="rf-receipt-total-final"><span>Total</span><span>' + money( symbol, total ) + '</span></div>' +
				// Prices with tax in them: the tax worked out, not added.
				( inclusive ? taxLines( 'Includes ' ) : '' );

			if ( paid !== null ) {
				var diffCents = totalCents - toMinor( paid, dp );
				totalsHtml += '<div><span>Amount Paid</span><span>' + money( symbol, paid ) + '</span></div>';
				if ( diffCents < 0 ) {
					totalsHtml += '<div class="rf-receipt-balance"><span>Change / Credit</span><span>' + money( symbol, -diffCents / unit ) + '</span></div>';
				} else {
					totalsHtml += '<div class="rf-receipt-balance"><span>Balance Due</span><span>' + money( symbol, diffCents / unit ) + '</span></div>';
				}
			}

			var wordsAmount = paid !== null ? paid : total;
			var wordsHtml = ( cfg.words && wordsAmount > 0 ) ?
				'<p class="rf-receipt-words"><strong>Amount received in words:</strong> ' + escapeHtml( amountInWords( wordsAmount, currency, dp ) ) + '</p>' : '';

			var statementHtml = qpqHtml || ( ( statement && statement.checked ) ?
				'<p class="rf-receipt-statement">' + escapeHtml( statement.dataset.text ) + '</p>' : '' );

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
					date: isoDate,
					currency: currency,
					dp: dp,
					items: items.map( function ( it ) {
						return { name: it.name, qty: it.qty, priceCents: toMinor( it.price, dp ), code: it.code };
					} ),
					discountCents: calc.discountCents,
					taxes: [
						{ label: taxLabel, rate: taxRate, cents: calc.taxCents[0] },
						{ label: tax2Label, rate: tax2Rate, cents: calc.taxCents[1] }
					],
					inclusive: inclusive,
					tipCents: tipCents,
					totalCents: totalCents,
					paidCents: paid === null ? null : toMinor( paid, dp ),
					payMethod: payMethod,
					extras: qrExtras
				} );
				var qr = qrDataUrl( verifyLink );
				var pendingPx = qr ? 0 : qrPixels( qrModulesFor( new TextEncoder().encode( verifyLink ).length ) );
				var qrText = '<span>Scan to view the details in this code<br>' + escapeHtml( verifyBase.replace( /^https?:\/\//, '' ) ) + '</span>';
				// While the QR library loads, an empty box holds its place so
				// the receipt (and an embedding iframe) doesn't jump.
				qrHtml = qr ?
					'<div class="rf-receipt-qr"><img src="' + qr.url + '" width="' + qr.size + '" height="' + qr.size + '" style="width:' + qr.size + 'px;height:' + qr.size + 'px" alt="QR code with this receipt\'s details">' + qrText + '</div>' :
					'<div class="rf-receipt-qr"><span class="rf-qr-pending" aria-hidden="true" style="width:' + pendingPx + 'px;height:' + pendingPx + 'px"></span>' + qrText + '</div>';
			}

			preview.innerHTML = wrapDesign( design,
				'<div class="rf-receipt-head">' +
				'<div><h3>' + escapeHtml( business ) + '</h3>' + sellerHtml + '<p class="rf-receipt-label">' + escapeHtml( cfg.title ) + '</p></div>' +
				'</div>' +
				'<div class="rf-receipt-meta">' + meta.join( '' ) + '</div>' +
				'<table class="rf-receipt-table" style="--rf-amount-ch:' + Math.min( amountCh, 10 ) + '"><thead><tr><th>Description</th><th class="rf-num">Qty</th><th class="rf-num">Price</th><th class="rf-num">Total</th></tr></thead><tbody>' +
				( rowsHtml || '<tr><td colspan="4" class="rf-empty">Add items in the form to see them here</td></tr>' ) +
				'</tbody></table>' +
				'<div class="rf-receipt-totals">' + totalsHtml + '</div>' +
				wordsHtml +
				statementHtml +
				( notes ? '<div class="rf-receipt-notes">' + escapeHtml( notes ) + '</div>' : '' ) +
				signatureHtml +
				qrHtml +
				( footerToggle && ! footerToggle.checked ? '' :
					'<p class="rf-receipt-footer">Generated with ' + escapeHtml( ( window.rfReceipt && window.rfReceipt.brand ) || 'ReceiptGenPro' ) + '</p>' ) );

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
				statement: statement ? statement.checked || !! qpqHtml : null,
				business_address: !! bizAddress,
				x: extraFacts(),
				amount: total,
				cash: payMethod === 'Cash',
				currency: currency,
				months: monthsBetween( fieldValue( 'rf-x-period_from' ), fieldValue( 'rf-x-period_to' ) )
			} );

			// What the download check looks at (an empty receipt).
			exportFacts = {
				business: !! document.getElementById( 'rf-business-name' ).value.trim(),
				zero: totalCents === 0 && ! items.some( function ( it ) {
					return it.noValue; // an in-kind gift is described, not valued
				} ),
				total: money( symbol, total )
			};

			if ( exportCheck && ! exportCheck.hidden && exportFacts.business && ! exportFacts.zero ) {
				hideExportCheck(); // fixed in the form
			}

			var totalText = 'Total: ' + money( symbol, total );
			if ( stickyTotal && stickyTotal.textContent !== totalText ) {
				stickyTotal.textContent = totalText;
			}

			// Screen readers hear only the new total, once typing pauses,
			// instead of the whole receipt on every keystroke.
			if ( totalLive ) {
				clearTimeout( totalLiveTimer );
				totalLiveTimer = setTimeout( function () {
					if ( totalLive.textContent !== totalText ) {
						totalLive.textContent = totalText;
					}
				}, 800 );
			}
		}

		renderReceipt();

		// ---- Total bar on phones -----------------------------------------------
		// main.css shows it only where the form sits above the preview. It
		// shows while the form is on screen and the preview and download
		// buttons are not, and sits above the cookie banner while that shows.
		var stickyBar = document.getElementById( 'rf-sticky-bar' );
		var actionsBox = document.querySelector( '.rf-preview-actions' );
		if ( stickyBar && window.IntersectionObserver ) {
			var inView = { form: false, preview: false, actions: false };
			var consentBanner = document.getElementById( 'rf-consent-banner' );
			var placeBar = function () {
				var banner = document.getElementById( 'rf-consent-banner' );
				stickyBar.hidden = ! inView.form || inView.preview || inView.actions;
				stickyBar.style.bottom = banner && ! banner.hidden ? banner.offsetHeight + 'px' : '';
			};
			var io = new window.IntersectionObserver( function ( entries ) {
				entries.forEach( function ( e ) {
					inView[ e.target === form ? 'form' : ( e.target === preview ? 'preview' : 'actions' ) ] = e.isIntersecting;
				} );
				placeBar();
			} );
			io.observe( form );
			io.observe( preview );
			if ( actionsBox ) {
				io.observe( actionsBox );
			}
			if ( consentBanner && window.MutationObserver ) {
				new MutationObserver( placeBar ).observe( consentBanner, { attributes: true, attributeFilter: [ 'hidden' ] } );
			}
			window.addEventListener( 'resize', placeBar );
			document.getElementById( 'rf-sticky-go' ).addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var still = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
				preview.scrollIntoView( { behavior: still ? 'auto' : 'smooth', block: 'start' } );
			} );
		}

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
				checkBeforeExport( function () {
					withCanvas( false, pngBtn, function ( canvas ) {
						download( canvas.toDataURL( 'image/png' ), fileName( 'png' ) );
					} );
				} );
			} );
		}

		var jpgBtn = document.getElementById( 'rf-download-jpg' );
		if ( jpgBtn ) {
			jpgBtn.addEventListener( 'click', function () {
				checkBeforeExport( function () {
					withCanvas( false, jpgBtn, function ( canvas ) {
						download( canvas.toDataURL( 'image/jpeg', 0.92 ), fileName( 'jpg' ) );
					} );
				} );
			} );
		}

		var pdfBtn = document.getElementById( 'rf-download-pdf' );
		if ( pdfBtn ) {
			pdfBtn.addEventListener( 'click', function () {
				checkBeforeExport( function () {
					withCanvas( true, pdfBtn, savePdf );
				} );
			} );
		}

		if ( paperSel ) {
			paperSel.addEventListener( 'change', saveFormSoon );
		}

		// The visitor's paper size, or the one their currency usually uses.
		function paperChoice() {
			var v = paperSel ? paperSel.value : '';
			return PAPER[ v ] ? v : paperFor( curCode );
		}

		// The receipt's text on page 1 in invisible ink over the image, so the
		// PDF can be searched, copied and read aloud. It is shrunk to fit the
		// box (x, y, width, height in mm) when the receipt is long.
		function addPdfText( pdf, x, y, width, height ) {
			var cur   = activeCurrency();
			var lines = pdfTextLines( preview, cur.symbol, cur.code );
			var brand = pdfText( ( window.rfReceipt && window.rfReceipt.brand ) || 'ReceiptGenPro' ) || 'ReceiptGenPro';
			pdf.setProperties( {
				title: 'Receipt ' + pdfText( document.getElementById( 'rf-receipt-number' ).value.trim() || '1001' ),
				subject: pdfText( document.getElementById( 'rf-business-name' ).value.trim() ),
				creator: brand
			} );
			if ( ! lines.length ) {
				return;
			}
			var LINE = 1.15;
			var PT_MM = 25.4 / 72;
			var size = 8;
			pdf.setFont( 'helvetica', 'normal' );
			pdf.setFontSize( size );
			var wrapped = pdf.splitTextToSize( lines, width );
			if ( wrapped.length * size * PT_MM * LINE > height ) {
				size = Math.max( 1, height / ( wrapped.length * PT_MM * LINE ) );
				pdf.setFontSize( size );
				wrapped = pdf.splitTextToSize( lines, width );
			}
			pdf.text( wrapped, x, y + size * PT_MM, { renderingMode: 'invisible', lineHeightFactor: LINE } );
		}

		function savePdf( canvas ) {
			// JPEG inside the PDF keeps files ~10x smaller than PNG (was ~5 MB).
			var imgData   = canvas.toDataURL( 'image/jpeg', 0.92 );
			var jsPDF     = window.jspdf.jsPDF;
			if ( design === 'thermal' ) {
				// A real 80 mm till roll: 72 mm printable, page as long as the slip.
				var slipHeight = ( canvas.height * 72 ) / canvas.width;
				var slip = new jsPDF( { orientation: 'portrait', unit: 'mm', format: [ 80, slipHeight + 8 ] } );
				slip.addImage( imgData, 'JPEG', 4, 4, 72, slipHeight );
				addPdfText( slip, 4, 4, 72, slipHeight );
				slip.save( fileName( 'pdf' ) );
				return;
			}
			// A4 or Letter at full width inside 10 mm margins; a receipt
			// taller than one page continues on the next pages, cut between
			// rows, instead of shrinking.
			var paper  = paperSize( paperChoice() );
			var m      = paper.margin;
			var w      = paper.contentWidth;
			var pdf    = new jsPDF( { orientation: 'portrait', unit: 'mm', format: paper.format } );
			var pagePx = Math.floor( canvas.width * paper.pageHeight / w );
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
					pdf.addPage( paper.format, 'portrait' );
				}
				pdf.addImage( slices.length > 1 ? part.toDataURL( 'image/jpeg', 0.92 ) : imgData, 'JPEG', m, m, w, ( part.height * w ) / canvas.width );
				if ( ! n ) {
					addPdfText( pdf, m, m, w, paper.pageHeight );
				}
			} );
			pdf.save( fileName( 'pdf' ) );
		}
	}
} )();
