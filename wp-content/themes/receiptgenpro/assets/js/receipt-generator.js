/**
 * ReceiptGenPro — Receipt Generator (v1.1.5)
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

	// Lets a Node check exercise the pure helpers without a browser.
	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = {
			amountInWords: amountInWords,
			intToWords: intToWords,
			intToWordsIndian: intToWordsIndian,
			toCents: toCents,
			nonNegative: nonNegative
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

		// ---- Currency dropdown ---------------------------------------------
		var currencies = ( window.rfReceipt && window.rfReceipt.currencies ) || { USD: '$ — US Dollar' };
		Object.keys( currencies ).forEach( function ( code ) {
			var opt = document.createElement( 'option' );
			opt.value = code;
			opt.textContent = currencies[ code ];
			currencySel.appendChild( opt );
		} );

		if ( form.dataset.currency && currencies[ form.dataset.currency ] ) {
			currencySel.value = form.dataset.currency; // template default (e.g. INR for HRA)
		}

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
			var data = { fields: {}, items: [], logo: state.logoDataUrl };
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

		restoreForm();

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
			var payMethod = document.getElementById( 'rf-payment-method' ).value;
			var notes     = document.getElementById( 'rf-notes' ).value.trim();
			var items     = collectItems();
			var tipCents  = tipInput ? toCents( nonNegative( tipInput.value ) ) : 0;
			var paidRaw   = paidInput ? paidInput.value.trim() : '';

			var subtotalCents = items.reduce( function ( sum, it ) {
				return sum + it.lineCents;
			}, 0 );
			var taxCents   = toCents( subtotalCents * taxRate / 10000 );
			var totalCents = subtotalCents + taxCents + tipCents;
			var subtotal   = subtotalCents / 100;
			var tax        = taxCents / 100;
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
				'<div><strong>Receipt #:</strong> ' + escapeHtml( number ) + '</div>',
				'<div><strong>Date:</strong> ' + escapeHtml( date ) + '</div>'
			];
			if ( client ) {
				meta.push( '<div><strong>' + escapeHtml( cfg.clientLabel.replace( / \(optional\)$/i, '' ) ) + ':</strong> ' + escapeHtml( client ) + '</div>' );
			}
			meta.push( '<div><strong>Payment Method:</strong> ' + escapeHtml( payMethod ) + '</div>' );

			form.querySelectorAll( '[data-rf-extra]' ).forEach( function ( el ) {
				var val = formatExtra( el );
				if ( val ) {
					var label = ( el.dataset.label || '' ).replace( / \(optional\)$/i, '' );
					meta.push( '<div><strong>' + escapeHtml( label ) + ':</strong> ' + escapeHtml( val ) + '</div>' );
				}
			} );

			var totalsHtml =
				'<div><span>Subtotal</span><span>' + money( symbol, subtotal ) + '</span></div>' +
				( taxRate ? '<div><span>Tax (' + taxRate + '%)</span><span>' + money( symbol, tax ) + '</span></div>' : '' ) +
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

			var signatureHtml = cfg.signature ?
				'<div class="rf-receipt-signature"><span class="rf-sig-line"></span><span>' + escapeHtml( cfg.signature ) + '</span></div>' : '';

			preview.innerHTML =
				'<div class="rf-receipt-inner">' +
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
				'<p class="rf-receipt-footer">Generated with ' + escapeHtml( ( window.rfReceipt && window.rfReceipt.brand ) || 'ReceiptGenPro' ) + '</p>' +
				'</div>';

			if ( logoRemove ) {
				logoRemove.style.display = state.logoDataUrl ? '' : 'none';
			}
			saveForm();

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
			var clone = preview.cloneNode( true );
			clone.removeAttribute( 'id' );
			clone.setAttribute( 'aria-hidden', 'true' );
			clone.style.cssText = 'position:absolute;left:-10000px;top:0;width:' + EXPORT_WIDTH + 'px;max-width:none;box-sizing:border-box;box-shadow:none;margin:0;';
			document.body.appendChild( clone );
			return window.html2canvas( clone, {
				scale: 2,
				backgroundColor: '#ffffff',
				width: EXPORT_WIDTH,
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
