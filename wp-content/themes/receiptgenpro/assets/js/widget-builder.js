/**
 * ReceiptGenPro — widget embed-code builder (v1.2.0), /receipt-generator-widget/
 */
( function () {
	'use strict';

	function esc( str ) {
		return String( str ).replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
	}

	// The embed code: iframe + credit link + an optional auto-height listener
	// (sites that strip scripts keep the fixed height).
	function buildCode( o ) {
		var src = o.embedBase + o.slug + '/';
		var origin = o.site.replace( /\/$/, '' );
		return '<iframe src="' + esc( src ) + '" title="' + esc( o.label ) + '" width="100%" height="' + o.height + '" style="border:0;width:100%;max-width:1180px" loading="lazy"></iframe>\n' +
			'<p style="font-size:13px">Free <a href="' + esc( o.page ) + '">' + esc( o.label.toLowerCase() ) + '</a> by ' + esc( o.brand ) + '</p>\n' +
			'<script>addEventListener("message",function(e){if(e.origin!=="' + origin + '"||!e.data||!e.data.rgpHeight)return;' +
			'document.querySelectorAll(\'iframe[src^="' + esc( o.embedBase ) + '"]\').forEach(function(f){if(f.contentWindow===e.source)f.style.height=e.data.rgpHeight+"px"})})</script>';
	}

	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { buildCode: buildCode };
	}
	if ( typeof document === 'undefined' ) {
		return;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.getElementById( 'rf-widget-builder' );
		if ( ! root ) {
			return;
		}
		var sel     = document.getElementById( 'rf-widget-template' );
		var height  = document.getElementById( 'rf-widget-height' );
		var code    = document.getElementById( 'rf-widget-code' );
		var copy    = document.getElementById( 'rf-widget-copy' );
		var copied  = document.getElementById( 'rf-widget-copied' );
		var preview = document.getElementById( 'rf-widget-preview' );

		function update( reloadPreview ) {
			var opt = sel.options[ sel.selectedIndex ];
			var h = Math.min( 4000, Math.max( 600, parseInt( height.value, 10 ) || 1600 ) );
			code.value = buildCode( {
				embedBase: root.dataset.embedBase,
				site: root.dataset.site,
				brand: root.dataset.brand,
				slug: opt.value,
				label: opt.textContent.trim(),
				page: opt.dataset.page,
				height: h
			} );
			if ( reloadPreview && preview ) {
				preview.src = root.dataset.embedBase + opt.value + '/';
			}
			copied.textContent = '';
		}

		sel.addEventListener( 'change', function () {
			update( true );
		} );
		height.addEventListener( 'input', function () {
			update( false );
		} );
		copy.addEventListener( 'click', function () {
			var done = function () {
				copied.textContent = ' Copied.';
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( code.value ).then( done, function () {
					code.select();
					copied.textContent = ' Press Ctrl+C (Cmd+C) to copy.';
				} );
			} else {
				code.select();
				copied.textContent = ' Press Ctrl+C (Cmd+C) to copy.';
			}
		} );
		update( false );
	} );
} )();
